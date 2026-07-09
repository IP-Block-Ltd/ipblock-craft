<?php
declare(strict_types=1);

namespace ipblock\protection\models;

use craft\base\Model;

class Settings extends Model
{
    public bool $enabled = false;
    public string $siteId = '';
    public string $apiKey = '';
    public string $apiUrl = 'https://api.ip-block.com/v1/check';
    public bool $failOpen = true;
    public int $cacheTtl = 300;
    public bool $behindProxy = false;
    public string $blockAction = 'redirect';
    public string $blockMessage = 'Access to this store has been blocked.';
    public string $whitelist = '';

    public function rules(): array
    {
        return [
            [['siteId', 'apiKey', 'apiUrl', 'blockAction', 'blockMessage', 'whitelist'], 'string'],
            [['enabled', 'failOpen', 'behindProxy'], 'boolean'],
            [['cacheTtl'], 'integer', 'min' => 0],
            [['apiUrl'], 'required'],
            [['blockAction'], 'in', 'range' => ['redirect', 'message']],
        ];
    }
}
