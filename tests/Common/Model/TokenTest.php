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
     * @var Token
     */
    private $token;

    /**
     * @var string
     */
    private $publicKey;

    /**
     * @var string
     */
    private $privateKey;

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

        $path = str_replace('\\', '/', realpath('teste.pem.pub'));

        $this->publicKey = 'file://' . $path;
        $this->privateKey = 'Private Key';
        $this->issuer = 'http://issuedby.com/auth/realms/teste';

        $signer = new Sha256();
        $privateKey = new Key('file://teste.pem');


        $jwt = (new Builder())
            ->issuedBy($this->issuer)
            ->issuedAt($this->now->getTimestamp())
            ->canOnlyBeUsedAfter($this->now->getTimestamp() + 1)
            ->expiresAt($this->now->getTimestamp() + 60)
            ->getToken($signer, $privateKey);

        $this->token = new Token($jwt);
    }

    public function testValidateToken()
    {
        // arrange
        $configuration = new Configuration();
        $configuration->setPublicKey($this->publicKey);
        $configuration->setRealmId('teste');
        $configuration->setAuthServiceUrl('http://issuedby.com');

        // act
        $result = $this->token->validate($configuration);

        // assert
        $this->assertEquals(ValidationTokenResultEnum::VALID, $result);
    }
}