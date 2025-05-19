<?php

namespace Potherca\Flysystem\Github;

use Github\Api\ApiInterface;
use Github\Api\GitData;
use Github\Api\GitData\Trees;
use Github\Api\Repo;
use Github\Api\Repository\Commits;
use Github\Api\Repository\Contents;
use Github\Client;
use Github\Exception\RuntimeException;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * Tests for the Api class
 *
 * @coversDefaultClass \Potherca\Flysystem\Github\Api
 *
 * @covers ::<!public>
 * @covers ::__construct
 *
 * @uses \Potherca\Flysystem\Github\Api::<public>
 */
class ApiTest extends \PHPUnit_Framework_TestCase
{
    ////////////////////////////////// FIXTURES \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    const MOCK_FILE_CONTENTS = 'Mock file contents';
    const MOCK_FILE_PATH = '/a-directory/another-file.js';
    const MOCK_FOLDER_PATH = 'a-directory/';
    const MOCK_PACKAGE = 'mockPackage';
    const MOCK_REFERENCE = 'mockReference';
    const MOCK_VENDOR = 'mockVendor';
    const MOCK_BRANCH = 'mockBranch';

    /** @var Api */
    private $api;
    /** @var Client|MockObject */
    private $mockClient;
    /** @var Settings|MockObject */
    private $mockSettings;

    /**
     *
     */
    protected function setUp()
    {
        $this->mockClient = $this->getMockClient();
        $this->mockSettings = $this->getMockSettings();

        $this->api = new Api($this->mockClient, $this->mockSettings);
    }

    /////////////////////////////////// TESTS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    /**
     *
     */
    final public function testApiShouldComplainWhenInstantiatedWithoutClient()
    {
        $message = sprintf(
            'Argument %d passed to %s::__construct() must be an instance of %s',
            1,
            Api::class,
            Client::class
        );

        $this->setExpectedException(\PHPUnit_Framework_Error::class, $message);

        /** @noinspection PhpParamsInspection */
        new Api();
    }

    /**
     * @coversNothing
     */
    final public function testApiShouldComplainWhenInstantiatedWithoutSettings()
    {
        $message = sprintf(
            'Argument %d passed to %s::__construct() must implement interface %s',
            2,
            Api::class,
            SettingsInterface::class
        );

        $this->setExpectedException(\PHPUnit_Framework_Error::class, $message);

        /** @noinspection PhpParamsInspection */
        new Api($this->getMockClient());
    }

    /**
     * @covers ::getFileContents
     */
    final public function testApiShouldUseValuesFromSettingsWhenAskingClientForFileContent()
    {
        $api = $this->api;

        $expected = self::MOCK_FILE_CONTENTS;

        $this->prepareMockSettings([
            'getVendor' => self::MOCK_VENDOR,
            'getPackage' => self::MOCK_PACKAGE,
            'getReference' => self::MOCK_REFERENCE,
        ]);

        $this->prepareMockApi(
            'download',
            $api::API_REPOSITORY,
            [self::MOCK_VENDOR, self::MOCK_PACKAGE, trim(self::MOCK_FILE_PATH, '/'), self::MOCK_REFERENCE],
            $expected
        );

        $actual = $api->getFileContents(self::MOCK_FILE_PATH);

        self::assertEquals($expected, $actual);
    }

    /**
     * @covers ::exists
     */
    final public function testApiShouldUseValuesFromSettingsWhenAskingClientIfFileExists()
    {
        $api = $this->api;

        $expected = self::MOCK_FILE_CONTENTS;

        $this->prepareMockSettings([
            'getVendor' => self::MOCK_VENDOR,
            'getPackage' => self::MOCK_PACKAGE,
            'getReference' => self::MOCK_REFERENCE,
        ]);

        $this->prepareMockApi(
            'exists',
            $api::API_REPOSITORY,
            [self::MOCK_VENDOR, self::MOCK_PACKAGE, trim(self::MOCK_FILE_PATH, '/'), self::MOCK_REFERENCE],
            $expected
        );

        $actual = $api->exists(self::MOCK_FILE_PATH);

        self::assertEquals($expected, $actual);
    }

