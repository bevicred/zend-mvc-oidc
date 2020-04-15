<?php

namespace Zend\Mvc\OIDC\Common\Parse;

use Zend\Mvc\OIDC\Common\Configuration;
use Zend\Mvc\OIDC\Common\Enum\ConfigurationEnum;
use Zend\Mvc\OIDC\Common\Exceptions\AudienceConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\InvalidExceptionMappingConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\RealmConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\ServiceUrlConfigurationException;

/**
 * Class ConfigurationParser
 *
 * @package Zend\Mvc\OIDC\Common\Parse
 */
class ConfigurationParser
{

    /**
     * @param array $configurationArray
     *
     * @return Configuration|null
     * @throws AudienceConfigurationException
     * @throws InvalidExceptionMappingConfigurationException
     * @throws RealmConfigurationException
     * @throws ServiceUrlConfigurationException
     */
    public function parse(array $configurationArray): ?Configuration
    {
        if (isset($configurationArray[ConfigurationEnum::AUTH_SERVICE])) {
            $this->applyValidations($configurationArray[ConfigurationEnum::AUTH_SERVICE]);

            /** @var array $config */
            $config = $configurationArray[ConfigurationEnum::AUTH_SERVICE];

            $configuration = new Configuration();
            $configuration->setAuthServiceUrl($config[ConfigurationEnum::AUTH_SERVICE_URL]);
            $configuration->setRealmId($config[ConfigurationEnum::REALM_ID]);
            $configuration->setClientId($config[ConfigurationEnum::CLIENT_ID]);
            $configuration->setAudience($config[ConfigurationEnum::AUDIENCE]);

            if (isset($config[ConfigurationEnum::EXCEPTION_MAPPING][ConfigurationEnum::INVALID_TOKEN])) {
                $configuration->setInvalidTokenExceptionMapping($config[ConfigurationEnum::EXCEPTION_MAPPING][ConfigurationEnum::INVALID_TOKEN]);
            }

            if (isset($config[ConfigurationEnum::EXCEPTION_MAPPING][ConfigurationEnum::EXPIRED_TOKEN])) {
                $configuration->setExpiredTokenExceptionMapping($config[ConfigurationEnum::EXCEPTION_MAPPING][ConfigurationEnum::EXPIRED_TOKEN]);
            }

            if (isset($config[ConfigurationEnum::EXCEPTION_MAPPING][ConfigurationEnum::FORBIDDEN_TOKEN])) {
                $configuration->setForbiddenTokenExceptionMapping($config[ConfigurationEnum::EXCEPTION_MAPPING][ConfigurationEnum::FORBIDDEN_TOKEN]);
            }

            return $configuration;
        }

        return null;
    }

    /**
     * @param array $configuration
     *
     * @throws AudienceConfigurationException
     * @throws InvalidExceptionMappingConfigurationException
     * @throws RealmConfigurationException
     * @throws ServiceUrlConfigurationException
     */
    private function applyValidations(array $configuration): void
    {
        $this->hasAuthServiceUrlConfiguration($configuration);

        $this->hasRealmIdConfiguration($configuration);

        $this->hasAudienceConfiguration($configuration);

        $this->hasExceptionMapping($configuration);
    }

    /**
     * @param array $configuration
     *
     * @throws InvalidExceptionMappingConfigurationException
     */
    private function hasExceptionMapping(array $configuration): void
    {
        if (isset($configuration[ConfigurationEnum::EXCEPTION_MAPPING])) {
            $mapping = $configuration[ConfigurationEnum::EXCEPTION_MAPPING];

            if (!is_array($mapping) || count($mapping) == 0) {
                throw new InvalidExceptionMappingConfigurationException(
                    'Invalid configuration for Exceptions Mapping.'
                );
            }

            $this->hasInvalidTokenMapping($mapping);
            $this->hasExpiredTokenMapping($mapping);
            $this->hasForbiddenTokenMapping($mapping);
        }
    }

    /**
     * @param array $mapping
     *
     * @throws InvalidExceptionMappingConfigurationException
     */
    private function hasInvalidTokenMapping(array $mapping): void
    {
        if (isset($mapping[ConfigurationEnum::INVALID_TOKEN])
            && ($mapping[ConfigurationEnum::INVALID_TOKEN] == null
                || $mapping[ConfigurationEnum::INVALID_TOKEN] == '')) {
            throw new InvalidExceptionMappingConfigurationException('Invalid configuration for mapping invalid_token.');
        }
    }

    /**
     * @param array $mapping
     *
     * @throws InvalidExceptionMappingConfigurationException
     */
    private function hasExpiredTokenMapping(array $mapping): void
    {
        if (isset($mapping[ConfigurationEnum::EXPIRED_TOKEN])
            && ($mapping[ConfigurationEnum::EXPIRED_TOKEN] == null
                || $mapping[ConfigurationEnum::EXPIRED_TOKEN] == '')) {
            throw new InvalidExceptionMappingConfigurationException('Invalid configuration for mapping expired_token.');
        }
    }

    /**
     * @param array $mapping
     *
     * @throws InvalidExceptionMappingConfigurationException
     */
    private function hasForbiddenTokenMapping(array $mapping): void
    {
        if (isset($mapping[ConfigurationEnum::FORBIDDEN_TOKEN])
            && ($mapping[ConfigurationEnum::FORBIDDEN_TOKEN] == null
                || $mapping[ConfigurationEnum::FORBIDDEN_TOKEN] == '')) {
            throw new InvalidExceptionMappingConfigurationException('Invalid configuration for mapping forbidden_token.');
        }
    }

    /**
     * @param array $configuration
     *
     * @throws ServiceUrlConfigurationException
     */
    private function hasAuthServiceUrlConfiguration(array $configuration): void
    {
        if (!isset($configuration[ConfigurationEnum::AUTH_SERVICE_URL])
            || $configuration[ConfigurationEnum::AUTH_SERVICE_URL] == null
            || $configuration[ConfigurationEnum::AUTH_SERVICE_URL] == '') {
            throw new ServiceUrlConfigurationException('There is no Realm definitions in configuration');
        }
    }

    /**
     * @param array $configuration
     *
     * @throws RealmConfigurationException
     */
    private function hasRealmIdConfiguration(array $configuration): void
    {
        if (!isset($configuration[ConfigurationEnum::REALM_ID])
            || $configuration[ConfigurationEnum::REALM_ID] == null
            || $configuration[ConfigurationEnum::REALM_ID] == '') {
            throw new RealmConfigurationException('There is no Realm definitions in configuration');
        }
    }

    /**
     * @param array $configuration
     *
     * @throws AudienceConfigurationException
     */
    private function hasAudienceConfiguration(array $configuration): void
    {
        if (!isset($configuration[ConfigurationEnum::AUDIENCE])
            || $configuration[ConfigurationEnum::AUDIENCE] == null
            || $configuration[ConfigurationEnum::AUDIENCE] == '') {
            throw new AudienceConfigurationException('There is no Audience definitions in configuration');
        }
    }

}