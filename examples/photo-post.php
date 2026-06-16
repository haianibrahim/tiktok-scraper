<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use Hki98\TikTok\TikTokScraper;

require __DIR__ . '/../vendor/autoload.php';

$client = new Client([
    'verify' => false,
]);
$scraper = new TikTokScraper($client);

// Photo posts use the same scrape() method and return a VideoDetails DTO.
$url = $argv[1] ?? 'https://www.tiktok.com/@hardik.kanani/photo/7370106573522472200';

$details = $scraper->scrape($url);
print_r($details->toArray());
