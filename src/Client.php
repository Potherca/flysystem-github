<?php

namespace Potherca\Flysystem\Github;

use Github\Api\GitData;
use Github\Api\Repo;
use Github\Client as GithubClient;
use Github\Exception\RuntimeException;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Util\MimeType;

class Client implements ClientInterface
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\
    const ERROR_NOT_FOUND = 'Not Found';

    const API_GIT_DATA = 'git';
    const API_REPO = 'repo';

    const KEY_BLOB = 'blob';
    const KEY_CONTENTS = 'contents';
    const KEY_DIRECTORY = 'dir';
    const KEY_FILE = 'file';
    const KEY_FILENAME = 'basename';
    const KEY_MODE = 'mode';
    const KEY_NAME = 'name';
    const KEY_PATH = 'path';
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
    /** @var bool */
    private $isAuthenticationAttempted = false;

    //////////////////////////// SETTERS AND GETTERS \\\\\\\\\\\\\\\\\\\\\\\\\\\
    /**
     * @param string $name
     * @return \Github\Api\ApiInterface
     */
    private function getApi($name)
    {
        $this->authenticate();
        return $this->client->api($name);
    }

    /**
     * @return GitData
     */
    private function getGitDataApi()
    {
        return $this->getApi(self::API_GIT_DATA);
    }

    /**
     * @return Repo
     */
    private function getRepositoryApi()
    {
        return $this->getApi(self::API_REPO);
    }

    /**
     * @return \Github\Api\Repository\Contents
     */
    private function getRepositoryContent()
    {
        return $this->getRepositoryApi()->contents();
    }

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    final public function __construct(GithubClient $client, SettingsInterface $settings)
    {
        /* @NOTE: If $settings contains `credentials` but not an `author` we are
         * still in `read-only` mode.
         */

        $this->client = $client;
        $this->settings = $settings;
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    final public function exists($path)
    {
        return $this->getRepositoryContent()->exists(
            $this->settings->getVendor(),
            $this->settings->getPackage(),
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
    final public function getFileContents($path)
    {
        return $this->getRepositoryContent()->download(
            $this->settings->getVendor(),
            $this->settings->getPackage(),
            $path,
            $this->settings->getReference()
        );
    }

    /**
     * @param string $path
     *
     * @return array
     */
    final public function getLastUpdatedTimestamp($path)
    {
        // List commits for a file
        $commits = $this->getRepositoryApi()->commits()->all(
            $this->settings->getVendor(),
            $this->settings->getPackage(),
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
     * @param string $path
     *
     * @return array|bool
     */
    final public function getMetaData($path)
    {
        try {
            $metadata = $this->getRepositoryContent()->show(
                $this->settings->getVendor(),
                $this->settings->getPackage(),
                $path,
                $this->settings->getReference()
            );
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
     * @param bool $recursive
     *
     * @return array
     */
    final public function getRecursiveMetadata($path, $recursive)
    {
        // If $info['truncated'] is `true`, the number of items in the tree array
        // exceeded the github maximum limit. If you need to fetch more items,
        // multiple calls will be needed

        $info = $this->getGitDataApi()->trees()->show(
            $this->settings->getVendor(),
            $this->settings->getPackage(),
            $this->settings->getReference(),
            $recursive
        );

        $treeMetadata = $this->extractMetaDataFromTreeInfo($info[self::KEY_TREE], $path, $recursive);

        return $this->normalizeTreeMetadata($treeMetadata);
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
            $mimeType = MimeType::detectByFileExtension($extension) ?: 'text/plain';
        } else {
            $content = $this->getFileContents($path);
            $mimeType = MimeType::detectByContent($content);
        }

        return $mimeType;
    }

    ////////////////////////////// UTILITY METHODS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    /**
     *
     */
    private function authenticate()
    {
        if ($this->isAuthenticationAttempted === false) {
            $credentials = $this->settings->getCredentials();

            if (empty($credentials) === false) {
                $credentials = array_replace(
                    [null, null, null],
                    $credentials
                );

                $this->client->authenticate(
                    $credentials[1],
                    $credentials[2],
                    $credentials[0]
                );
            }
            $this->isAuthenticationAttempted = true;
        }
    }

    /**
     * @param array $tree
     * @param string $path
     * @param bool $recursive
     *
     * @return array
     */
    private function extractMetaDataFromTreeInfo(array $tree, $path, $recursive)
    {
        if(empty($path) === false) {
            $metadata = array_filter($tree, function ($entry) use ($path, $recursive) {
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
        } elseif ($recursive === false) {
            $metadata = array_filter($tree, function ($entry) use ($path) {
                return (strpos($entry[self::KEY_PATH], '/', strlen($path)) === false);
            });
        } else {
            $metadata = $tree;
        }

        return $metadata;
    }

    /**
     * @param $permissions
     * @return string
     */
    private function guessVisibility($permissions)
    {
        return $permissions & 0044 ? AdapterInterface::VISIBILITY_PUBLIC : AdapterInterface::VISIBILITY_PRIVATE;
    }

    /**
     * @param array $metadata
     *
     * @return array
     */
    private function normalizeTreeMetadata($metadata)
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
                $entry[self::KEY_VISIBILITY] = $this->guessVisibility($entry[self::KEY_MODE]);
            } else {
                $entry[self::KEY_VISIBILITY] = false;
            }

            $result[] = $entry;
        }

        return $result;
    }
}