    /**
     * @covers ::getLastUpdatedTimestamp
     */
    final public function testApiShouldUseValuesFromSettingsWhenAskingClientForLastUpdatedTimestamp()
    {
        $api = $this->api;

        $expected = ['timestamp' => 1420070400];

        $this->prepareFixturesForTimeStamp();

        $actual = $api->getLastUpdatedTimestamp(self::MOCK_FILE_PATH);

        self::assertEquals($expected, $actual);
    }

    /**
     * @covers ::getCreatedTimestamp
     */
    final public function testApiShouldUseValuesFromSettingsWhenAskingClientForCreatedTimestamp()
    {
        $api = $this->api;

        $expected = ['timestamp' => 1362268800];

        $this->prepareFixturesForTimeStamp();

        $actual = $api->getCreatedTimestamp(self::MOCK_FILE_PATH);

        self::assertEquals($expected, $actual);
    }

    /**
     * @covers ::getMetaData
     */
    final public function testApiShouldUseValuesFromSettingsWhenAskingClientForFileInfo()
    {
        $api = $this->api;

        $expected = self::MOCK_FILE_CONTENTS;

        $this->prepareMockSettings([
            'getVendor' => self::MOCK_VENDOR,
            'getPackage' => self::MOCK_PACKAGE,
            'getReference' => self::MOCK_REFERENCE,
        ]);

        $this->prepareMockApi(
            'show',
            $api::API_REPOSITORY,
            [self::MOCK_VENDOR, self::MOCK_PACKAGE, trim(self::MOCK_FILE_PATH, '/'), self::MOCK_REFERENCE],
            $expected
        );

        $actual = $api->getMetaData(self::MOCK_FILE_PATH);

        self::assertEquals($expected, $actual);
    }

    /**
     * @covers ::getMetaData
     */
    final public function testApiShouldAccountForFileNotExistingWhenAskingInfoForFile()
    {
        $api = $this->api;

        $expected = false;

        $this->mockClient->expects(self::exactly(1))
            ->method('api')
            ->willThrowException(new RuntimeException(Api::ERROR_NOT_FOUND));

        $actual = $api->getMetaData(self::MOCK_FILE_PATH);

        self::assertEquals($expected, $actual);
    }

    /**
     * @covers ::getMetaData
     */
    final public function testApiShouldReturnMetadataForDirectoryWhenGivenPathIsDirectory()
    {
        $api = $this->api;

        $mockPath = trim(self::MOCK_FOLDER_PATH, '/');

        $expectedUrl = sprintf(
            '%s/repos/%s/%s/contents/%s?ref=%s',
            $api::GITHUB_API_URL,
            self::MOCK_VENDOR,
            self::MOCK_PACKAGE,
            $mockPath,
            self::MOCK_REFERENCE
        );

        $expectedHtmlUrl = sprintf(
            '%s/%s/%s/blob/%s/%s',
            $api::GITHUB_URL,
            self::MOCK_VENDOR,
            self::MOCK_PACKAGE,
            self::MOCK_REFERENCE,
            $mockPath
        );

        $expected = [
            'path' => $mockPath,
            'timestamp' => 1450252770,
            'type' => $api::KEY_DIRECTORY,
            'url' => $expectedUrl,
            'html_url' => $expectedHtmlUrl,
            '_links' => Array (
                'self' => $expectedUrl,
                'html' => $expectedHtmlUrl,
            ),
            'mode' => '040000',
            'sha' => '30b7e362894eecb159ce0ba2921a8363cd297213',
            'name' => 'a-directory',
            'visibility' => 'public',
            'contents' => false,
            'stream' => false,
        ];

        $this->prepareMockSettings([
            'getVendor' => self::MOCK_VENDOR,
            'getPackage' => self::MOCK_PACKAGE,
            'getReference' => self::MOCK_REFERENCE,
        ]);

        $recursiveTreesFixture = $this->loadFixture('repos/potherca-bot/test-repository/git/trees/HEAD?recursive=1');

        $files = array_column($recursiveTreesFixture['tree'], $api::KEY_PATH);

        $this->addMocksToClient($this->mockClient, [
            Repo::class => [
                Commits::class => [
                    'method' => 'all',
                    'exactly' => count($files),
                    'with' => self::callback(function($vendor, $package, $context) use ($files) {
                        return $vendor === self::MOCK_VENDOR && $package === self::MOCK_PACKAGE && $context['sha'] === null
                        && preg_match(sprintf('#%s#', implode('|', $files)), $context['path']) === 1;
                    }),
                    'willReturn' => $this->loadFixture('repos/potherca-bot/test-repository/commits'),
                ],
                Contents::class => [
                    'method' => 'show',
                    'exactly' => 1,
                    'with' => [self::MOCK_VENDOR, self::MOCK_PACKAGE, trim(self::MOCK_FOLDER_PATH, '/'), self::MOCK_REFERENCE],
                    'willReturn' => $this->loadFixture('repos/potherca-bot/test-repository/contents/a-directory'),
                ],
            ],
            GitData::class => [
                Trees::class => [
                    'method' => 'show',
                    'exactly' => 1,
                    'with' => [self::MOCK_VENDOR, self::MOCK_PACKAGE, self::MOCK_REFERENCE, $api::RECURSIVE],

                    'willReturn' => $recursiveTreesFixture,
                ],
            ],
        ]);

        $actual = $api->getMetaData($mockPath);

        self::assertEquals($expected, $actual);
    }

