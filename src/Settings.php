<?php

namespace Potherca\Flysystem\Github;

use Github\Client as GithubClient;

class Settings implements SettingsInterface
{
    const AUTHENTICATE_USING_TOKEN = GithubClient::AUTH_URL_TOKEN;
    const AUTHENTICATE_USING_PASSWORD = GithubClient::AUTH_HTTP_PASSWORD;

    const REFERENCE_HEAD = 'HEAD';
    const BRANCH_MASTER = 'master';
    const ERROR_INVALID_REPOSITORY_NAME = 'Given Repository name "%s" should be in the format of "vendor/project"';

    /** @var string */
    private $repository;
    /** @var string */
    private $reference = self::REFERENCE_HEAD;
    /** @var array */
    private $credentials;
    /** @var string */
    private $branch = self::BRANCH_MASTER;

    final public function __construct(
        $repository,
        array $credentials = [],
        $branch = self::BRANCH_MASTER,
        $reference = self::REFERENCE_HEAD
    ) {
        $this->isValidRepositoryName($repository);

        $this->branch = (string) $branch;
        $this->credentials = $credentials;
        $this->reference = (string) $reference;
        $this->repository = (string) $repository;
    }

    /**
     * @return string
     */
    final public function getBranch()
    {
        return $this->branch;
    }

    /**
     * @return array
     */
    final public function getCredentials()
    {
        return $this->credentials;
    }

    /**
     * @return string
     */
    final public function getReference()
    {
        return $this->reference;
    }

    /**
     * @return string
     */
    final public function getRepository()
    {
        return $this->repository;
    }

    /**
     * @param $repository
     */
    private function isValidRepositoryName($repository)
    {
        if (is_string($repository) === false
            || strpos($repository, '/') === false
            || strpos($repository, '/') === 0
            || substr_count($repository, '/') !== 1
        ) {
            $message = sprintf(
                self::ERROR_INVALID_REPOSITORY_NAME,
                var_export($repository, true)
            );
            throw new \InvalidArgumentException($message);
        }
    }
}

/*EOF*/
