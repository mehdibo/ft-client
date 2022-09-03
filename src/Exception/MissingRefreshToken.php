<?php

namespace Mehdibo\FortyTwo\SDK\Exception;

class MissingRefreshToken extends \Exception
{
    public function __construct()
    {
        parent::__construct('Refresh token is missing for some reason');
    }
}
