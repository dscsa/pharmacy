<?php

function preprocessing($sig) {
    //Cleanup
    $sig = preprocessing_cleanup($sig);

    //Spanish
    $sig = preg_replace('/\\btomar\\b/i', 'take', $sig);
    $sig = preg_replace('/\\bcada\\b/i', 'each', $sig);
    $sig = preg_replace('/\\bhoras\\b/i', 'hours', $sig);

    //Abreviations
    $sig = preg_replace('/\\bhrs\\b/i', 'hours', $sig);
    $sig = preg_replace('/\\b(prn|at onset|when)\\b/i', 'as needed', $sig);
    $sig = preg_replace('/\\bdays per week\\b/i', 'times per week', $sig);

    $sig = preprocessing_number_substitution($sig);

    $sig = preprocessing_duration_substitution($sig);

    $sig = preprocessing_alternative_units($sig);

    $sig = preprocessing_alternative_wording($sig);

    //Cleanup
    $sig = preg_replace('/  +/i', ' ', $sig); //Remove double spaces for aesthetics

    return trim($sig);
}

function preprocessing_cleanup($sig) {
    //Cleanup
    $sig = preg_replace('/\(.*?\)/', '', $sig); //get rid of parenthesis // "Take 1 capsule (300 mg total) by mouth 3 (three) times daily."
    $sig = preg_replace('/\\\/', '', $sig);   //get rid of backslashes and single quotes (latter for sql savings errors)
    $sig = preg_replace('/\\band\\b/i', '&', $sig);   // & is easier tp search in regex than "and"
    $sig = preg_replace('/\\bDr\./i', '', $sig);   // The period in Dr. will mess up our split for durations
    $sig = preg_replace('/\\bthen call 911/i', '', $sig); //"then call 911" is not a duration
    $sig = preg_replace('/\\bas directed/i', '', $sig); //< Diriection. As Directed was triggering a 2nd duration

    $sig = preg_replace('/ +(mc?g)\\b| +(ml)\\b/i', '$1$2', $sig);   //get rid of extra spaces
    $sig = preg_replace('/[\w ]*replaces[\w ]*/i', '$1', $sig); //Take 2 tablets (250 mcg total) by mouth daily. This medication REPLACES Levothyroxine 112 mcg",

    //Interpretting as 93 qty per day. Not sure if its best to just get rid of it here or fix the issue further down
    $sig = preg_replace('/ 90 days?$/i', '', $sig); //TAKE 1 CAPSULE(S) 3 TIMES A DAY BY ORAL ROUTE AS NEEDED. 90 days

    $sig = preg_replace('/xdaily/i', ' times per day', $sig);

    // Split with commas "then" so that durations/frequencies/dosages are recognized separately by AWS CH 
    $sig = preg_replace('/(?<!,) then /i', ', then ', $sig);

    return $sig;
}

