<?php

namespace Mehdibo\FortyTwo\Client\Exception;

/**
 * This exception is thrown when the rate limit is reached while enumerating
 * It can be used to resume the enumeration later from the page where it stopped
 */
class EnumerationRateLimited extends \Exception
{

    public int|null $retryAfter;

    /**
     * @param int $reachedPage The page where the enumeration was stopped
     */
    public function __construct(public int $reachedPage, RateLimitReached $rateLimitReached)
    {
        $this->retryAfter = $rateLimitReached->retryAfter;
        parent::__construct($rateLimitReached->getMessage(), $rateLimitReached->getCode(), $rateLimitReached);
    }
}
