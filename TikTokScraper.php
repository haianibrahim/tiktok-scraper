<?php
/**
 * Deprecated shim kept for backwards compatibility with older code that
 * instantiated this class directly and expected array responses.
 *
 * New code should use the namespaced class `Hki98\\TikTok\\TikTokScraper`.
 */

declare(strict_types=1);

namespace hki98;

use GuzzleHttp\Client;
use Hki98\TikTok\TikTokScraper as NewTikTokScraper;

class TikTokScraper
{
    public function __construct(private string $url)
    {
    }

    /**
     * @return array{status:string,link:string,user:string,username:string,user_id:string,video_id:string,video_desc:string,thumbnail:string,views:int,likes:int,comments:int,shares:int,favorites:int}|array{status:string,code:int,message:string}
     */
    public function scrapeVideoDetails(): array
    {
        try {
            $client = new Client();
            $scraper = new NewTikTokScraper($client);
            $dto = $scraper->scrape($this->url);
            return $dto->toArray();
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'code' => 2,
                'message' => 'Something went wrong: ' . $e->getMessage(),
            ];
        }
    }
}
