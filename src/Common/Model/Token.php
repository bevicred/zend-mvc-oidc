<?php

namespace Zend\Mvc\OIDC\Common\Model;

use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\ValidationData;
use Zend\Mvc\OIDC\Common\Configuration;
use Zend\Mvc\OIDC\Common\Enum\ValidationTokenResultEnum;

/**
 * Class Token
 *
 * @package Zend\Mvc\OIDC\Common\Model
 */
class Token
{
    /**
     * @var string
     */
    private $rawContent;

    /**
     * @var \Lcobucci\JWT\Token
     */
    private $jwt;

    /**
     * Token constructor.
     *
     * @param string $rawContent
     */
    public function __construct(string $rawContent)
    {
        $this->rawContent = $rawContent;
        $this->jwt = (new Parser())->parse($this->rawContent);
    }

    public function validate(Configuration $configuration): int
    {
        $now = new \DateTime();

        $data = $this->setValidationData($now, $configuration);

        $validSignature = $this->verifySignature($configuration->getPublicKey());

        $valid = $this->jwt->validate($data);

        $expired = $this->jwt->isExpired($now);

        if ($valid && $validSignature){
            return ValidationTokenResultEnum::VALID;
        } else if ($expired && $validSignature) {
            return ValidationTokenResultEnum::EXPIRED;
        } else {
            return ValidationTokenResultEnum::INVALID;
        }
    }

    public function hasClaim(string $name, string $value): bool
    {
        if ($this->jwt->hasClaim($name)) {
            return ($this->jwt->getClaim($name) === $value);
        }

        return false;
    }

    private function setValidationData(\DateTime $moment, Configuration $configuration): ValidationData
    {
        $data = new ValidationData($moment->getTimestamp());
        $data->setIssuer($configuration->getRealmUrl());
        $data->setAudience($configuration->getAudience());

        return $data;
    }

    private function verifySignature(string $publicKey): bool
    {
        $signer = new Sha256();
        return $this->jwt->verify($signer, $publicKey);
    }
}