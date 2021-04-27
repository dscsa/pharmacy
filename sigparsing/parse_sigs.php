<?php

require 'aws/aws-autoloader.php';

use Aws\ComprehendMedical\ComprehendMedicalClient;
use Aws\Credentials\Credentials;

const CH_TEST_FILE = "aws-ch-res/responses.json";

class SigParser {

    private $defaultsigs;
    private $client;

    function __construct() {
        $this->defaultsigs = array();
        $credentials = new Credentials($_ENV['AWS_ACCESS_KEY'], $_ENV['AWS_SECRET_KEY']);
        $this->client = new ComprehendMedicalClient([
            'credentials' => $credentials,
            'region' => 'us-west-2',
            'version' => '2018-10-30'
        ]);

        if (file_exists(CH_TEST_FILE)) {
            $res = file_get_contents(CH_TEST_FILE);
            $this->defaultsigs = json_decode(file_get_contents(CH_TEST_FILE), true);
        }
    }

    function parse($text) {
        $text = $this->preprocessing($text);
        $sections = $this->get_sections($text);
        return $this->postprocessing($sections['UnmappedAttributes']);
    }

    private function get_sections($text) {
        if (array_key_exists($text, $this->defaultsigs)) {
            return $this->defaultsigs[$text];
        }
        printf("Requesting DetectEntitiesV2 for ".$text."\n");
        $result = $this->client->detectEntitiesV2(['Text' => $text]);
        $this->defaultsigs[$text] = $result->toArray();
        file_put_contents(CH_TEST_FILE, json_encode($this->defaultsigs));
        return $this->defaultsigs[$text];
    }

