<?php

ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

require_once 'helpers/helper_log.php';
require_once 'helpers/helper_parse_sig.php';

$test_sigs = [
  "Use 4 vials in nebulizer as directed in the morning and evening",
  "Take 1 tablet (12.5 mg) by mouth daily in the morning",
  "Take 1 tablet (80 mg) by mouth daily",
  "1 tablet by mouth every day",
  "1 Tab(s) by Oral route 1 time per day",
  "take 1 tablet (25 mg) by oral route once daily",
  "1 capsule by Oral route 1 time per week",
  "take 1 tablet (150 mg) by oral route 2 times per day",
  "take 1 tablet (20 mg) by oral route once daily",
  "1 capsule by mouth every day on empty stomach",
  "1 capsule by mouth every day",
  "TAKE ONE CAPSULE BY MOUTH THREE TIMES A WEEK ON MONDAY, WEDNESDAY, AND FRIDAY",
  "take 1 tablet (100 mg) by oral route once daily",
  "Take 1 tablet (25 mg) by oral route once daily",
  "take 1 tablet (10 mg) by oral route once daily in the evening",
  "take 2 tablet by Oral route 1 time per day",
  "Inject 1ml intramuscularly once a week as directed",
  "3 ml every 6 hrs Inhalation 90 days",
  "1.5 tablets at bedtime",
  "1 capsule by mouth at bedtime for 1 week then 2 capsules at bedtime",
  "Take 1 capsule (300 mg total) by mouth 3 (three) times daily.",
  "take 2 tabs PO daily",
  "take one PO qd",
  "Take 1 tablet (15 mg) by mouth 2 times per day for 21 days with food, then increase to 20 mg BID",
  "Take one tablet every 12 hours",
  "1 tablet 4 times per day on an empty stomach,1 hour before or 2 hours after a meal",
  "1 tablet 4 times per day as needed on an empty stomach,1 hour before or 2 hours after a meal",
  "ORAL 1 TAB PO QAM PRN SWELLING",
  "one tablet ORAL every day",
  "take 1 tablet by oral route  every 8 hours as needed for nausea",
  "Take 2 tablets in the morning and 1 at noon and 1 at supper", //UNFIXED
  "1 at noon",
  "Take  One capsule by mouth four times daily.",
  "1 tab(s) PO BID,x30 day(s)",
  "Inject 1 each under the skin 3 (three) times a day  To test sugar DX e11.9",
  "Use daily with lantus",
  "take 1 tablet by Oral route 3 times per day with food as needed for pain",
  "Take 1 capsule daily for 7 days then increase to 1 capsule twice daily",  //UNFIXED BUT USING 2nd RATHER THAN 1st HALF
  "take 1 tablet (500 mg) by oral route 2 times per day with morning and evening meals",
  "Take 1 tablet by mouth twice a day for 10 days , then take 1 tablet daily", //Unfixed
  "Take 2 capsules by mouth in the morning 1 capsule once daily AT NOON AND 2 capsules at bedtime", //Unfixed
  "Take 1 tablet by mouth 1 time daily then, if no side effects, increase to 1 tablet 2 times daily with meals", //Unfixed
  "Take 1 tablet by mouth once daily and two on sundays", //Unfixed
  "Take 4 tablets by mouth 3 times a day with meal and 2 tablets twice a day with snacks", //Unfixed
  "Take 7.5mg three days per week (M,W,F) and 5mg four days per week OR as directed per Coumadin Clinic.", //Unfixed
  "Take 1 tablet by mouth every 8 hours as needed", //Unfixed
  "Take 1 tablet by mouth twice a day 1 in am and 1 at 3pm", //Unfixed
  "Take 3 tablets by mouth 3 times a day with meal and 2 tablets twice a day with snack", //Unfixed
  "Take 1 tablet by mouth once  every morning then 1/2 tablet at night", //Unfixed
  "Inject 0.4 mL (40 mg total) under the skin daily as directed", //Unfixed
  "Place 1 tablet under the tongue every 5 minutes as needed for chest pain Not to exceed 3 tablets per day", //Unfixed
  "Take 1 capsule by mouth once  at bedtime x7 days then 1 capsule twice a day x7 days then 3 times a day", //Unfixed
  "Take 2 tablets by mouth once  every morning and 3 tablets in the evening", //Unfixed
  "Take 1 tablet by mouth twice a day with meal", //Unfixed?
  "TAKE ONE AND ONE-HALF TABLET BY MOUTH TWICE A DAY", //Unfixed
  "Take 1 TO 2 tablets by mouth twice a day FOR DIABETES", //Unfixed
  "1 tab under tongue at onset of CP may repeat twice in five minutes", //Unfixed
  "Take 1 tablet by mouth once daily with fluids, as early as possible after the onset of a migraine attack, may repeat 2 hours if headahce returns, not to 200 mg in 24 hours", //Unfixed
  "ORAL Take  1 half tablet daily for high blood pressure",
  "ORAL Take bid for diabetes",
  "Take 1 tablet by mouth @8am and 1/2 tablet @3pm",
  "Take 1 tablet under tongue as directed Take every 5 minutes up to 3 doses as needed for chest",
  "Take 2 tablets by mouth in the morning and Take 1 tablet once in the evening",
  "Take 1 tablet by mouth once a day when your feet are swollen. When not swollen, take every other day"
  //"Take 1 tablet (12.5 mg total) by mouth every 12 (twelve) hours",
  //"1 ORAL every eight hours as needed",
  //"Take 5 mg by mouth 2 (two) times daily.",
  //"Take 5 by mouth 2 (two) times daily.",
  //"Use 1 vial via neb every 4 hours"  //Should be 1620mls for a 90 day supply
  //"Take 1 tablet by mouth every morning and 2 tablets in the evening",
  //"Take 1 tablet by mouth every twelve hours",
  //"Take 1/2 tablet by mouth every day",
  //"Take 1-2 tablet by mouth at bedtime",
  //"1/2 tablet Once a day Orally 90 days",
  //"1 capsule every 8 hrs Orally 30 days",
  //"TAKE 1/2 TO 1 TABLET(S) by mouth EVERY DAY",
  //"TAKE 1/2 TO 2 TABLETS AT BEDTIME FOR SLEEP.",
  //"Take 60 mg daily  1 1\\/2 tablet",
  //"ORAL 1 q8-12h prn muscle spasm",
  //"Take 1 tablet (12.5 mg total) by mouth every 12 (twelve) hours",
  //"Take 1 capsule by mouth at bedtime for chronic back pain/ may increase 1 cap/ week x 3 weeks to 3 caps at bedtime", //NOT FIXED
  //"Take 1 tablet by mouth 3 times a day"
  //"Take 1 tablet by mouth 2 (two) times a day with meals."
  //"Take 1 tablet (5 mg total) by mouth 2 (two) times daily.",
  //"Take 1 tablet by mouth every other day",
  //"week 1: 100 mg po bid; week 2: 200 mg po bid; week 3: 300 mg po bid; week 4: 400 mg po bid" //Not Fixed
];


//TODO: NOT WORKING
//"Take 2 tablet by mouth three times a day Take 2 with meals and 1 with snacks", //Not working
//"Take 5 tablets by mouth 3 times a day with meals and 3 tablets 3 times a day with snack", //Not working
//"Take 1 tablet by mouth every morning then 1/2 tablet in the evening", //Not working
//2 am 2 pm ORAL three times a day
//"Take 5 mg by mouth daily."


global $argv;
$sig_index = array_search('sig', $argv);

if ($sig_index === false) {

  foreach ($test_sigs as $i => $test_sig) {
    $parsed = parse_sig(['rx_number' => $i, 'sig_actual' => $test_sig]);
    log_notice("test_parse_sig: $test_sig", [$parsed, $test_sig, $i]);
  }

} else {

  $test_sig = $argv[$sig_index+1];
  $parsed = parse_sig(['rx_number' => 'test', 'sig_actual' => $test_sig]);
  log_notice("test_parse_sig: $test_sig", [$parsed, $test_sig]);

}
