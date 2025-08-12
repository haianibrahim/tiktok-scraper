<?php

declare(strict_types=1);

namespace Hki98\TikTok\Exception;

final class ParseException extends TikTokScraperException
{
    public static function unableToLocateData(): self
    {
        return new self('Unable to locate embedded data on the page.');
    }

    public static function jsonDecode(): self
    {
        return new self('Failed to decode embedded JSON.');
    }

    public static function invalidStructure(): self
    {
        return new self('Please enter a valid TikTok URL!');
    }
}
