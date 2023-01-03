<?php

class Settings extends Controller
{
    /**
     * Renders the settings index and returns the content.
     * 
     * @return string
     */
    public function index()
    {
        $this->isAdminOrExit();

        $this->view->setTitle('Settings');
        $this->view->renderTemplate('settings/index');

        if ($this->isPOST()) {
            try {
                $this->validateCsrfToken();

                // Check if posted data is changing application settings
                if ($this->getPostValue('application') !== null) {
                    $timezone = $this->getPostValue('timezone');
                    $theme = $this->getPostValue('theme');
                    $filter = $this->getPostValue('filter');
                    $dompart = $this->getPostValue('dompart');
                    $this->applicationSettings($timezone, $theme, $filter, $dompart);
                }

                // Check if posted data is changing global payload settings
                if ($this->getPostValue('global-payload') !== null) {
                    $this->payloadSettings();
                }

                // Check if posted data is changing global alerting settings
                if ($this->getPostValue('global-alert') !== null) {
                    $this->alertSettings();
                }

                // Check if posted data is enabling killswitch
                if ($this->getPostValue('killswitch') !== null) {
                    $password = $this->getPostValue('password');
                    $this->killSwitch($password);
                }

                // Check if posted data is changing alert method settings
                if ($this->getPostValue('alert-methods') !== null) {
                    $this->alertMethods();
                }

                // Check if posted data is changing callback alert settings
                if ($this->getPostValue('callback-alert') !== null) {
                    $callbackOn = $this->getPostValue('callbackon');
                    $url = $this->getPostValue('callback_url');
                    $this->callbackAlertSettings($callbackOn, $url);
                }
            } catch (Exception $e) {
                $this->view->renderMessage($e->getMessage());
            }
        }

        // Retrieves and renders all and current timezone
        $timezones = [];
        $timezone = $this->model('Setting')->get('timezone');
        foreach (timezone_identifiers_list() as $key => $name) {
            $selected = $timezone == $name ? 'selected' : '';
            $timezones[$key]['html'] = "<option $selected value=\"$name\">$name</option>";
        }
        $this->view->renderDataset('timezone', $timezones, true);

        // Retrieves and renders all and current theme
        $themes = [];
        $theme = $this->model('Setting')->get('theme');
        $files = array_diff(scandir(__DIR__ . '/../../assets/css'), array('.', '..'));
        foreach ($files as $file) {
            $themeName = e(str_replace('.css', '', $file));
            $selected = $theme == $themeName ? 'selected' : '';
            $themes[$themeName]['html'] = "<option $selected value=\"$themeName\">" . ucfirst($themeName) . "</option>";
        }
        $this->view->renderDataset('theme', $themes, true);

        // Set settings values
        $settings = $this->model('Setting');
        $filterSave = $settings->get('filter-save');
        $filterAlert = $settings->get('filter-alert');

        // Renders data of correct selected filter
        $this->view->renderData('filter1', $filterSave == 1 && $filterAlert == 1 ? 'selected' : '');
        $this->view->renderData('filter2', $filterSave == 1 && $filterAlert == 0 ? 'selected' : '');
        $this->view->renderData('filter3', $filterSave == 0 && $filterAlert == 1 ? 'selected' : '');
        $this->view->renderData('filter4', $filterSave == 0 && $filterAlert == 0 ? 'selected' : '');

        // Renders checkboxes
        $renderSettings = [
            'collect_uri', 'collect_ip', 'collect_referer', 'collect_user-agent', 'collect_cookies', 'collect_localstorage', 'collect_sessionstorage',
            'collect_dom', 'collect_origin', 'collect_screenshot', 'alert-mail', 'alert-telegram', 'alert-slack', 'alert-discord', 'alert-callback'
        ];
        foreach ($renderSettings as $setting) {
            $this->view->renderChecked($setting, $settings->get($setting) == 1);
        }

        // Renders checkboxes of global alerts
        $alerts = $this->model('Alert');
        $this->view->renderChecked('mailAll', $alerts->get(0, 1, 'enabled'));
        $this->view->renderChecked('telegramAll', $alerts->get(0, 2, 'enabled'));
        $this->view->renderChecked('slackAll', $alerts->get(0, 3, 'enabled'));
        $this->view->renderChecked('discordAll', $alerts->get(0, 4, 'enabled'));

        // Renders data of global alerts
        $this->view->renderData('email', $alerts->get(0, 1, 'value1'));
        $this->view->renderData('telegramToken', $alerts->get(0, 2, 'value1'));
        $this->view->renderData('telegramChatID', $alerts->get(0, 2, 'value2'));
        $this->view->renderData('slackWebhook', $alerts->get(0, 3, 'value1'));
        $this->view->renderData('discordWebhook', $alerts->get(0, 4, 'value1'));

        // Render last data parts
        $this->view->renderData('customjs', $settings->get('customjs'));
        $this->view->renderData('dompart', $settings->get('dompart'));
        $this->view->renderData('callbackURL', $settings->get('callback-url'));

        return $this->showContent();
    }

    /**
     * Updates the applications settings
     * 
     * @param string $timezone The timezone to set
     * @param string $theme The theme name
     * @param string $filter The filter option
     * @param string $dompart The length of the dom part
     * @throws Exception
     * @return void
     */
    private function applicationSettings($timezone, $theme, $filter, $dompart)
    {
        // Validate timezone
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            throw new Exception('The timezone is not a valid timezone.');
        }

