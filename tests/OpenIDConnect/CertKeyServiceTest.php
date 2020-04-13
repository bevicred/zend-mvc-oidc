<?php

namespace Tests\OpenIDConnect;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Shared\FakeImplementations\FakeCertKeyCacheReader;
use Tests\Shared\FakeImplementations\FakeCertKeyCacheWriter;
use Zend\Mvc\OIDC\Common\Configuration;
use Zend\Mvc\OIDC\Common\Enum\ServiceEnum;
use Zend\Mvc\OIDC\Common\Exceptions\CertificateKeyException;
use Zend\Mvc\OIDC\Common\Exceptions\InvalidAuthorizationTokenException;
use Zend\Mvc\OIDC\Common\Exceptions\JwkRecoveryException;
use Zend\Mvc\OIDC\Common\Exceptions\MissingCertificateKeyException;
use Zend\Mvc\OIDC\Common\Exceptions\OidcConfigurationDiscoveryException;
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

        $configJson = json_decode(
            file_get_contents(__DIR__ . '/../Shared/JsonFiles/OpenIdConnectDiscoveryResult.json'),
            true
        );
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

    public function testResolveCertificateWithValidConfigurationAndWithCertKeyCacheReaderShouldNeverCallDiscoverMethodFromConfigurationDiscoveryService()
    {
        $this->configurationDiscoveryService
            ->expects($this->never())
            ->method('discover');

        $jsonResult = json_decode(file_get_contents(__DIR__ . '/../Shared/JsonFiles/CertificateResponse.json'), true);

        $this->httpClient
            ->expects($this->never())
            ->method('sendRequest');

        $certKeyService = new CertKeyService($this->configurationDiscoveryService, $this->httpClient);

        $jwtHeaders = [
            "kid" => "DrkOnYQmfKNmuEJcPu3t5ECvdCOqm8PD8mXHoo45TvE",
            "alg" => "RS256",
        ];

        $serviceManager = new ServiceManager();
        $serviceManager->setService(ServiceEnum::CERT_KEY_CACHE_READER, new FakeCertKeyCacheReader());

        $result = $certKeyService->resolveCertificate($this->configuration, $jwtHeaders, $serviceManager);

        $this->assertNotNull($result);
    }

    public function testResolveCertificateWithValidConfigurationAndWithoutCertKeyCacheReaderShouldCallDiscoverMethodFromConfigurationDiscoveryService()
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
                    'body' => json_encode($jsonResult)
                ]
            );

        $certKeyService = new CertKeyService($this->configurationDiscoveryService, $this->httpClient);

        $jwtHeaders = [
            "kid" => "DrkOnYQmfKNmuEJcPu3t5ECvdCOqm8PD8mXHoo45TvE",
            "alg" => "RS256",
        ];

        $result = $certKeyService->resolveCertificate($this->configuration, $jwtHeaders, new ServiceManager());

        $this->assertNotNull($result);
    }

    public function testResolveCertificateWithValidConfigurationAndWithCertKeyCacheWriter()
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
                    'body' => json_encode($jsonResult)
                ]
            );

        $certKeyService = new CertKeyService($this->configurationDiscoveryService, $this->httpClient);

        $jwtHeaders = [
            "kid" => "DrkOnYQmfKNmuEJcPu3t5ECvdCOqm8PD8mXHoo45TvE",
            "alg" => "RS256",
        ];

        $serviceManager = new ServiceManager();
        $serviceManager->setService(ServiceEnum::CERT_KEY_CACHE_WRITER, new FakeCertKeyCacheWriter());

        $result = $certKeyService->resolveCertificate($this->configuration, $jwtHeaders, $serviceManager);

        $this->assertNotNull($result);
    }

    /**
     * @throws JwkRecoveryException
     * @throws CertificateKeyException
     * @throws InvalidAuthorizationTokenException
     * @throws MissingCertificateKeyException
     * @throws OidcConfigurationDiscoveryException
     */
    public function testResolveCertificateWithInvalidJWKShouldThrowsJwkRecoveryException()
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
                    'code' => 200,
                    'body' => '{}'
                ]
            );

        $certKeyService = new CertKeyService($this->configurationDiscoveryService, $this->httpClient);

        $jwtHeaders = [
            "kid" => "DrkOnYQmfKNmuEJcPu3t5ECvdCOqm8PD8mXHoo45TvE",
            "alg" => "RS256",
        ];

        $certKeyService->resolveCertificate($this->configuration, $jwtHeaders, new ServiceManager());
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

        $jwtHeaders = [
            "kid" => "DrkOnYQmfKNmuEJcPu3t5ECvdCOqm8PD8mXHoo45TvE",
            "alg" => "RS256",
        ];

        $certKeyService->resolveCertificate($this->configuration, $jwtHeaders, new ServiceManager());
    }

    public function testResolveCertificateWithInvalidJWTHeadersShouldThrowsInvalidAuthorizationTokenException()
    {
        $this->expectException(InvalidAuthorizationTokenException::class);

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
                    'body' => json_encode($jsonResult)
                ]
            );

        $certKeyService = new CertKeyService($this->configurationDiscoveryService, $this->httpClient);

        $jwtHeaders = [];

        $certKeyService->resolveCertificate($this->configuration, $jwtHeaders, new ServiceManager());
    }

    public function testResolveCertificateWithoutCertificateKeyShouldThrowsMissingCertificateKeyException()
    {
        $this->expectException(MissingCertificateKeyException::class);

        $this->configurationDiscoveryService
            ->expects($this->once())
            ->method('discover')
            ->willReturn($this->oidcConfiguration);

        $jsonResult = json_decode(file_get_contents(__DIR__ . '/../Shared/JsonFiles/CertificateResponseWithoutCertificateKey.json'), true);

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->willReturn(
                [
                    'code' => 200,
                    'body' => json_encode($jsonResult)
                ]
            );

        $certKeyService = new CertKeyService($this->configurationDiscoveryService, $this->httpClient);

        $jwtHeaders = [
            "kid" => "DrkOnYQmfKNmuEJcPu3t5ECvdCOqm8PD8mXHoo45TvE",
            "alg" => "RS256",
        ];

        $certKeyService->resolveCertificate($this->configuration, $jwtHeaders, new ServiceManager());
    }

    public function testResolveCertificateWithInvalidCertificateKeyShouldThrowsCertificateKeyException()
    {
        $this->expectException(CertificateKeyException::class);

        $this->configurationDiscoveryService
            ->expects($this->once())
            ->method('discover')
            ->willReturn($this->oidcConfiguration);

        $jsonResult = json_decode(file_get_contents(__DIR__ . '/../Shared/JsonFiles/CertificateResponseWithInvalidCertificateKey.json'), true);

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->willReturn(
                [
                    'code' => 200,
                    'body' => json_encode($jsonResult)
                ]
            );

        $certKeyService = new CertKeyService($this->configurationDiscoveryService, $this->httpClient);

        $jwtHeaders = [
            "kid" => "DrkOnYQmfKNmuEJcPu3t5ECvdCOqm8PD8mXHoo45TvE",
            "alg" => "RS256",
        ];

        $certKeyService->resolveCertificate($this->configuration, $jwtHeaders, new ServiceManager());
    }
}