<?php

declare(strict_types=1);

namespace Hki98\TikTok;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Hki98\TikTok\DTO\UserInfo;
use Hki98\TikTok\DTO\VideoDetails;
use Hki98\TikTok\Exception\TikTokScraperException;
use Hki98\TikTok\Exception\InvalidUrlException;
use Hki98\TikTok\Exception\HttpRequestException;
use Hki98\TikTok\Exception\EmptyResponseException;
use Hki98\TikTok\Exception\ParseException;

/**
 * TikTok video and photo details scraper.
 *
 * - PHP 8.1+ with typed properties and return types
 * - PSR-4 autoloadable (namespace Hki98\\TikTok)
 * - Uses a pluggable Guzzle ClientInterface for testability
 * - Supports both video posts (/video/) and photo posts (/photo/)
 */
final class TikTokScraper
{
    private const REHYDRATION_SCRIPT_ID = '__UNIVERSAL_DATA_FOR_REHYDRATION__';

    public function __construct(private readonly ClientInterface $httpClient)
    {
    }

    /**
     * Scrape details for a TikTok video or photo page by URL.
     * 
     * @param string $url The TikTok URL (supports video posts, photo posts, and short URLs)
     * @return VideoDetails The scraped post details in a normalized format
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

    /**
     * Scrape user/profile information by username or full TikTok profile URL.
     *
     * Accepts:
     *  - "username"
     *  - "@username"
     *  - "https://www.tiktok.com/@username"
     *
     * @param string $usernameOrUrl
     * @return UserInfo
     */
    public function scrapeUser(string $usernameOrUrl): UserInfo
    {
        $url = $this->buildUserUrl($usernameOrUrl);
        $html = $this->fetchHtml($url);
        $json = $this->extractEmbeddedJson($html);

        return $this->normalizeUserData($json);
    }

    private function buildUserUrl(string $usernameOrUrl): string
    {
        $value = trim($usernameOrUrl);
        if ($value === '') {
            throw InvalidUrlException::forUrl($usernameOrUrl);
        }

        // Full URL: validate it's a profile URL (has @username, no /video/ or /photo/)
        if (preg_match('~^https?://~i', $value)) {
            if (!preg_match('~^https?://([\w.-]+\.)?tiktok\.com/@[^/?\#]+/?$~i', $value)) {
                throw InvalidUrlException::forUrl($usernameOrUrl);
            }
            return $value;
        }

        // Bare username (optionally with leading @)
        $username = ltrim($value, '@');
        if (!preg_match('/^[A-Za-z0-9._]{1,24}$/', $username)) {
            throw InvalidUrlException::forUrl($usernameOrUrl);
        }

        return 'https://www.tiktok.com/@' . $username;
    }

    /**
     * @param array<string,mixed> $decoded
     */
    private function normalizeUserData(array $decoded): UserInfo
    {
        $scope = $decoded['__DEFAULT_SCOPE__'] ?? $decoded;
        if (!is_array($scope)) {
            throw ParseException::invalidStructure();
        }

        $userInfo = $scope['webapp.user-detail']['userInfo'] ?? null;

        // Fallback: deep search for a node with both `user` and `stats` and a `secUid`
        if (!is_array($userInfo)) {
            $flat = [];
            $this->flattenArray($decoded, $flat);
            $userInfo = $this->findFirstMatch($flat, function ($node) {
                return is_array($node)
                    && isset($node['user']) && is_array($node['user'])
                    && isset($node['user']['secUid'])
                    && isset($node['stats']) && is_array($node['stats']);
            });
        }

        if (!is_array($userInfo) || !isset($userInfo['user']) || !is_array($userInfo['user'])) {
            throw ParseException::invalidStructure();
        }

        $user = $userInfo['user'];
        $stats = is_array($userInfo['stats'] ?? null) ? $userInfo['stats'] : [];
        $shareMeta = is_array($userInfo['shareMeta'] ?? null) ? $userInfo['shareMeta'] : [];

        $username = (string)($user['uniqueId'] ?? '');
        $userId = (string)($user['id'] ?? '');
        $secUid = (string)($user['secUid'] ?? '');

        if ($username === '' || $userId === '' || $secUid === '') {
            throw ParseException::invalidStructure();
        }

        return new UserInfo(
            userId: $userId,
            secUid: $secUid,
            username: $username,
            nickname: (string)($user['nickname'] ?? ''),
            signature: (string)($user['signature'] ?? ''),
            avatarThumb: (string)($user['avatarThumb'] ?? ''),
            avatarMedium: (string)($user['avatarMedium'] ?? ''),
            avatarLarger: (string)($user['avatarLarger'] ?? ''),
            verified: (bool)($user['verified'] ?? false),
            privateAccount: (bool)($user['privateAccount'] ?? false),
            createTime: (int)($user['createTime'] ?? 0),
            region: (string)($user['region'] ?? ''),
            followerCount: (int)($stats['followerCount'] ?? 0),
            followingCount: (int)($stats['followingCount'] ?? 0),
            heartCount: (int)($stats['heartCount'] ?? ($stats['heart'] ?? 0)),
            videoCount: (int)($stats['videoCount'] ?? 0),
            diggCount: (int)($stats['diggCount'] ?? 0),
            friendCount: (int)($stats['friendCount'] ?? 0),
            profileUrl: 'https://www.tiktok.com/@' . $username,
            shareTitle: (string)($shareMeta['title'] ?? ''),
            shareDesc: (string)($shareMeta['desc'] ?? ''),
        );
    }

