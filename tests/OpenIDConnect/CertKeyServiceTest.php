<?php

namespace Tests\OpenIDConnect;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zend\Mvc\OIDC\Common\Configuration;
use Zend\Mvc\OIDC\Common\Exceptions\JwkRecoveryException;
use Zend\Mvc\OIDC\Common\Infra\HttpClient;
use Zend\Mvc\OIDC\Common\OidcConfiguration;
use Zend\Mvc\OIDC\OpenIDConnect\CertKeyService;
use Zend\Mvc\OIDC\OpenIDConnect\ConfigurationDiscoveryService;
use Zend\ServiceManager\ServiceManager;

/**
 * Class CertKeyServiceTest
 *
 * @package Tests\OpenIDConnect
 */
class CertKeyServiceTest extends TestCase
{
    /**
     * @var ConfigurationDiscoveryService|MockObject
     */
    private $configurationDiscoveryService;

    /**
     * @var HttpClient|MockObject
     */
    private $httpClient;

    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var OidcConfiguration
     */
    private $oidcConfiguration;

    public function setUp()
    {
        $this->httpClient = $this
            ->getMockBuilder(HttpClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->configurationDiscoveryService = $this
            ->getMockBuilder(ConfigurationDiscoveryService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->configuration = new Configuration();
        $this->configuration->setAuthServiceUrl('http://34.95.175.142:8080');
        $this->configuration->setRealmId('RealmMaster');
        $this->configuration->setClientId('demo-app');
        $this->configuration->setAudience('pos-api.com');


        $configJson = json_decode(file_get_contents(__DIR__ . '/../Shared/JsonFiles/OpenIdConnectDiscoveryResult.json'), true);
        $this->oidcConfiguration = new OidcConfiguration();
        $this->oidcConfiguration->setAuthorizationEndpoint($configJson['authorization_endpoint']);
        $this->oidcConfiguration->setEndSessionEndpoint($configJson['end_session_endpoint']);
        $this->oidcConfiguration->setIntrospectionEndpoint($configJson['introspection_endpoint']);
        $this->oidcConfiguration->setIssuer($configJson['issuer']);
        $this->oidcConfiguration->setJwksUri($configJson['jwks_uri']);
        $this->oidcConfiguration->setTokenEndpoint($configJson['token_endpoint']);
        $this->oidcConfiguration->setTokenIntrospectionEndpoint($configJson['token_introspection_endpoint']);
        $this->oidcConfiguration->setUserInfoEndpoint($configJson['userinfo_endpoint']);
    }

    public function testResolveCertificateWithValidConfigurationReturn()
    {
        $this->configurationDiscoveryService
            ->expects($this->once())
            ->method('discover')
            ->willReturn($this->oidcConfiguration);

        $jsonResult = json_decode(file_get_contents(__DIR__ . '/../Shared/JsonFiles/CertificateResponse.json'), true);

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->willReturn(
                [
                    'code' => 200,
                    'body' => $jsonResult
                ]
            );

        $certKeyService = new CertKeyService($this->configurationDiscoveryService, $this->httpClient);

        $result = $certKeyService->resolveCertificate($this->configuration, new ServiceManager());

        $this->assertNotNull($result);
    }

    public function testResolveCertificateWithInvalidConfiguratioShouldReturnNull()
    {
        $this->configurationDiscoveryService
            ->expects($this->once())
            ->method('discover')
            ->willReturn($this->oidcConfiguration);

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->willReturn(
                [
                    'code' => 200,
                    'body' => []
                ]
            );

        $certKeyService = new CertKeyService($this->configurationDiscoveryService, $this->httpClient);

        $result = $certKeyService->resolveCertificate($this->configuration, new ServiceManager());

        $this->assertNull($result);
    }

    public function testResolveCertificateWithErrorResponseShouldThrowsJwkRecoveryException()
    {
        $this->expectException(JwkRecoveryException::class);

        $this->configurationDiscoveryService
            ->expects($this->once())
            ->method('discover')
            ->willReturn($this->oidcConfiguration);

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->willReturn(
                [
                    'code' => 404,
                    'body' => []
                ]
            );

        $certKeyService = new CertKeyService($this->configurationDiscoveryService, $this->httpClient);

        $certKeyService->resolveCertificate($this->configuration, new ServiceManager());
    }

}