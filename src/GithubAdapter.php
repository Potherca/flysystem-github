<?php

namespace Potherca\Flysystem\Github;

use Github\Api\Repo;
use Github\Client;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;

class GithubAdapter extends AbstractAdapter
{
    const BRANCH_MASTER = 'master';

    const COMMITTER_MAIL = 'email';
    const COMMITTER_NAME = 'name';


    /** @var Client */
    protected $client;
    /** @var string */
    private $branch = self::BRANCH_MASTER;
    private $commitMessage;
    private $committerEmail;
    private $committerName;
    private $package;
    private $reference;
    private $repository;
    private $vendor;
    private $committer = array(
        self::COMMITTER_NAME => null,
        self::COMMITTER_MAIL => null,
    );

    /**
     * Constructor.
     *
     * @param Client $client
     * @param Settings $settings
     */
    public function __construct(
        Client $client,
        Settings $settings
    ) {
        $this->client = $client;
        $this->repository = $settings->repository;
        $this->reference = $settings->reference;

        //@TODO: If $client contains credentials, $settings MUST contains author info!
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
        $fileInfo = $this->getRepositoryContents()->create(
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
        // TODO: Implement writeStream() method.
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

        $fileInfo = $this->getRepositoryContents()->update(
            $this->vendor,
            $this->package,
            $path,
            $contents,
            $this->commitMessage,
            $oldFile['sha'],
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

        $fileInfo = $this->getRepositoryContents()->rm(
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
        $fileExists = $this->getRepositoryContents()->exists(
            $this->vendor,
            $this->package,
            $path,
            $this->reference
        );

        return $fileExists;
    }

    /**
     * Download a file
     *
     * @param string $path
     *
     * @return array|false
     */
    public function read($path)
    {
        $fileContent = $this->getRepositoryContents()->download(
            $this->vendor,
            $this->package,
            $path, $this->reference
        );

        return $fileContent;
    }

    /**
     * Read a file as a stream.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function readStream($path)
    {
        // TODO: Implement readStream() method.
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool $recursive
     *
     * @return array
     */
    public function listContents($directory = '', $recursive = false)
    {
        $directoryInfo = $this->getMetadata($directory);
        // @TODO: Read file names from $directoryInfo
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
        $fileInfo = $this->getRepositoryContents()->show(
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
        // TODO: Implement getSize() method.
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
        // TODO: Implement getMimetype() method.
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
        $commits = $this->getRepository()->commits()->all(
            $this->vendor,
            $this->package,
            array(
                'sha' => $this->branch,
                'path' => $path
            )
        );
        //@TODO: Get timestamp from first commit in $commits

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
        // TODO: Implement getVisibility() method.
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
    private function getRepositoryContents()
    {
        return $this->getRepository()->contents();
    }

    /**
     * @return Repo
     */
    private function getRepository()
    {
        return $this->client->api('repo');
    }
}