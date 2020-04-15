<?php

namespace Tests\Common\Parse;

use PHPUnit\Framework\TestCase;
use Zend\Mvc\OIDC\Common\Configuration;
use Zend\Mvc\OIDC\Common\Exceptions\AudienceConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\InvalidExceptionMappingConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\RealmConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\ServiceUrlConfigurationException;
use Zend\Mvc\OIDC\Common\Parse\ConfigurationParser;

/**
 * Class ConfigurationParserTest
 *
 * @package Tests\Common\Parse
 */
class ConfigurationParserTest extends TestCase
{

    public function testParserMethodWithValidConfiguration()
    {
        $parser = new ConfigurationParser();

        $configuration = $parser->parse(
            [
                'zend_mvc_oidc' => [
                    'auth_service_url'  => 'http://34.95.175.142:8080',
                    'realmId'           => 'bvcteste',
                    'client_id'         => 'demo-app',
                    'audience'          => 'pos-api.com',
                    'exception_mapping' => [
                        'invalid_token'   => 'Tests\Shared\ExternalExceptions\ExternalAuthenticationTokenException',
                        'expired_token'   => 'Tests\Shared\ExternalExceptions\ExternalAuthenticationTokenException',
                        'forbidden_token' => 'Tests\Shared\ExternalExceptions\ExternalAuthorizationTokenException',
                    ]
                ]
            ]
        );

        $this->assertInstanceOf(Configuration::class, $configuration);
    }

    public function testParserWhenAnEmptyExceptionMappingWasGivenShouldThrowsInvalidExceptionMappingConfigurationException(
    )
    {
        $this->expectException(InvalidExceptionMappingConfigurationException::class);

        $parser = new ConfigurationParser();

        $configuration = $parser->parse(
            [
                'zend_mvc_oidc' => [
                    'auth_service_url'  => 'http://34.95.175.142:8080',
                    'realmId'           => 'bvcteste',
                    'client_id'         => 'demo-app',
                    'audience'          => 'pos-api.com',
                    'exception_mapping' => []
                ]
            ]
        );
    }

    public function testParserWhenAnEmptyInvalidTokenExceptionMappingWasGivenShouldThrowsInvalidExceptionMappingConfigurationException(
    )
    {
        $this->expectException(InvalidExceptionMappingConfigurationException::class);

        $parser = new ConfigurationParser();

        $parser->parse(
            [
                'zend_mvc_oidc' => [
                    'auth_service_url'  => 'http://34.95.175.142:8080',
                    'realmId'           => 'bvcteste',
                    'client_id'         => 'demo-app',
                    'audience'          => 'pos-api.com',
                    'exception_mapping' => [
                        'invalid_token' => '',
                    ]
                ]
            ]
        );
    }

    public function testParserWhenAnEmptyExpiredTokenExceptionMappingWasGivenShouldThrowsInvalidExceptionMappingConfigurationException(
    )
    {
        $this->expectException(InvalidExceptionMappingConfigurationException::class);

        $parser = new ConfigurationParser();

        $parser->parse(
            [
                'zend_mvc_oidc' => [
                    'auth_service_url'  => 'http://34.95.175.142:8080',
                    'realmId'           => 'bvcteste',
                    'client_id'         => 'demo-app',
                    'audience'          => 'pos-api.com',
                    'exception_mapping' => [
                        'expired_token' => '',
                    ]
                ]
            ]
        );
    }

    public function testParserWhenAnEmptyForbiddenTokenExceptionMappingWasGivenShouldThrowsInvalidExceptionMappingConfigurationException(
    )
    {
        $this->expectException(InvalidExceptionMappingConfigurationException::class);

        $parser = new ConfigurationParser();

        $parser->parse(
            [
                'zend_mvc_oidc' => [
                    'auth_service_url'  => 'http://34.95.175.142:8080',
                    'realmId'           => 'bvcteste',
                    'client_id'         => 'demo-app',
                    'audience'          => 'pos-api.com',
                    'exception_mapping' => [
                        'forbidden_token' => '',
                    ]
                ]
            ]
        );
    }

    public function testParserWhenAnStringMappingWasGivenShouldThrowsInvalidExceptionMappingConfigurationException()
    {
        $this->expectException(InvalidExceptionMappingConfigurationException::class);

        $parser = new ConfigurationParser();

        $parser->parse(
            [
                'zend_mvc_oidc' => [
                    'auth_service_url'  => 'http://34.95.175.142:8080',
                    'realmId'           => 'bvcteste',
                    'client_id'         => 'demo-app',
                    'audience'          => 'pos-api.com',
                    'exception_mapping' => 'some invalid configuration'
                ]
            ]
        );
    }

    public function testParserWhenOnlyForbiddenTokenExceptionMappingWasGivenShouldReturnValidConfigurationInstance()
    {
        $parser = new ConfigurationParser();

        $configuration = $parser->parse(
            [
                'zend_mvc_oidc' => [
                    'auth_service_url'  => 'http://34.95.175.142:8080',
                    'realmId'           => 'bvcteste',
                    'client_id'         => 'demo-app',
                    'audience'          => 'pos-api.com',
                    'exception_mapping' => [
                        'forbidden_token' => 'Tests\Shared\ExternalExceptions\ExternalAuthorizationTokenException',
                    ]
                ]
            ]
        );

        $this->assertInstanceOf(Configuration::class, $configuration);
    }

    public function testParserWhenAuthServiceConfigurationWasNotDefinedShouldReturnNull()
    {
        $parser = new ConfigurationParser();

        $configuration = $parser->parse([]);

        $this->assertNull($configuration);
    }

    public function testParserWhenAuthServiceUrlConfigurationWasNotDefinedShouldThrowsServiceUrlConfigurationException()
    {
        $this->expectException(ServiceUrlConfigurationException::class);

        $parser = new ConfigurationParser();

        $parser->parse(
            [
                'zend_mvc_oidc' => [
                    'auth_service_url' => '',
                    'realmId'          => 'bvcteste',
                    'client_id'        => 'demo-app',
                    'audience'         => 'pos-api.com'
                ]
            ]
        );
    }

    public function testParserWhenRealmIdConfigurationWasNotDefinedShouldThrowsRealmConfigurationException()
    {
        $this->expectException(RealmConfigurationException::class);

        $parser = new ConfigurationParser();

        $parser->parse(
            [
                'zend_mvc_oidc' => [
                    'auth_service_url' => 'http://34.95.175.142:8080',
                    'realmId'          => '',
                    'client_id'        => 'demo-app',
                    'audience'         => 'pos-api.com'
                ]
            ]
        );
    }

    public function testParserWhenAudienceConfigurationWasNotDefinedShouldThrowsAudienceConfigurationException()
    {
        $this->expectException(AudienceConfigurationException::class);

        $parser = new ConfigurationParser();

        $parser->parse(
            [
                'zend_mvc_oidc' => [
                    'auth_service_url' => 'http://34.95.175.142:8080',
                    'realmId'          => 'realm_name',
                    'client_id'        => 'demo-app',
                    'audience'         => ''
                ]
            ]
        );
    }

}