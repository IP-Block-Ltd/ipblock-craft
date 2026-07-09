<?php
/**
 * HTTP client for the ip-block.com screening API.
 *
 * Contract (https://api.ip-block.com/v1):
 *   POST /check  body: {api_key, site_id, ip, user_agent?, referrer?}
 *                response: {"action": "allow"} | {"action": "block"}
 */

declare(strict_types=1);

namespace ipblock\protection\services;

use Craft;
use ipblock\protection\models\Settings;

class Client
{
    public function __construct(private readonly Settings $settings)
    {
    }

    /**
     * @throws \Throwable on transport, HTTP or parse failure
     */
    public function isBlocked(string $ip, string $userAgent = '', string $referrer = ''): bool
    {
        $guzzle = Craft::createGuzzleClient([
            'timeout' => 1, // vendor recommends a 1-second timeout and failing open
            'connect_timeout' => 1,
        ]);

        $response = $guzzle->post($this->settings->apiUrl ?: 'https://api.ip-block.com/v1/check', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'api_key' => $this->settings->apiKey,
                'site_id' => $this->settings->siteId,
                'ip' => $ip,
                'user_agent' => $userAgent,
                'referrer' => $referrer,
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);
        if (!is_array($data) || !isset($data['action'])) {
            throw new \RuntimeException('ip-block.com returned an unreadable response');
        }

        return $data['action'] === 'block';
    }
}
