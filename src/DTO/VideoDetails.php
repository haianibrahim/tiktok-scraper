<?php

declare(strict_types=1);

namespace Hki98\TikTok\DTO;

final class VideoDetails
{
    public function __construct(
        public readonly string $canonicalUrl,
        public readonly string $videoId,
        public readonly string $description,
        public readonly string $userNickname,
        public readonly string $username,
        public readonly string $userId,
        public readonly string $thumbnail,
        public readonly int $views,
        public readonly int $likes,
        public readonly int $comments,
        public readonly int $shares,
        public readonly int $favorites,
    ) {
    }

    /**
     * Convert to array for JSON serialization or other uses.
     * Keys mirror the previous array structure for backwards compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => 'ok',
            'link' => $this->canonicalUrl,
            'user' => $this->userNickname,
            'username' => $this->username,
            'user_id' => $this->userId,
            'video_id' => $this->videoId,
            'video_desc' => $this->description,
            'thumbnail' => $this->thumbnail,
            'views' => $this->views,
            'likes' => $this->likes,
            'comments' => $this->comments,
            'shares' => $this->shares,
            'favorites' => $this->favorites,
        ];
    }
}