    private function preprocessing($sig) {
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

        //echo "1 $sig";

        //Spanish
        $sig = preg_replace('/\\btomar\\b/i', 'take', $sig);
        $sig = preg_replace('/\\bcada\\b/i', 'each', $sig);
        $sig = preg_replace('/\\bhoras\\b/i', 'hours', $sig);

        //Abreviations
        $sig = preg_replace('/\\bhrs\\b/i', 'hours', $sig);
        $sig = preg_replace('/\\b(prn|at onset|when)\\b/i', 'as needed', $sig);
        $sig = preg_replace('/\\bdays per week\\b/i', 'times per week', $sig);

        //echo "2 $sig";
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

        //echo "3 $sig";
        //Substitute fractions
        $sig = preg_replace('/\\b(\d+) (& )?(\.5|1\/2|1-half|1 half)\\b/i', '$1.5', $sig); //Take 1 1/2 tablets
        $sig = preg_replace('/(^| )(\.5|1\/2|1-half|1 half|half a)\\b/i', ' 0.5', $sig);

        //Take First (Min?) of Numeric Ranges, except 0.5 to 1 in which case we use 1
        $sig = preg_replace('/\\b0.5 *(or|to|-) *1\\b/i', '1', $sig); //Special case of the below where we want to round up rather than down
        // $sig = preg_replace('/\\b([0-9]*\.[0-9]+|[1-9][0-9]*) *(or|to|-) *([0-9]*\.[0-9]+|[1-9][0-9]*)\\b/i', '$1', $sig); //Take 1 or 2 every 3 or 4 hours. Let's convert that to Take 1 every 3 hours (no global flag).  //Take 1 capsule by mouth twice a day as needed Take one or two twice a day as needed for anxiety

        //echo "4 $sig";
        //Duration
        $sig = preg_replace('/\\bx ?(\d+)\\b/i', 'for $1', $sig); // X7 Days == for 7 days

        $sig = preg_replace('/\\bon the (first|second|third|fourth|fifth|sixth|seventh) day/i', 'for 1 days', $sig);
        $sig = preg_replace('/\\bfor 1 dose/i', 'for 1 days', $sig);
        $sig = preg_replace('/\\bfor 1 months?|months? \d+/i', 'for 30 days', $sig);
        $sig = preg_replace('/\\bfor 2 months?/i', 'for 60 days', $sig);
        $sig = preg_replace('/\\bfor 3 months?/i', 'for 90 days', $sig);
        $sig = preg_replace('/\\bfor 1 weeks?|weeks? \d+/i', 'for 7 days', $sig);
        $sig = preg_replace('/\\bfor 1 weeks?/i', 'for 7 days', $sig);
        $sig = preg_replace('/\\bfor 2 weeks?/i', 'for 14 days', $sig);
        $sig = preg_replace('/\\bfor 3 weeks?/i', 'for 21 days', $sig);
        $sig = preg_replace('/\\bfor 4 weeks?/i', 'for 28 days', $sig);
        $sig = preg_replace('/\\bfor 5 weeks?/i', 'for 35 days', $sig);
        $sig = preg_replace('/\\bfor 6 weeks?/i', 'for 42 days', $sig);
        $sig = preg_replace('/\\bfor 7 weeks?/i', 'for 49 days', $sig);
        $sig = preg_replace('/\\bfor 8 weeks?/i', 'for 56 days', $sig);
        $sig = preg_replace('/\\bfor 9 weeks?/i', 'for 63 days', $sig);
        $sig = preg_replace('/\\bfor 10 weeks?/i', 'for 70 days', $sig);
        $sig = preg_replace('/\\bfor 11 weeks?/i', 'for 77 days', $sig);
        $sig = preg_replace('/\\bfor 12 weeks?/i', 'for 84 days', $sig);

        //Get rid of superflous "durations" e.g 'Take 1 tablet by mouth 2 times a day. Do not crush or chew.' -> 'Take 1 tablet by mouth 2 times a day do not crush or chew.'
        //TODO probably need to add a lot more of these.  Eg ". For 90 days."
        $sig = preg_replace('/\\b[.;\/] *(?=do not)/i', ' ', $sig);

        //Frequency Denominator
        $sig = preg_replace('/\\bq\\b/i', 'every', $sig); //take 1 tablet by oral route q 12 hrs
        $sig = preg_replace('/ *24 hours?/i', ' 1 day', $sig);
        $sig = preg_replace('/ *48 hours?/i', ' 2 days', $sig);
        $sig = preg_replace('/ *72 hours?/i', ' 3 days', $sig);
        $sig = preg_replace('/ *96 hours?/i', ' 4 days', $sig);

        //echo "5 $sig";
        //Alternative frequency numerator wordings
        $sig = preg_replace('/(?<!all )(other|otra)\\b/i', '2', $sig); //Exclude: Take 4mg 1 time per week, Wed; 2mg all other days or as directed.
        $sig = preg_replace('/\\bonce\\b/i', '1 time', $sig);
        $sig = preg_replace('/\\btwice\\b/i', '2 times', $sig);
        $sig = preg_replace('/\\bhourly\\b/i', 'per hour', $sig);
        $sig = preg_replace('/\\bdaily\\b/i', 'per day', $sig);
        $sig = preg_replace('/\\bweekly\\b/i', 'per week', $sig);
        $sig = preg_replace('/\\bmonthly\\b/i', 'per month', $sig);

        $sig = preg_replace('/\\b(\d) days? a week\\b/i', '$1 times per week', $sig);
        $sig = preg_replace('/\\bon (tuesdays?|tu?e?s?)[, &]*(thursdays?|th?u?r?s?)\\b/i', '2 times per week', $sig);
        $sig = preg_replace('/\\bon (mondays?|mo?n?)[, &]*(wednesdays?|we?d?)[, &]*(fridays?|fr?i?)\\b/i', '3 times per week', $sig);
        $sig = preg_replace('/\\bon (sundays?|sun|mondays?|mon|tuesdays?|tues?|wednesdays?|wed|thursdays?|thur?s?|fridays?|fri|saturdays?|sat)\\b/i', '1 time per week', $sig);

        //echo "6 $sig";

        $sig = preg_replace('/\\bmonthly\\b/i', 'per month', $sig);
        $sig = preg_replace('/\\bmonthly\\b/i', 'per month', $sig);
        $sig = preg_replace('/\\bmonthly\\b/i', 'per month', $sig);

        $sig = preg_replace('/\\b(breakfast|mornings?)[, &]*(dinner|night|evenings?)\\b/i', '2 times per day', $sig);
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

        //Alternate units of measure
        $sig = preg_replace('/\\b4,?000ml\\b/i', '1', $sig); //We count Polyethylene Gylcol (PEG) as 1 unit not 4000ml.  TODO Maybe replace this rule with a more generalized rule?
        $sig = preg_replace('/\\b1 vial\\b/i', '3ml', $sig); // vials for inhalation are 2.5 or 3ml, so use 3ml to be conservative
        $sig = preg_replace('/\\b2 vials?\\b/i', '6ml', $sig); // vials for inhalation are 2.5 or 3ml, so use 3ml to be conservative
        $sig = preg_replace('/\\b3 vials?\\b/i', '9ml', $sig); // vials for inhalation are 2.5 or 3ml, so use 3ml to be conservative
        $sig = preg_replace('/\\b4 vials?\\b/i', '12ml', $sig); // vials for inhalation are 2.5 or 3ml, so use 3ml to be conservative
        $sig = preg_replace('/\\b5 vials?\\b/i', '15ml', $sig); // vials for inhalation are 2.5 or 3ml, so use 3ml to be conservative
        $sig = preg_replace('/\\b6 vials?\\b/i', '18ml', $sig); // vials for inhalation are 2.5 or 3ml, so use 3ml to be conservative

        //echo "9 $sig";

        //Alternative Wordings
        $sig = preg_replace('/\\bin (an|\d) hours?/i', '', $sig); //Don't catch "in an hour" from "Take 2 tablets by mouth as needed of gout & 1 more in an hour as needed"
        $sig = preg_replace('/\\bin \d+ minutes?/i', '', $sig);   //Don't use "in 10 minutes" for the frequency
        $sig = preg_replace('/\\b(an|\d) hours? later/i', '', $sig); //Don't catch "in an hour" from "Take 2 tablets by mouth as needed of gout & 1 more in an hour as needed"
        $sig = preg_replace('/\\b\d+ minutes? later/i', '', $sig);   //Don't use "in 10 minutes" for the frequency

        $sig = preg_replace('/\\bInject \d+ units?\\b/i', 'Inject 1', $sig); //INJECT 18 UNITS
        $sig = preg_replace('/\\bInject [.\d]+ *ml?\\b/i', 'Inject 1', $sig); //Inject 0.4 mL (40 mg total) under the skin daily for 18 days.
        $sig = preg_replace('/\\b\d+ units?(.*?subcutan)|\\b(subcutan.*?)\d+ units?\\b/i', 'Inject 1 $1$2', $sig); // "15 units at bedtime 1 time per day Subcutaneous 90 days":

        //Cleanup
        $sig = preg_replace('/  +/i', ' ', $sig); //Remove double spaces for aesthetics

        return trim($sig);
    }