    private function assertTikTokUrl(string $url): void
    {
        // Basic validation; allow http(s) and common subdomains (including vt.tiktok.com for short URLs).
            if (!preg_match('#^https?://([\w.-]+\.)?tiktok\.com/.*#i', $url)) {
                throw InvalidUrlException::forUrl($url);
        }

        // Avoid the homepage which doesn't contain video/photo details
            if (preg_match('#^https?://(www\.)?tiktok\.com/?$#i', $url)) {
                throw InvalidUrlException::forUrl($url);
        }
    }

    private function fetchHtml(string $url): string
    {
        try {
            $res = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => $this->userAgent(),
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                ],
                'allow_redirects' => [
                    'max' => 10,
                    'strict' => false,
                    'referer' => true,
                    'track_redirects' => true
                ],
                'decode_content' => true,  // Auto-decode gzip/deflate
            ]);
        } catch (GuzzleException $e) {
                throw HttpRequestException::from($e);
        }

        $body = (string) $res->getBody();
            if ($body === '') {
                throw EmptyResponseException::create();
        }
        return $body;
    }

    private function extractEmbeddedJson(string $html): array
    {
        // Find the script tag by id and extract its JSON content safely.
        $pattern = '/<script[^>]*id="' . preg_quote(self::REHYDRATION_SCRIPT_ID, '/') . '"[^>]*>(.*?)<\/script>/si';
        if (!preg_match($pattern, $html, $m)) {
                throw ParseException::unableToLocateData();
        }

        $jsonRaw = html_entity_decode(trim($m[1]));
        $decoded = json_decode($jsonRaw, true);
            if (!is_array($decoded)) {
                throw ParseException::jsonDecode();
        }
        return $decoded;
    }

    /**
     * Normalize the large embedded JSON into a compact details array.
     * Handles both video and photo posts using a robust flattening approach.
     *
     * @param array<string,mixed> $decoded
     * @return array<string,mixed>
     */
    private function normalizeData(array $decoded): array
    {
        // Flatten the entire JSON structure into a searchable array
        $flatNodes = [];
        $this->flattenArray($decoded, $flatNodes);

        // Try to find canonical URL
        $canonical = $this->findFirstMatch($flatNodes, function($node) {
            return is_string($node) && preg_match('~https?://www\.tiktok\.com/@[^/]*/(video|photo)/\d+~i', $node);
        });

        // Find the main post structure (works for both video and photo)
        $item = $this->findFirstMatch($flatNodes, function($node) {
            return is_array($node) && (
                (isset($node['imagePost']) || isset($node['video'])) &&
                isset($node['author']) &&
                isset($node['stats'])
            );
        });

        // Try alternate path if not found
        if (!is_array($item)) {
            $item = $this->findFirstMatch($flatNodes, function($node) {
                return is_array($node) && isset($node['itemStruct']);
            });
            if (is_array($item) && isset($item['itemStruct'])) {
                $item = $item['itemStruct'];
            }
        }

        // Final fallback: look in traditional locations
        if (!is_array($item)) {
            foreach ($decoded as $object) {
                if (!is_array($object)) {
                    continue;
                }

                $canonical = $canonical ?? ($object['seo.abtest']['canonical'] ?? '');

                // Try video-detail first, then photo-detail for photo posts
                $item = $object['webapp.video-detail']['itemInfo']['itemStruct'] ?? null;
                if (!is_array($item)) {
                    $item = $object['webapp.photo-detail']['itemInfo']['itemStruct'] ?? null;
                }
                if (!is_array($item)) {
                    $item = $object['itemInfo']['itemStruct'] ?? null;
                }

                if (is_array($item)) {
                    break;
                }
            }
        }

        // If still no item found, try minimal data extraction for photo posts (login wall/bot detection scenario)
        if (!is_array($item) && is_string($canonical) && $canonical !== '') {
            return $this->extractMinimalPhotoData($canonical);
        }

        if (!is_array($item)) {
            throw ParseException::invalidStructure();
        }

        $videoId = (string)($item['id'] ?? '');
        $author = $item['author'] ?? [];
        $stats = $item['stats'] ?? [];

        $username = (string)($author['uniqueId'] ?? '');
        $userId = (string)($author['id'] ?? '');

        if ($videoId !== '' && $userId !== '' && $username !== '') {
            // Extract thumbnail: check for photo post first, then video
            $thumbnail = '';
            
            if (isset($item['imagePost']['images']) && is_array($item['imagePost']['images'])) {
                // Photo post - get first image
                $firstImage = $item['imagePost']['images'][0] ?? null;
                if (is_array($firstImage)) {
                    // Try multiple paths for the image URL
                    $thumbnail = $this->extractFirstUrl($firstImage['imageURL'] ?? $firstImage['displayImage'] ?? null);
                }
            } else {
                // Video post
                $video = $item['video'] ?? [];
                $thumbnail = (string)($video['dynamicCover'] ?? $video['cover'] ?? '');
            }

            return [
                'canonical' => (string)($canonical ?? ''),
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

        throw ParseException::invalidStructure();
    }

    /**
     * Extract minimal data for photo posts when full data is not available.
     * This handles cases where TikTok returns limited data (login wall/bot detection).
     * 
     * @param string $canonical The canonical URL of the post
     * @return array<string,mixed>
     */
    private function extractMinimalPhotoData(string $canonical): array
    {
        // Extract post ID and username from URL
        // Format: https://www.tiktok.com/@username/photo/1234567890
        if (preg_match('~https?://www\.tiktok\.com/@([^/]*)/(video|photo)/(\d+)~i', $canonical, $matches)) {
            $username = $matches[1];
            $postId = $matches[3];
            
            // Return minimal valid data structure
            return [
                'canonical' => $canonical,
                'videoId' => $postId,
                'description' => '',
                'user' => '',
                'username' => $username,
                'userId' => '',
                'thumbnail' => '',
                'views' => 0,
                'likes' => 0,
                'comments' => 0,
                'shares' => 0,
                'favorites' => 0,
            ];
        }

        throw ParseException::invalidStructure();
    }

    /**
     * Flatten a nested array structure into a list of all nodes.
     */
    private function flattenArray($node, array &$output): void
    {
        $output[] = $node;
        if (is_array($node)) {
            foreach ($node as $value) {
                $this->flattenArray($value, $output);
            }
        }
    }

    /**
     * Find the first node that matches a predicate.
     */
    private function findFirstMatch(array $nodes, callable $predicate)
    {
        foreach ($nodes as $node) {
            if ($predicate($node)) {
                return $node;
            }
        }
        return null;
    }

    /**
     * Extract the first URL from a value that might be a string, array, or nested structure.
     */
    private function extractFirstUrl($value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value)) {
            // Try urlList first (common in image structures)
            if (isset($value['urlList']) && is_array($value['urlList'])) {
                $first = $value['urlList'][0] ?? null;
                if (is_string($first)) {
                    return $first;
                }
            }
            // Try first element
            $first = reset($value);
            if (is_string($first)) {
                return $first;
            }
        }
        return '';
    }

    private function userAgent(): string
    {
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0';
    }
}
