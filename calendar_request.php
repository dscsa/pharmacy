<?php
require 'vendor/autoload.php';

$client = new Google_Client();
$client->setAuthConfig('unified-logging-292316-9c58449917b6.json');
$client->addScope('https://www.googleapis.com/auth/calendar');
$client->addScope('https://www.googleapis.com/auth/calendar.events');
$client->setSubject('bbsecure@sirum.org');
$client->setApplicationName("Unified Logging");
//$client->refreshTokenWithAssertion();

$token = $client->getAccessToken();
$service = new Google_Service_Calendar($client);

$event = new Google_Service_Calendar_Event(array(
  'summary' => 'Ben Test Even',
  'description' => 'A chance to hear more about Google\'s developer products.',
  'start' => array(
    'dateTime' => date('c'),
    'timeZone' => 'America/New_York',
  ),
  'end' => array(
    'dateTime' => date('c', strtotime('+10 minute')),
    'timeZone' => 'America/New_York',
  )
));

$calendarId = 'support@goodpill.org';
$event = $service->events->insert($calendarId, $event);
printf('Event created: %s\n', $event->htmlLink);

// $results = $service->events->listEvents('support@goodpill.org', $optParams);
// $events = $results->getItems();

//var_dump($calendar);