    private function filter_attributes($sections, $type) {
        $attrs = [];
        foreach ($sections as $attr) {
            if ($attr["Attribute"]["Type"] == $type AND $attr["Attribute"]["Score"] > 0.7) {
                $attrs[] = $attr["Attribute"]["Text"];
            }
        }

        return $attrs;
    }

    private function postprocessing($sections) {
        $dosages = $this->filter_attributes($sections, "DOSAGE");
        $durations = $this->filter_attributes($sections, "DURATION");
        $frequencies = $this->filter_attributes($sections, "FREQUENCY");

        $freq = $this->parse_frequencies($frequencies);
        $dose = $this->parse_dosages($dosages);
        $dur = $this->parse_durations($durations);
        return $dose * $freq * $dur;
    }

    private function parse_frequencies($frequencies) {
        if (count($frequencies) == 0) {
            return 1;
        }

        $total_freq = 0;
        foreach ($frequencies as $freq) {
            // NOTE: Gets the LAST number from a particular frequency.
            // "1 to 2 days" => 2.
            preg_match('/(\d+)(?!.*\d)(.*)day?/i', $freq, $match);
            if ($match AND $match[1]) {
                $total_freq += (int)$match[1];
                continue;
            }

            preg_match('/per (|(\d+ ))day?/i', $freq, $match);
            if ($match) {
                if ($match[1]) {
                    $total_freq += (int)$match[1];
                } else {
                    $total_freq += 1;
                }
                continue;
            }

            preg_match('/(\d+)(?!.*\d)(.*)hour/i', $freq, $match);
            if ($match AND $match[1]) {
                $total_freq += 24 / (int)$match[1];
            }
        }
        if ($total_freq == 0) {
            return 1;
        }
        return $total_freq;
    }

