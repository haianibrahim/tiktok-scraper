<?php

declare(strict_types=1);

namespace Hki98\TikTok\Exception;

use Throwable;

final class HttpRequestException extends TikTokScraperException
{
    public static function from(Throwable $previous): self
    {
        return new self('Network error fetching page: ' . $previous->getMessage(), 0, $previous);
    }
}
