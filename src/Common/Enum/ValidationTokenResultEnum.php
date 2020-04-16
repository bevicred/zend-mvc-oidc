<?php

namespace Zend\Mvc\OIDC\Common\Enum;

interface ValidationTokenResultEnum
{
    const VALID = 1;
    const INVALID = 2;
    const EXPIRED = 3;
    const FORBIDDEN = 4;
}