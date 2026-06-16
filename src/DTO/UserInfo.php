<?php

declare(strict_types=1);

namespace Hki98\TikTok\DTO;

final class UserInfo
{
    public function __construct(
        public readonly string $userId,
        public readonly string $secUid,
        public readonly string $username,
        public readonly string $nickname,
        public readonly string $signature,
        public readonly string $avatarThumb,
        public readonly string $avatarMedium,
        public readonly string $avatarLarger,
        public readonly bool $verified,
        public readonly bool $privateAccount,
        public readonly int $createTime,
        public readonly string $region,
        public readonly int $followerCount,
        public readonly int $followingCount,
        public readonly int $heartCount,
        public readonly int $videoCount,
        public readonly int $diggCount,
        public readonly int $friendCount,
        public readonly string $profileUrl,
        public readonly string $shareTitle,
        public readonly string $shareDesc,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => 'ok',
            'user_id' => $this->userId,
            'sec_uid' => $this->secUid,
            'username' => $this->username,
            'nickname' => $this->nickname,
            'signature' => $this->signature,
            'avatar_thumb' => $this->avatarThumb,
            'avatar_medium' => $this->avatarMedium,
            'avatar_larger' => $this->avatarLarger,
            'verified' => $this->verified,
            'private_account' => $this->privateAccount,
            'create_time' => $this->createTime,
            'region' => $this->region,
            'follower_count' => $this->followerCount,
            'following_count' => $this->followingCount,
            'heart_count' => $this->heartCount,
            'video_count' => $this->videoCount,
            'digg_count' => $this->diggCount,
            'friend_count' => $this->friendCount,
            'profile_url' => $this->profileUrl,
            'share_title' => $this->shareTitle,
            'share_desc' => $this->shareDesc,
        ];
    }
}
