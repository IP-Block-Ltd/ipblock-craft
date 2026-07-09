<?php
/**
 * IP Block Protection for Craft CMS / Craft Commerce.
 *
 * Screens front-end (site) requests against ip-block.com before the page renders.
 */

declare(strict_types=1);

namespace ipblock\protection;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\web\Application;
use ipblock\protection\models\Settings;
use ipblock\protection\services\Checker;
use yii\base\Event;

class Plugin extends BasePlugin
{
    public bool $hasCpSettings = true;

    public function init(): void
    {
        parent::init();

        // Screen site requests as early as possible. The control panel and console
        // are never screened, so an operator can never be locked out.
        Event::on(Application::class, Application::EVENT_BEFORE_REQUEST, function (): void {
            $this->guard();
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('ip-block-protection/settings', [
            'settings' => $this->getSettings(),
        ]);
    }

    private function guard(): void
    {
        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest() || $request->getIsCpRequest()) {
            return;
        }

        /** @var Settings $settings */
        $settings = $this->getSettings();
        if (!$settings->enabled) {
            return;
        }

        $checker = new Checker($settings);
        $ip = $checker->getVisitorIp();
        if ($ip === null || !$checker->shouldBlock($ip)) {
            return;
        }

        $response = Craft::$app->getResponse();

        if ($settings->blockAction === 'redirect') {
            $response->redirect('https://www.ip-block.com/blocked.php')->send();
        } else {
            $message = htmlspecialchars($settings->blockMessage, ENT_QUOTES);
            $response->setStatusCode(403);
            $response->getHeaders()->set('Cache-Control', 'no-store, no-cache, must-revalidate');
            $response->content = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                . '<title>Access blocked</title></head>'
                . '<body style="font-family:Arial,Helvetica,sans-serif;text-align:center;padding:64px 24px;color:#333;">'
                . '<h1 style="font-size:28px;margin-bottom:12px;">Access blocked</h1>'
                . '<p style="font-size:16px;">' . $message . '</p></body></html>';
            $response->send();
        }

        Craft::$app->end();
    }
}
