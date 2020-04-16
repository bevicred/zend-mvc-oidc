<?php

namespace Zend\Mvc\OIDC\Custom\Interfaces;

interface AuthResultHandlerInterface
{
    public function handle(int $tokenValidationResult, bool $isAuthorized);
}