    /**
     * @covers ::getMetaData
     */
    final public function testApiShouldPassOtherRuntimeExceptionsWhenAskingInfoForFileCausesRuntimeException()
    {
        $api = $this->api;

        $this->setExpectedException(RuntimeException::class, self::MOCK_FILE_CONTENTS);

        $expected = false;

        $this->mockClient->expects(self::exactly(1))
            ->method('api')
            ->willThrowException(new RuntimeException(self::MOCK_FILE_CONTENTS));

        $actual = $api->getMetaData(self::MOCK_FILE_PATH);

        self::assertEquals($expected, $actual);
    }

    /**
     * @covers ::getMetaData
     */
    final public function testApiShouldPassOnExceptionsWhenAskingInfoForFileCausesAnException()
    {
        $api = $this->api;

        $this->setExpectedException(\RuntimeException::class, Api::ERROR_NOT_FOUND);

        $expected = false;

        $this->mockClient->expects(self::exactly(1))
            ->method('api')
            ->willThrowException(new \RuntimeException(Api::ERROR_NOT_FOUND));

        $actual = $api->getMetaData(self::MOCK_FILE_PATH);

        self::assertEquals($expected, $actual);
    }

    /**
     * @covers ::getDirectoryContents
     *
     * @dataProvider provideDirectoryContents
     *
     * @param array $data
     */
    final public function testApiShouldRetrieveExpectedDirectoryContentsWhenAskedToGetDirectoryContents(array $data)
    {
        $api = $this->api;

        $this->prepareMockSettings([
            'getVendor' => self::MOCK_VENDOR,
            'getPackage' => self::MOCK_PACKAGE,
            'getReference' => self::MOCK_REFERENCE,
        ]);

        $recursiveTreesFixture = $this->loadFixture('repos/potherca-bot/test-repository/git/trees/HEAD?recursive=1');

        $files = array_column($recursiveTreesFixture['tree'], $api::KEY_PATH);

        $this->addMocksToClient($this->mockClient, [
            Repo::class => [
                Commits::class => [
                    'method' => 'all',
                    'exactly' => count($files),
                    'with' => self::callback(function($vendor, $package, $context) use ($files) {
                        return $vendor === self::MOCK_VENDOR && $package === self::MOCK_PACKAGE && $context['sha'] === null
                            && preg_match(sprintf('#%s#', implode('|', $files)), $context['path']) === 1;
                    }),
                    'willReturn' => $this->loadFixture('repos/potherca-bot/test-repository/commits'),
                ],
            ],
            GitData::class => [
                Trees::class => [
                    'method' => 'show',
                    'exactly' => 1,
                    'with' => [self::MOCK_VENDOR, self::MOCK_PACKAGE, self::MOCK_REFERENCE, $api::RECURSIVE],
                    'willReturn' => $recursiveTreesFixture,
                ],
            ],
        ]);

        $actual = $api->getDirectoryContents($data['path'], $data['recursive']);

        self::assertEquals($data['expected'], $actual);
    }

