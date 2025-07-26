<?php

declare(strict_types=1);

namespace App\Services\VideoExtraction\Drivers;

use App\Enums\Platform;

class FacebookDriver extends AbstractDriver
{
    public function getPlatform(): Platform
    {
        return Platform::FACEBOOK;
    }

    public function getUrlPatterns(): array
    {
        return [
            '/facebook\.com\/.*\/videos\//',
            '/facebook\.com\/watch\//',
            '/facebook\.com\/share\/v\//',
            '/fb\.watch\//',
            '/m\.facebook\.com\//',
        ];
    }

    protected function extractVideoId(string $url): ?string
    {
        if (preg_match('/facebook\.com\/.*\/videos\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('/facebook\.com\/watch\/\?v=(\d+)/', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('/facebook\.com\/share\/v\/([a-zA-Z0-9]+)/', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('/fb\.watch\/([a-zA-Z0-9]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
