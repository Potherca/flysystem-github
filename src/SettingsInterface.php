<?php

namespace Potherca\Flysystem\Github;

interface SettingsInterface
{
    /**
     * @return string
     */
    public function getBranch();

    /**
     * @return array
     */
    public function getCredentials();

    /**
     * @return string
     */
    public function getReference();

    /**
     * @return string
     */
    public function getRepository();
}
