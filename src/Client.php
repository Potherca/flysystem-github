<?php

namespace Potherca\Flysystem\Github;

use Github\Api\GitData;
use Github\Api\Repo;
use Github\Client as GithubClient;
use Github\Exception\RuntimeException;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Util\MimeType;

class Client
{
    const ERROR_NOT_FOUND = 'Not Found';

    const KEY_BLOB = 'blob';
    const KEY_CONTENTS = 'contents';
    const KEY_DIRECTORY = 'dir';
    const KEY_FILE = 'file';
    const KEY_FILENAME = 'basename';
    const KEY_GIT_DATA = 'git';
    const KEY_MODE = 'mode';
    const KEY_NAME = 'name';
    const KEY_PATH = 'path';
    const KEY_REPO = 'repo';
    const KEY_SHA = 'sha';
    const KEY_SIZE = 'size';
    const KEY_STREAM = 'stream';
    const KEY_TIMESTAMP = 'timestamp';
    const KEY_TREE = 'tree';
    const KEY_TYPE = 'type';
    const KEY_VISIBILITY = 'visibility';

    /** @var GithubClient */
    private $client;
    /** @var Settings */
    private $settings;
    /** @var string */
    private $package;
    /** @var string */
    private $vendor;

    final public function __construct(GithubClient $client, Settings $settings)
    {
        $this->client = $client;
        $this->settings = $settings;

        /* @NOTE: If $settings contains `credentials` but not an `author` we are
         * still in `read-only` mode.
         */
        list($this->vendor, $this->package) = explode('/', $this->settings->getRepository());
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    final public function exists($path)
    {
        return $this->repositoryContents()->exists(
            $this->vendor,
            $this->package,
            $path,
            $this->settings->getReference()
        );
    }

    /**
     * @param $path
     *
     * @return null|string
     *
     * @throws \Github\Exception\ErrorException
     */
    final public function download($path)
    {
        $fileContent = $this->repositoryContents()->download(
            $this->vendor,
            $this->package,
            $path,
            $this->settings->getReference()
        );

        return $fileContent;
    }

    /**
     * @param string $path
     * @param bool $recursive
     *
     * @return array
     */
    final public function metadata($path, $recursive)
    {
        // If $info['truncated'] is `true`, the number of items in the tree array
        // exceeded the github maximum limit. If you need to fetch more items,
        // multiple calls will be needed

        $info = $this->trees($recursive);
        $tree = $this->getPathFromTree($info, $path, $recursive);
        $result = $this->normalizeMetadata($tree);

        return $result;
    }

    /**
     * @param string $path
     *
     * @return array
     */
    final public function show($path)
    {
        // Get information about a repository file or directory
        $fileInfo = $this->repositoryContents()->show(
            $this->vendor,
            $this->package,
            $path,
            $this->settings->getReference()
        );
        return $fileInfo;
    }

    /**
     * @param string $path
     *
     * @return array|bool
     */
    final public function getMetaData($path)
    {
        try {
            $metadata = $this->show($path);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === self::ERROR_NOT_FOUND) {
                $metadata = false;
            } else {
                throw $exception;
            }
        }

        return $metadata;
    }

    /**
     * @param string $path
     *
     * @return null|string
     */
    final public function guessMimeType($path)
    {
        //@NOTE: The github API does not return a MIME type, so we have to guess :-(
        if (strrpos($path, '.') > 1) {
            $extension = substr($path, strrpos($path, '.')+1);
        }

        if (isset($extension)) {
            $mimeType = MimeType::detectByFileExtension($extension) ?: 'text/plain';
        } else {
            $content = $this->download($path);
            $mimeType = MimeType::detectByContent($content);
        }

        return $mimeType;
    }

    /**
     * @param string $path
     *
     * @return array
     */
    final public function updated($path)
    {
        // List commits for a file
        $commits = $this->repository()->commits()->all(
            $this->vendor,
            $this->package,
            array(
                'sha' => $this->settings->getBranch(),
                'path' => $path
            )
        );

        $updated = array_shift($commits);
        //@NOTE: $created = array_pop($commits);

        $time = new \DateTime($updated['commit']['committer']['date']);

        return ['timestamp' => $time->getTimestamp()];
    }

