<?php

namespace Potherca\Flysystem\Github;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\StreamedTrait;
use League\Flysystem\Config;
use League\Flysystem\Exception;
use League\Flysystem\Util;

/**
 *
 */
class GithubAdapter extends AbstractAdapter
{
    use StreamedTrait;

    const COMMITTER_MAIL = 'email';
    const COMMITTER_NAME = 'name';

    const VISIBILITY_PRIVATE = 'private';
    const VISIBILITY_PUBLIC = 'public';

    /** @var Client */
    private $client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
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
        throw new Exception('Write action are not (yet) supported');
        //@TODO: return $this->client->create($path, $contents);
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
        throw new Exception('Write action are not (yet) supported');
        // @TODO: return $this->client->update($path, $contents);
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
        throw new Exception('Write action are not (yet) supported');
        // @TODO: return $this->client->rename($path, $newPath);
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
        throw new Exception('Write action are not (yet) supported');
        // @TODO: return $this->client->copy($path, $newPath);
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
        throw new Exception('Write action are not (yet) supported');
        // @TODO: return $this->client->delete($path);
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
        throw new Exception('Write action are not (yet) supported');
        // @TODO: return $this->client->deleteDir($dirname);
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
        throw new Exception('Write action are not (yet) supported');
        // @TODO: return $this->client->createDir($dirname);
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
        return $this->client->exists($path);
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
        return [Client::KEY_CONTENTS => $this->client->download($path)];
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
        return $this->client->metadata($path, $recursive);
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
        return $this->client->show($path);
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
        return $this->client->getMetaData($path);
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
        return ['mimetype' => $this->client->guessMimeType($path)];
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
        return $this->client->updated($path);
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
        $recursive = false;
        $metadata = $this->client->metadata($path, $recursive);
        return $metadata[0];
    }
}

/*EOF*/
