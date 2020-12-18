<?php

require_once 'vendor/autoload.php';

use \PagerDuty\TriggerEvent;
use \PagerDuty\Exceptions\PagerDutyException;

const ADAM_API_KEY    = 'e3dc75a25b1b4d289a83a067a93f543e';
const PA_HIGH_API_KEY = 'b5d70a71999d499f8a801aa08ce96fda';
const PA_LOW_API_KEY  = '62696f7ac2854a8284231a0c655d6503';

/**
 * Send an alert to just Adam
 * @param  string $message The message to display
 * @param  string $id      A human readable id.  This id will be used to group
 *      messages, so it should unique for individual alerts
 * @param  int    $level   (Optional) One of the leves available in the
 *      TriggerEvent class
 * @return boolean  True if the message was succesfully sent
 */
function pd_alert_adam($message, $id, $level = TriggerEvent::ERROR)
{
    return pd_alert($message, $id, ADAM_API_KEY, $level);
}

/**
 * Send an alert to the low urgency message service
 * @param  string $message The message to display
 * @param  string $id      A human readable id.  This id will be used to group
 *      messages, so it should unique for individual alerts
 * @return boolean  True if the message was succesfully sent
 */
function pd_low_priority($message, $id)
{
    return pd_alert($message, $id, PA_LOW_API_KEY);
}

/**
 * Send an alert to the high urgency message service
 * @param  string $message The message to display
 * @param  string $id      A human readable id.  This id will be used to group
 *      messages, so it should unique for individual alerts
 * @return boolean  True if the message was succesfully sent
 */
function pd_high_priority($message, $id)
{
    return pd_alert($message, $id, PA_HIGH_API_KEY);
}

/**
 * Send an alert to pagerduty
 * @param  string $message The message to display in pager duty
 * @param  string $id      A unique id that will be used to group messages.  The
 *      ID should be unique to the event.  if multiple messages with the same id
 *      are sent, they will be grouped together in a single incident.
 * @param  string $key     An API key for the specific pagerduty service
 * @param  int    $level   (Optional) The level of the event.  Should be one of
 *      the levels availabe inside ther TriggerEvent class
 * @return boolean  True if the message was succesfully sent
 */
function pd_alert($message, $id, $key, $level = TriggerEvent::ERROR)
{
    try {
        $event = new TriggerEvent(
            $key,
            $message,
            $id,
            $level,
            true
        );

        $responseCode = $event->send();

        return ($responseCode == 200);
    } catch (PagerDutyException $exception) {
        return false;
    }
}
