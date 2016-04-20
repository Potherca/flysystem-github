<?php

use Github\Client;

use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\Filesystem;

use Potherca\Flysystem\Github\Api;
use Potherca\Flysystem\Github\GithubAdapter;
use Potherca\Flysystem\Github\Settings;

class CompareToLocalAdapterIntegrationTest extends PHPUnit_Framework_TestCase {

    ////////////////////////////////// FIXTURES \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    /** @var string  */
    private $gitRemote = 'https://github.com/potherca-bot/test-repository.git';
    /** @var Filesystem */
    private $filesystem;

    final public static function setUpBeforeClass()
    {
        $envFilePath = __DIR__ . '/.env';
        self::loadEnvironmentalVariables($envFilePath);
    }

    final public function setUp()
    {
        $project = 'potherca-bot/test-repository';
        $credentials = [Settings::AUTHENTICATE_USING_TOKEN, getenv('GITHUB_API_KEY')];
        $this->filesystem = new Filesystem(new GithubAdapter(new Api(new Client(), new Settings($project, $credentials))));
    }

    /////////////////////////////////// TESTS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    /**
     * @param string $function
     * @param array $parameters
     * @param array $file
     *
     * @dataProvider provideFiles
     */
    final public function testOutputMatchesLocalAdapterOutputWhenCalledOnTheSameSource($function, array $parameters, array $file)
    {
        $path = $file['path'];

        $localFileSystem = $this->getLocalFileSystem();

        $localResult = $localFileSystem->{$function}($path);
        $result = $this->filesystem->{$function}($path);

        if (array_key_exists('callback', $parameters)) {
            $parameters['callback']($localResult, $result);
        } else {
            self::assertEquals($localResult, $result);
        }
    }

    /////////////////////////////// DATAPROVIDERS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    final public function provideFiles()
    {
        $this->createFixture();

        $files = [];

        $localFileSystem = $this->getLocalFileSystem();

        // @TODO: Test for FileNotFoundException
        //$files[] = ['path'=>'NON-EXISTENT-FILE'];

        $functions = [
            'assertPresent' => [],
            'get' => ['callback' => function ($localResult, $result) {
                if ($localResult instanceof \League\Flysystem\Directory) {
                    /** @var $localResult \League\Flysystem\Directory */
                    /** @var $result \League\Flysystem\Directory */
                    $localContents = $localResult->getContents();
                    /** @var array $contents */
                    $contents = $result->getContents();
                    $this->compare($localContents, $contents);
                } elseif ($localResult instanceof \League\Flysystem\File) {
                    /** @var $localResult \League\Flysystem\File */
                    /** @var $result \League\Flysystem\File */
                    self::assertEquals($localResult->read(), $result->read());
                } else {
                    self::assertEquals($localResult, $result);
                }
            }],
            'getMimetype' => [],
            'getSize' => [],
            //@FIXME: Synchronize local timestamp with remote git repo timestamp so "getTimestamp" can be tested
            // 'getTimestamp' => [],
            'getVisibility' => [],
            'has' => [],
            'listContents' => ['type' => 'dir', 'callback' => [$this, 'compare']],
            'read' => ['type' => 'file'],
            'readStream' => ['type' => 'file', 'callback' => function ($localStream, $githubStream){
                self::assertEquals(stream_get_contents($localStream), stream_get_contents($githubStream));
            }],
        ];

        $localFiles = $localFileSystem->listContents('', true);

        foreach ($localFiles as $index => $file) {

            $path = $file['path'];

            if (strpos($path, '.') !== 0) {
                foreach ($functions as $function => $parameters) {
                    if (array_key_exists('type', $parameters) === false || $file['type'] === $parameters['type']) {
                        $key = sprintf('%s - %s', $function, $path);
                        $files[$key] = [$function, $parameters, $file];
                    }
                }
            }
        }
        ksort($files);

        return $files;
    }

    ////////////////////////////// UTILITY METHODS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    /**
     * @param $envFilePath
     */
    private static function loadEnvironmentalVariables($envFilePath)
    {
        if (is_file($envFilePath)) {
            $loader = (new josegonzalez\Dotenv\Loader($envFilePath));
            $loader->parse()->putenv();
        }
    }

    private function createFixture()
    {
        $gitRemote = $this->gitRemote;
        $fixturesPath = $this->getFixturePath();
        $glob = glob($fixturesPath.'**');

        if (is_dir($fixturesPath) === false || count($glob) === 0) {

            fwrite(STDERR, sprintf('Creating fixture directory from %s.%s', $gitRemote, "\n"));

            exec(sprintf('git clone %s %s', $gitRemote, $fixturesPath));
        }
    }

    /**
     * @param array $localContents
     * @param array $contents
     */
    private function compare(array $localContents, array $contents)
    {
        array_walk($contents, 'ksort');
        array_walk($localContents, 'ksort');

        $localContents = array_map(function ($value) {
            unset($value['timestamp']);
            return $value;
        }, $localContents);

        foreach ($localContents as $index => $localContent) {
            foreach ($localContent as $key => $value) {
                self::assertEquals($value, $contents[$index][$key]);
            }
        }
    }

    private function getLocalFileSystem()
    {
        return new Filesystem(new LocalAdapter($this->getFixturePath()));
    }

    /**
     * @return string
     */
    private function getFixturePath()
    {
        return dirname(__DIR__) . '/fixtures/integration-test-repository/';
    }
}

/*EOF*/
