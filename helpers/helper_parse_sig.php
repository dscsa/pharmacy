<?php

require_once 'helpers/helper_imports.php';

function parse_sig($rx) {


  //Inhalers might come with qty 18 (# of inhales/puffs rather than 1 so ignore these).  Not sure if these hardcoded assumptions are correct?  Cindy could need to dispense two inhalers per month?  Or one inhaler lasts more than a month?
  //Could be written in milliliters since prescriber cannot prescribe over 12 months of inhalers at a time
  //Convert to Unit of Use by just assuming each inhaler is 30 days
  //Same for Nasal "Sprays"
  if (strpos($rx['drug_name'], ' INH') !== false OR $rx['qty_original'] < 5) {
    return [
      'sig_qty_per_day'           => 1/30,
      'sig_clean'                 => "'AK assuming 1 unit per month'",
      'sig_qty_per_time'          => "NULL",
      'sig_frequency'             => "NULL",
      'sig_frequency_numerator'   => "NULL",
      'sig_frequency_denominator' => "NULL"
    ];
  }

  //TODO capture BOTH parts of "then" but for now just use second half
  //"1 capsule by mouth at bedtime for 1 week then 2 capsules at bedtime" --split
  //"Take 2 tablets in the morning and 1 at noon and 1 at supper" --split
  //"take 1 tablet (500 mg) by oral route 2 times per day with morning and evening meals" -- don't split
  //"Take 1 tablet by mouth every morning and 2 tablets in the evening" -- split
  $complex_sig_regex = '/ then | and (?=\d)/';
  $sigs_clean        = array_reverse(preg_split($complex_sig_regex, subsitute_numerals($rx['sig_initial'])));

  foreach ($sigs_clean as $sig_clean) {

    $qty_per_time = get_qty_per_time($sig_clean);
    $frequency = get_frequency($sig_clean);
    $frequency_numerator = get_frequency_numerator($sig_clean);
    $frequency_denominator = get_frequency_denominator($sig_clean);

    $parsed = [
      'sig_clean'                 => clean_val($sig_clean), //this may have a single quote in it that needs escaping
      'sig_qty_per_time'          => $qty_per_time,
      'sig_frequency'             => $frequency,
      'sig_frequency_numerator'   => $frequency_numerator,
      'sig_frequency_denominator' => $frequency_denominator
    ];

    if ($qty_per_time AND $frequency AND $frequency_numerator AND $frequency_denominator) {
      $parsed['sig_qty_per_day'] = $qty_per_time * $frequency_numerator / $frequency_denominator / $frequency;
      //log('Parsed $sig '.$rx['sig_initial'].' | '.$sig_clean.' | '.print_r($parsed, true));
      return $parsed;
    }

    log_error("Could not parse sig $rx[sig_initial] >>> $sig_clean", $parsed);
  }
}

