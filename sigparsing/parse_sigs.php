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

    function parse($text, $drugname = '') {
        $text = preprocessing($text);
        $attributes = $this->get_attributes($text);
        $attributes_drug = $this->get_attributes($drugname);
        return $this->postprocessing($attributes, $attributes_drug, $text);
    }

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

    private function request_attributes($text) {
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
        if ($dur != $parsed_dur) {
            $final_days = DAYS_STD;
        }
        return [
            'sig_unit' => $valid_unit,
            'sig_qty' => $final_qty,
            'sig_days' => $final_days
        ];
    }

    private function parse_frequencies($split) {
        $frequencies = $this->filter_attributes($split, "FREQUENCY", 0.6);
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
    private function parse_dosages($split, $attributes_drug) {
        $dosages = $this->filter_attributes($split, "DOSAGE", 0.5);
        $unit = $this->filter_attributes($attributes_drug, "FORM", 0.5)[0];
        $weight = $this->filter_attributes($attributes_drug, "STRENGTH", 0.5)[0];
        if (!$weight) {
            $weight = $this->filter_attributes($attributes_drug, "DOSAGE", 0.5)[0];
        }

        // Get the LAST number and its unit
        // "2.5MG/3ML" => "3" "ML"
        preg_match('/((?:\d*\.)?\d+)(?!.*((?:\d*\.)?\d+))(.*)/i', $weight, $match);
        if ($match) {
            $weight_unit = $match[3];
            $weight_value = (float) $match[1];
            // Special case: If the unit is ML, don't normalize it
            if (preg_match('/ml/i', $weight_unit)) {
                $weight_value = 1;
                $unit = 'ml';
            }
        }

        $parsed = [
            "sig_qty" => 0,
            "sig_unit" => $unit
        ];

        foreach ($dosages as $dose) {
            // Gets the LAST number from a particular dosage.
            // "1 to 2 capsules" => 2.
            preg_match('/((?:\d*\.)?\d+)(?!.*((?:\d*\.)?\d+))(.*)/i', $dose, $match);

            if ($match AND $match[1]) {
                $sig_qty = (float) $match[1];
                $sig_unit = trim($match[3]);

                if ($weight_unit AND preg_match('/'.$weight_unit.'/i', $sig_unit)) {
                    $parsed['sig_qty'] += $sig_qty / $weight_value;
                } else {
                    $parsed['sig_qty'] += $sig_qty;
                }

                if (!$parsed['sig_unit']) {
                    $parsed['sig_unit'] = $sig_unit;
                }
            }
        }
        return $parsed;
    }

    private function parse_durations($split) {
        $durations = $this->filter_attributes($split, "DURATION", 0.6);
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

?>