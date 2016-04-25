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
            'timestamp' => 1459698679,
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

        $this->addMocksToClient($this->mockClient, [
            Repo::class => [
                Contents::class => [
                    'method' => 'show',
                    'exactly' => 1,
                    'with' => [self::MOCK_VENDOR, self::MOCK_PACKAGE, trim(self::MOCK_FOLDER_PATH, '/'), self::MOCK_REFERENCE],
                    'willReturn' => [0 => null]
                ],
                Commits::class => [
                    'method' => 'all',
                    'exactly' => 3,
                    'with' => [self::MOCK_VENDOR, self::MOCK_PACKAGE, [
                        'sha' => null,
                        'path' => 'a-directory'
                    ]],
                    'willReturn' => $this->loadFixture('repos/potherca-bot/test-repository/commits'),
                ],
            ],
            GitData::class => [
                Trees::class => [
                    'method' => 'show',
                    'exactly' => 1,
                    'with' => [self::MOCK_VENDOR, self::MOCK_PACKAGE, self::MOCK_REFERENCE],

                    'willReturn' => $this->loadFixture('repos/potherca-bot/test-repository/git/trees/HEAD'),
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
     * @dataProvider provideExpectedMetadata
     *
     * @param array $data
     */
    final public function testApiShouldRetrieveExpectedContentsWhenAskedToGetDirectoryContents($data) {
        $api = $this->api;

        $this->prepareMockSettings([
            'getVendor' => self::MOCK_VENDOR,
            'getPackage' => self::MOCK_PACKAGE,
            'getReference' => self::MOCK_REFERENCE,
        ]);

        $this->prepareMockApi(
            'show',
            $api::API_GIT_DATA,
            [self::MOCK_VENDOR, self::MOCK_PACKAGE, self::MOCK_REFERENCE, true],
            $this->getMockApiTreeResponse($data['truncated'], $api),
            Trees::class
        );

        $actual = $api->getDirectoryContents($data['path'], $data['recursive']);

        $actual = array_map(function ($value) {
            $value['timestamp'] = null;
            return $value;
        }, $actual);

        self::assertEquals($data['expected'], $actual);
    }

    /**
     * @covers ::guessMimeType
     *
     * @uses League\Flysystem\Util\MimeType
     * @uses Potherca\Flysystem\Github\Api::getFileContents
     * @uses Potherca\Flysystem\Github\Api::getMetaData
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
     * @uses Potherca\Flysystem\Github\Api::getFileContents
     * @uses Potherca\Flysystem\Github\Api::getMetaData
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

        $this->prepareMockApi(
            'trees',
            $api::API_GIT_DATA,
            [self::MOCK_VENDOR, self::MOCK_PACKAGE, trim(self::MOCK_FOLDER_PATH, '/'), self::MOCK_REFERENCE],
            [0 => [$api::KEY_TYPE => $api::MIME_TYPE_DIRECTORY]]
        );

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

    /**
     * @param bool $truncated
     * @param Api $api
     * @return array
     */
    private function getMockApiTreeResponse($truncated, Api $api)
    {
        return [
            $api::KEY_TREE => [
                [
                    'path' => self::MOCK_FILE_PATH,
                    'mode' => '100644',
                    'type' => 'tree',
                    'size' => 57,
                ],
                [
                    'path' => self::MOCK_FILE_PATH . 'Foo',
                    'basename' => self::MOCK_FILE_PATH . 'Foo',
                    'mode' => '100644',
                    'type' => 'blob',
                    'size' => 57,
                ],
                [
                    'path' => self::MOCK_FILE_PATH . '/Bar',
                    'name' => self::MOCK_FILE_PATH . '/Bar',
                    'mode' => '100644',
                    'type' => 'blob',
                    'size' => 57,
                ],
                [
                    'path' => 'some/other/file',
                    'mode' => '100644',
                    'type' => 'blob',
                    'size' => 747,
                ],
            ],
            'truncated' => $truncated,
        ];
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
            'Filepath, not recursive, not truncated' => [[
                'path' => self::MOCK_FILE_PATH,
                'expected' => [
                    [
                        'path' => '/path/to/mock/file',
                        'mode' => '100644',
                        'type' => 'dir',
                        'size' => 57,
                        'name' => '/path/to/mock/file',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null,
                        'visibility' => 'public'
                    ],
                    [
                        'path' => '/path/to/mock/fileFoo',
                        'basename' => '/path/to/mock/fileFoo',
                        'mode' => '100644',
                        'type' => 'file',
                        'size' => 57,
                        'name' => '/path/to/mock/fileFoo',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null,
                        'visibility' => 'public'
                    ],
                    [
                        'path' => '/path/to/mock/file/Bar',
                        'name' => '/path/to/mock/file/Bar',
                        'mode' => '100644',
                        'type' => 'file',
                        'size' => 57,
                        'visibility' => 'public',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null
                    ],
                ],
                'recursive' => false,
                'truncated' => false,
            ]],
            'Filepath, recursive, not truncated' => [[
                'path' => self::MOCK_FILE_PATH,
                'expected' => [
                    [
                        'path' => '/path/to/mock/file',
                        'mode' => '100644',
                        'type' => 'dir',
                        'size' => 57,
                        'name' => '/path/to/mock/file',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null,
                        'visibility' => 'public'
                    ],
                    [
                        'path' => '/path/to/mock/fileFoo',
                        'basename' => '/path/to/mock/fileFoo',
                        'mode' => '100644',
                        'type' => 'file',
                        'size' => 57,
                        'name' => '/path/to/mock/fileFoo',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null,
                        'visibility' => 'public'
                    ],
                    [
                        'path' => '/path/to/mock/file/Bar',
                        'mode' => '100644',
                        'type' => 'file',
                        'size' => 57,
                        'name' => '/path/to/mock/file/Bar',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null,
                        'visibility' => 'public'
                    ]
                ],
                'recursive' => true,
                'truncated' => false,
            ]],
            'Filepath, not recursive, truncated' => [[
                'path' => self::MOCK_FILE_PATH,
                'expected' => [
                    [
                        'path' => '/path/to/mock/file',
                        'mode' => '100644',
                        'type' => 'dir',
                        'size' => 57,
                        'name' => '/path/to/mock/file',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null,
                        'visibility' => 'public'
                    ],
                    [
                        'path' => '/path/to/mock/fileFoo',
                        'basename' => '/path/to/mock/fileFoo',
                        'mode' => '100644',
                        'type' => 'file',
                        'size' => 57,
                        'name' => '/path/to/mock/fileFoo',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null,
                        'visibility' => 'public'
                    ],
                    [
                        'path' => '/path/to/mock/file/Bar',
                        'name' => '/path/to/mock/file/Bar',
                        'mode' => '100644',
                        'type' => 'file',
                        'size' => 57,
                        'visibility' => 'public',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null
                    ],
                ],
                'recursive' => false,
                'truncated' => true,
            ]],
            'Filepath, recursive, truncated' => [[
                'path' => self::MOCK_FILE_PATH,
                'expected' => [
                    [
                        'path' => '/path/to/mock/file',
                        'mode' => '100644',
                        'type' => 'dir',
                        'size' => 57,
                        'name' => '/path/to/mock/file',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null,
                        'visibility' => 'public'
                    ],
                    [
                        'path' => '/path/to/mock/fileFoo',
                        'basename' => '/path/to/mock/fileFoo',
                        'mode' => '100644',
                        'type' => 'file',
                        'size' => 57,
                        'name' => '/path/to/mock/fileFoo',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null,
                        'visibility' => 'public'
                    ],
                    [
                        'path' => '/path/to/mock/file/Bar',
                        'mode' => '100644',
                        'type' => 'file',
                        'size' => 57,
                        'name' => '/path/to/mock/file/Bar',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null,
                        'visibility' => 'public'
                    ]
                ],
                'recursive' => true,
                'truncated' => true,
            ]],
            'No Filepath, recursive, not truncated' => [[
                'path' => '',
                'expected' => [
                    [
                        'path' => '/path/to/mock/file',
                        'mode' => '100644',
                        'type' => 'dir',
                        'size' => 57,
                        'name' => '/path/to/mock/file',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null,
                        'visibility' => 'public'
                    ],
                    [
                        'path' => '/path/to/mock/fileFoo',
                        'basename' => '/path/to/mock/fileFoo',
                        'mode' => '100644',
                        'type' => 'file',
                        'size' => 57,
                        'name' => '/path/to/mock/fileFoo',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null,
                        'visibility' => 'public'
                    ],
                    [
                        'path' => '/path/to/mock/file/Bar',
                        'mode' => '100644',
                        'type' => 'file',
                        'size' => 57,
                        'name' => '/path/to/mock/file/Bar',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null,
                        'visibility' => 'public'
                    ],
                    [
                        'path' => 'some/other/file',
                        'mode' => '100644',
                        'type' => 'file',
                        'size' => 747,
                        'name' => 'some/other/file',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null,
                        'visibility' => 'public'
                    ]
                ],
                'recursive' => true,
                'truncated' => false,
            ]],
            'No Filepath, recursive, truncated' => [[
                'path' => '',
                'expected' => [
                    [
                        'path' => '/path/to/mock/file',
                        'mode' => '100644',
                        'type' => 'dir',
                        'size' => 57,
                        'name' => '/path/to/mock/file',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null,
                        'visibility' => 'public'
                    ],
                    [
                        'path' => '/path/to/mock/fileFoo',
                        'basename' => '/path/to/mock/fileFoo',
                        'mode' => '100644',
                        'type' => 'file',
                        'size' => 57,
                        'name' => '/path/to/mock/fileFoo',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null,
                        'visibility' => 'public'
                    ],
                    [
                        'path' => '/path/to/mock/file/Bar',
                        'mode' => '100644',
                        'type' => 'file',
                        'size' => 57,
                        'name' => '/path/to/mock/file/Bar',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null,
                        'visibility' => 'public'
                    ],
                    [
                        'path' => 'some/other/file',
                        'mode' => '100644',
                        'type' => 'file',
                        'size' => 747,
                        'name' => 'some/other/file',
                        'contents' => false,
                        'stream' => false,
                        'timestamp' => null,
                        'visibility' => 'public'
                    ]
                ],
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