function preprocessing_number_substitution($sig) {
    //Substitute Integers
    $sig = preg_replace('/\\b(one|uno)\\b/i', '1', $sig); // \\b is for space or start of line
    $sig = preg_replace('/\\b(two|dos)\\b/i', '2', $sig); // \\b is for space or start of line
    $sig = preg_replace('/\\b(three|tres)\\b/i', '3', $sig); // \\b is for space or start of line
    $sig = preg_replace('/\\b(four|quatro)\\b/i', '4', $sig); // \\b is for space or start of line
    $sig = preg_replace('/\\b(five|cinco)\\b/i', '5', $sig); // \\b is for space or start of line
    $sig = preg_replace('/\\b(six|seis)\\b/i', '6', $sig); // \\b is for space or start of line
    $sig = preg_replace('/\\b(seven|siete)\\b/i', '7', $sig); // \\b is for space or start of line
    $sig = preg_replace('/\\b(eight|ocho)\\b/i', '8', $sig); // \\b is for space or start of line
    $sig = preg_replace('/\\b(nine|nueve)\\b/i', '9', $sig); // \\b is for space or start of line
    $sig = preg_replace('/\\b(ten|diez)\\b/i', '10', $sig); // \\b is for space or start of line
    $sig = preg_replace('/\\beleven\\b/i', '11', $sig); // once for 11 is both spanish and english
    $sig = preg_replace('/\\b(twelve|doce)\\b/i', '12', $sig); // \\b is for space or start of line
    $sig = preg_replace('/\\b(twenty|veinte)\\b/i', '20', $sig); // \\b is for space or start of line
    $sig = preg_replace('/\\b(thirty|treinta)\\b/i', '30', $sig); // \\b is for space or start of line
    $sig = preg_replace('/\\b(forty|cuarenta)\\b/i', '40', $sig); // \\b is for space or start of line
    $sig = preg_replace('/\\b(fifty|cincuenta)\\b/i', '50', $sig); // \\b is for space or start of line
    $sig = preg_replace('/\\b(sixty|sesenta)\\b/i', '60', $sig); // \\b is for space or start of line
    $sig = preg_replace('/\\b(seventy|setenta)\\b/i', '70', $sig); // \\b is for space or start of line
    $sig = preg_replace('/\\b(eighty|ochenta)\\b/i', '80', $sig); // \\b is for space or start of line
    $sig = preg_replace('/\\b(ninety|noventa)\\b/i', '90', $sig); // \\b is for space or start of line

    $sig = preg_replace('/\\b(\d+)?( ?& ?)?(\.5|1\/2|1-half|1 half)\\b/i', '$1.5', $sig); //Take 1 1/2 tablets
    $sig = preg_replace('/(\d+) .5\\b/i', '$1.5', $sig);
    $sig = preg_replace('/(^| )(\.5|1\/2|1-half|1 half|half a)\\b/i', ' 0.5', $sig);


    //Take First (Min?) of Numeric Ranges, except 0.5 to 1 in which case we use 1
    $sig = preg_replace('/\\b0.5 *(or|to|-) *1\\b/i', '1', $sig); //Special case of the below where we want to round up rather than down
    // $sig = preg_replace('/\\b([0-9]*\.[0-9]+|[1-9][0-9]*) *(or|to|-) *([0-9]*\.[0-9]+|[1-9][0-9]*)\\b/i', '$1', $sig); //Take 1 or 2 every 3 or 4 hours. Let's convert that to Take 1 every 3 hours (no global flag).  //Take 1 capsule by mouth twice a day as needed Take one or two twice a day as needed for anxiety

    return $sig;
}

function _replace_time_interval($sig, $word, $time_in_days) {
    for ($x = 1; $x <= 15; $x++) {
        $regex = '/\\bfor '.$x.' '.$word.'?|'.$word.'? \d+/i';
        $replace = 'for '.$time_in_days*$x.' days';
        $sig = preg_replace($regex, $replace, $sig);
    }
    return $sig;
}

