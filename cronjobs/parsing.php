<?php

ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

require_once 'keys.php';
require_once 'helpers/helper_log.php';
require_once 'helpers/helper_parse_sig.php';
require_once 'helpers/helper_constants.php';
require_once 'dbs/mysql_wc.php';

$test_sigs = [
  "Take 2 tablets by mouth in the morning and Take 1 tablet once in the evening" => [
    'qtys_per_time' => '2,1',
    'frequency_numerators' => '1,1',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '0,0'
  ],
  "Use 4 vials in nebulizer as directed in the morning and evening" => [
    'qtys_per_time' => '12', //MLs
    'frequency_numerators' => '2',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "Take 1 tablet (12.5 mg) by mouth daily in the morning" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "Take 1 tablet (80 mg) by mouth daily" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "1 tablet by mouth every day" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "1 Tab(s) by Oral route 1 time per day" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "take 1 tablet (25 mg) by oral route once daily" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "1 capsule by Oral route 1 time per week" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '30/4',
    'durations' => '0'
  ],
  "take 1 tablet (150 mg) by oral route 2 times per day" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '2',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "take 1 tablet (20 mg) by oral route once daily" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "1 capsule by mouth every day on empty stomach" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "1 capsule by mouth every day" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "TAKE ONE CAPSULE BY MOUTH THREE TIMES A WEEK ON MONDAY, WEDNESDAY, AND FRIDAY" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '3',
    'frequency_denominators' => '1',
    'frequencies' => '30/4',
    'durations' => '0'
  ],
  "take 1 tablet (100 mg) by oral route once daily" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "Take 1 tablet (25 mg) by oral route once daily" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "take 1 tablet (10 mg) by oral route once daily in the evening" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "take 2 tablet by Oral route 1 time per day" => [
    'qtys_per_time' => '2',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "Inject 1ml intramuscularly once a week as directed" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '30/4',
    'durations' => '0'
  ],
  "3 ml every 6 hrs Inhalation 90 days" => [
    'qtys_per_time' => '3',
    'frequency_numerators' => '1',
    'frequency_denominators' => '6',
    'frequencies' => '1/24',
    'durations' => '90'
  ],
  "1.5 tablets at bedtime" => [
    'qtys_per_time' => '1.5',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "1 capsule by mouth at bedtime for 1 week then 2 capsules at bedtime" => [
    'qtys_per_time' => '1,2',
    'frequency_numerators' => '1,1',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '7,83'
  ],
  "Take 1 capsule (300 mg total) by mouth 3 (three) times daily." => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '3',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "take 2 tabs PO daily" => [
    'qtys_per_time' => '2',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "take one PO qd" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "Take 1 tablet (10 mg) by mouth 2 times per day for 21 days with food, then increase to 2 tablets (20 mg) BID" => [
    'qtys_per_time' => '1,2',
    'frequency_numerators' => '2,2',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '21,69'
  ],
  "Take one tablet every 12 hours" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '12',
    'frequencies' => '1/24',
    'durations' => '0'
  ],
  "1 tablet 4 times per day on an empty stomach,1 hour before or 2 hours after a meal" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '4',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "1 tablet 4 times per day as needed on an empty stomach,1 hour before or 2 hours after a meal" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '4',
    'frequency_denominators' => '1',
    'frequencies' => '2', //Daily as needed
    'durations' => '0'
  ],
  "ORAL 1 TAB PO QAM PRN SWELLING" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '2', //Daily as needed
    'durations' => '0'
  ],
  "one tablet ORAL every day" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "take 1 tablet by oral route every 8 hours as needed for nausea" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '8',
    'frequencies' => '2/24', //hourly as needed
    'durations' => '0'
  ],
  "Take 2 tablets in the morning and 1 at noon and 1 at supper" => [
    'qtys_per_time' => '2,1,1',
    'frequency_numerators' => '1,1,1',
    'frequency_denominators' => '1,1,1',
    'frequencies' => '1,1,1',
    'durations' => '0,0,0'
  ], //UNFIXED
  "1 at noon" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "Take one capsule by mouth four times daily." => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '4',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "1 tab(s) PO BID,x30 day(s)" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '2',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '30'
  ],
  "Inject 1 each under the skin 3 (three) times a day  To test sugar DX e11.9" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '3',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "Use daily with lantus" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "take 1 tablet by Oral route 3 times per day with food as needed for pain" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '3',
    'frequency_denominators' => '1',
    'frequencies' => '2',  //daily as needed
    'durations' => '0'
  ],
  "Take 1 capsule daily for 7 days then increase to 1 capsule twice daily" => [
    'qtys_per_time' => '1,1',
    'frequency_numerators' => '1,2',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '7,83'
  ],  //UNFIXED BUT USING 2nd RATHER THAN 1st HALF
  "take 1 tablet (500 mg) by oral route 2 times per day with morning and evening meals" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '2',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "Take 1 tablet by mouth twice a day for 10 days , then take 1 tablet daily" => [
    'qtys_per_time' => '1,1',
    'frequency_numerators' => '2,1',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '10,80'
  ], //Unfixed
  "Take 2 capsules by mouth in the morning 1 capsule once daily AT NOON AND 2 capsules at bedtime" => [
    'qtys_per_time' => '3,2',
    'frequency_numerators' => '1,1',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '0,0'
  ], //Unfixed
  "Take 1 tablet by mouth 1 time daily then, if no side effects, increase to 1 tablet 2 times daily with meals" => [
    'qtys_per_time' => '1,1',
    'frequency_numerators' => '1,2',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '0,0' //Uncertain Duration!  What's the default
  ], //Unfixed
  "Take 1 tablet by mouth once daily and two on sundays" => [
    'qtys_per_time' => '1,2',
    'frequency_numerators' => '1,1',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,30/4',
    'durations' => '0,0' //Note this incorrectly gives 1 + 2 = 3 on sunday rather than just 2
  ], //Unfixed
  "Take 4 tablets by mouth 3 times a day with meal and 2 tablets twice a day with snacks" => [
    'qtys_per_time' => '4,2',
    'frequency_numerators' => '3,2',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '0,0'
  ], //Unfixed
  "Take 7.5mg three days per week (M,W,F) and 5mg four days per week OR as directed per Coumadin Clinic." => [
    'drug_name' => 'DRUGXXXXXX 2.5mg',
    'qtys_per_time' => '3,2',
    'frequency_numerators' => '3,4',
    'frequency_denominators' => '1,1',
    'frequencies' => '30/4,30/4',
    'durations' => '0,0'
  ], //Unfixed
  "Take 1 tablet by mouth every 8 hours as needed" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '8',
    'frequencies' => '2/24', //hourly as needed
    'durations' => '0'
  ], //Unfixed
  "Take 1 tablet by mouth twice a day 1 in am and 1 at 3pm" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '2',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ], //Unfixed
  "Take 3 tablets by mouth 3 times a day with meal and 2 tablets twice a day with snack" => [
    'qtys_per_time' => '3,2',
    'frequency_numerators' => '3,2',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '0,0'
  ], //Unfixed
  "Take 1 tablet by mouth once every morning then 1/2 tablet at night" => [
    'qtys_per_time' => '1,0.5',
    'frequency_numerators' => '1,1',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '0,0'
  ], //Unfixed
  "Inject 0.4 mL (40 mg total) under the skin daily as directed" => [
    'qtys_per_time' => '0.4',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ], //Unfixed
  "Place 1 tablet under the tongue every 5 minutes as needed for chest pain Not to exceed 3 tablets per day" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '5',
    'frequencies' => '2', //Daily as needed. TODO This is unclear: minutes or days?  Has effect of qty_per_time too
    'durations' => '0'  //Not attempting to use the limit
  ], //Unfixed
  "Take 1 capsule by mouth once at bedtime x7 days then 1 capsule twice a day x7 days then 3 times a day" => [
    'qtys_per_time' => '1,1,1',
    'frequency_numerators' => '1,2,3',
    'frequency_denominators' => '1,1,1',
    'frequencies' => '1,1,1',
    'durations' => '7,7,76'
  ], //Unfixed
  "Take 2 tablets by mouth once every morning and 3 tablets in the evening" => [
    'qtys_per_time' => '2,3',
    'frequency_numerators' => '1,1',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '0,0'
  ], //Unfixed
  "Take 1 tablet by mouth twice a day with meal" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '2',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ], //Unfixed?
  "TAKE ONE AND ONE-HALF TABLET BY MOUTH TWICE A DAY" => [
    'qtys_per_time' => '1.5',
    'frequency_numerators' => '2',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ], //Unfixed
  "Take 1 TO 2 tablets by mouth twice a day FOR DIABETES" => [
    'qtys_per_time' => '2',
    'frequency_numerators' => '2',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ], //Unfixed
  "1 tab under tongue at onset of CP may repeat twice in five minutes" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '2',
    'frequency_denominators' => '1',
    'frequencies' => '2', //Daily as needed
    'durations' => '0' //"At onset == As needed?
  ], //Unfixed
  "Take 1 tablet by mouth once daily with fluids, as early as possible after the onset of a migraine attack, may repeat 2 hours if headahce returns, not to 200 mg in 24 hours" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ], //Unfixed
  "ORAL Take 1 half tablet daily for high blood pressure" => [
    'qtys_per_time' => '0.5',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "ORAL Take bid for diabetes" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '2',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "Take 1 tablet by mouth @8am and 1/2 tablet @3pm" => [
    'qtys_per_time' => '1,0.5',
    'frequency_numerators' => '1,1',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '0,0'
  ],
  "Take 1 tablet under tongue as directed Take every 5 minutes up to 3 doses as needed for chest" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '5',
    'frequencies' => '2/24/60', // as needed minutes
    'durations' => '0'
  ],
  "Take 1 tablet by mouth once a day when your feet are swollen. When not swollen, take every other day" => [
    'qtys_per_time' => '1,1',
    'frequency_numerators' => '1,1',
    'frequency_denominators' => '1,2',
    'frequencies' => '2,2', //when == as needed, as needed days
    'durations' => '0,0'
  ],
  "Take 1 tablet by mouth 1-3 hours before bedtime" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "Take 2 capsule by mouth three times a day for mood" => [
    'qtys_per_time' => '2',
    'frequency_numerators' => '3',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "Take 1 tablet by mouth in the morning AND Take 0.5 tablets AT LUNCHTIME" => [
    'qtys_per_time' => '1,0.5',
    'frequency_numerators' => '1,1',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '0,0'
  ],
  "Take 1/2 tablet by mouth once daily X7 DAYS THEN INCREASE TO 1 tablet once daily" => [
    'qtys_per_time' => '0.5,1',
    'frequency_numerators' => '1,1',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '7,83'
  ],
  "Take 1 tablet by mouth every morning then 1/2 tablet in the evening" => [
    'qtys_per_time' => '1,0.5',
    'frequency_numerators' => '1,1',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '0,0'
  ],
  "take 3 tablets by oral route daily for 5 days" => [
    'qtys_per_time' => '3',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '5'
  ],
  "Take 3 capsules by mouth 3 times daily with meals and 1 capsule with snacks" => [
    'qtys_per_time' => '3,1',
    'frequency_numerators' => '3,1',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '0,0'
  ],
  "Take 1 tablet by mouth before meals AND at bedtime" => [
    'qtys_per_time' => '1,1',
    'frequency_numerators' => '3,1',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '0,0'
  ],
  "Take 1 tablet (12.5 mg total) by mouth every 12 (twelve) hours" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '12',
    'frequencies' => '1/24',
    'durations' => '0'
  ],
  "1 ORAL every eight hours as needed" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '8',
    'frequencies' => '2/24', //hourly as needed
    'durations' => '0'
  ],
  "Take 5 mg by mouth 2 (two) times daily." => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '2',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "Take 5 by mouth 2 (two) times daily." => [
    'qtys_per_time' => '5',
    'frequency_numerators' => '2',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "Use 1 vial via neb every 4 hours" => [
    'qtys_per_time' => '3', //MLs
    'frequency_numerators' => '1',
    'frequency_denominators' => '4',
    'frequencies' => '1/24',
    'durations' => '0'
  ],  //Should be 1620mls for a 90 day supply
  "Take 1 tablet by mouth every morning and 2 tablets in the evening" => [
    'qtys_per_time' => '1,2',
    'frequency_numerators' => '1,1',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '0,0'
  ],
  "Take 1 tablet by mouth every twelve hours" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '12',
    'frequencies' => '1/24',
    'durations' => '0'
  ],
  "Take 1/2 tablet by mouth every day" => [
    'qtys_per_time' => '0.5',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "Take 1-2 tablet by mouth at bedtime" => [
    'qtys_per_time' => '2',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "1/2 tablet Once a day Orally 90 days" => [
    'qtys_per_time' => '0.5',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '90'
  ],
  "1 capsule every 8 hrs Orally 30 days" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '8',
    'frequencies' => '1/24',
    'durations' => '30'
  ],
  "TAKE 1/2 TO 1 TABLET(S) by mouth EVERY DAY" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "TAKE 1/2 TO 2 TABLETS AT BEDTIME FOR SLEEP." => [
    'qtys_per_time' => '2',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "Take 60 mg daily  1 1\\/2 tablet" => [
    'qtys_per_time' => '1.5',
    'frequency_numerators' => '1',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "ORAL 1 q8-12h prn muscle spasm" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '8',
    'frequencies' => '2/24', //hourly as needed
    'durations' => '0'
  ],
  "Take 1 tablet (12.5 mg total) by mouth every 12 (twelve) hours" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '12',
    'frequencies' => '1/24',
    'durations' => '0'
  ],
  "Take 1 capsule by mouth at bedtime for chronic back pain/ may increase 1 cap/ week x 3 weeks to 3 caps at bedtime" => [
    'qtys_per_time' => '1,3',
    'frequency_numerators' => '1,1',
    'frequency_denominators' => '1,1',
    'frequencies' => '1,1',
    'durations' => '0,21'
  ], //NOT FIXED
  "Take 1 tablet by mouth 3 times a day" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '3',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "Take 1 tablet by mouth 2 (two) times a day with meals." => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '2',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "Take 1 tablet (5 mg total) by mouth 2 (two) times daily." => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '2',
    'frequency_denominators' => '1',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "Take 1 tablet by mouth every other day" => [
    'qtys_per_time' => '1',
    'frequency_numerators' => '1',
    'frequency_denominators' => '2',
    'frequencies' => '1',
    'durations' => '0'
  ],
  "week 1: 100 mg po bid; week 2: 200 mg po bid; week 3: 300 mg po bid; week 4: 400 mg po bid" => [
    'drug_name' => 'DRUGXXXXXX 50mg',
    'qtys_per_time' => '2,4,6,8',
    'frequency_numerators' => '2,2,2,2',
    'frequency_denominators' => '1,1,1,1',
    'frequencies' => '1,1,1,1',
    'durations' => '7,7,7,7'
  ] //Not Fixed
];


