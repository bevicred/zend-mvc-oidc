<?php

namespace Tests\Common\Model;

use DateTime;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use PHPUnit\Framework\TestCase;
use Zend\Mvc\OIDC\Common\Configuration;
use Zend\Mvc\OIDC\Common\Enum\ValidationTokenResultEnum;
use Zend\Mvc\OIDC\Common\Model\Token;

/**
 * Class TokenTest
 *
 * @package Tests\Common\Model
 */
class TokenTest extends TestCase
{

    /**
     * @var string
     */
    private $publicKey;

    /**
     * @var DateTime
     */
    private $now;

    /**
     * @var string
     */
    private $issuer;

    public function setUp()
    {
        $this->now = new DateTime();

        $path = str_replace('\\', '/', realpath('teste.key.pub'));

        $this->publicKey = 'file://' . $path;
        $this->issuer = 'http://issuedby.com/auth/realms/teste';
    }

    private function createJwt(): \Lcobucci\JWT\Token
    {
        $signer = new Sha256();
        $privateKey = new Key('file://teste.key');

        return (new Builder())
            ->issuedBy($this->issuer)
            ->issuedAt($this->now->getTimestamp())
            ->canOnlyBeUsedAfter($this->now->getTimestamp())
            ->expiresAt($this->now->getTimestamp() + 60)
            ->permittedFor('pos-api.com')
            ->getToken($signer, $privateKey);
    }

    public function testValidateWithCorrectIssuerClaimTokenShouldReturnValidResult()
    {
        // arrange
        $configuration = new Configuration();
        $configuration->setPublicKey($this->publicKey);
        $configuration->setRealmId('teste');
        $configuration->setAuthServiceUrl('http://issuedby.com');
        $configuration->setAudience('pos-api.com');

        $jwt = $this->createJwt();

        $token = new Token($jwt);

        // act
        $result = $token->validate($configuration);

        // assert
        $this->assertEquals(ValidationTokenResultEnum::VALID, $result);
    }

    public function testValidateWithIncorrectIssuerClaimShouldReturnInvalidResult()
    {
        // arrange
        $configuration = new Configuration();
        $configuration->setPublicKey($this->publicKey);
        $configuration->setRealmId('teste');
        $configuration->setAuthServiceUrl('http://issuedby.com/bla/bla');
        $configuration->setAudience('pos-api.com');

        $jwt = $this->createJwt();

        $token = new Token($jwt);

        // act
        $result = $token->validate($configuration);

        // assert
        $this->assertEquals(ValidationTokenResultEnum::INVALID, $result);
    }

    public function testValidateWithIncorrectAudienceClaimShouldReturnInvalidResult()
    {
        // arrange
        $configuration = new Configuration();
        $configuration->setPublicKey($this->publicKey);
        $configuration->setRealmId('teste');
        $configuration->setAuthServiceUrl('http://issuedby.com');
        $configuration->setAudience('wrong.pos-api.com');

        $jwt = $this->createJwt();

        $token = new Token($jwt);

        // act
        $result = $token->validate($configuration);

        // assert
        $this->assertEquals(ValidationTokenResultEnum::INVALID, $result);
    }

    public function testValidateWithExpiredTokenShouldReturnExpiredResult()
    {
        // arrange
        $configuration = new Configuration();
        $configuration->setPublicKey($this->publicKey);
        $configuration->setRealmId('teste');
        $configuration->setAuthServiceUrl('http://issuedby.com');
        $configuration->setAudience('wrong.pos-api.com');

        $signer = new Sha256();
        $privateKey = new Key('file://teste.key');

        $jwt = (new Builder())
            ->issuedBy($this->issuer)
            ->issuedAt($this->now->getTimestamp() - 10)
            ->canOnlyBeUsedAfter($this->now->getTimestamp() - 9)
            ->expiresAt($this->now->getTimestamp() - 9)
            ->permittedFor('pos-api.com')
            ->getToken($signer, $privateKey);

        $token = new Token($jwt);

        // act
        $result = $token->validate($configuration);

        // assert
        $this->assertEquals(ValidationTokenResultEnum::EXPIRED, $result);
    }

    public function testValidateWithInvalidNotBeforeClaimShouldReturnInvalidResult()
    {
        // arrange
        $configuration = new Configuration();
        $configuration->setPublicKey($this->publicKey);
        $configuration->setRealmId('teste');
        $configuration->setAuthServiceUrl('http://issuedby.com');
        $configuration->setAudience('wrong.pos-api.com');

        $signer = new Sha256();
        $privateKey = new Key('file://teste.key');

        $jwt = (new Builder())
            ->issuedBy($this->issuer)
            ->issuedAt($this->now->getTimestamp())
            ->canOnlyBeUsedAfter($this->now->getTimestamp() - 9)
            ->expiresAt($this->now->getTimestamp() + 60)
            ->permittedFor('pos-api.com')
            ->getToken($signer, $privateKey);

        $token = new Token($jwt);

        // act
        $result = $token->validate($configuration);

        // assert
        $this->assertEquals(ValidationTokenResultEnum::INVALID, $result);
    }


}