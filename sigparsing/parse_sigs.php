<?php

require 'aws/aws-autoloader.php';
require 'helper_constants.php';
require 'parse_preprocessing.php';

use Aws\ComprehendMedical\ComprehendMedicalClient;
use Aws\Credentials\Credentials;

class SigParser {

    private static $defaultsigs = array();
    private static $save_test_results = true;
    private static $ch_test_file = "aws-ch-res/responses.json";
    private $client;

    /**
     * If static::$save_test_results is true, it loads the AWS CH API results from 
     * the file static::$ch_test_file. Useful for testing and limiting
     * the ammunt of requests.
     */
    function __construct() {
        $credentials = new Credentials($_ENV['AWS_ACCESS_KEY'], $_ENV['AWS_SECRET_KEY']);
        $this->client = new ComprehendMedicalClient([
            'credentials' => $credentials,
            'region' => 'us-west-2',
            'version' => '2018-10-30'
        ]);

        if (static::$save_test_results AND file_exists(static::$ch_test_file)) {
            $res = file_get_contents(static::$ch_test_file);
            static::$defaultsigs = json_decode($res, true);
        }
    }

    /**
     * Given a sig and a drugname, returns the sig attributes.
     * @return Array    With keys "sig_qty", "sig_days" and "sig_unit"
     */
    function parse($sig, $drugname = '') {
        $sig = preprocessing($sig);
        $attributes = $this->get_attributes($sig);
        $attributes_drug = $this->get_attributes($drugname);
        return $this->postprocessing($attributes, $attributes_drug, $sig);
    }


    /**
     * Given a text, returns the AWS CH Attributes of the text, both mapped and unmapped.
     * See: https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-comprehendmedical-2018-10-30.html#shape-attribute
     * @return Array    of AWS Attributes
     */
    private function get_attributes($text) {
        if (empty($text)) {
            return array();
        }
        $sections = $this->request_attributes($text);
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
        return $attributes;     
    }

    /**
     * Given a text, returns its AWS CH result.
     * If static::$save_test_results is true, it saves the result in
     * the file static::$ch_test_file.
     */
    private function request_attributes($text) {
        if (static::$save_test_results AND array_key_exists($text, static::$defaultsigs)) {
            return static::$defaultsigs[$text];
        }
        printf("Requesting DetectEntitiesV2 for ".$text."\n");
        $result = $this->client->detectEntitiesV2(['Text' => $text])->toArray();

        if (static::$save_test_results) {
            static::$defaultsigs[$text] = $result;
            file_put_contents(static::$ch_test_file, json_encode(static::$defaultsigs));
        }
        return static::$defaultsigs[$text];
    }

    /**
     * Filters the Attributes by "Type" and which have a minimum "Score" of $score_tol.
     * Usual "Types" may include "DOSAGE", "DURATION", "FREQUENCY", "FORM", etc.
     * The score is assigned by the NLP of AWS, being a value between [0, 1] representing
     * how sure it is by the categorization.
     * 
     * @param  Array $sections of AWS CH Attributes
     * @param  Array $type of Attributes to filter by
     * @param  Array $score_tol minimum score necesary to include it in the results
     */
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

