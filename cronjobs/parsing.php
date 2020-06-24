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
  ],
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
  ]

  //Take 2 tablets (250 mcg total) by mouth daily. This medication REPLACES Levothyroxine 112 mcg",
  //'Take 1 tablet by mouth daily Take additional tablet as needed for HF symptoms'
  //Place 1 tablet under the tongue every 5 minutes as needed for chest pain.
  //'Take 1 tablet by mouth five times a day for 10 days.'
  //Take 1 tablet by mouth 5 times a day Take 1 tablet by mouth 5 times a day for 10 days.
  //'Take 1 tablet by mouth once a day as directed Take 30 minute before first meal of the day
  //"Take 1 tablet by mouth at bedtime Dr. Jonathan Williams NPI 1346391299
  //1 Capsule(s) PO QHS TAKE 1 CAPSULE BY MOUTH EVERYDAY AT BEDTIME
  //Take 1/2 TO 1 tablet by mouth once daily
  //1 tab 1 time a day. Orally 90 days
  //Take 2 tablets by mouth as directed, then wait 1 hour and, if needed, take 1 additional tablet. May repeat in 48 hours if needed
  //Take 9 mg ( 1.5 tablets) on Monday and Wednesday, and 6 mg ( 1 tablet) all other days or as dierected.
  //Take 1 capsule by mouth in the morning and @ noon and 2 capsules at bedtime
  //Take 1 tablet by mouth at bedtime after meals for cholesterol
  //take 1 tablet by oral route 1 time per week in the morning, at least 30 min before first food, beverage, or medication of day
  //Take 1 tablet by mouth once weekly every morning at least 30 minutes before first food,beverage, or mediacation of day
  //15 units at bedtime 1 time per day Subcutaneous 90 days
  //Take 5 Tablet with meals TID , max daily dose: 15 Tablet
  //Take 1 tablet by mouth every 48 hours
  //TAKE 3 CAPSULES BY MOUTH THREE TIMES A DAY WITH MEALS 1 with snacks
  //Take 1 capsule by mouth twice a day as needed Take one or two twice a day as needed for anxiety
  //Take 1 tablet by mouth every evening with meals
  //APPLY TO AFFECTED AREA OF PAIN 1 time per day IF NEEDED FOR PAIN. WEAR FOR 12 HOURS & REMOVE FOR 12 HOURS
  //Take 1 tablet by mouth twice a day 8 am and 3 pm
  //1 tab(s) every 24 hours orally 90 days
  //Take 1 tablet by mouth every morning after meals
  //Take 1 capsule by mouth once a day Take 1 capsule by mouth once a day take 30 minutes before breakfast.
  //Take 1 tablet by oral route every day swallowing whole with water. Do not crush, chew and\/or divide
  //3 Tablet with meals 3 times per day, 1 tablet with snack, max per day dose: 10 Tablet
  //Max 3 in 24 hours
  //Take 3 tablets by mouth 3 times a day with meal & Take 1 tablet with a snack, for a total of 10 tablets per day
  //Ttake 1 tablet by Oral route 4 times per day 1 hour before meals & at bedtime for 30 days
  //Take 4mg 1 time per week, Wed; 2mg all other days or as directed. (Drug Name: Warfarin 2mg)
  // 'Apply 1 patch topically once daily . Do not apply to same area more than once every 14 days. for 90 days'
  //Take 1 tablet by mouth  three times per day for 14 days then once daily for 16 days
  //place 1 tablet (0.4 mg) by sublingual route at 1st sign of attack; may repeat every 5 minutes up to 3 tabs; if no relief seek medical help
  //take half a tablet twice daily
  //Inject 0.4 mL (40 mg total) under the skin daily for 18 days.
  //Take 1 tablet by mouth once daily / take 1/2 tablet on sundays
  //Take 1 tablet by mouth 2 times a day. Do not crush or chew.'
  //Take 1 capsule by mouth once daily as needed for reflux
  //'Take 1 tablet by mouth once daily  TAKE WITH 2 MG TABLET..'
  //Take 1 tablet by mouth daily. take 200 mg for one week then increase to 300 mg daily (Note: Allopurinol 300mg Drug)
  //PLACE 1 tablet under the tongue EVERY 5 MINUTES for 3 doses FOR CHEST PAIN. If unchanged, call 911.
  //20 mg PO qDay
  //Take 4,000 mL by mouth once for 1 dose. As directed (PEG should be qty of 1)
  //Take 1 tablet by mouth  6 DAYS A WEEK as directed
  //Take 1 capsule by mouth in the morning and noon then 2 capsules at bedtime
  //Place 1 tablet under the tongue every 5 minutes as needed for chest pain Not to exceed 3 tablets per day
  //Take 30ml by mouth as needed Take 30cc PO as needed for constipation
  //Take 1 tablet and dissolve in 2oz liquid before meals and at bedtime
  //PLACE 1 TABLET UNDER TONGUE 3 times per day FOR UP TO 3 DOSES AS NEEDED FOR CHEST PAIN
  //Take 1& 1\/2 tablet by mouth once daily on Monday and Wednesday and Take 1 tablet once daily on all other days
  //Take 1 tablet by mouth in the morning 1& 1/2 tablet in the evening
];

global $argv;
$sig_index = array_search('sig', $argv);

if ($sig_index === false) {

  foreach ($test_sigs as $sig => $correct) {
    $correct['sig_actual'] = $sig;
    $parsed = get_parsed_sig($sig, @$correct['drug_name'], $correct);
  }

  log_notice("...sig testing complete...");

} else if ($argv[$sig_index+1] != 'database') {

  $sig = $argv[$sig_index+1];
  $parsed = get_parsed_sig($sig, null);
  log_notice("parsing test sig specified: $sig", [$parsed, $sig]);

} else {

  $mysql = new Mysql_Wc();

  $rxs = $mysql->run("SELECT * FROM gp_rxs_single WHERE sig_initial IS NULL OR sig_qty_per_day_default IS NULL LIMIT 1000")[0];

  //log_notice("parsing test sig database rxs", $rxs);

  foreach ($rxs as $rx) {

    $parsed = get_parsed_sig($rx['sig_actual'], $rx['drug_name']);

    if ($rx['sig_qty_per_day_default'] == $parsed['qty_per_day'])
      log_notice("parsing test sig database SAME: $rx[rx_number] qty_per_day $parsed[qty_per_day], $rx[drug_name], $rx[sig_actual]", $parsed);
    else
      log_notice("parsing test sig database CHANGE: $rx[rx_number] sig_qty_per_day_default $rx[sig_qty_per_day_default] >>> $parsed[qty_per_day], $rx[drug_name], $rx[sig_actual]", $parsed);

    set_parsed_sig($rx['rx_number'], $parsed, $mysql);
  }
}
