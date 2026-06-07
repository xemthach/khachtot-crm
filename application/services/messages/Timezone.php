<?php

namespace app\services\messages;

defined('BASEPATH') or exit('No direct script access allowed');

use app\services\messages\AbstractMessage;

class Timezone extends AbstractMessage
{
    private static $timezonesList = null;

    private function getTimezonesList()
    {
        if (self::$timezonesList === null) {
            self::$timezonesList = array_values(array_unique(array_flatten(get_timezones_list())));
        }

        return self::$timezonesList;
    }

    public function isVisible()
    {
        $currentTimezone = get_option('default_timezone');

        return $currentTimezone == '' || !in_array($currentTimezone, $this->getTimezonesList(), true);
    }

    public function getMessage()
    {
        $html = '';
        if (get_option('default_timezone') == '') {
            $html .= '<strong>Default timezone not set. Navigate to Setup->Settings->Localization to set default system timezone.</strong>';
        } else {
            if (!in_array(get_option('default_timezone'), $this->getTimezonesList(), true)) {
                $html .= '<strong>We updated the timezone logic for the app. Seems like your previous timezone do not fit with the new logic. Navigate to Setup->Settings->Localization to set new proper timezone.</strong>';
            }
        }

        return $html;
    }
}
