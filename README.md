> ⚠️ **Status: untested.** This extension is provided as-is and has **not been tested in production**. Please feel free to fork, modify, improve, and open pull requests.
>
> Licensed under **GNU GPLv3** (see [LICENSE](LICENSE)).

# IP Block Protection — Craft CMS / Craft Commerce (Craft 5.10.10)

Screens front-end (site) requests against the **ip-block.com** service and blocks
flagged IP addresses before the page renders. Works for both Craft CMS and Craft Commerce.

## Install
From your project root:
```
composer require ipblock/craft-ip-block-protection
php craft plugin/install ip-block-protection
```
(For local development, add a `path` repository in `composer.json` pointing at this
`ip-block-protection` folder, then require `ipblock/craft-ip-block-protection`.)

Then configure under **Settings → Plugins → IP Block Protection**.

## Settings
| Field | Purpose |
|-------|---------|
| Enable IP Protection | Master on/off |
| Site ID | Your 12-character ip-block.com site identifier |
| API Key | Your 48-character key (supports env vars, e.g. `$IPBLOCK_API_KEY`) |
| API Endpoint URL | Defaults to `https://api.ip-block.com/v1/check` |
| Fail open | Allow (recommended) or block on service error/timeout |
| Decision cache lifetime | Seconds a decision is cached; `0` = every request |
| Behind a proxy / CDN | Read real IP from CF-Connecting-IP / X-Forwarded-For |
| When a visitor is blocked | Redirect to ip-block.com, or show a local 403 |
| Block message | Local 403 text |
| Allowlisted IPs | One IP per line — never blocked |

## How it works
- Registers on `craft\web\Application::EVENT_BEFORE_REQUEST` — runs early, before
  the front-end response is built.
- **Control panel and console requests are never screened** (`getIsCpRequest()` /
  `getIsConsoleRequest()`), so you cannot lock yourself out of the CP.
- `services\Checker` handles allowlisting, decision caching (`Craft::$app->cache`) and
  fail-open/closed; `services\Client` speaks the ip-block.com API
  (`POST /v1/check` → `{"action":"allow"|"block"}`, 1-second timeout).

## Note
Allowlist your own IP before enabling so you cannot lock yourself out of the site.
Store the API key as an environment variable rather than in project config.
