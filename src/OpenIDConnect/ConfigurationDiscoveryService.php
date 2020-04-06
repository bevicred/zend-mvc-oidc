<?php

namespace Zend\Mvc\OIDC\OpenIDConnect;

use Zend\Mvc\OIDC\Common\Configuration;
use Zend\Mvc\OIDC\Common\Exceptions\OidcConfigurationDiscoveryException;
use Zend\Mvc\OIDC\Common\Infra\HttpClient;
use Zend\Mvc\OIDC\Common\OidcConfiguration;

/**
 * Class ConfigurationDiscoveryService
 *
 * @package Zend\Mvc\OIDC\OpenIDConnect
 */
class ConfigurationDiscoveryService
{
    /**
     * @var HttpClient
     */
    private $httpClient;

    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * @param Configuration $configuration
     *
     * @return OidcConfiguration
     * @throws OidcConfigurationDiscoveryException
     */
    public function discover(Configuration $configuration): OidcConfiguration
    {
        $response = $this->httpClient->sendRequest(
            $configuration->getRealmUrl(),
            'GET',
            '/.well-known/openid-configuration'
        );

        if ($response['code'] < 200 || $response['code'] > 209) {
            throw new OidcConfigurationDiscoveryException('OpenID Connect configuration discovery error.');
        }

        return $this->adapt($response['body']);
    }

    /**
     * @param array $responseBody
     *
     * @return OidcConfiguration
     */
    private function adapt(array $responseBody): OidcConfiguration
    {
        $result = new OidcConfiguration();
        $result->setAuthorizationEndpoint($responseBody['authorization_endpoint']);
        $result->setEndSessionEndpoint($responseBody['end_session_endpoint']);
        $result->setIntrospectionEndpoint($responseBody['introspection_endpoint']);
        $result->setIssuer($responseBody['issuer']);
        $result->setJwksUri($responseBody['jwks_uri']);
        $result->setTokenEndpoint($responseBody['token_endpoint']);
        $result->setTokenIntrospectionEndpoint($responseBody['token_introspection_endpoint']);
        $result->setUserInfoEndpoint($responseBody['userinfo_endpoint']);

        return $result;
    }
}