global $argv;
$sig_index = array_search('sig', $argv);

if ($sig_index === false) {

  foreach ($test_sigs as $sig => $correct) {
    $correct['sig_actual'] = $sig;
    $parsed = parse_sig($sig, @$correct['drug_name'], $correct);
  }

  log_notice("...sig testing complete...");

} else if ($argv[$sig_index+1] != 'database') {

  $sig = $argv[$sig_index+1];
  $parsed = parse_sig($sig, null);
  log_notice("parsing test sig specified: $sig", [$parsed, $sig]);

} else {

  $mysql = new Mysql_Wc();

  $rxs = $mysql->run("SELECT * FROM gp_rxs_single WHERE sig_initial IS NULL LIMIT 10")[0];

  //log_notice("parsing test sig database rxs", $rxs);

  foreach ($rxs as $rx) {

    $parsed = parse_sig($rx['sig_actual'], $rx['drug_name']);

    if ($rx['sig_qty_per_day'] == $parsed['qty_per_day'])
      log_notice("parsing test sig database SAME: $rx[rx_number] qty_per_day $parsed[qty_per_day], $rx[drug_name], $rx[sig_actual]", $parsed);
    else
      log_notice("parsing test sig database CHANGE: $rx[rx_number] sig_qty_per_day $rx[sig_qty_per_day] >>> $parsed[qty_per_day], $rx[drug_name], $rx[sig_actual]", $parsed);

    if ( ! $parsed['qty_per_day']) {
      log_error("parsing database: sig could not be parsed");
      continue;
    }

    $mysql->run("
      UPDATE gp_rxs_single SET
        sig_initial                = '$parsed[sig_actual]',
        sig_clean                  = '$parsed[sig_clean]',
        sig_qty                    = $parsed[sig_qty],
        sig_days                   = ".($parsed['sig_days'] ?: 'NULL').",
        sig_qty_per_day            = $parsed[qty_per_day],
        sig_durations              = ',".implode(',', $parsed['durations']).",',
        sig_qtys_per_time          = ',".implode(',', $parsed['qtys_per_time']).",',
        sig_frequencies            = ',".implode(',', $parsed['frequencies']).",',
        sig_frequency_numerators   = ',".implode(',', $parsed['frequency_numerators']).",',
        sig_frequency_denominators = ',".implode(',', $parsed['frequency_denominators']).",'
      WHERE
        rx_number = $rx[rx_number]
    ");

  }





}
