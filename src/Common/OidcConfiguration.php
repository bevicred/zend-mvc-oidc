<?php

namespace Zend\Mvc\OIDC\Common;

/**
 * Class OidcConfiguration
 *
 * @package Zend\Mvc\OIDC\Common
 */
class OidcConfiguration
{




    /**
     * @var string
     */
    private $secret;



    /**
     * @var string
     */
    private $authorizationEndpoint;

    /**
     * @var string
     */
    private $endSessionEndpoint;

    /**
     * @var string
     */
    private $introspectionEndpoint;

    /**
     * @var string
     */
    private $issuer;

    /**
     * @var string
     */
    private $jwksUri;

    /**
     * @var string
     */
    private $tokenEndpoint;

    /**
     * @var string
     */
    private $tokenIntrospectionEndpoint;

    /**
     * @var string
     */
    private $userInfoEndpoint;





    /**
     * @return string
     */
    public function getSecret(): string
    {
        return $this->secret;
    }

    /**
     * @param string $secret
     */
    public function setSecret(string $secret): void
    {
        $this->secret = $secret;
    }







    /**
     * @return string
     */
    public function getAuthorizationEndpoint(): string
    {
        return $this->authorizationEndpoint;
    }

    /**
     * @param string $authorizationEndpoint
     */
    public function setAuthorizationEndpoint(string $authorizationEndpoint): void
    {
        $this->authorizationEndpoint = $authorizationEndpoint;
    }

    /**
     * @return string
     */
    public function getEndSessionEndpoint(): string
    {
        return $this->endSessionEndpoint;
    }

    /**
     * @param string $endSessionEndpoint
     */
    public function setEndSessionEndpoint(string $endSessionEndpoint): void
    {
        $this->endSessionEndpoint = $endSessionEndpoint;
    }

    /**
     * @return string
     */
    public function getIntrospectionEndpoint(): string
    {
        return $this->introspectionEndpoint;
    }

    /**
     * @param string $introspectionEndpoint
     */
    public function setIntrospectionEndpoint(string $introspectionEndpoint): void
    {
        $this->introspectionEndpoint = $introspectionEndpoint;
    }

    /**
     * @return string
     */
    public function getIssuer(): string
    {
        return $this->issuer;
    }

    /**
     * @param string $issuer
     */
    public function setIssuer(string $issuer): void
    {
        $this->issuer = $issuer;
    }

    /**
     * @return string
     */
    public function getJwksUri(): string
    {
        return $this->jwksUri;
    }

    /**
     * @param string $jwksUri
     */
    public function setJwksUri(string $jwksUri): void
    {
        $this->jwksUri = $jwksUri;
    }

    /**
     * @return string
     */
    public function getTokenEndpoint(): string
    {
        return $this->tokenEndpoint;
    }

    /**
     * @param string $tokenEndpoint
     */
    public function setTokenEndpoint(string $tokenEndpoint): void
    {
        $this->tokenEndpoint = $tokenEndpoint;
    }

    /**
     * @return string
     */
    public function getTokenIntrospectionEndpoint(): string
    {
        return $this->tokenIntrospectionEndpoint;
    }

    /**
     * @param string $tokenIntrospectionEndpoint
     */
    public function setTokenIntrospectionEndpoint(string $tokenIntrospectionEndpoint): void
    {
        $this->tokenIntrospectionEndpoint = $tokenIntrospectionEndpoint;
    }

    /**
     * @return string
     */
    public function getUserInfoEndpoint(): string
    {
        return $this->userInfoEndpoint;
    }

    /**
     * @param string $userInfoEndpoint
     */
    public function setUserInfoEndpoint(string $userInfoEndpoint): void
    {
        $this->userInfoEndpoint = $userInfoEndpoint;
    }
}