function preprocessing_duration_substitution($sig) {

    $sig = preg_replace('/\\bx ?(\d+)\\b/i', 'for $1', $sig); // X7 Days == for 7 days

    $sig = preg_replace('/\\bon the (first|second|third|fourth|fifth|sixth|seventh) day/i', 'for 1 days', $sig);

    // Normalizes the intervals to days. "for 3 months" => "for 90 days"
    $sig = _replace_time_interval($sig, "doses", 1);
    $sig = _replace_time_interval($sig, "months", 30);
    $sig = _replace_time_interval($sig, "weeks", 7);

    //Get rid of superflous "durations" e.g 'Take 1 tablet by mouth 2 times a day. Do not crush or chew.' -> 'Take 1 tablet by mouth 2 times a day do not crush or chew.'
    //TODO probably need to add a lot more of these.  Eg ". For 90 days."
    $sig = preg_replace('/\\b[.;\/] *(?=do not)/i', ' ', $sig);

    //Frequency Denominator
    $sig = preg_replace('/\\bq\\b/i', 'every', $sig); //take 1 tablet by oral route q 12 hrs

    //echo "5 $sig";
    //Alternative frequency numerator wordings
    $sig = preg_replace('/(?<!all )(other|otra)\\b/i', '2', $sig); //Exclude: Take 4mg 1 time per week, Wed; 2mg all other days or as directed.
    $sig = preg_replace('/\\bonce\\b/i', '1 time', $sig);
    $sig = preg_replace('/\\btwice\\b/i', '2 times', $sig);
    $sig = preg_replace('/\\bhourly\\b/i', 'per hour', $sig);
    $sig = preg_replace('/\\bdaily\\b/i', 'per day', $sig);
    $sig = preg_replace('/\\bweekly\\b/i', 'per week', $sig);
    $sig = preg_replace('/\\bmonthly\\b/i', 'per month', $sig);
    // $sig = preg_replace('/\\bevery other\\b/i', 'each 2', $sig);

    $sig = preg_replace('/\\b(\d) days? a week\\b/i', '$1 times per week', $sig);
    $sig = preg_replace('/\\b(tuesdays?|tu?e?s?|sundays?|saturdays?|sat)[, &]*(thursdays?|th?u?r?s?|sundays?|saturdays?)\\b/i', '2 times per week', $sig);
    $sig = preg_replace('/\\b(mondays?|mo?n?)[, &]*(wednesdays?|we?d?)[, &]*(fridays?|fr?i?)\\b/i', '3 times per week', $sig);
    $sig = preg_replace('/\\b(sundays?|sun|mondays?|mon|tuesdays?|tues?|wednesdays?|wed|thursdays?|thur?s?|fridays?|fri|saturdays?|sat)\\b/i', '1 time per week', $sig);

    //echo "6 $sig";

    $sig = preg_replace('/\\bmonthly\\b/i', 'per month', $sig);

    $sig = preg_replace('/\\b(breakfast|mornings?)[, & @]*(dinner|night|evenings?|noon)\\b/i', '2 times per day', $sig);
    $sig = preg_replace('/\\b(1 (in|at) )?\d* ?(am|pm)[, &]*(1 (in|at) )?\d* ?(am|pm)\\b/i', '2 times per day', $sig); // Take 1 tablet by mouth twice a day 1 in am and 1 at 3pm was causing issues
    $sig = preg_replace('/\\b(in|at) \d\d\d\d?[, &]*(in|at)?\d\d\d\d?\\b/i', '2 times per day', $sig); //'Take 2 tablets by mouth twice a day at 0800 and 1700'
    $sig = preg_replace('/\\b(with)?in (a )?\d+ (minutes?|days?|weeks?|months?)\\b|/i', '', $sig); // Remove "in 5 days|hours|weeks" so that we don't confuse frequencies
    $sig = preg_replace('/\\bevery +5 +min\w*/i', '3 times per day', $sig); //Nitroglycerin

    //echo "7 $sig";
    //Latin and Appreviations
    $sig = preg_replace('/\\bSUB-Q\\b/i', 'subcutaneous', $sig);
    $sig = preg_replace('/\\bBID\\b/i', '2 times per day', $sig);
    $sig = preg_replace('/\\bTID\\b/i', '3 times per day', $sig);
    $sig = preg_replace('/\\b(QAM|QPM)\\b/i', '1 time per day', $sig);
    $sig = preg_replace('/\\b(q12.*?h)\\b/i', 'every 12 hours', $sig);
    $sig = preg_replace('/\\b(q8.*?h)\\b/i', 'every 8 hours', $sig);
    $sig = preg_replace('/\\b(q6.*?h)\\b/i', 'every 6 hours', $sig);
    $sig = preg_replace('/\\b(q4.*?h)\\b/i', 'every 4 hours', $sig);
    $sig = preg_replace('/\\b(q3.*?h)\\b/i', 'every 3 hours', $sig);
    $sig = preg_replace('/\\b(q2.*?h)\\b/i', 'every 2 hours', $sig);
    $sig = preg_replace('/\\b(q1.*?h|every hour)\\b/i', 'every 1 hours', $sig);

    $sig = preg_replace('/on day \d+/i', 'for 1 day', $sig);

    //Remove wording like "X minutes before" so that its not picked up as a frequency
    $sig = preg_replace('/\d+ (minutes?|hours?) (before|prior|about|after|period)/i', '', $sig);
    return $sig;
}

