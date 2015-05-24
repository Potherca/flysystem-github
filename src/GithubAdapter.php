<?php

namespace Potherca\Flysystem\Github;

use Github\Api\GitData;
use Github\Api\Repo;
use Github\Client;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\StreamedTrait;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util;


/**
 * @FIXME: Once the functionality is clear, all of the Github Client calls need to be moved
 *         to Potherca\Flysystem\GithubClient which is a composite of the Github\Api\* and
 *         the Github\Client classes.
 */
class GithubAdapter extends AbstractAdapter
{
    use StreamedTrait;

    /*
        stream      stream (resource)
     */
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

    const BRANCH_MASTER = 'master';

    const COMMITTER_MAIL = 'email';
    const COMMITTER_NAME = 'name';

    const VISIBILITY_PRIVATE = 'private';
    const VISIBILITY_PUBLIC = 'public';

    /** @var Client */
    protected $client;
    /** @var string */
    private $branch = self::BRANCH_MASTER;
    private $commitMessage;
    private $committerEmail;
    private $committerName;
    private $package;
    private $reference;
    private $vendor;
    private $committer = array(
        self::COMMITTER_NAME => null,
        self::COMMITTER_MAIL => null,
    );
    private $credentials = [];

    /**
     * @param Client $client
     * @param Settings $settings
     */
    public function __construct(
        Client $client,
        Settings $settings
    ) {
        $this->client = $client;

        /* @NOTE: If $client contains `credentials` but not an `author` we are
         * still in `read-only` mode.
         */
        //@CHECKME Is it really necessary that we man-handle each setting?
        //         Can't we just use the settings object directly instead?
        $this->repository = $settings->repository;
        $this->reference = $settings->reference;
        list($this->vendor, $this->package) = explode('/', $this->repository);
        $this->credentials = $settings->credentials;
    }

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function write($path, $contents, Config $config)
    {
        // Create a file
        $fileInfo = $this->repositoryContents()->create(
            $this->vendor,
            $this->package,
            $path,
            $contents,
            $this->commitMessage,
            $this->branch,
            $this->committer
        );

        return $fileInfo;
    }

