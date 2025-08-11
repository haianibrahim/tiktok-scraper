<?php

declare(strict_types=1);

namespace Hki98\TikTok;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Hki98\TikTok\DTO\VideoDetails;
use Hki98\TikTok\Exception\TikTokScraperException;

/**
 * TikTok video details scraper.
 *
 * - PHP 8.1+ with typed properties and return types
 * - PSR-4 autoloadable (namespace Hki98\\TikTok)
 * - Uses a pluggable Guzzle ClientInterface for testability
 */
final class TikTokScraper
{
    private const REHYDRATION_SCRIPT_ID = '__UNIVERSAL_DATA_FOR_REHYDRATION__';

    public function __construct(private readonly ClientInterface $httpClient)
    {
    }

    /**
     * Scrape details for a TikTok video page by URL.
     */
    public function scrape(string $url): VideoDetails
    {
        $this->assertTikTokUrl($url);
        $html = $this->fetchHtml($url);
        $json = $this->extractEmbeddedJson($html);
        $data = $this->normalizeData($json);

        return new VideoDetails(
            canonicalUrl: $data['canonical'],
            videoId: $data['videoId'],
            description: $data['description'],
            userNickname: $data['user'],
            username: $data['username'],
            userId: $data['userId'],
            thumbnail: $data['thumbnail'],
            views: (int) $data['views'],
            likes: (int) $data['likes'],
            comments: (int) $data['comments'],
            shares: (int) $data['shares'],
            favorites: (int) $data['favorites'],
        );
    }

    private function assertTikTokUrl(string $url): void
    {
        // Basic validation; allow http(s) and common subdomains.
        if (!preg_match('#^https?://([\w.-]+\.)?tiktok\.com/.*#i', $url)) {
            throw new TikTokScraperException('Please enter a valid TikTok URL!');
        }

        // Avoid the homepage which doesn't contain video details
        if (preg_match('#^https?://(www\.)?tiktok\.com/?$#i', $url)) {
            throw new TikTokScraperException('Please enter a valid TikTok URL!');
        }
    }

    private function fetchHtml(string $url): string
    {
        try {
            $res = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => $this->userAgent(),
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new TikTokScraperException('Network error fetching page: ' . $e->getMessage(), 0, $e);
        }

        $body = (string) $res->getBody();
        if ($body === '') {
            throw new TikTokScraperException('Empty response body from TikTok.');
        }
        return $body;
    }

    private function extractEmbeddedJson(string $html): array
    {
        // Find the script tag by id and extract its JSON content safely.
        $pattern = '/<script[^>]*id="' . preg_quote(self::REHYDRATION_SCRIPT_ID, '/') . '"[^>]*>(.*?)<\/script>/si';
        if (!preg_match($pattern, $html, $m)) {
            throw new TikTokScraperException('Unable to locate embedded data on the page.');
        }

        $jsonRaw = html_entity_decode(trim($m[1]));
        $decoded = json_decode($jsonRaw, true);
        if (!is_array($decoded)) {
            throw new TikTokScraperException('Failed to decode embedded JSON.');
        }
        return $decoded;
    }

    /**
     * Normalize the large embedded JSON into a compact details array.
     *
     * @param array<string,mixed> $decoded
     * @return array<string,mixed>
     */
    private function normalizeData(array $decoded): array
    {
        // The embedded object keys can vary. Iterate and pick the first matching structure.
        foreach ($decoded as $object) {
            if (!is_array($object)) {
                continue;
            }

            $canonical = $object['seo.abtest']['canonical'] ?? '';

            $item = $object['webapp.video-detail']['itemInfo']['itemStruct'] ?? null;
            if (!is_array($item)) {
                // Fallback: sometimes the key might be different; try a few common shapes
                $item = $object['itemInfo']['itemStruct'] ?? null;
            }

            if (!is_array($item)) {
                continue; // try next object
            }

            $videoId = (string)($item['id'] ?? '');
            $author = $item['author'] ?? [];
            $video = $item['video'] ?? [];
            $stats = $item['stats'] ?? [];

            $username = (string)($author['uniqueId'] ?? '');
            $userId = (string)($author['id'] ?? '');

            if ($videoId !== '' && $userId !== '' && $username !== '') {
                $thumbnail = (string)($video['dynamicCover'] ?? $video['cover'] ?? '');

                return [
                    'canonical' => (string)$canonical,
                    'videoId' => $videoId,
                    'description' => (string)($item['desc'] ?? ''),
                    'user' => (string)($author['nickname'] ?? ''),
                    'username' => $username,
                    'userId' => $userId,
                    'thumbnail' => $thumbnail,
                    'views' => (int)($stats['playCount'] ?? 0),
                    'likes' => (int)($stats['diggCount'] ?? 0),
                    'comments' => (int)($stats['commentCount'] ?? 0),
                    'shares' => (int)($stats['shareCount'] ?? 0),
                    'favorites' => (int)($stats['collectCount'] ?? 0),
                ];
            }
        }

        throw new TikTokScraperException('Please enter a valid TikTok URL!');
    }

    private function userAgent(): string
    {
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
    }
}
