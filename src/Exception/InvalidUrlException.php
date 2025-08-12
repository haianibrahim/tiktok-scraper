<?php

declare(strict_types=1);

namespace Hki98\TikTok\Exception;

final class InvalidUrlException extends TikTokScraperException
{
    public static function forUrl(string $url): self
    {
        return new self('Please enter a valid TikTok URL! Given: ' . $url);
    }
}
