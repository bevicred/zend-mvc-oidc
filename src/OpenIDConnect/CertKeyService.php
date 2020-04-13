<?php

namespace Zend\Mvc\OIDC\OpenIDConnect;

use phpseclib\File\X509;
use Zend\Mvc\OIDC\Common\Configuration;
use Zend\Mvc\OIDC\Common\Enum\ServiceEnum;
use Zend\Mvc\OIDC\Common\Exceptions\CertificateKeyException;
use Zend\Mvc\OIDC\Common\Exceptions\InvalidAuthorizationTokenException;
use Zend\Mvc\OIDC\Common\Exceptions\JwkRecoveryException;
use Zend\Mvc\OIDC\Common\Exceptions\MissingCertificateKeyException;
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
     * @param array $jwtHeaders
     * @param ServiceLocatorInterface $serviceManager
     *
     * @return string|null
     * @throws CertificateKeyException
     * @throws InvalidAuthorizationTokenException
     * @throws JwkRecoveryException
     * @throws MissingCertificateKeyException
     * @throws OidcConfigurationDiscoveryException
     */
    public function resolveCertificate(
        Configuration $configuration,
        array $jwtHeaders,
        ServiceLocatorInterface $serviceManager
    ): ?string {
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

        $certKey = $this->getCertKeyFromServer($configuration, $jwtHeaders);

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
     * @param array $jwtHeaders
     *
     * @return string
     * @throws CertificateKeyException
     * @throws InvalidAuthorizationTokenException
     * @throws JwkRecoveryException
     * @throws MissingCertificateKeyException
     * @throws OidcConfigurationDiscoveryException
     */
    private function getCertKeyFromServer(Configuration $configuration, array $jwtHeaders): string
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

        $certificate = $this->findKeyByIdAndAlg($jwk, $jwtHeaders);

        if (is_null($certificate)) {
            throw new MissingCertificateKeyException("Missing certificate key.");
        } else {
            $certKey = $this->loadKeyFromCertificate($certificate);

            if (is_null($certKey) || (!is_null($certKey) && $certKey == '')) {
                throw new CertificateKeyException('Failed to retrieve the token certificate key.');
            } else {
                return $certKey;
            }
        }
    }

    /**
     * @param string $certificate
     *
     * @return string
     */
    private function loadKeyFromCertificate(string $certificate): string
    {
        $x509 = new X509();
        $x509->loadX509($certificate, X509::FORMAT_PEM);

        return (string)$x509->getPublicKey();
    }

    /**
     * @param array $jwk
     * @param array $jwtHeaders
     *
     * @return string|null
     * @throws InvalidAuthorizationTokenException
     * @throws JwkRecoveryException
     */
    private function findKeyByIdAndAlg(array $jwk, array $jwtHeaders): ?string
    {
        if (isset($jwtHeaders['kid']) && isset($jwtHeaders['alg'])) {
            if (count($jwk) > 0 && isset($jwk['keys'])) {
                foreach ($jwk['keys'] as $keys) {
                    if ($keys['kid'] == $jwtHeaders['kid']
                        && $keys['alg'] == $jwtHeaders['alg']
                        && isset($keys['x5c'])
                        && isset($keys['x5c'][0])) {
                        return $keys['x5c'][0];
                    } else {
                        return null;
                    }
                }
            }
            else {
                throw new JwkRecoveryException('Invalid JWK loaded.');
            }
        } else {
            throw new InvalidAuthorizationTokenException('Invalid token header.');
        }

        return null;
    }
}
