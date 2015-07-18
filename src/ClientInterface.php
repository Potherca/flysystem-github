<?php
/**
 * Created by PhpStorm.
 * User: ben
 * Date: 18/07/15
 * Time: 21:36
 */
namespace Potherca\Flysystem\Github;

interface ClientInterface
{
    /**
     * @param string $path
     *
     * @return bool
     */
    public function exists($path);

    /**
     * @param $path
     *
     * @return null|string
     *
     * @throws \Github\Exception\ErrorException
     */
    public function getFileContents($path);

    /**
     * @param string $path
     *
     * @return array
     */
    public function getLastUpdatedTimestamp($path);

    /**
     * @param string $path
     *
     * @return array|bool
     */
    public function getMetaData($path);

    /**
     * @param string $path
     * @param bool $recursive
     *
     * @return array
     */
    public function getRecursiveMetadata($path, $recursive);

    /**
     * @param string $path
     *
     * @return null|string
     */
    public function guessMimeType($path);
}
