<?php

require 'aws/aws-autoloader.php';
require 'helper_constants.php';
require 'parse_preprocessing.php';

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
        $text = preprocessing($text);
        $sections = $this->get_sections($text);
        $attributes = [];
        foreach ($sections['Entities'] as $entity) {
            if (array_key_exists('Attributes', $entity)) {
                foreach ($entity['Attributes'] as $attr) {
                    $attributes[] = $attr;
                }
            }
        }
        foreach ($sections['UnmappedAttributes'] as $attr) {
            $attributes[] = $attr['Attribute'];
        }
        return $this->postprocessing($attributes, $text);
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

    private function filter_attributes($sections, $type, $score_tol) {
        $attrs = [];
        foreach ($sections as $attr) {
            if ($attr["Type"] == $type AND $attr["Score"] > $score_tol) {
                $attrs[] = $attr["Text"];
            }
        }

        return $attrs;
    }

    private function cmp_attrs($attr1, $attr2) {
        return $attr1['EndOffset'] - $attr2['EndOffset'];
    }

    private function split_sections($sections, $text) {
        $increase = "(may|can) increase(.*?\/ *(month|week))?";
        $sentence = "(?<=[a-z ])[.;\/] *(?=\w)";  //Sentence ending in . ; or / e.g. "Take 1 tablet by mouth once daily / take 1/2 tablet on sundays"
        $then     = " then[ ,]+";
        $and_at   = " & +at ";
        $and_verb = " &[ ,]+(?=\d|use +|take +|inhale +|chew +|inject +|oral +)";

        $durations_regex = "/($increase|$sentence|$then|$and_at|$and_verb)/i";

        $splits = preg_split($durations_regex, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        usort($sections, Array('SigParser', 'cmp_attrs'));
        $subsections = [];
        $offset_len = 0;
        $splits_idx = 0;
        foreach ($sections as $section) {
            while ($section["EndOffset"] > $offset_len AND $splits_idx < count($splits)) {
                $offset_len += strlen($splits[$splits_idx]);
                $splits_idx += 1;
            }
            if (!array_key_exists($splits_idx - 1, $subsections)) {
                $subsections[$splits_idx - 1] = [];
            }
            $subsections[$splits_idx - 1][] = $section;
        }
        return $subsections;
    }

    private function postprocessing($sections, $text) {
        $splits = $this->split_sections($sections, $text);
        $final_qty = 0;
        $final_days = 0;
        $valid_unit = "";
        $last_sig_qty = 1;

        foreach ($splits as $split) {
            $dosages = $this->filter_attributes($split, "DOSAGE", 0.5);
            $durations = $this->filter_attributes($split, "DURATION", 0.6);
            $frequencies = $this->filter_attributes($split, "FREQUENCY", 0.6);
    
            $freq = $this->parse_frequencies($frequencies);
            $dose = $this->parse_dosages($dosages);
            $parsed_dur = $this->parse_durations($durations);

            $final_days += $parsed_dur;
            $dur = ($parsed_dur ? $parsed_dur : (DAYS_STD - $final_days));

            $valid_unit = (strlen($valid_unit) > 0 ? $valid_unit : $dose['sig_unit']);

            // If a unit of a split doesn't match, use the last_sig_qty.
            // TODO: Could be solved by parsing the drug name.
            $similarity = 0;
            similar_text($valid_unit, $dose['sig_unit'], $similarity);
            if ($similarity > 60) {
                $last_sig_qty = $dose['sig_qty'];
            }
            $final_qty += $last_sig_qty * $freq * $dur;
        }

        // If the last split didn't have a duration, set $final_days to DAYS_STD
        if ($dur != $parsed_dur) {
            $final_days = DAYS_STD;
        }
        return [
            'sig_unit' => $valid_unit,
            'sig_qty' => $final_qty,
            'sig_days' => $final_days
        ];
    }

    private function parse_frequencies($frequencies) {
        if (count($frequencies) == 0) {
            return 1;
        }

        $total_freq = 1;
        foreach ($frequencies as $freq) {
            // NOTE: Gets the LAST number from a particular frequency.
            // "1 to 2 days" => 2.
            preg_match('/(\d+)(?!.*\d)(.*)hour/i', $freq, $match);
            if ($match AND $match[1]) {
                $total_freq *= 24 / (float)$match[1];
                continue;
            }

            preg_match('/(\d+)(?!.*\d)(.*) week/i', $freq, $match);
            if ($match AND $match[1]) {
                $total_freq *= (float)$match[1] / 7;
                continue;
            }

            preg_match('/(\d+)(?!.*\d)(.*)/i', $freq, $match);
            if ($match AND $match[1]) {
                $total_freq *= (float)$match[1];
                continue;
            }
        }
        return $total_freq;
    }

    /**
     * Parses the dosages of a sig
     *
     * @param  Array $dosages Strings to parse. Only get the first "valid one".
     *
     * @return Array    With keys "sig_qty" and "sig_unit"
     */
    private function parse_dosages($dosages) {
        $parsed = [
            "sig_qty" => 1,
            "sig_unit" => ""
        ];
        foreach ($dosages as $dose) {
            // NOTE: Gets the LAST number from a particular dosage.
            // "1 to 2 capsules" => 2.
            preg_match('/((?:\d*\.)?\d+)(?!.*((?:\d*\.)?\d+))(.*)/i', $dose, $match);

            if ($match AND $match[1]) {
                $parsed["sig_qty"] = (float) $match[1];
                $parsed["sig_unit"] = trim($match[3]);

                // If sig has multiple doses, give presedence to the first one.
                // ["1 capsule", "40mg", "60mg"] will only count the 1 capsule.
                break;
            }
        }
        return $parsed;
    }

    private function parse_durations($durations) {
        if (count($durations) == 0) {
            return 0;
        }

        $total_duration = 1;
        foreach ($durations as $dur) {
            preg_match('/(\d+)(.*)day/i', $dur, $match);
            if ($match AND $match[1]) {
                $total_duration *= (int)$match[1];
            }
        }
        if ($total_duration == 1) {
            return 0;
        }
        return $total_duration;
    }
}

