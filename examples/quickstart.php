<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use Hki98\TikTok\TikTokScraper;

require __DIR__ . '/../vendor/autoload.php';

$client = new Client();
$scraper = new TikTokScraper($client);

$url = $argv[1] ?? 'https://www.tiktok.com/@scout2015/video/6718335390845095173';

$details = $scraper->scrape($url);
print_r($details->toArray());
