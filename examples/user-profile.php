<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use Hki98\TikTok\TikTokScraper;

require __DIR__ . '/../vendor/autoload.php';

$client = new Client([
    'verify' => false,
]);
$scraper = new TikTokScraper($client);

// Accepts a bare username, "@username", or a full profile URL.
$usernameOrUrl = $argv[1] ?? 'scout2015';

$info = $scraper->scrapeUser($usernameOrUrl);
print_r($info->toArray());