    /**
     * @covers ::getDirectoryContents
     *
     * @dataProvider provideExpectedMetadata
     *
     * @param array $data
     */
    final public function testApiShouldRetrieveExpectedMetadataWhenAskedToGetMetadata(array $data)
    {
        $api = $this->api;

        $this->prepareMockSettings([
            'getVendor' => self::MOCK_VENDOR,
            'getPackage' => self::MOCK_PACKAGE,
            'getReference' => self::MOCK_REFERENCE,
        ]);

        $treesFixture = $this->loadFixture('repos/potherca-bot/test-repository/git/trees/HEAD?recursive=1');

        $files = array_column($treesFixture['tree'], $api::KEY_PATH);

        $mockApis = [
            Repo::class => [
                Commits::class => [
                    'method' => 'all',
                    'exactly' => count($files),
                    'with' => self::callback(function($vendor, $package, $context) use ($files) {
                        return $vendor === self::MOCK_VENDOR && $package === self::MOCK_PACKAGE && $context['sha'] === null
                        && preg_match(sprintf('#%s#', implode('|', $files)), $context['path']) === 1;
                    }),
                    'willReturn' => $this->loadFixture('repos/potherca-bot/test-repository/commits'),
                ],
                Contents::class => [
                    'method' => 'show',
                    'exactly' => 1,
                    'with' => self::callback(function ($vendor, $package, $path, $reference) use ($files) {
                        return $vendor === self::MOCK_VENDOR && $package === self::MOCK_PACKAGE && $reference === self::MOCK_REFERENCE
                        && preg_match(sprintf('#%s#', implode('|', $files)), $path) === 1;
                    }),
                    'willReturn' => $this->loadFixture('repos/potherca-bot/test-repository/contents/a-directory'),
                ],
            ],
            GitData::class => [
                Trees::class => [
                    'method' => 'show',
                    'exactly' => 1,
                    'with' => [self::MOCK_VENDOR, self::MOCK_PACKAGE, self::MOCK_REFERENCE, $api::RECURSIVE],
                    'willReturn' => $treesFixture,
                ],
            ],
        ];

//        if ($data['count'] !== 0) {
//            $mockApis[Repo::class][Commits::class] = [
//                'method' => 'all',
//                'exactly' => $data['count'],
//                'with' => self::callback(function ($vendor, $package, $context) use ($files) {
//                    return $vendor === self::MOCK_VENDOR && $package === self::MOCK_PACKAGE && $context['sha'] === null
//                    && preg_match(sprintf('#%s#', implode('|', $files)), $context['path']) === 1;
//                }),
//                'willReturn' => $this->loadFixture('repos/potherca-bot/test-repository/commits'),
//            ];
//        }

        $this->addMocksToClient($this->mockClient, $mockApis);

        $actual = $api->getMetaData($data['path']);

        self::assertEquals($data['expected'], $actual);
    }

    /**
     * @covers ::guessMimeType
     *
     * @uses League\Flysystem\Util\MimeType
     */
    final public function testApiShouldUseFileContentsToGuessMimeTypeWhenExtensionUnavailable()
    {
        $api = $this->api;

        $expected = 'image/png';

        $this->prepareMockSettings([
            'getVendor' => self::MOCK_VENDOR,
            'getPackage' => self::MOCK_PACKAGE,
            'getReference' => self::MOCK_REFERENCE,
        ]);

        $image = imagecreatetruecolor(1,1);
        ob_start();
        imagepng($image);
        $contents = ob_get_contents();
        ob_end_clean();
        imagedestroy($image);

        $this->prepareMockApi(
            'download',
            $api::API_REPOSITORY,
            [self::MOCK_VENDOR, self::MOCK_PACKAGE, trim(self::MOCK_FILE_PATH, '/'), self::MOCK_REFERENCE],
            $contents
        );

        $actual = $api->guessMimeType(self::MOCK_FILE_PATH);

        self::assertEquals($expected, $actual);
    }

