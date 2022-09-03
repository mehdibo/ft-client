<?php

namespace Mehdibo\FortyTwo\Client\Exception;

class ServerError extends \Exception
{
    public function __construct()
    {
        parent::__construct("Intranet API returned a 500 Internal Server Error");
    }
}