$parser = new SigParser();

$correct_pairs = [

    // TODO: Check for repeated wording in "multiple sigs"
    // "1 tablet by mouth daily; TAKE ONE TABLET BY MOUTH ONCE DAILY" => 1,

    "Take 7.5mg three days per week (M,W,F) and 5mg four days per week OR as directed per Coumadin Clinic." => [
        "sig_qty" => (22.5 + 20) * DAYS_STD / 7,
        "sig_days" => DAYS_STD,
        "sig_unit" => "mg"
    ],
    "1 tablet by mouth every 8 hours as needed for Blood Pressure greater than 140/90" => [
        "sig_qty" => 3 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "tablet"
    ],
    "Take 4 tablets by mouth 3 times a day with meal and 2 tablets twice a day with snacks" => [
        "sig_qty" => 16 * 90,
        "sig_days" => DAYS_STD,
        "sig_unit" => "tablets"
    ],
    "1 capsule by mouth at bedtime for 1 week then 2 capsules at bedtime" => [
        "sig_qty" => 7 + 2 * (DAYS_STD - 7),
        "sig_days" => DAYS_STD,
        "sig_unit" => "capsule"
    ],
    "Take 1 tablet (10 mg) by mouth 2 times per day for 21 days with food, then increase to 2 tablets (20 mg) BID" => [
        "sig_qty" => 2 * 21 + 4 * (DAYS_STD - 21),
        "sig_days" => DAYS_STD,
        "sig_unit" => "tablet"
    ],
    // Outlier
    // "Take 2 tablets in the morning and 1 at noon and 1 at supper" => [
    //     "sig_qty" => 4 * DAYS_STD,
    //     "sig_days" => DAYS_STD,
    //     "sig_unit" => "tablets"
    // ],
    "Take 1 capsule(s) 3 times a day by oral route with meals for 7 days." => [
        "sig_qty" => 21,
        "sig_days" => 7,
        "sig_unit" => "capsule"
    ],
    // Outlier
    // "1 capsule by mouth every day for 7 days then continue on with 60mg capsuled" => [
    //     "sig_qty" => 7,
    //     "sig_days" => 7,
    //     "sig_unit" => "capsule"
    // ],
    "Take 1 tablet by mouth 1 time daily then, if no side effects, increase to 1 tablet 2 times daily with meals" => [
        "sig_qty" => 3 * DAYS_STD,    // Uncertain
        "sig_days" => DAYS_STD,
        "sig_unit" => "tablet"
    ],
    "take 10 tablets (40MG total)  by ORAL route   every day for  4 days only" => [
        "sig_qty" => 40,
        "sig_days" => 4,
        "sig_unit" => "tablets"
    ],
    "Take 1 tablet (12.5 mg) by mouth per day in the morning" => [
        "sig_qty" => 1 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "tablet"
    ],
    "1 capsule by mouth 30 minutes after the same meal each day" => [
        "sig_qty" => 1 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "capsule",
    ],
    "1 capsule by mouth every day (Start after finishing 30mg capsules first)" => [
        "sig_qty" => 1 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "capsule"
    ],
    "1 capsule by mouth twice a day" => [
        "sig_qty" => 2 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "capsule"
    ],
    "1 capsule once daily 30 minutes after the same meal each day" => [
        "sig_qty" => 1 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "capsule"
    ],
    "1 tablet (5 mg total) by PEG Tube route 2 (two) times a day" => [
        "sig_qty" => 2 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "tablet"
    ],
    "1 tablet by mouth  every morning" => [
        "sig_qty" => 1 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "tablet"
    ],
    "1 tablet by mouth at bedtime" => [
        "sig_qty" => 1 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "tablet"
    ],
    "1 tablet by mouth at bedtime as directed" => [
        "sig_qty" => 1 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "tablet"
    ],
    "1 tablet by mouth at bedtime as needed" => [
        "sig_qty" => 1 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "tablet"
    ],
    "1 tablet by mouth at bedtime mood" => [
        "sig_qty" => 1 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "tablet"
    ],
    "1 tablet by mouth daily" => [
        "sig_qty" => 1 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "tablet"
    ],
    "1 tablet by mouth day" => [
        "sig_qty" => 1 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "tablet"
    ],
    "1 tablet by mouth every 8 hours" => [
        "sig_qty" => 3 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "tablet"
    ],
    "Take 5 tablets by mouth once  at bedtime" => [
        "sig_qty" => 5 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "tablets"
    ],
    "Take  1 TO 2 capsules by mouth 3 times a day as needed FOR NERVE PAINS" => [
        "sig_qty" => 6 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "capsules"
    ],
    // Outlier?
    // "Take 1 capsule once a day take along with 40mg for total of 60mg" => [
    //     "sig_qty" => 1 * DAYS_STD,
    //     "sig_days" => DAYS_STD,
    //     "sig_unit" => "capsule"
    // ],
    "1 capsule as needed every 6 hrs Orally 30 day(s)" => [
        "sig_qty" => 120,
        "sig_days" => 30,
        "sig_unit" => "capsule"
    ],
    "1 tablet every 6 to 8 hours as needed Orally 30 day(s)" => [
        "sig_qty" => 90,     // or 120?
        "sig_days" => 30,
        "sig_unit" => "tablet"
    ],
    "Take 1 tablet by mouth every night as needed sleep" => [
        "sig_qty" => 1 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "tablet"
    ],
    "Take 1 tablet (800 mg) by oral route 3 times per day with food as needed for pain" => [
        "sig_qty" => 3 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "tablet"
    ],
    "Take 1 tablet by mouth 3 times a day as needed" => [
        "sig_qty" => 3 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "tablet"
    ],

    // TODO: For now taking the unit which is in the sig itself
    // Unless it's vials, in which case parse it to ml.
    "Use 1 vial via nebulizer every 6 hours" => [
        "sig_qty" => 12 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "ml"
    ],
    "Take 20 mEq by mouth 2 (two) times a day." => [
        "sig_qty" => 40 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "mEq"
    ],
    "Inhale 2 puff(s) every 4 hours by inhalation route." => [
        "sig_qty" => 12 * DAYS_STD,
        "sig_days" => DAYS_STD,
        "sig_unit" => "puff"
    ]

];

foreach($correct_pairs as $text => $expected) {
    $result = $parser->parse($text);

    foreach ($expected as $key => $val) {
        $msg = "For $key expected ".$expected[$key].", got ".$result[$key].". \n\tSig: $text\n";
        if (is_float($expected[$key])) {
            assert(abs($expected[$key] - $result[$key]) - 0.001, $msg);
        } else {
            assert($expected[$key] == $result[$key], $msg);
        }
    }
}

?>
