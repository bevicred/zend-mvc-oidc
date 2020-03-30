<?php

namespace Zend\Mvc\OIDC\Common;

/**
 * Class Configuration
 *
 * @package Zend\Mvc\OIDC\Common
 */
class Configuration
{
    /**
     * @var string
     */
    private $realmId;

    /**
     * @var string
     */
    private $clientId;

    /**
     * @var string
     */
    private $publicKey;

    /**
     * @var string
     */
    private $authServiceUrl;

    /**
     * @var string
     */
    private $audience;


    /**
     * @return string
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * @param string $clientId
     */
    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    /**
     * @param string $publicKey
     */
    public function setPublicKey(string $publicKey): void
    {
        $this->publicKey = $publicKey;
    }


    /**
     * @return string
     */
    public function getRealmId(): string
    {
        return $this->realmId;
    }

    /**
     * @param string $realmId
     */
    public function setRealmId(string $realmId): void
    {
        $this->realmId = $realmId;
    }

    /**
     * @return string
     */
    public function getRealmUrl(): string
    {
        return $this->authServiceUrl . '/auth/realms/' . $this->realmId;
    }

    /**
     * @return string
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * @return string
     */
    public function getAuthServiceUrl(): string
    {
        return $this->authServiceUrl;
    }

    /**
     * @param string $authServiceUrl
     */
    public function setAuthServiceUrl(string $authServiceUrl): void
    {
        $this->authServiceUrl = $authServiceUrl;
    }

    /**
     * @return string
     */
    public function getAudience(): string
    {
        return $this->audience;
    }

    /**
     * @param string $audience
     */
    public function setAudience(string $audience): void
    {
        $this->audience = $audience;
    }


}