function subsitute_numerals($sig) {
  $sig = preg_replace('/\(.*?\)/', '', $sig); //get rid of parenthesis // "Take 1 capsule (300 mg total) by mouth 3 (three) times daily."
  $sig = preg_replace('/\\\/', '', $sig);   //get rid of backslashes

  $sig = preg_replace('/(^| ?and ?| ?& ?)(1\/2|one-half) /i', '.5 ', $sig); //Take 1 and 1/2 tablets or Take 1 & 1/2 tablets.  Could combine with next regex but might get complicated
  $sig = preg_replace('/(\d+) (1\/2|one-half) /i', '$1.5 ', $sig); //Take 1 1/2 tablets
  $sig = preg_replace('/ (1\/2|one-half) /i', ' .5 ', $sig);
  $sig = preg_replace('/\\bone /i', '1 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\btwo |\\bother /i', '2 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\bthree /i', '3 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\bfour /i', '4 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\bfive /i', '5 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\bsix /i', '6 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\bseven /i', '7 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\beight /i', '8 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\bnine /i', '9 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\bten /i', '10 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\beleven /i', '11 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\btwelve /i', '12 ', $sig); // \\b is for space or start of line

  $sig = preg_replace('/ hrs\\b/i', ' hours', $sig);
  $sig = preg_replace('/ once /i', ' 1 time ', $sig);
  $sig = preg_replace('/ twice\\b| q12.*?h\\b| BID\\b|(?<!every) 12 hours\\b/i', ' 2 times', $sig);
  $sig = preg_replace('/ q8.*?h\\b| TID\\b|(?<!every) 8 hours\\b/i', ' 3 times ', $sig);
  $sig = preg_replace('/ q6.*?h\\b|(?<!every) 6 hours\\b/i', ' 4 times', $sig);

  $sig = preg_replace('/\\b1 vial /i', '3ml ', $sig); // vials for inhalation are 2.5 or 3ml, so use 3ml to be conservative
  $sig = preg_replace('/\\b2 vials? /i', '6ml ', $sig); // vials for inhalation are 2.5 or 3ml, so use 3ml to be conservative
  $sig = preg_replace('/\\b3 vials? /i', '9ml ', $sig); // vials for inhalation are 2.5 or 3ml, so use 3ml to be conservative
  $sig = preg_replace('/\\b4 vials? /i', '12ml ', $sig); // vials for inhalation are 2.5 or 3ml, so use 3ml to be conservative
  $sig = preg_replace('/\\b5 vials? /i', '15ml ', $sig); // vials for inhalation are 2.5 or 3ml, so use 3ml to be conservative
  $sig = preg_replace('/\\b6 vials? /i', '18ml ', $sig); // vials for inhalation are 2.5 or 3ml, so use 3ml to be conservative

  //Take Last (Max?) of Numeric Ranges
  $sig = preg_replace('/[.\d]+ or ([.\d]+) /i', '$1 ', $sig, 1); //Take 1 or 2 every 3 or 4 hours. Let's convert that to Take 2 every 3 or 4 hours (no global flag).  CK approves of first substitution but not sure of the 2nd so the conservative answer is to leave it alone
  $sig = preg_replace('/[.\d]+ to ([.\d]+) /i', '$1 ', $sig, 1); //Take 1 to 2 every 3 or 4 hours. Let's convert that to Take 2 every 3 or 4 hours (no global flag).  CK approves of first substitution but not sure of the 2nd so the conservative answer is to leave it alone
  $sig = preg_replace('/[.\d]+-([.\d]+) /i', '$1 ', $sig, 1); //Take 1-2 every 3 or 4 hours. Let's convert that to Take 2 every 3 or 4 hours (no global flag).  CK approves of first substitution but not sure of the 2nd so the conservative answer is to leave it alone

  $sig = preg_replace('/ breakfast /i', ' morning ', $sig);
  $sig = preg_replace('/ dinner /i', ' evening ', $sig);
  $sig = preg_replace('/ mornings? and evenings? /i', ' 2 times ', $sig);

  return trim($sig);
}

function get_qty_per_time($sig) {

    preg_match('/([0-9]?\.[0-9]+|[1-9]) (tab|cap|pill|softgel)/i', $sig, $match);

    if ($match) return $match[1];

    preg_match('/(^|use +|take +|inhale +|chew +|inject +|oral +)([0-9]?\.[0-9]+|[1-9])(?<!\d* ?mg)/i', $sig, $match);

    return $match ? $match[2] : 1; //"Use daily with lantus" won't match the RegEx above
}

function get_frequency_numerator($sig) {
  preg_match('/([1-9]\\b|10|11|12) +time/i', $sig, $match);
  return $match ? $match[1] : 1;
}

function get_frequency_denominator($sig) {
  preg_match('/every ([1-9]\\b|10|11|12)(?<! +time)/i', $sig, $match);
  return $match ? $match[1] : 1;
}

//Returns frequency in number of days (e.g, weekly means 7 days)
function get_frequency($sig) {

  $freq = 1; //defaults to daily if no matches

  if (preg_match('/ day| daily/i', $sig))
    $freq = 1;

  else if (preg_match('/ week| weekly/i', $sig))
    $freq = 30/4; //rather than 7 days, calculate as 1/4th a month so we get 45/90 days rather than 42/84 days

  else if (preg_match('/ month| monthly/i', $sig))
    $freq = 30;

  else if (preg_match('/( hours?| hourly)(?<! before| after| prior to)/i', $sig)) //put this last so less likely to match thinks like "2 hours before (meals|bedtime) every day"
    $freq = 1/24; // One 24th of a day

  if (preg_match('/ prn| as needed/i', $sig)) //Not mutually exclusive like the others. TODO: Does this belong in freq denominator instead? TODO: Check with Cindy how often does as needed mean on average.  Assume once every 3 days for now
    $freq *= 3; // I had this as 3 which I think is approximately correct, but Cindy didn't like so setting at 1 which basically means we ignore for now

  //Default to daily Example 1 tablet by mouth at bedtime
  return $freq;
}


function test_parse_sig() {
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

  foreach ($test_sigs as $i => $test_sig) {
    $parsed = parse_sig(['rx_number' => $i, 'sig_initial' => $test_sig]);
    log_info("test_parse_sig: $test_sig", get_defined_vars());
  }
}
