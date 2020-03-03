<?php

require_once 'helpers/helper_imports.php';

function parse_sig($rx) {


  //Inhalers might come with qty 18 (# of inhales/puffs rather than 1 so ignore these).  Not sure if these hardcoded assumptions are correct?  Cindy could need to dispense two inhalers per month?  Or one inhaler lasts more than a month?
  //Could be written in milliliters since prescriber cannot prescribe over 12 months of inhalers at a time
  //Convert to Unit of Use by just assuming each inhaler is 30 days
  //Same for Nasal "Sprays"
  if (isset($rx['drug_name']) AND isset($rx['qty_original']) AND (strpos($rx['drug_name'], ' INH') !== false OR $rx['qty_original'] < 5)) {
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
  $sigs_clean        = array_reverse(preg_split($complex_sig_regex, subsitute_numerals($rx['sig_actual'])));

  foreach ($sigs_clean as $sig_clean) {

    $qty_per_time = get_qty_per_time($sig_clean);
    $frequency = get_frequency($sig_clean);
    $frequency_numerator = get_frequency_numerator($sig_clean);
    $frequency_denominator = get_frequency_denominator($sig_clean);

    $parsed = [
      'sig_qty_per_day'           => "NULL",
      'sig_clean'                 => clean_val($sig_clean), //this may have a single quote in it that needs escaping
      'sig_qty_per_time'          => $qty_per_time,
      'sig_frequency'             => $frequency,
      'sig_frequency_numerator'   => $frequency_numerator,
      'sig_frequency_denominator' => $frequency_denominator
    ];

    if ($qty_per_time AND $frequency AND $frequency_numerator AND $frequency_denominator) {
      $parsed['sig_qty_per_day'] = $qty_per_time * $frequency_numerator / $frequency_denominator / $frequency;

      if ($parsed['sig_qty_per_day'] > 6)
        log_error("Parse sig sig_qty_per_day is >6: $rx[sig_actual] >>> $sig_clean", $parsed);
    
      return $parsed;
    }

    log_error("Could not parse sig $rx[sig_actual] >>> $sig_clean", $parsed);
  }
}

function subsitute_numerals($sig) {

  //Spanish
  $sig = preg_replace('/\\btomar /i', 'take ', $sig);
  $sig = preg_replace('/\\bcada /i', 'each ', $sig);
  $sig = preg_replace('/\\bhoras /i', 'hours ', $sig);

  $sig = preg_replace('/\(.*?\)/', '', $sig); //get rid of parenthesis // "Take 1 capsule (300 mg total) by mouth 3 (three) times daily."
  $sig = preg_replace('/\\\/', '', $sig);   //get rid of backslashes

  $sig = preg_replace('/(^| *and *| *& *)(1\/2|one-half|one half|1 half) /i', '.5 ', $sig); //Take 1 and 1/2 tablets or Take 1 & 1/2 tablets.  Could combine with next regex but might get complicated
  $sig = preg_replace('/(\d+) (1\/2|one-half) /i', '$1.5 ', $sig); //Take 1 1/2 tablets
  $sig = preg_replace('/ (1\/2|one-half|one half|1 half) /i', ' .5 ', $sig);
  $sig = preg_replace('/\\bone |\\buno /i', '1 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\btwo |\\bdos |\\bother |\\botra /i', '2 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\bthree |\\tres /i', '3 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\bfour |\\bquatro /i', '4 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\bfive |\\bcinco /i', '5 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\bsix |\\bseis /i', '6 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\bseven |\\bsiete /i', '7 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\beight |\\bocho /i', '8 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\bnine |\\bnueve /i', '9 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\bten |\\bdiez /i', '10 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\beleven |\\bonce /i', '11 ', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\btwelve |\\bdoce /i', '12 ', $sig); // \\b is for space or start of line

  $sig = preg_replace('/ hrs\\b/i', ' hours', $sig);
  $sig = preg_replace('/ once /i', ' 1 time ', $sig);
  $sig = preg_replace('/ twice\\b| q12.*?h\\b| BID\\b|(?!every) 12 hours\\b/i', ' 2 times', $sig);
  $sig = preg_replace('/ q8.*?h\\b| TID\\b|(?!every) 8 hours\\b/i', ' 3 times ', $sig);
  $sig = preg_replace('/ q6.*?h\\b|(?!every) 6 hours\\b/i', ' 4 times', $sig);

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

  //Remove double spaces for aesthetics
  $sig = preg_replace('/  +/i', ' ', $sig);

  return trim($sig);
}

function get_qty_per_time($sig) {

    preg_match('/([0-9]?\.[0-9]+|[1-9]) (tab|cap|pill|softgel)/i', $sig, $match);

    if ($match) return $match[1];

    preg_match('/(^|use +|take +|inhale +|chew +|inject +|oral +)([0-9]?\.[0-9]+|[1-9])(?!\d* ?mg)(?! +time)/i', $sig, $match);

    return $match ? $match[2] : 1; //"Use daily with lantus" won't match the RegEx above
}

function get_frequency_numerator($sig) {
  preg_match('/([1-9]\\b|10|11|12) +time/i', $sig, $match);
  return $match ? $match[1] : 1;
}

function get_frequency_denominator($sig) {
  preg_match('/every ([1-9]\\b|10|11|12)(?! +time)/i', $sig, $match);
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

  else if (preg_match('/( hours?| hourly)(?! before| after| prior to)/i', $sig)) //put this last so less likely to match thinks like "2 hours before (meals|bedtime) every day"
    $freq = 1/24; // One 24th of a day

  if (preg_match('/ prn| as needed/i', $sig)) //Not mutually exclusive like the others. TODO: Does this belong in freq denominator instead? TODO: Check with Cindy how often does as needed mean on average.  Assume once every 3 days for now
    $freq *= $freq > 1 ? 2 : 1; // I had this as 3 which I think is approximately correct, but Cindy didn't like so setting at 1 which basically means we ignore for now

  //Default to daily Example 1 tablet by mouth at bedtime
  return $freq;
}