        // Validate if theme exists
        $theme = preg_replace('/[^a-zA-Z0-9]/', '', $theme);
        if (!file_exists(__DIR__ . "/../../assets/css/{$theme}.css")) {
            throw new Exception('This theme is not installed.');
        }

        // Check if type is digit
        if (!ctype_digit($dompart)) {
            throw new Exception('The dom length needs to be digits.');
        }

        // Set the value based on the posted filter
        $filterSave = ($filter == 1 || $filter == 2) ? 1 : 0;
        $filterAlert = ($filter == 1 || $filter == 3) ? 1 : 0;

        // Save settings
        $this->model('Setting')->set('dompart', $dompart);
        $this->model('Setting')->set('filter-save', $filterSave);
        $this->model('Setting')->set('filter-alert', $filterAlert);
        $this->model('Setting')->set('timezone', $timezone);
        $this->model('Setting')->set('theme', $theme);
    }

    /**
     * Updates the payload settings
     * 
     * @return void
     */
    private function payloadSettings()
    {
        $options = ['uri', 'ip', 'referer', 'user-agent', 'cookies', 'localstorage', 'sessionstorage', 'dom', 'origin', 'screenshot'];

        foreach ($options as $option) {
            if ($this->getPostValue($option) !== null) {
                $this->model('Setting')->set("collect_{$option}", '1');
            } else {
                $this->model('Setting')->set("collect_{$option}", '0');
            }
        }

        $this->model('Setting')->set("customjs", $this->getPostValue('customjs'));
    }

    /**
     * Updates the global alerting settings
     * 
     * @throws Exception
     * @return void
     */
    private function alertSettings()
    {
        $alerts = $this->model('Alert');

        // Update mail settings
        $mailOn = $this->getPostValue('mailon');
        $mail = $this->getPostValue('mail');
        if (!filter_var($mail, FILTER_VALIDATE_EMAIL) && !empty($mail)) {
            throw new Exception('This is not a correct email address.');
        }
        $alerts->set(0, 1, $mailOn !== null, $mail);

        // Update Telegram settings
        $telegramOn = $this->getPostValue('telegramon');
        $telegramToken = $this->getPostValue('telegram_bottoken');
        $telegramChatID = $this->getPostValue('chatid');
        if (!empty($telegramToken) || !empty($telegramChatID)) {
            if (!preg_match('/^[a-zA-Z0-9:_-]+$/', $telegramToken)) {
                throw new Exception('This does not look like an valid Telegram bot token');
            }

            if (!ctype_digit($telegramChatID)) {
                throw new Exception('The chat id needs to be a digits');
            }
        }
        $alerts->set(0, 2, $telegramOn !== null, $telegramToken, $telegramChatID);

        // Update Slack settings
        $slackOn = $this->getPostValue('slackon');
        $slackWebhook = $this->getPostValue('slack_webhook');
        if (!empty($slackWebhook)) {
            if (!preg_match('/https:\/\/hooks\.slack\.com\/services\/([a-zA-Z0-9]+)\/([a-zA-Z0-9_-]+)\/([a-zA-Z0-9_-]+)$/', $slackWebhook)) {
                throw new Exception('This does not look like an valid Slack webhook URL');
            }
        }
        $alerts->set(0, 3, $slackOn !== null, $slackWebhook);

        // Update Discord settings
        $discordOn = $this->getPostValue('discordon');
        $discordWebhook = $this->getPostValue('discord_webhook');
        if (!empty($discordWebhook)) {
            if (!preg_match('/https:\/\/(discord|discordapp)\.com\/api\/webhooks\/([\d]+)\/([a-zA-Z0-9_-]+)$/', $discordWebhook)) {
                throw new Exception('This does not look like an valid Discord webhook URL');
            }
        }
        $alerts->set(0, 4, $discordOn !== null, $discordWebhook);
    }

    /**
     * Kills the ezXSS platform
     * 
     * @param string $password
     * @return string
     */
    private function killSwitch($password)
    {
        $this->model('Setting')->set("killswitch", $password);
        $this->view->renderErrorPage("ezXSS is now killed with password $password");
    }

    /**
     * Enable or disable alerting methods
     * 
     * @return void
     */
    private function alertMethods()
    {
        $mailOn = $this->getPostValue('mailon') !== null ? '1' : '0';
        $this->model('Setting')->set("alert-mail", $mailOn);

        $telegramOn = $this->getPostValue('telegramon') !== null ? '1' : '0';
        $this->model('Setting')->set("alert-telegram", $telegramOn);

        $slackOn = $this->getPostValue('slackon') !== null ? '1' : '0';
        $this->model('Setting')->set("alert-slack", $slackOn);

        $discordOn = $this->getPostValue('discordon') !== null ? '1' : '0';
        $this->model('Setting')->set("alert-discord", $discordOn);
    }

    /**
     * Updates callback alerting settings
     * 
     * @param string $callbackOn Switch to turn function on or off
     * @param string $url The callback url
     * @throws Exception
     * @return void
     */
    private function callbackAlertSettings($callbackOn, $url)
    {
        $this->model('Setting')->set("alert-callback", $callbackOn !== null ? '1' : '0');

        // Validate callback url
        if (!empty($url) && (strpos($url, "http") !== 0 || filter_var($url, FILTER_VALIDATE_URL) === false)) {
            throw new Exception('Invalid callback URL');
        }
        $this->model('Setting')->set("callback-url", $url);
    }
}