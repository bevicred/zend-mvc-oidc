<?php

namespace Zend\Mvc\OIDC\Custom;

/**
 * Class AuthInformationProvider
 *
 * @package Zend\Mvc\OIDC\Custom
 */
class AuthInformationProvider
{
    /**
     * @var array
     */
    private $claims;

    private function setClaim(string $name, string $value): void
    {
        if (!is_null($name) && is_string($name) && !is_null($value)) {
            $this->claims = [$name => $value];
        }
    }

    public function hasClaim(string $name): bool
    {
        return array_key_exists($name, $this->claims);
    }

    public function getClaim(string $name): mixed
    {
        if ($this->hasClaim($name)) {
            return $this->claims[$name];
        }

        return null;
    }
}