    /**
     * @return \Github\Api\Repository\Contents
     */
    private function repositoryContents()
    {
        return $this->repository()->contents();
    }

    /**
     *
     */
    private function authenticate()
    {
        static $hasRun;

        if ($hasRun === null) {
            if (empty($this->settings->getCredentials()) === false) {
                $credentials = array_replace(
                    [null, null, null],
                    $this->settings->getCredentials()
                );

                $this->client->authenticate(
                    $credentials[1],
                    $credentials[2],
                    $credentials[0]
                );
            }
            $hasRun = true;
        }
    }

    /**
     * @return Repo
     */
    private function repository()
    {
        return $this->fetchApi(self::KEY_REPO);
    }

    /**
     * @param string $name
     * @return \Github\Api\ApiInterface
     */
    private function fetchApi($name)
    {
        $this->authenticate();
        return $this->client->api($name);
    }

    /**
     * @param array $metadata
     * @param string $path
     * @param bool $recursive
     *
     * @return array
     */
    private function getPathFromTree(array $metadata, $path, $recursive)
    {
        if (empty($path)) {
            if ($recursive === false) {
                $metadata = array_filter($metadata, function ($entry) use ($path) {
                    return (strpos($entry[self::KEY_PATH], '/', strlen($path)) === false);
                });
            }
        } else {
            $metadata = array_filter($metadata, function ($entry) use ($path, $recursive) {
                $match = false;

                if (strpos($entry[self::KEY_PATH], $path) === 0) {
                    if ($recursive === true) {
                        $match = true;
                    } else {
                        $length = strlen($path);
                        $match = (strpos($entry[self::KEY_PATH], '/', $length) === false);
                    }
                }

                return $match;
            });
        }

        return $metadata;
    }

    /**
     * @param array $metadata
     *
     * @return array
     */
    private function normalizeMetadata($metadata)
    {
        $result = [];

        if (is_array(current($metadata)) === false) {
            $metadata = [$metadata];
        }

        foreach ($metadata as $entry) {
            if (isset($entry[self::KEY_NAME]) === false){
                if(isset($entry[self::KEY_FILENAME]) === true) {
                    $entry[self::KEY_NAME] = $entry[self::KEY_FILENAME];
                } elseif(isset($entry[self::KEY_PATH]) === true) {
                    $entry[self::KEY_NAME] = $entry[self::KEY_PATH];
                } else {
                    // ?
                }
            }

            if (isset($entry[self::KEY_TYPE]) === true) {
                switch ($entry[self::KEY_TYPE]) {
                    case self::KEY_BLOB:
                        $entry[self::KEY_TYPE] = self::KEY_FILE;
                        break;

                    case self::KEY_TREE:
                        $entry[self::KEY_TYPE] = self::KEY_DIRECTORY;
                        break;
                }
            }

            if (isset($entry[self::KEY_CONTENTS]) === false) {
                $entry[self::KEY_CONTENTS] = false;
            }

            if (isset($entry[self::KEY_STREAM]) === false) {
                $entry[self::KEY_STREAM] = false;
            }

            if (isset($entry[self::KEY_TIMESTAMP]) === false) {
                $entry[self::KEY_TIMESTAMP] = false;
            }

            if (isset($entry[self::KEY_MODE])) {
                $entry[self::KEY_VISIBILITY] = $this->visibility($entry[self::KEY_MODE]);
            } else {
                $entry[self::KEY_VISIBILITY] = false;
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * @return GitData
     */
    private function gitData()
    {
        return $this->fetchApi(self::KEY_GIT_DATA);
    }

    /**
     * @param bool $recursive
     * @return \Guzzle\Http\EntityBodyInterface|mixed|string
     */
    private function trees($recursive)
    {
        $trees = $this->gitData()->trees();

        $info = $trees->show(
            $this->vendor,
            $this->package,
            $this->settings->getReference(),
            $recursive
        );

        return $info[self::KEY_TREE];
    }

    /**
     * @param $permissions
     * @return string
     */
    private function visibility($permissions)
    {
        return $permissions & 0044 ? AdapterInterface::VISIBILITY_PUBLIC : AdapterInterface::VISIBILITY_PRIVATE;
    }
}