    /**
     * Write a new file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function writeStream($path, $resource, Config $config)
    {
        // @TODO: Use writeStream() trait, once write() has been implemented
    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function update($path, $contents, Config $config)
    {
        $oldFile = $this->getMetadata($path);

        $fileInfo = $this->repositoryContents()->update(
            $this->vendor,
            $this->package,
            $path,
            $contents,
            $this->commitMessage,
            $oldFile[self::KEY_SHA],
            $this->branch,
            $this->committer
        );

        return $fileInfo;
    }

    /**
     * Update a file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function updateStream($path, $resource, Config $config)
    {
        // TODO: Implement updateStream() method.
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function rename($path, $newpath)
    {
        // TODO: Implement rename() method.
    }

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function copy($path, $newpath)
    {
        // TODO: Implement copy() method.
    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return bool
     */
    public function delete($path)
    {
        $oldFile = $this->getMetadata($path);

        $fileInfo = $this->repositoryContents()->rm(
            $this->vendor,
            $this->package,
            $path,
            $this->commitMessage,
            $oldFile['sha'],
            $this->branch,
            $this->committer
        );

        return $fileInfo;
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @return bool
     */
    public function deleteDir($dirname)
    {
        // TODO: Implement deleteDir() method.
    }

    /**
     * Create a directory.
     *
     * @param string $dirname directory name
     * @param Config $config
     *
     * @return array|false
     */
    public function createDir($dirname, Config $config)
    {
        // TODO: Implement createDir() method.
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return array|false file meta data
     */
    public function setVisibility($path, $visibility)
    {
        // TODO: Implement setVisibility() method.
    }

    /**
     * Check that a file or directory exists in the repository
     *
     * @param string $path
     *
     * @return array|bool|null
     */
    public function has($path)
    {
        return $this->repositoryContents()->exists(
            $this->vendor,
            $this->package,
            $path,
            $this->reference
        );
    }

    /**
     * Read a file
     *
     * @param string $path
     *
     * @return array|false
     */
    public function read($path)
    {
        $fileContent = $this->repositoryContents()->download(
            $this->vendor,
            $this->package,
            $path,
            $this->reference
        );

        return [self::KEY_CONTENTS => $fileContent];
    }

    /**
     * List contents of a directory.
     *
     * @param string $path
     * @param bool $recursive
     *
     * @return array
     */
    public function listContents($path = '/', $recursive = false)
    {
        return $this->internalMetadata($path, $recursive);
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMetadata($path)
    {
        // Get information about a repository file or directory
        $fileInfo = $this->repositoryContents()->show(
            $this->vendor,
            $this->package,
            $path,
            $this->reference
        );

        return $fileInfo;
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMimetype($path)
    {
        //@NOTE: The github API does not return a MIME type, so we have to guess :-(
        if (strrpos($path, '.') > 1) {
            $extension = substr($path, strrpos($path, '.')+1);
        }

        if (isset($extension)) {
            $mimeType = Util\MimeType::detectByFileExtension($extension) ?: 'text/plain';
        } else {
            $content = $this->read($path);
            $mimeType = Util\MimeType::detectByContent($content['contents']);
        }

        return ['mimetype' => $mimeType];
    }

    /**
     * Get the timestamp of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getTimestamp($path)
    {
        // List commits for a file
        $commits = $this->repository()->commits()->all(
            $this->vendor,
            $this->package,
            array(
                'sha' => $this->branch,
                'path' => $path
            )
        );
        $updated = array_shift($commits);
        //@NOTE: $created = array_pop($commits);

        $time = new \DateTime($updated['commit']['committer']['date']);

        return ['timestamp' => $time->getTimestamp()];
    }

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getVisibility($path)
    {
        $metadata = $this->internalMetadata($path, false);
        return $metadata[0];
    }

    /**
     * @param string $path      File or Folder path
     * @param string $content   The content to write to the given file
     *
     * @throws \Github\Exception\ErrorException
     * @throws \Github\Exception\MissingArgumentException
     */
    private function foo($path, $content = '')
    {
        $this->branch = $this->reference;

        $this->committerName = 'KnpLabs';
        $this->committerEmail = 'info@knplabs.com';
        $this->vendor = 'knp-labs';
        $this->package = 'php-github-api';

        $this->committer = array(
            self::COMMITTER_NAME => $this->committerName,
            self::COMMITTER_MAIL => $this->committerEmail
        );
        $this->commitMessage = 'Edited with Flysystem';


        // https://github.com/thephpleague/flysystem/wiki/Adapter-Internals
        /*********** Meta Data Values **********
        -------------------------------------
            key     |       description
        -------------------------------------
        type        | file or dir
        path        | path to the file or dir
        contents    | file contents (string)
        stream      | stream (resource)
        visibility  | public or private
        timestamp   | modified time
        -------------------------------------

        When an adapter can not provide the metadata with the key that's required to satisfy the call, false should be returned.

        */
    }

    /**
     * @return \Github\Api\Repository\Contents
     */
    private function repositoryContents()
    {
        return $this->repository()->contents();
    }

    /**
     * @return Repo
     */
    private function repository()
    {
        return $this->fetchApi(self::KEY_REPO);
    }

    /**
     * @return GitData
     */
    private function gitData()
    {
        return $this->fetchApi(self::KEY_GIT_DATA);
    }

    /**
     * @param $recursive
     * @return \Guzzle\Http\EntityBodyInterface|mixed|string
     */
    private function trees($recursive)
    {
        $trees = $this->gitData()->trees();

        $info = $trees->show(
            $this->vendor,
            $this->package,
            $this->reference,
            $recursive
        );

        return $info[self::KEY_TREE];
    }

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
                $entry[self::KEY_VISIBILITY] = $this->fooVisibility($entry[self::KEY_MODE]);
            } else {
                $entry[self::KEY_VISIBILITY] = false;
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * @param $name
     * @return \Github\Api\ApiInterface
     */
    private function fetchApi($name)
    {
        $this->authenticate();
        return $this->client->api($name);
    }

    private function authenticate()
    {
        static $hasRun;

        if ($hasRun === null) {
            if (empty($this->credentials) === false) {
                $credentials = array_replace(
                    [null, null, null],
                    $this->credentials
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

    private function fooVisibility($permissions)
    {
        return $permissions & 0044 ? AdapterInterface::VISIBILITY_PUBLIC : AdapterInterface::VISIBILITY_PRIVATE;
    }

    private function getPathFromTree($metadata, $path, $recursive)
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

    private function internalMetadata($path, $recursive)
    {
        // If $info['truncated'] is `true`, the number of items in the tree array
        // exceeded the github maximum limit. If you need to fetch more items,
        // multiple calls will be needed

        $info = $this->trees($recursive);
        $tree = $this->getPathFromTree($info, $path, $recursive);
        $result = $this->normalizeMetadata($tree);

        return $result;
    }
}

/*EOF*/
