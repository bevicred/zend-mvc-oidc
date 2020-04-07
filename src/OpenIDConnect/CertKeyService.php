<?php

namespace Zend\Mvc\OIDC\OpenIDConnect;

use Zend\Mvc\OIDC\Common\Configuration;
use Zend\Mvc\OIDC\Common\Enum\ServiceEnum;
use Zend\Mvc\OIDC\Common\Exceptions\JwkRecoveryException;
use Zend\Mvc\OIDC\Common\Exceptions\OidcConfigurationDiscoveryException;
use Zend\Mvc\OIDC\Common\Infra\HttpClient;
use Zend\Mvc\OIDC\Custom\Interfaces\CertKeyCacheReaderInterface;
use Zend\Mvc\OIDC\Custom\Interfaces\CertKeyCacheWriterInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Class CertKeyService
 *
 * @package Zend\Mvc\OIDC\Auth
 */
class CertKeyService
{
    /**
     * @var ConfigurationDiscoveryService
     */
    private $configurationDiscoveryService;

    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * CertKeyService constructor.
     *
     * @param ConfigurationDiscoveryService $configurationDiscoveryService
     * @param HttpClient $httpClient
     */
    public function __construct(ConfigurationDiscoveryService $configurationDiscoveryService, HttpClient $httpClient)
    {
        $this->configurationDiscoveryService = $configurationDiscoveryService;
        $this->httpClient = $httpClient;
    }

    /**
     * @param Configuration $configuration
     * @param ServiceLocatorInterface $serviceManager
     *
     * @return string|null
     * @throws JwkRecoveryException
     * @throws OidcConfigurationDiscoveryException
     */
    public function resolveCertificate(Configuration $configuration, ServiceLocatorInterface $serviceManager): ?string
    {
        /** @var string|null $certKey */
        $certKey = null;

        if ($serviceManager->has(ServiceEnum::CERT_KEY_CACHE_READER)) {
            /** @var CertKeyCacheReaderInterface $certKeyReader */
            $certKeyReader = $serviceManager->get(ServiceEnum::CERT_KEY_CACHE_READER);

            if (!is_null($certKeyReader)) {
                $certKey = $certKeyReader->read($configuration->getRealmId() . ':certKey');
                if (!is_null($certKey)) {
                    return $certKey;
                }
            }
        }

        $certKey = $this->getCertKeyFromServer($configuration);

        if (!is_null($certKey)) {
            $this->writeCertKey($certKey, $configuration, $serviceManager);
        }

        return $certKey;
    }

    /**
     * @param string $certKey
     * @param Configuration $configuration
     * @param ServiceLocatorInterface $serviceManager
     */
    private function writeCertKey(
        string $certKey,
        Configuration $configuration,
        ServiceLocatorInterface $serviceManager
    ): void {
        if ($serviceManager->has(ServiceEnum::CERT_KEY_CACHE_WRITER)) {
            /** @var CertKeyCacheWriterInterface $certKeyWriter */
            $certKeyWriter = $serviceManager->get(ServiceEnum::CERT_KEY_CACHE_WRITER);

            if (!is_null($certKeyWriter)) {
                $certKeyWriter->write($configuration->getRealmId() . ':certKey', $certKey);
            }
        }
    }

    /**
     * @param Configuration $configuration
     *
     * @return string
     * @throws JwkRecoveryException
     * @throws OidcConfigurationDiscoveryException
     */
    private function getCertKeyFromServer(Configuration $configuration): ?string
    {
        $oidcConfiguration = $this->configurationDiscoveryService->discover($configuration);

        $response = $this->httpClient->sendRequest(
            $oidcConfiguration->getJwksUri(),
            'GET',
            ''
        );

        if ($response['code'] < 200 || $response['code'] > 209) {
            throw new JwkRecoveryException('JWK recovery error.');
        }
        /** @var array $jwk */
        $jwk = json_decode($response['body'], true);

        if (isset($jwk['keys'])
            && isset($jwk['keys'][0])
            && isset($jwk['keys'][0]['x5c'])
            && isset($jwk['keys'][0]['x5c'][0])) {
            return $jwk['keys'][0]['x5c'][0];
        }

        return null;
    }
}
