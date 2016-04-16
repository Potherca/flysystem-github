<?php

namespace Potherca\Flysystem\Github;

use PHPUnit_Framework_MockObject_Matcher_Parameters;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * Tests for the  GithubAdapter class
 *
 * @coversDefaultClass \Potherca\Flysystem\Github\GithubAdapter
 * @covers ::<!public>
 * @covers ::__construct
 * @covers ::getApi
 */
class GithubAdapterTest extends \PHPUnit_Framework_TestCase
{
    ////////////////////////////////// FIXTURES \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    const MOCK_FILE_PATH = '/path/to/mock/file';
    const MOCK_FOLDER_PATH = 'a-directory';

    /** @var GithubAdapter  */
    private $adapter;
    /** @var ApiInterface|MockObject */
    private $mockClient;

    /**
     *
     */
    protected function setup()
    {
        $this->mockClient = $this->getMock(ApiInterface::class);
        $this->adapter = new GithubAdapter($this->mockClient);
    }

    /////////////////////////////////// TESTS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    /**
     * @covers ::has
     * @covers ::read
     * @covers ::listContents
     * @covers ::getMetadata
     * @covers ::getSize
     * @covers ::getMimetype
     * @covers ::getTimestamp
     * @covers ::getVisibility
     *
     * @dataProvider provideReadMethods
     *
     * @param $method
     * @param $apiMethod
     * @param $parameters
     * @param mixed $returnValue
     */
    final public function testAdapterShouldPassParameterToClient($method, $apiMethod, $parameters, $returnValue = null)
    {
        if (is_string($returnValue) && is_file(sprintf('%s/../fixtures/%s.json', __DIR__, $returnValue))) {
            $fixturePath = sprintf('%s/../fixtures/%s.json', __DIR__, $returnValue);
            $fixture = json_decode(file_get_contents($fixturePath), true);
            $returnValue = $fixture['tree'];
        }


        $mocker = $this->mockClient->expects(self::exactly(1))
            ->method($apiMethod)
            ->willReturn($returnValue)
        ;

        $mocker->getMatcher()->parametersMatcher = new PHPUnit_Framework_MockObject_Matcher_Parameters($parameters);

        call_user_func_array([$this->adapter, $method], $parameters);
    }

    ////////////////////////////// MOCKS AND STUBS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    /////////////////////////////// DATAPROVIDERS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    final public function provideReadMethods()
    {
        return [
            'has' => ['has', 'exists', [self::MOCK_FILE_PATH]],
            'read' => ['read', 'getFileContents', [self::MOCK_FILE_PATH]],
            'listContents - File' => ['listContents', 'getTreeMetadata', [self::MOCK_FILE_PATH, false]],
            'listContents - File - recursive' => ['listContents', 'getTreeMetadata', [self::MOCK_FILE_PATH, true]],
            'listContents - Folder' => ['listContents', 'getTreeMetadata', [self::MOCK_FOLDER_PATH, false], ''],
            'listContents - Folder - recursive' => ['listContents', 'getTreeMetadata', [self::MOCK_FOLDER_PATH, true], 'listContents-folder-recursive'],
            'getMetadata' => ['getMetadata', 'getMetadata', [self::MOCK_FILE_PATH]],
            'getSize' => ['getSize', 'getMetadata', [self::MOCK_FILE_PATH]],
            'getMimetype' => ['getMimetype', 'guessMimeType', [self::MOCK_FILE_PATH]],
            'getTimestamp' => ['getTimestamp', 'getLastUpdatedTimestamp', [self::MOCK_FILE_PATH]],
            'getVisibility' => ['getVisibility', 'getTreeMetadata', [self::MOCK_FILE_PATH]],
        ];
    }
}
