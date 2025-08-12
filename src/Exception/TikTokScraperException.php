<?php

declare(strict_types=1);

namespace Hki98\TikTok\Exception;

use RuntimeException;

/**
 * Base library exception. Catch this to handle all scraper-related errors.
 */
class TikTokScraperException extends RuntimeException implements TikTokException
{
}
