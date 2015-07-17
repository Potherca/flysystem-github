<?php

namespace Potherca\Flysystem\Github;

use Github\Client;

class Settings
{
    const AUTHENTICATE_USING_TOKEN = Client::AUTH_URL_TOKEN;
    const AUTHENTICATE_USING_PASSWORD = Client::AUTH_HTTP_PASSWORD;

    const REFERENCE_HEAD = 'HEAD';
    const BRANCH_MASTER = 'master';

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
        $this->branch = $branch;
        $this->credentials = $credentials;
        $this->reference = $reference;
        $this->repository = $repository;
    }


    /**
     * @return string
     */
    final public function getRepository()
    {
        return $this->repository;
    }

    /**
     * @return string
     */
    final public function getReference()
    {
        return $this->reference;
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
    final public function getBranch()
    {
        return $this->branch;
    }
}

/*EOF*/