function preprocessing_alternative_units($sig) {
    $sig = preg_replace('/\\b4,?000ml\\b/i', '1', $sig); //We count Polyethylene Gylcol (PEG) as 1 unit not 4000ml.  TODO Maybe replace this rule with a more generalized rule?
    $sig = preg_replace('/\\b1 vial\\b/i', '3ml', $sig);
    $sig = preg_replace('/\\b2 vials?\\b/i', '6ml', $sig);
    $sig = preg_replace('/\\b3 vials?\\b/i', '9ml', $sig);
    $sig = preg_replace('/\\b4 vials?\\b/i', '12ml', $sig);
    $sig = preg_replace('/\\b5 vials?\\b/i', '15ml', $sig);
    $sig = preg_replace('/\\b6 vials?\\b/i', '18ml', $sig);
    
    return $sig;
}

function _delete_all_after($sig, $regexp) {
    preg_match($regexp, $sig, $match, PREG_OFFSET_CAPTURE);
    if ($match) {
        $sig = substr($sig, 0, $match[0][1]);
    }
    return $sig;
}

function preprocessing_alternative_wording($sig) {
    $sig = preg_replace('/\\bin (an|\d) hours?/i', '', $sig); //Don't catch "in an hour" from "Take 2 tablets by mouth as needed of gout & 1 more in an hour as needed"
    $sig = preg_replace('/\\bin \d+ minutes?/i', '', $sig);   //Don't use "in 10 minutes" for the frequency
    $sig = preg_replace('/\\b(an|\d) hours? later/i', '', $sig); //Don't catch "in an hour" from "Take 2 tablets by mouth as needed of gout & 1 more in an hour as needed"
    $sig = preg_replace('/\\b\d+ minutes? later/i', '', $sig);   //Don't use "in 10 minutes" for the frequency

    $sig = preg_replace('/\\bInject \d+ units?\\b/i', 'Inject 1', $sig); //INJECT 18 UNITS
    $sig = preg_replace('/\\bInject [.\d]+ *ml?\\b/i', 'Inject 1', $sig); //Inject 0.4 mL (40 mg total) under the skin daily for 18 days.
    $sig = preg_replace('/\\b\d+ units?(.*?subcutan)|\\b(subcutan.*?)\d+ units?\\b/i', 'Inject 1 $1$2', $sig); // "15 units at bedtime 1 time per day Subcutaneous 90 days":

    // Delete everything after the first ocurrance of "total of"
    $sig = _delete_all_after($sig, '/total of/i');

    // Delete everything after the first ocurrance of "max"
    $sig = _delete_all_after($sig, '/ max/i');

    // Delete everything after the first ocurrance of "may repeat"
    $sig = _delete_all_after($sig, '/may repeat /i');

    // Delete everything after the first ocurrance of "hold" (it's usually about another sig/prescription)
    $sig = _delete_all_after($sig, '/hold /i');

    // Delete everything after the first ocurrance of "with" and a number
    $sig = _delete_all_after($sig, '/ with.?(\d)/i');

    // Delete everything after the first ocurrance of "up to" (to ignore both total allowed per day and increases in dosages)
    $sig = _delete_all_after($sig, '/ up.?to/i');

    $sig = preg_replace('/\\bPlace \\b/i', 'Take ', $sig);

    return $sig;
}

?>