    /**
     * @covers ::guessMimeType
     *
     * @uses League\Flysystem\Util\MimeType
     */
    final public function testApiShouldGuessMimeTypeCorrectlyWhenGivenPathIsDirectory()
    {
        $api = $this->api;

        $expected = $api::MIME_TYPE_DIRECTORY;

        $this->prepareMockSettings([
            'getVendor' => self::MOCK_VENDOR,
            'getPackage' => self::MOCK_PACKAGE,
            'getReference' => self::MOCK_REFERENCE,
        ]);

        $treesFixture = $this->loadFixture('repos/potherca-bot/test-repository/git/trees/HEAD');
        $files = array_column($treesFixture['tree'], $api::KEY_PATH);

        $this->addMocksToClient($this->mockClient, [
            Repo::class => [
                Commits::class => [
                    'method' => 'all',
                    'exactly' => count($files),
                    'with' => self::callback(function($vendor, $package, $context) use ($files) {
                        return $vendor === self::MOCK_VENDOR && $package === self::MOCK_PACKAGE && $context['sha'] === null
                        && preg_match(sprintf('#%s#', implode('|', $files)), $context['path']) === 1;
                    }),
                    'willReturn' => $this->loadFixture('repos/potherca-bot/test-repository/commits'),
                ],
                Contents::class => [
                    'method' => 'show',
                    'exactly' => 1,
                    'with' => [self::MOCK_VENDOR, self::MOCK_PACKAGE, trim(self::MOCK_FOLDER_PATH, '/'), self::MOCK_REFERENCE],
                    'willReturn' => $this->loadFixture('repos/potherca-bot/test-repository/contents/a-directory'),
                ],
            ],
            GitData::class => [
                Trees::class => [
                    'method' => 'show',
                    'exactly' => 1,
                    'with' => [self::MOCK_VENDOR, self::MOCK_PACKAGE, self::MOCK_REFERENCE, $api::RECURSIVE],
                    'willReturn' => $treesFixture,
                ],
            ],
        ]);

        $actual = $api->guessMimeType(self::MOCK_FOLDER_PATH);

        self::assertEquals($expected, $actual);
    }

    /**
     *
     */
    final public function testApiShouldUseCredentialsWhenTheyHaveBeenGiven()
    {
        $api = $this->api;

        $this->prepareMockSettings([
            'getVendor' => self::MOCK_VENDOR,
            'getPackage' => self::MOCK_PACKAGE,
            'getReference' => self::MOCK_REFERENCE,
            'getCredentials' => ['foo']
        ]);

        $this->prepareMockApi(
            'exists',
            $api::API_REPOSITORY,
            [self::MOCK_VENDOR, self::MOCK_PACKAGE, trim(self::MOCK_FILE_PATH, '/'), self::MOCK_REFERENCE],
            ''
        );

        $this->mockClient->expects(self::exactly(1))
            ->method('authenticate')
        ;

        $api->exists(self::MOCK_FILE_PATH);
    }

