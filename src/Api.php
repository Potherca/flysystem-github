<?php

namespace Potherca\Flysystem\Github;

use Github\Api\ApiInterface;
use Github\Api\GitData;
use Github\Api\Repo;
use Github\Client;
use Github\Exception\RuntimeException;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Util\MimeType;

/**
 * Facade class for the Github Api Library
 */
class Api implements \Potherca\Flysystem\Github\ApiInterface
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\
    const ERROR_NO_NAME = 'Could not set name for entry';
    const ERROR_NOT_FOUND = 'Not Found';

    const API_GIT_DATA = 'gitData';
    const API_REPOSITORY = 'repo';
    const API_REPOSITORY_COMMITS = 'commits';
    const API_REPOSITORY_CONTENTS = 'contents';

    const KEY_BLOB = 'blob';
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

    const GITHUB_API_URL = 'https://api.github.com';
    const GITHUB_URL = 'https://github.com';

    const MIME_TYPE_DIRECTORY = 'directory';    // or application/x-directory

    const NOT_RECURSIVE = false;
    const RECURSIVE = true;

    /** @var ApiInterface[] */
    private $apiCollection = [];
    /** @var Client */
    private $client;
    /** @var array */
    private $commits = [];
    /** @var bool */
    private $isAuthenticationAttempted = false;
    /** @var array */
    private $metadata = [];
    /** @var SettingsInterface */
    private $settings;

    //////////////////////////// SETTERS AND GETTERS \\\\\\\\\\\\\\\\\\\\\\\\\\\
    /**
     * @param string $name
     *
     * @return \Github\Api\ApiInterface
     *
     * @throws \Github\Exception\InvalidArgumentException
     */
    private function getApi($name)
    {
        $this->assureAuthenticated();

        if ($this->hasKey($this->apiCollection, $name) === false) {
            $this->apiCollection[$name] = $this->client->api($name);
        }

        return $this->apiCollection[$name];
    }

    /**
     * @param $name
     * @param $api
     * @return ApiInterface
     */
    private function getApiFrom($name, $api)
    {
        if ($this->hasKey($this->apiCollection, $name) === false) {
            $this->apiCollection[$name] = $api->{$name}();
        }
        return $this->apiCollection[$name];
    }

    /**
     * @return \Github\Api\Repository\Commits
     *
     * @throws \Github\Exception\InvalidArgumentException
     */
    private function getCommitsApi()
    {
        return $this->getApiFrom(self::API_REPOSITORY_COMMITS, $this->getRepositoryApi());
    }

    /**
     * @return \Github\Api\Repository\Contents
     *
     * @throws \Github\Exception\InvalidArgumentException
     */
    private function getContentApi()
    {
        return $this->getApiFrom(self::API_REPOSITORY_CONTENTS, $this->getRepositoryApi());
    }

    /**
     * @return GitData
     *
     * @throws \Github\Exception\InvalidArgumentException
     */
    private function getGitDataApi()
    {
        return $this->getApi(self::API_GIT_DATA);
    }

    /**
     * @return Repo
     *
     * @throws \Github\Exception\InvalidArgumentException
     */
    private function getRepositoryApi()
    {
        return $this->getApi(self::API_REPOSITORY);
    }

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    final public function __construct(Client $client, SettingsInterface $settings)
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
     *
     * @throws \Github\Exception\InvalidArgumentException
     */
    final public function exists($path)
    {
        $path = $this->normalizePathName($path);

        return $this->getContentApi()->exists(
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
     * @throws \Github\Exception\InvalidArgumentException
     *
     * @throws \Github\Exception\ErrorException
     */
    final public function getFileContents($path)
    {
        $path = $this->normalizePathName($path);

        return $this->getContentApi()->download(
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
     *
     * @throws \Github\Exception\InvalidArgumentException
     */
    final public function getLastUpdatedTimestamp($path)
    {
        return $this->filterCommits($path, 'reset');
    }

    /**
     * @param string $path
     *
     * @return array
     *
     * @throws \Github\Exception\InvalidArgumentException
     */
    final public function getCreatedTimestamp($path)
    {
        return $this->filterCommits($path, 'end');
    }

    /**
     * @param string $path
     *
     * @return array|bool
     *
     * @throws \Github\Exception\InvalidArgumentException
     * @throws \Github\Exception\RuntimeException
     * @throws \League\Flysystem\NotSupportedException
     */
    final public function getMetaData($path)
    {
        $path = $this->normalizePathName($path);

        if ($this->hasKey($this->metadata, $path) === false) {
            try {
                $metadata = $this->getContentApi()->show(
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
    
            if ($this->isMetadataForDirectory($metadata) === true) {
                $metadata = $this->metadataForDirectory($path);
            }

            $this->metadata[$path] = $metadata;
        }

        return $this->metadata[$path];
    }

    /**
     * @param string $path
     * @param bool $recursive
     *
     * @return array
     *
     * @throws \Github\Exception\InvalidArgumentException
     */
    final public function getDirectoryContents($path, $recursive)
    {
        $path = $this->normalizePathName($path);

        // If $info['truncated'] is `true`, the number of items in the tree array
        // exceeded the github maximum limit. If we need to fetch more items,
        // multiple calls will be needed

        $info = $this->getGitDataApi()->trees()->show(
            $this->settings->getVendor(),
            $this->settings->getPackage(),
            $this->settings->getReference(),
            self::RECURSIVE //@NOTE: To retrieve all needed date the 'recursive' flag should always be 'true'
        );

        $treeData = $this->addTimestamps($info[self::KEY_TREE]);

        $filteredTreeData = $this->filterTreeData($treeData, $path, $recursive);

        return $this->normalizeTreeData($filteredTreeData);
    }

    /**
     * @param string $path
     *
     * @return null|string
     *
     * @throws \Github\Exception\ErrorException
     * @throws \Github\Exception\InvalidArgumentException
     * @throws \Github\Exception\RuntimeException
     */
    final public function guessMimeType($path)
    {
        $path = $this->normalizePathName($path);

        //@NOTE: The github API does not return a MIME type, so we have to guess :-(
        $meta = $this->getMetaData($path);

        /** @noinspection OffsetOperationsInspection *//* @NOTE: The existence of $meta[self::KEY_TYPE] has been validated by `hasKey`. */
        if ($this->hasKey($meta, self::KEY_TYPE) && $meta[self::KEY_TYPE] === self::KEY_DIRECTORY) {
            $mimeType = self::MIME_TYPE_DIRECTORY;
        } else {
            $content = $this->getFileContents($path);
            $mimeType = MimeType::detectByContent($content);
        }

        return $mimeType;
    }

    ////////////////////////////// UTILITY METHODS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    /**
     *
     * @throws \Github\Exception\InvalidArgumentException If no authentication method was given
     */
    private function assureAuthenticated()
    {
        if ($this->isAuthenticationAttempted === false) {
            $credentials = $this->settings->getCredentials();

            if (count($credentials) !== 0) {
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
    private function filterTreeData(array $tree, $path, $recursive)
    {
        $length = strlen($path);

        $metadata = array_filter($tree, function ($entry) use ($path, $recursive, $length) {
            $match = false;

            if ($path === '' || strpos($entry[self::KEY_PATH], $path) === 0) {
                if ($recursive === self::RECURSIVE) {
                    $match = true;
                } else {
                    $match = ($path !== '' || strpos($entry[self::KEY_PATH], '/', $length) === false);
                }
            }

            return $match;
        });

        return array_values($metadata);
    }

    /**
     * @param $permissions
     * @return string
     */
    private function guessVisibility($permissions)
    {
        $visibility = AdapterInterface::VISIBILITY_PUBLIC;

        if (! substr($permissions, -4) & 0044) {
            $visibility = AdapterInterface::VISIBILITY_PRIVATE;
        }

        return $visibility;
    }

    /**
     * @param array $treeData
     *
     * @return array
     *
     * @throws \Github\Exception\InvalidArgumentException
     */
    private function normalizeTreeData($treeData)
    {
        if (is_array(current($treeData)) === false) {
            $treeData = [$treeData];
        }

        $normalizedTreeData = array_map(function ($entry) {
            $this->setEntryName($entry);
            $this->setEntryType($entry);
            $this->setEntryVisibility($entry);

            $this->setDefaultValue($entry, self::KEY_CONTENTS);
            $this->setDefaultValue($entry, self::KEY_STREAM);

            return $entry;
        }, $treeData);

        return $normalizedTreeData;
    }

    /**
     * @param $path
     *
     * @return array
     *
     * @throws \Github\Exception\InvalidArgumentException
     */
    private function commitsForFile($path)
    {
        if ($this->hasKey($this->commits, $path) === false) {
            $this->commits[$path] = $this->getCommitsApi()->all(
                $this->settings->getVendor(),
                $this->settings->getPackage(),
                array(
                    'sha' => $this->settings->getBranch(),
                    'path' => $path
                )
            );
        }

        return $this->commits[$path];
    }

    /**
     * @param array $entry
     * @param string $key
     * @param bool $default
     *
     * @return mixed
     */
    private function setDefaultValue(array &$entry, $key, $default = false)
    {
        if ($this->hasKey($entry, $key) === false) {
            $entry[$key] = $default;
        }
    }

    /**
     * @param $entry
     */
    private function setEntryType(&$entry)
    {
        if ($this->hasKey($entry, self::KEY_TYPE) === true) {
            switch ($entry[self::KEY_TYPE]) {
                case self::KEY_BLOB:
                    $entry[self::KEY_TYPE] = self::KEY_FILE;
                    break;

                case self::KEY_TREE:
                    $entry[self::KEY_TYPE] = self::KEY_DIRECTORY;
                    break;
                //@CHECKME: what should the 'default' be? Throw exception for unknown?
            }
        } else {
            $entry[self::KEY_TYPE] = false;
        }
    }

    /**
     * @param $entry
     */
    private function setEntryVisibility(&$entry)
    {
        if ($this->hasKey($entry, self::KEY_MODE)) {
            $entry[self::KEY_VISIBILITY] = $this->guessVisibility($entry[self::KEY_MODE]);
        } else {
            /* Assume public by default */
            $entry[self::KEY_VISIBILITY] = GithubAdapter::VISIBILITY_PUBLIC;
        }
    }

    /**
     * @param $entry
     */
    private function setEntryName(&$entry)
    {
        if ($this->hasKey($entry, self::KEY_NAME) === false) {
            if ($this->hasKey($entry, self::KEY_FILENAME) === true) {
                $entry[self::KEY_NAME] = $entry[self::KEY_FILENAME];
            } elseif ($this->hasKey($entry, self::KEY_PATH) === true) {
                $entry[self::KEY_NAME] = $entry[self::KEY_PATH];
            } else {
                $entry[self::KEY_NAME] = null;
            }
        }
    }

    /**
     * @param $metadata
     * @return bool
     */
    private function isMetadataForDirectory($metadata)
    {
        $isDirectory = false;

        if (is_array($metadata) === true) {
            $keys = array_keys($metadata);

            if ($keys[0] === 0) {
                $isDirectory = true;
            }
        }

        return $isDirectory;
    }

    /**
     * @param $subject
     * @param $key
     * @return mixed
     */
    private function hasKey(&$subject, $key)
    {
        $keyExists = false;

        if (is_array($subject)) {
        /** @noinspection ReferenceMismatchInspection */
            $keyExists = array_key_exists($key, $subject);
        }

        return $keyExists;
    }

    /**
     * @param array $treeMetadata
     * @param $path
     *
     * @return int
     *
     * @throws \Github\Exception\InvalidArgumentException
     */
    private function getDirectoryTimestamp(array $treeMetadata, $path)
    {
        $directoryTimestamp = 0000000000;

        $filteredTreeData = $this->filterTreeData($treeMetadata, $path, self::RECURSIVE);

        array_walk($filteredTreeData, function ($entry) use (&$directoryTimestamp, $path) {
            if ($entry[self::KEY_TYPE] === self::KEY_FILE
                && strpos($entry[self::KEY_PATH], $path) === 0
            ) {
                // @CHECKME: Should the directory Timestamp reflect the `getCreatedTimestamp` or `getLastUpdatedTimestamp`?
                $timestamp = $this->getCreatedTimestamp($entry[self::KEY_PATH])[self::KEY_TIMESTAMP];

                if ($timestamp > $directoryTimestamp) {
                    $directoryTimestamp = $timestamp;
                }
            }
        });

        return $directoryTimestamp;
    }

    private function normalizePathName($path)
    {
        return trim($path, '/');
    }

    /**
     * @param $path
     * @return array
     * @throws \League\Flysystem\NotSupportedException
     * @throws \Github\Exception\RuntimeException
     *
     * @throws \Github\Exception\InvalidArgumentException
     */
    private function metadataForDirectory($path)
    {
        $reference = $this->settings->getReference();
        $project = sprintf('%s/%s', $this->settings->getVendor(), $this->settings->getPackage());

        $url = sprintf(
            '%s/repos/%s/contents/%s?ref=%s',
            self::GITHUB_API_URL,
            $project,
            $path,
            $reference
        );
        $htmlUrl = sprintf(
            '%s/%s/blob/%s/%s',
            self::GITHUB_URL,
            $project,
            $reference,
            $path
        );

        $directoryContents =  $this->getDirectoryContents($path, self::RECURSIVE);

        $directoryMetadata = array_filter($directoryContents, function ($entry) use ($path) {
            return $entry[self::KEY_PATH] === $path;
        });

        $metadata = array_merge(
            $directoryMetadata[0],
            [
                self::KEY_TYPE => self::KEY_DIRECTORY,
                'url' => $url,
                'html_url' => $htmlUrl,
                '_links' => [
                    'self' => $url,
                    'html' => $htmlUrl
                ]
            ]
        );
        
        return $metadata;
    }

    /**
     * @param array $treeData
     *
     * @return array
     *
     * @throws \Github\Exception\InvalidArgumentException
     */
    private function addTimestamps(array $treeData)
    {
        return array_map(function ($entry) use ($treeData) {
            if ($entry[self::KEY_TYPE] === self::KEY_DIRECTORY) {
                $timestamp = $this->getDirectoryTimestamp($treeData, $entry[self::KEY_PATH]);
            } else {
                // @CHECKME: Should the Timestamp reflect the `getCreatedTimestamp` or `getLastUpdatedTimestamp`?
                $timestamp = $this->getCreatedTimestamp($entry[self::KEY_PATH])[self::KEY_TIMESTAMP];
            }
            $entry[self::KEY_TIMESTAMP] = $timestamp;

            return $entry;
        }, $treeData);
    }

    /**
     * @param $path
     * @param $function
     * @return array
     */
    private function filterCommits($path, callable $function)
    {
        $path = $this->normalizePathName($path);

        $commits = $this->commitsForFile($path);

        $subject = $function($commits);

        $time = new \DateTime($subject['commit']['committer']['date']);

        return [self::KEY_TIMESTAMP => $time->getTimestamp()];
    }
}
