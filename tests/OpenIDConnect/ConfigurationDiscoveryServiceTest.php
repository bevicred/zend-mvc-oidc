<?php

namespace Tests\OpenIDConnect;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zend\Mvc\OIDC\Common\Configuration;
use Zend\Mvc\OIDC\Common\Exceptions\OidcConfigurationDiscoveryException;
use Zend\Mvc\OIDC\Common\Infra\HttpClient;
use Zend\Mvc\OIDC\Common\OidcConfiguration;
use Zend\Mvc\OIDC\OpenIDConnect\ConfigurationDiscoveryService;

/**
 * Class ConfigurationDiscoveryServiceTest
 *
 * @package Tests\OpenIDConnect
 */
class ConfigurationDiscoveryServiceTest extends TestCase
{

    /**
     * @var HttpClient|MockObject
     */
    private $httpClient;

    /**
     * @var Configuration
     */
    private $configuration;

    public function setUp()
    {
        $this->httpClient = $this
            ->getMockBuilder(HttpClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->configuration = new Configuration();
        $this->configuration->setAuthServiceUrl('http://34.95.175.142:8080');
        $this->configuration->setRealmId('RealmMaster');
        $this->configuration->setClientId('demo-app');
        $this->configuration->setAudience('pos-api.com');
    }

    public function testDiscoverMethod()
    {
        $jsonResult = json_decode(file_get_contents(__DIR__ . '/../Shared/JsonFiles/OpenIdConnectDiscoveryResult.json'), true);

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->willReturn(
                [
                    'code' => 200,
                    'body' => $jsonResult
                ]
            );

        $discoveryService = new ConfigurationDiscoveryService($this->httpClient);

        $oidcConfiguration = $discoveryService->discover($this->configuration);

        $this->assertInstanceOf(OidcConfiguration::class, $oidcConfiguration);
    }

    public function testDiscoverMethodWithErrorShouldThrowsOidcConfigurationDiscoveryException()
    {
        $this->expectException(OidcConfigurationDiscoveryException::class);

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->willReturn(
                [
                    'code' => 404,
                    'body' => []
                ]
            );

        $discoveryService = new ConfigurationDiscoveryService($this->httpClient);

        $discoveryService->discover($this->configuration);
    }
}