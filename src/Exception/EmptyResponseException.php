<?php

declare(strict_types=1);

namespace Hki98\TikTok\Exception;

final class EmptyResponseException extends TikTokScraperException
{
    public static function create(): self
    {
        return new self('Empty response body from TikTok.');
    }
}