    ////////////////////////////// MOCKS AND STUBS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    /**
     * @return Client|MockObject
     */
    private function getMockClient()
    {
        return $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return Settings|MockObject
     */
    private function getMockSettings()
    {
        return $this->getMockBuilder(SettingsInterface::class)
            ->getMock();
    }

    private function addMocksToClient(MockObject $mockClient, array $apiCollection)
    {
        $parentApiCollection = [];

        foreach ($apiCollection as $parentApiClass => $children) {

            $mockParentApi = $this->getMockBuilder($parentApiClass)
                ->disableOriginalConstructor()
                ->getMock()
            ;

            foreach ($children as $childApiClass => $properties) {
                $parts = explode('\\', $childApiClass);
                $childApiName = strtolower(array_pop($parts));

                $mockChildApi = $this->getMockBuilder($childApiClass)
                    ->disableOriginalConstructor()
                    ->getMock()
                ;

                $returnMethod = 'willReturn';
                if (is_callable($properties['willReturn'])) {
                    $returnMethod = 'willReturnCallback';
                }

                $mockChildApi->expects(self::exactly($properties['exactly']))
                    ->method($properties['method'])
                    ->withConsecutive($properties['with'])
                    ->{$returnMethod}($properties['willReturn'])
                ;

                $mockParentApi->expects(self::exactly(1))
                    ->method($childApiName)
                    ->with()
                    ->willReturn($mockChildApi)
                ;
            }

            $parts = explode('\\', $parentApiClass);
            $parentApiName = strtolower(array_pop($parts));
            $parentApiCollection[$parentApiName] = $mockParentApi;
        }

        $parentApiCount = count($parentApiCollection);
        $parentApiNames = array_keys($parentApiCollection);
        $parentApiNamesPattern = implode('|', $parentApiNames);

        $mockClient->expects(self::exactly($parentApiCount))
            ->method('api')
            ->with(self::matchesRegularExpression(sprintf('/%s/i', $parentApiNamesPattern)))
            ->willReturnCallback(function ($apiName) use ($parentApiCollection) {
                return $parentApiCollection[strtolower($apiName)];
            })
        ;
    }

    /**
     * @deprecated Use `addMocksToClient` instead.
     * 
     * @param string $method
     * @param string $apiName
     * @param array $apiParameters
     * @param mixed $apiOutput
     * @param string $childApiClass
     */
    private function prepareMockApi(
        $method,
        $apiName,
        $apiParameters,
        $apiOutput,
        $childApiClass = Contents::class
    ) {

        $parts = explode('\\', $childApiClass);
        $childApiName = strtolower(array_pop($parts));

        $methods = [$childApiName, 'getPerPage', 'setPerPage'];

        $shouldMockCommitsRepository = false;
        if (in_array('commits', $methods, true) === false) {
            $shouldMockCommitsRepository = true;
            $methods[] = 'commits';
        }

        $mockParentApi = $this->getMockBuilder(ApiInterface::class)
            ->setMethods($methods)
            ->getMock()
        ;

        $mockChildApi = $this->getMockBuilder($childApiClass)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $mockChildApi->expects(self::exactly(1))
            ->method($method)
            ->withAnyParameters()
            ->willReturnCallback(function () use ($apiParameters, $apiOutput) {
                self::assertEquals($apiParameters, func_get_args());
                return $apiOutput;
            })
        ;

        $mockParentApi->expects(self::exactly(1))
            ->method($childApiName)
            ->willReturn($mockChildApi)
        ;

        if ($shouldMockCommitsRepository === true) {
            $mockCommitsApi = $this->getMockBuilder(Commits::class)
                ->disableOriginalConstructor()
                ->getMock()
            ;

            $apiOutput = [
                ['commit' => ['committer' => ['date' => '20150101']]],
                ['commit' => ['committer' => ['date' => '20140202']]]
            ];

            $mockCommitsApi->expects(self::any())
                ->method('all')
                ->withAnyParameters()
                ->willReturn($apiOutput)
            ;
            $mockParentApi->expects(self::any())
                ->method('commits')
                ->willReturn($mockCommitsApi)
            ;
        }

        $this->mockClient->expects(self::any())
            ->method('api')
            ->with(self::matchesRegularExpression(sprintf('/%s|repo/', $apiName)))
            ->willReturn($mockParentApi)
        ;
    }

    /**
     * @param array $expectations
     */
    private function prepareMockSettings(array $expectations)
    {
        foreach ($expectations as $methodName => $returnValue) {
            $this->mockSettings->expects(self::any())
                ->method($methodName)
                ->willReturn($returnValue)
            ;
        }
    }

    private function prepareFixturesForTimeStamp()
    {
        date_default_timezone_set('UTC');

        $this->prepareMockSettings([
            'getVendor' => self::MOCK_VENDOR,
            'getPackage' => self::MOCK_PACKAGE,
            'getBranch' => self::MOCK_BRANCH,
        ]);

        $apiParameters = [
            self::MOCK_VENDOR,
            self::MOCK_PACKAGE,
            [
                'sha' => self::MOCK_BRANCH,
                'path' => trim(self::MOCK_FILE_PATH, '/')
            ]

        ];

        $apiOutput = [
            ['commit' => ['committer' => ['date' => '20150101']]],
            ['commit' => ['committer' => ['date' => '20140202']]],
            ['commit' => ['committer' => ['date' => '20130303']]],
        ];

        $this->prepareMockApi(
            'all',
            Api::API_REPOSITORY,
            $apiParameters,
            $apiOutput,
            Commits::class
        );
    }

    /////////////////////////////// DATAPROVIDERS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    /**
     * @return array
     */
    final public  function provideExpectedMetadata()
    {
        return [
            'FilePath' => [[
                'count' => 0,
                'path' => self::MOCK_FILE_PATH,
                'expected' => [
                    'path' => 'a-directory/another-file.js',
                    'mode' => '100755',
                        'type' => 'dir',
                    'sha' => 'f542363e1b45aa7a33e5e731678dee18f7a1e729',
                    'size' => 52,
                    'url' => 'https://api.github.com/repos/mockVendor/mockPackage/contents/a-directory/another-file.js?ref=mockReference',
                    'name' => 'a-directory/another-file.js',
                        'visibility' => 'public',
                        'contents' => false,
                        'stream' => false,
                    'timestamp' => 1450252770,
                    'html_url' => 'https://github.com/mockVendor/mockPackage/blob/mockReference/a-directory/another-file.js',
                    '_links' => [
                        'self' => 'https://api.github.com/repos/mockVendor/mockPackage/contents/a-directory/another-file.js?ref=mockReference',
                        'html' => 'https://github.com/mockVendor/mockPackage/blob/mockReference/a-directory/another-file.js',
                    ],
                ],
            ]],
            'DirectoryPath' => [[
                'count' => 2,
                'path' => self::MOCK_FOLDER_PATH,
                'expected' => [
                    'path' => 'a-directory',
                    'mode' => '040000',
                        'type' => 'dir',
                    'sha' => '30b7e362894eecb159ce0ba2921a8363cd297213',
                    'url' => 'https://api.github.com/repos/mockVendor/mockPackage/contents/a-directory?ref=mockReference',
                    'name' => 'a-directory',
                        'visibility' => 'public',
                        'contents' => false,
                        'stream' => false,
                    'timestamp' => 1450252770,
                    'html_url' => 'https://github.com/mockVendor/mockPackage/blob/mockReference/a-directory',
                    '_links' => [
                        'self' => 'https://api.github.com/repos/mockVendor/mockPackage/contents/a-directory?ref=mockReference',
                        'html' => 'https://github.com/mockVendor/mockPackage/blob/mockReference/a-directory',
                    ],
                ],
            ]],
        ];
    }

    final public  function provideDirectoryContents()
    {
        $subDirectoryContent = [
            [
                'path' => 'a-directory/another-file.js',
                'mode' => '100755',
                'type' => 'file',
                'size' => 52,
                'name' => 'a-directory/another-file.js',
                'contents' => false,
                'stream' => false,
                'timestamp' => 1450252770,
                'visibility' => 'public',
                'sha' => 'f542363e1b45aa7a33e5e731678dee18f7a1e729',
                'url' => 'https://api.github.com/repos/potherca-bot/test-repository/git/blobs/f542363e1b45aa7a33e5e731678dee18f7a1e729',
            ],
            [
                'path' => 'a-directory/readme.txt',
                'mode' => '100755',
                'type' => 'file',
                'size' => 31,
                'name' => 'a-directory/readme.txt',
                'contents' => false,
                'stream' => false,
                'timestamp' => 1450252770,
                'visibility' => 'public',
                'sha' => '27f8ec8435cb07992ecf18f9d5494ffc14948368',
                'url' => 'https://api.github.com/repos/potherca-bot/test-repository/git/blobs/27f8ec8435cb07992ecf18f9d5494ffc14948368',
            ],
        ];

        $subDirectory = [
            'path' => 'a-directory',
            'mode' => '040000',
            'type' => 'dir',
            'name' => 'a-directory',
            'contents' => false,
            'stream' => false,
            'timestamp' => 1450252770,
            'visibility' => 'public',
            'sha' => '30b7e362894eecb159ce0ba2921a8363cd297213',
            'url' => 'https://api.github.com/repos/potherca-bot/test-repository/git/trees/30b7e362894eecb159ce0ba2921a8363cd297213',
        ];

        $nonRecursiveRepositoryContent = [
            [
                'path' => 'README',
                'mode' => '100755',
                'type' => 'file',
                'size' => 58,
                'name' => 'README',
                'contents' => false,
                'stream' => false,
                'timestamp' => 1450252770,
                'visibility' => 'public',
                'sha' => '1ff3a296caf2d27828dd8c40673c88dbf99d4b3a',
                'url' => 'https://api.github.com/repos/potherca-bot/test-repository/git/blobs/1ff3a296caf2d27828dd8c40673c88dbf99d4b3a',
            ],
            $subDirectory,
            [
                'path' => 'a-file.php',
                'mode' => '100755',
                'type' => 'file',
                'sha' => 'c6e6cd91e3ae40ab74883720a0d6cfb2af89e4b1',
                'size' => 117,
                'url' => 'https://api.github.com/repos/potherca-bot/test-repository/git/blobs/c6e6cd91e3ae40ab74883720a0d6cfb2af89e4b1',
                'name' => 'a-file.php',
                'visibility' => 'public',
                'contents' => false,
                'stream' => false,
                'timestamp' => 1450252770,
            ],
        ];

        $recursiveRepositoryContent = array_merge($nonRecursiveRepositoryContent, $subDirectoryContent);

        return [
            // @TODO: Add Directory path?
            'Directorypath, not recursive, not truncated' => [[
                //@TODO: Add sub-sub-directory so non-recursive directory contents can be properly tested
                'path' => self::MOCK_FOLDER_PATH,
                'expected' => array_merge([$subDirectory], $subDirectoryContent),
                'recursive' => false,
                'truncated' => false,
            ]],
            'Directorypath, recursive, not truncated' => [[
                'path' => self::MOCK_FOLDER_PATH,
                'expected' => array_merge([$subDirectory], $subDirectoryContent),
                'recursive' => true,
                'truncated' => false,
            ]],
            'No FilePath, not recursive, not truncated' => [[
                'path' => '',
                'expected' => $nonRecursiveRepositoryContent,
                'recursive' => false,
                'truncated' => false,
            ]],
            'No FilePath, not recursive, truncated' => [[
                'path' => '',
                'expected' => $nonRecursiveRepositoryContent,
                'recursive' => false,
                'truncated' => true,
            ]],
            'No FilePath, recursive, not truncated' => [[
                'path' => '',
                'expected' => $recursiveRepositoryContent,
                'recursive' => true,
                'truncated' => false,
            ]],
            'No FilePath, recursive, truncated' => [[
                'path' => '',
                'expected' => $recursiveRepositoryContent,
                'recursive' => true,
                'truncated' => true,
            ]],
        ];
    }

    /**
     * @param $fixtureName
     * @return mixed
     */
    private function loadFixture($fixtureName)
    {
        $fixtureName = urlencode($fixtureName);

        $fixtureDirectory = sprintf('%s/fixtures', dirname(__DIR__));
        $fixturePath = sprintf('%s/%s.json', $fixtureDirectory, $fixtureName);

        if (is_file($fixturePath) === false) {
            self::fail(
                sprintf('Could not find file for fixture "%s"', $fixtureName)
            );
        } else {
            $fixture = json_decode(file_get_contents($fixturePath), true);
            $lastJsonError = json_last_error();
            if ($lastJsonError !== JSON_ERROR_NONE) {
                self::fail(
                    sprintf('Error Reading Fixture "%s": %s', $fixturePath, json_last_error_msg())
                );
            }
        }

        return $fixture;
    }
}