    /**
     * Splits the array of $sections into more arrays, in order to "recognize"
     * multiple sigs. For example, for the text:
     * "Take 4 tablets by mouth 3 times a day with meal and 2 tablets twice a day with snacks"
     * 
     * It should return the attributes splitted by "and" like this:
     * [[4 tablets, 3 times a day], [2 tablets, twice a day]]
     * 
     * @param  Array $sections of AWS CH Attributes
     * @param  Array $text The original sig that was sent to the AWS CH API
     */
    private function split_sections($sections, $text) {
        $increase = "(may|can) increase(.*?\/ *(month|week))?";
        $sentence = "(?<=[a-z ])[.;\/] *(?=\w)";  //Sentence ending in . ; or / e.g. "Take 1 tablet by mouth once daily / take 1/2 tablet on sundays"
        $then     = " then[ ,]+";
        $commas   = ",";
        $and_at   = " & +at ";
        $and_verb = " &[ ,]+(?=\d|use +|take +|inhale +|chew +|inject +|oral +)";

        $durations_regex = "/($increase|$sentence|$then|$commas|$and_at|$and_verb)/i";

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

    private function postprocessing($attributes, $attributes_drug, $text) {
        $splits = $this->split_sections($attributes, $text);
        $final_qty = 0;
        $final_days = 0;
        $valid_unit = "";
        $last_sig_qty = 1;

        foreach ($splits as $split) {
            $freq = $this->parse_frequencies($split);
            $dose = $this->parse_dosages($split, $attributes_drug);
            $parsed_dur = $this->parse_durations($split);

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
        if ($dur != $parsed_dur OR $final_days == 0) {
            $final_days = DAYS_STD;
        }

        return [
            'sig_unit' => $valid_unit,
            'sig_qty' => $final_qty,
            'sig_days' => $final_days
        ];
    }

    private function parse_frequencies($split) {
        $frequencies = $this->filter_attributes($split, "FREQUENCY", 0.7);
        if (count($frequencies) == 0) {
            return 1;
        }

        $total_freq = 1;
        foreach ($frequencies as $freq) {
            // NOTE: Gets the LAST number from a particular frequency.
            // Because it gives better results
            // "1 to 2 days" => 2.

            // Hours match
            preg_match('/(\d+)(?!.*\d)(.*)hour/i', $freq, $match);
            if ($match AND $match[1]) {
                // If it's "every N hours before X", don't normalize it to 24hrs
                if (preg_match('/(before|prior)/i', $freq)) {
                    $total_freq *= (float)$match[1];
                } else {
                    $total_freq *= 24 / (float)$match[1];
                }
                continue;
            }

            // Weeks match
            preg_match('/(\d+)(?!.*\d)(.*) week/i', $freq, $match);
            if ($match AND $match[1]) {
                $total_freq *= (float)$match[1] / 7;
                continue;
            }

            // Minutes match. It's usually under context so it's not always "Take X every 30 minutes"
            // For that reason, it skips the minutes if it matches
            preg_match('/(\d+)(?!.*\d)(.*)Minute/i', $freq, $match);
            if ($match AND $match[1]) {
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

    private function parse_dosages($split, $attributes_drug) {
        $dosages = $this->filter_attributes($split, "DOSAGE", 0.5);
        $unit = $this->filter_attributes($attributes_drug, "FORM", 0.5)[0];
        $weight = $this->filter_attributes($attributes_drug, "STRENGTH", 0.5)[0];
        if (!$weight) {
            $weight = $this->filter_attributes($attributes_drug, "DOSAGE", 0.5)[0];
        }

        // Array to transform the dosage unit, normalizing the whole sig.
        $equivalences = [
            // Two inhalers should be enough to cover for 90 days, so it's normalized that way.
            'puff' => DAYS_STD * 2
        ];
        foreach (preg_split('/\//', $weight, -1) as $eq) {
            preg_match('/((?:\d*\.)?\d+)(?!.*((?:\d*\.)?\d+))(.*)/i', $eq, $match);
            if (!$match) {
                continue;
            }
            $equivalences[$match[3]] = (float) $match[1];
        }

        // If the unit is ML, don't normalize it and assign the unit as ML.
        if (array_key_exists('ML', $equivalences)) {
            $unit = 'ML';
            foreach ($equivalences as $unit => $value) {
                $equivalences[$unit] = $equivalences[$unit] / $equivalences['ML'];
            }
        }

        $parsed = [
            "sig_qty" => 0,
            "sig_unit" => $unit
        ];

        foreach ($dosages as $dose) {
            // Gets ALL numbers from a particular dosage.
            // "1 to 2 capsules" => 2.
            preg_match('/((?:\d*\.)?\d+)(?!.*((?:\d*\.)?\d+))(.*)/i', $dose, $match);

            if ($match AND $match[1]) {
                $sig_qty = (float) $match[1];
                $sig_unit = trim($match[3]);

                $found = false;
                // If the unit matches, normalize it with the weight value.
                foreach ($equivalences as $unit => $value) {
                    if ($unit AND preg_match('/'.preg_quote($unit).'/i', $sig_unit)) {
                        $parsed['sig_qty'] += $sig_qty / $value;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $parsed['sig_qty'] += $sig_qty;
                }
                // If no sig unit was assigned, give it the unit of the first dose
                if (!$parsed['sig_unit']) {
                    $parsed['sig_unit'] = $sig_unit;
                }
            }
        }
        if ($parsed["sig_qty"] == 0) {
            $parsed["sig_qty"] = 1;
        }
        return $parsed;
    }

    private function parse_durations($split) {
        $durations = $this->filter_attributes($split, "DURATION", 0.3);
        if (count($durations) == 0) {
            return 0;
        }

        $total_duration = 1;
        foreach ($durations as $dur) {
            preg_match('/(\d+)(.*)day/i', $dur, $match);
            if ($match AND $match[1]) {
                $total_duration *= (int)$match[1];
            }

            preg_match('/(\d+)(.*)week/i', $dur, $match);
            if ($match AND $match[1]) {
                $total_duration *= 7 * (int)$match[1];
            }

            preg_match('/(\d+)(.*)month/i', $dur, $match);
            if ($match AND $match[1]) {
                $total_duration *= 30 * (int)$match[1];
            }
        }
        if ($total_duration == 1) {
            return 0;
        }
        return $total_duration;
    }
}

?>