    private function parse_dosages($dosages) {
        if (count($dosages) == 0) {
            return 1;
        }

        $total_dose = 0;
        foreach ($dosages as $dose) {
            // NOTE: Gets the LAST number from a particular dosage.
            // "1 to 2 capsules" => 2.
            preg_match('/(\d+)(?!.*\d)/i', $dose, $match);
            if ($match AND $match[1]) {
                $total_dose += (int)$match[1];

                // If sig has multiple doses, give presedence to the first one.
                // ["1 capsule", "40mg", "60mg"] will only count the 1 capsule.
                break;
            }
        }
        if ($total_dose == 0) {
            return 1;
        }
        return $total_dose;
    }

    private function parse_durations($durations) {
        if (count($durations) == 0) {
            return 1;
        }

        $total_duration = 1;
        foreach ($durations as $dur) {
            preg_match('/(\d+)(.*)day/i', $dur, $match);
            if ($match AND $match[1]) {
                $total_duration *= (int)$match[1];
            }
        }
        if ($total_duration == 0) {
            return 1;
        }
        return $total_duration / 30;
    }
}

$parser = new SigParser();

$correct_pairs = [

    // TODO: Ambiguous? qty_per_day should be daily dose over the course of a month?
    // "Take 1 capsule(s) 3 times a day by oral route with meals for 7 days." => 3,
    // "1 capsule by mouth every day for 7 days then continue on with 60mg capsuled" => 1,
    // "take 10 tablets (40MG total)  by ORAL route   every day for  4 days only" => 1.3300000,

    // TODO: "Multiple sigs" in one (then, commas, punctuation, etc)
    // "1 tablet 2 hours before bedtime, may increase by 1 tablet per week to 4 tablets per night" => 4,

    // TODO: Check for repeated wording in "multiple sigs"
    // "1 tablet by mouth daily; TAKE ONE TABLET BY MOUTH ONCE DAILY" => 1

    "Take 1 tablet (12.5 mg) by mouth per day in the morning" => 1,
    "1 capsule by mouth 30 minutes after the same meal each day" => 1,
    "1 capsule by mouth every day (Start after finishing 30mg capsules first)" => 1,
    "1 capsule by mouth twice a day" => 2,
    "1 capsule once daily 30 minutes after the same meal each day" => 1,
    "1 tablet (5 mg total) by PEG Tube route 2 (two) times a day" => 2,
    "1 tablet by mouth  every morning" => 1,
    "1 tablet by mouth at bedtime" => 1,
    "1 tablet by mouth at bedtime as directed" => 1,
    "1 tablet by mouth at bedtime as needed" => 1,
    "1 tablet by mouth at bedtime mood" => 1,
    "1 tablet by mouth daily" => 1,
    "1 tablet by mouth day" => 1,
    "1 tablet by mouth every 8 hours" => 3,
    "Take 5 tablets by mouth once  at bedtime" => 5,
    
    // Fails in current parser
    "1 tablet by mouth every 8 hours as needed for Blood Pressure greater than 140/90" => 3,
    "Take  1 TO 2 capsules by mouth 3 times a day as needed FOR NERVE PAINS" => 6,
    "Take 1 capsule once a day take along with 40mg for total of 60mg" => 1,
    "1 capsule as needed every 6 hrs Orally 30 day(s)" => 4,
    "1 tablet every 6 to 8 hours as needed Orally 30 day(s)" => 3,  // Or 4?
    "Take 1 tablet by mouth every night as needed sleep" => 1,
    "Take 1 tablet (800 mg) by oral route 3 times per day with food as needed for pain" => 3,
    "Take 1 tablet by mouth 3 times a day as needed" => 3,

    // TODO: The first one has qty_per_day in ml, the second one is in units
    "Use 1 vial via nebulizer every 6 hours" => 12,
    "Take 20 mEq by mouth 2 (two) times a day." => 2,

];

foreach($correct_pairs as $text => $qty) {
    $result = $parser->parse($text);

    // TODO: Accept error at the moment is 0.01
    assert(abs($parser->parse($text) - $qty) < 0.01, "Expected ".$qty.", got ".$result." for ".$text."\n");
}

?>