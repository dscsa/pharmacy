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
 * @param  array  $data    (Optional) A small batch of additional details to
 *      be attached to the event
 * @param  int    $level   (Optional) One of the leves available in the
 *      TriggerEvent class
 * @return boolean  True if the message was succesfully sent
 */
function pd_alert_adam($message, $id, $data = [], $level = TriggerEvent::ERROR)
{

    $event = get_pd_event(
        $message,
        $id,
        ADAM_API_KEY,
        $data,
        $level
    );

    return pd_alert($event);
}

/**
 * Send an alert to the low urgency message service
 * @param  string $message The message to display
 * @param  string $id      A human readable id.  This id will be used to group
 *      messages, so it should unique for individual alerts
 * @param  array  $data    (Optional) A small batch of additional details to
 *      be attached to the event
 * @return boolean  True if the message was succesfully sent
 */
function pd_low_priority($message, $id, $data = [])
{

    $event = get_pd_event(
        $message,
        $id,
        PA_LOW_API_KEY,
        $data
    );

    return pd_alert($event);
}

/**
 * Send an alert to the high urgency message service
 * @param  string $message The message to display
 * @param  string $id      A human readable id.  This id will be used to group
 *      messages, so it should unique for individual alerts
 * @param  array  $data    (Optional) A small batch of additional details to
 *      be attached to the event
 * @return boolean  True if the message was succesfully sent
 */
function pd_high_priority($message, $id, $data = [])
{
    $event = get_pd_event(
        $message,
        $id,
        PA_HIGH_API_KEY,
        $data
    );

    return pd_alert($event);
}

/**
 * Send an alert to the high urgency message service
 * @param  string $message The message to display
 * @param  string $id      A human readable id.  This id will be used to group
 *      messages, so it should unique for individual alerts
 * @param  array  $data    (Optional) A small batch of additional details to
 *      be attached to the event
 * @param  string $deDup   (Optional) A key used to identify this event so
 *      duplicates will not trigger multiple alarms
 * @param  int    $level   (Optional) On of the leves specified on the TriggerEvent Class
 * @return TriggerEvent  A populated trigger event
 */
function get_pd_event(
    $message,
    $id,
    $pdKey,
    $data = [],
    $deDup = null,
    $level = TriggerEvent::ERROR
) {

    $event = new TriggerEvent(
        $pdKey,
        $message,
        $id,
        $level,
        true
    );

    if (!is_null($deDup)) {
        $event->setDeDupKey(md5($deDup));
    }

    if (!empty($data)) {
        $event->setPayloadCustomDetails($data);
    }

    // Lets set a URL if we have one

    return $event;
}

/**
 * Send an alert to pagerduty
 * @param  TriggerEvent $event The pagerduty event
 * @return boolean  True if the message was succesfully sent
 */
function pd_alert(TriggerEvent $event)
{
    try {
        $responseCode = $event->send();
        return ($responseCode == 200);
    } catch (PagerDutyException $exception) {
        return false;
    }

}
