# TikTok Custom PHP Scraper

[![GitHub Repo](https://img.shields.io/badge/GitHub-tiktok--scraper-black?logo=github)](https://github.com/haianibrahim/tiktok-scraper)
[![GitHub Stars](https://img.shields.io/github/stars/haianibrahim/tiktok-scraper)](https://github.com/haianibrahim/tiktok-scraper/stargazers)
[![Packagist Version](https://img.shields.io/packagist/v/haianibrahim/tiktok-scraper?color=%23008CFF&logo=packagist)](https://packagist.org/packages/haianibrahim/tiktok-scraper)
![PHP](https://img.shields.io/badge/PHP-%5E8.1-777BB4?logo=php)
![License](https://img.shields.io/badge/License-GPL--3.0-blue)
![Static Analysis](https://img.shields.io/badge/PSR-12-blueviolet)

Scrape basic details from a public TikTok video URL in PHP. This is a cleaned-up, PSR‑4, PHP 8 rewrite using Guzzle and zero private APIs.


## Features

- Minimal public video details: canonical URL, IDs, username, nickname, description, thumbnail and counters
- PSR‑4 autoloaded library with typed DTO output
- Pluggable Guzzle client for testing and timeouts
- No headless browser, no TikTok private API usage


## Installation

Install via Composer:

```bash
composer require haianibrahim/tiktok-scraper
```


## Quick Start

```php
use GuzzleHttp\Client; // Or any ClientInterface
use Hki98\TikTok\TikTokScraper;

require __DIR__ . '/vendor/autoload.php';

$client = new Client();
$scraper = new TikTokScraper($client);

$details = $scraper->scrape('https://www.tiktok.com/@scout2015/video/6718335390845095173');

print_r($details->toArray());
```

Output shape:

- status: "ok"
- link
- user (nickname)
- username
- user_id
- video_id
- video_desc
- thumbnail
- views, likes, comments, shares, favorites


## API

- TikTokScraper::scrape(string $url): VideoDetails
- VideoDetails::toArray(): array

Exceptions
- Base: Hki98\\TikTok\\Exception\\TikTokScraperException (catch-all)
- Specific:
	- Hki98\\TikTok\\Exception\\InvalidUrlException
	- Hki98\\TikTok\\Exception\\HttpRequestException
	- Hki98\\TikTok\\Exception\\EmptyResponseException
	- Hki98\\TikTok\\Exception\\ParseException


## Notes

- TikTok frequently changes HTML structure; this scraper parses the rehydration JSON in a script tag. If the key paths change, update the normalization method.
- Respect robots.txt and terms of service in your jurisdiction.


## Development

- PHP 8.1+
- PSR‑12 coding style recommended
- Autoload: PSR‑4 under namespace `Hki98\\TikTok` (src/)

Run autoload dump after cloning:

```bash
composer dump-autoload
```


## Credit

- Original author: Haian K. Ibrahim (https://github.com/haianibrahim)
- HTML parsing here is regex-based for one script tag; the bundled `simple_html_dom.php` is kept for historical context but not used by the new class.


## License

GPL-3.0
