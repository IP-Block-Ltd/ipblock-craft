<?php
/**
 * Decides whether the current visitor should be blocked, with allowlisting,
 * per-request caching and configurable fail-open / fail-closed behaviour.
 */

declare(strict_types=1);

namespace ipblock\protection\services;

use Craft;
use ipblock\protection\models\Settings;

class Checker
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function getVisitorIp(): ?string
    {
        if ($this->settings->behindProxy) {
            if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                $ip = trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $first = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }
        }

        $ip = Craft::$app->getRequest()->getUserIP();
        return ($ip !== null && filter_var($ip, FILTER_VALIDATE_IP)) ? $ip : null;
    }

    public function shouldBlock(string $ip): bool
    {
        // Allowlist always wins.
        if (in_array($ip, $this->whitelist(), true)) {
            return false;
        }

        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        $referrer  = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';

        $cache = Craft::$app->getCache();
        $ttl = $this->settings->cacheTtl;
        $key = 'ipblock_' . md5($ip . '|' . $userAgent . '|' . $referrer);

        if ($ttl > 0) {
            $cached = $cache->get($key);
            if ($cached !== false) {
                return $cached === '1';
            }
        }

        try {
            $blocked = (new Client($this->settings))->isBlocked($ip, $userAgent, $referrer);
        } catch (\Throwable $e) {
            Craft::warning('IP Block Protection: ' . $e->getMessage(), 'ip-block-protection');
            // Honour the configured fail mode; do not cache failures.
            return !$this->settings->failOpen;
        }

        if ($ttl > 0) {
            $cache->set($key, $blocked ? '1' : '0', $ttl);
        }

        return $blocked;
    }

    /**
     * @return string[]
     */
    private function whitelist(): array
    {
        $raw = trim($this->settings->whitelist);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn ($ip) => $ip !== ''));
    }
}
