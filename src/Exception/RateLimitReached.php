<?php

namespace Mehdibo\FortyTwo\Client\Exception;

use Throwable;

class RateLimitReached extends \Exception
{
    /**
     * @param int|null $retryAfter The number of seconds to wait before retrying
     */
    public function __construct(public int|null $retryAfter)
    {
        parent::__construct("Rate limit reached");
    }
}
