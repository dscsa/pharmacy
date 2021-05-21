<?php

require 'aws/aws-autoloader.php';
require 'helper_constants.php';
require 'parse_preprocessing.php';

use Aws\ComprehendMedical\ComprehendMedicalClient;
use Aws\Credentials\Credentials;

/**
 * A class for parsing attributes of sigs with their drug name,
 * using the AWS Comprehend Medical API.
 */
class SigParser {

    /**
     * Used for testing purposes only. An array to cache AWS CH results,
     * where the key of each element is the text and the value the result of the AWS call.
     * @var array
     */
    private static $defaultsigs = array();

    /**
     * Points to a JSON with the same structure as $defaultsigs.
     * It should only be provided to the class while testing.
     * @var string
     */
    private $ch_test_file;

    /**
     * true if $ch_test_file was provided.
     * @var boolean
     */
    private $save_test_results;

    /**
     * AWS Comprehend Medical client
     * @var ComprehendMedicalClient
     */
    private $client;

    /**
     * array of floats between 0 to 1, used to determine the sig score
     * by the end of the parsing.
     * It is reset everytime the parse() function is called.
     * @var array<int>
     */
    private $scores;

    /**
     * If $ch_test_file was provided, the class will store and load the AWS CH results into it,
     * to prevent multiple requests while testing.
     * @param string $ch_test_file path to the json file
     */
    function __construct($ch_test_file = '') {
        $credentials = new Credentials($_ENV['AWS_ACCESS_KEY'], $_ENV['AWS_SECRET_KEY']);
        $this->client = new ComprehendMedicalClient([
            'credentials' => $credentials,
            'region' => 'us-west-2',
            'version' => '2018-10-30'
        ]);
        $this->save_test_results = strlen($ch_test_file) > 0;
        $this->ch_test_file = $ch_test_file;

        if ($this->save_test_results AND file_exists($this->ch_test_file)) {
            $res = file_get_contents($this->ch_test_file);
            static::$defaultsigs = json_decode($res, true);
        }
    }


    /**
     * Given a sig and a drugname, returns the parsed sig attributes.
     *
     * @param string $sig
     * @param string $drugname
     * @return array<string,mixed> ['sig_unit', 'sig_days', 'sig_qty', 'sig_conf_score']
     */
    function parse($sig, $drugname = '') {
        if ($this->early_return($sig, $drugname, $result)) {
            return $result;
        }

        $this->scores = array();
        $sig = preprocessing($sig);
        $attributes = $this->get_attributes($sig);
        $attributes_drug = $this->get_attributes($drugname);
        return $this->postprocessing($attributes, $attributes_drug, $sig);
    }


    /**
     * Before starting to parse the sig and the drugname with the 
     * three steps (pre/aws-ch-api/post), determine if there's a simpler
     * result that can be returned. For example, if it's inhalers
     * it can always return sig_qty = 3/90.
     * 
     * @param string               $sig
     * @param string               $drugname
     * @param array<string,mixed>  &$result
     * @return bool
     */
    private function early_return($sig, $drugname, &$result) {

        // If its an inhaler, spray, cream, gel or eye drops, return a fixed value.
        $fixed_drugnames_values = [
            '/ (CREAM|INH|INHALER|SPR|SPRAY)$/i' => 3/90,
            '/ (GEL)$/i' => 1,
            '/(EYE DROP)/i' => 0.1,
        ];
        foreach ($fixed_drugnames_values as $regex => $qty_per_day) {
            if (preg_match($regex, $drugname, $match)) {
                $result = [
                    'sig_unit' => trim($match[1]),
                    'sig_qty' => $qty_per_day * DAYS_STD,
                    'sig_days' => DAYS_STD,
                    'sig_conf_score' => 1
                ];
                return true;
            }
        }

        // If the dose has a maximum, return that result.
        // e.g. "Take 2 per day, max of 3 if needed" => "Take 3 if needed"
        $splits = preg_split('/(max (of)?|may go up to|may increase to|may gradually increase to)/i', $sig);
        if (count($splits) > 1) {
            // Get the first word of the sig + everything after the split
            $sig = explode(' ',trim($sig))[0].' '.$splits[1];
            $result = $this->parse($sig, $drugname);

            // Only return the new result if it obtained a result from AWS
            return $result['sig_conf_score'] > 0;
        }

        return false;
    }


    /**
     * Given a text, returns the AWS CH Attributes of the text, both mapped and unmapped.
     * @param string $text
     * @return array<Attribute>
     * 
     * @link https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-comprehendmedical-2018-10-30.html#shape-attribute AWS CH Attribute Shape
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
     * Given a text, returns its full AWS CH result.
     * If $this->save_test_results is true, it saves the result in
     * the file $this->ch_test_file.
     *
     * @param string $text
     * @return array
     */
    private function request_attributes($text) {
        if ($this->save_test_results) {
            if (array_key_exists($text, static::$defaultsigs)) {
                return static::$defaultsigs[$text];
            }
            printf("Requesting DetectEntitiesV2 for ".$text."\n");
        }
        $result = $this->client->detectEntitiesV2(['Text' => $text])->toArray();

        if ($this->save_test_results) {
            static::$defaultsigs[$text] = $result;
            file_put_contents($this->ch_test_file, json_encode(static::$defaultsigs));
        }
        return $result;
    }


    /**
     * Filters the Attributes by "Type" and which have a minimum "Score" of $score_tol.
     * Usual "Types" may include "DOSAGE", "DURATION", "FREQUENCY", "FORM", etc.
     * The score is assigned by the NLP of AWS, being a value between [0, 1] representing
     * how sure it is by the categorization.
     * 
     * @param  array<Attribute> $sections of AWS CH Attributes
     * @param  string           $type of Attributes to filter by
     * @param  bool             $save_score if true, adds filtered attributes to $this->scores.
     * @param  float            $score_tol minimum score necesary to include it in the results
     * @return array
     */
    private function filter_attributes($sections, $type, $save_scores = true, $score_tol = 0) {
        $attrs = [];
        $score_tol = (float) $score_tol;
        foreach ($sections as $attr) {
            if ($attr["Type"] == $type AND $attr["Score"] > $score_tol) {
                $attrs[] = $attr["Text"];
                if ($save_scores) {
                    $this->scores[] = $attr["Score"];
                }
            }
        }

        return $attrs;
    }


    /**
     * Compares two AWS CH attributes by their 'EndOffset' value
     *
     * @param Attribute $attr1
     * @param Attribute $attr2
     * @return int
     */
    private function cmp_attrs($attr1, $attr2) {
        return $attr1['EndOffset'] - $attr2['EndOffset'];
    }


    /**
     * Splits the array of $sections into more arrays, in order to "recognize"
     * multiple sigs. 
     * 
     * For example, for the text:
     * "Take 4 tablets by mouth 3 times a day with meal and 2 tablets twice a day with snacks"
     * 
     * It should return the attributes splitted by "and" like this:
     * 
     * [[4 tablets, 3 times a day], [2 tablets, twice a day]]
     *
     * @param  array<Attribute> $sections of AWS CH Attributes
     * @param  string           $text The original sig that was sent to the AWS CH API
     * @return array<array<Attribute>>
     */
    private function split_sections($sections, $text) {
        $increase = "(may|can) increase(.*?\/ *(month|week))?";
        $sentence = "(?<=[a-z ])[.;\/] *(?=\w)";  //Sentence ending in . ; or / e.g. "Take 1 tablet by mouth once daily / take 1/2 tablet on sundays"
        $then     = " then[ ,]+";
        $commas   = ",";
        $and_at   = " & +at ";
        $and_may   = " & +may ";
        $and_verb = " &[ ,]+(?=\d|use +|take +|inhale +|chew +|inject +|oral +)";

        $durations_regex = "/($increase|$sentence|$then|$commas|$and_at|$and_verb|$and_may)/i";

        $splits = preg_split($durations_regex, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        usort($sections, array('SigParser', 'cmp_attrs'));
        $subsections = [];
        $offset_len = 0;
        $splits_idx = 0;
        $attrs_len = 0;
        foreach ($sections as $section) {
            while ($section["EndOffset"] > $offset_len AND $splits_idx < count($splits)) {
                $offset_len += strlen($splits[$splits_idx]);
                $splits_idx += 1;
            }
            if (!array_key_exists($splits_idx - 1, $subsections)) {
                $subsections[$splits_idx - 1] = [];
            }
            $subsections[$splits_idx - 1][] = $section;
            $attrs_len += strlen($section['Text']);
        }

        $this->scores[] = $attrs_len / strlen($text);
        return $subsections;
    }


    /**
     * Postprocessing stage, parses the $attributes and $attributes_drug arrays 
     * given by the AWS CH API.
     *
     * @param array<Attribute>  $attributes      AWS CH result array for the sig
     * @param array<Attribute>  $attributes_drug AWS CH result array for the drug_name
     * @param string            $text            The original sig to be parsed. Used for splitting the attributes.
     * @return array
     */
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

        $score = empty($this->scores) ? 0 : array_product($this->scores);
        return [
            'sig_unit' => $valid_unit,
            'sig_qty' => $final_qty,
            'sig_days' => $final_days,
            'sig_conf_score' => $score
        ];
    }


    /**
     * Given an array of AWS CH Attributes, it filters the frequencies and returns
     * an array, taking into account the time interval of each frequency.
     *
     * @param array<Attribute> $split AWS CH Attribute array of the sig
     * @return int             The result of the parsed frequencies. If no frequency attr was found, returns 1.
     */
    private function parse_frequencies($split) {
        $frequencies = $this->filter_attributes($split, "FREQUENCY");
        if (count($frequencies) == 0) {
            return 1;
        }

        $total_freq = 1;
        foreach ($frequencies as $freq) {
            // If "as needed" or "as directed" matches, reduce the confidence score?
            if (preg_match('/(as needed|if needed|as directed)/i', $freq, $match)) {
                // $this->scores[] = 0.8;
            }

            // NOTE: Gets the LAST number from a particular frequency.
            // Because it gives better results
            // "1 to 2 days" => 2.

            // Hours match
            preg_match('/(\d+)(?!.*\d)(.*)hour/i', $freq, $match);
            if ($match AND $match[1]) {
                // If it's "every N hours before X", don't normalize it to 24hrs
                if (!preg_match('/(before|prior|about|after|period)/i', $freq)) {
                    // $total_freq *= (float)$match[1];
                    $total_freq *= 24 / (float)$match[1];
                }
                break; // Use breaks to only register a single frequency per section
            }

            // Weeks match
            preg_match('/(\d+)(?!.*\d)(.*) week/i', $freq, $match);
            if ($match AND $match[1]) {
                $total_freq *= (float)$match[1] / 7;
                break;
            }

            // Minutes match. It's usually under context so it's not always "Take X every 30 minutes"
            // For that reason, it skips the minutes if it matches
            preg_match('/(\d+)(?!.*\d)(.*)Minute/i', $freq, $match);
            if ($match AND $match[1]) {
                continue;
            }

            preg_match('/(\d+)(?!.*\d)(.*)/i', $freq, $match);
            if ($match AND $match[1]) {
                // If the frequency starts with "every", invert it. "every 2 days" should be freq=0.5
                if (preg_match('/^every/i', $freq)) {
                    $total_freq *= 1 / (float)$match[1];
                } else {
                    $total_freq *= (float)$match[1];
                }
                break;
            }

        }
        return $total_freq;
    }


    /**
     * Given an array of AWS CH Attributes, it filters the dosages and returns
     * an array, taking into account the unit and value of each dosage.
     * 
     * @param array<Attribute> $split           AWS CH Attribute array of the sig
     * @param array<Attribute> $attributes_drug AWS CH Attribute array of the drug
     * @return array<string|mixed> ['sig_qty', 'sig_unit']. If no dosage was found, 'sig_qty' => 1.
     */
    private function parse_dosages($split, $attributes_drug) {
        $dosages = $this->filter_attributes($split, "DOSAGE");
        $unit = $this->filter_attributes($attributes_drug, "FORM", false)[0];
        $weight = $this->filter_attributes($attributes_drug, "STRENGTH", false)[0];
        if (!$weight) {
            $weight = $this->filter_attributes($attributes_drug, "DOSAGE", false)[0];
        }

        // array to transform the dosage unit, normalizing the whole sig.
        $equivalences = [
            // Two inhalers should be enough to cover for 90 days, so it's normalized that way.
            'puff' => DAYS_STD * 2,
            'spray' => DAYS_STD * 2,
            $unit => 1
        ];
        foreach (preg_split('/\//', $weight, -1) as $eq) {
            preg_match('/((?:\d*\.)?\d+)(?!.*((?:\d*\.)?\d+))(.*)/i', $eq, $match);
            if (!$match OR array_key_exists($match[3], $equivalences)) {
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

                $found_unit = false;
                // If the unit matches, normalize it with the weight value.
                foreach ($equivalences as $unit => $value) {
                    if ($unit AND preg_match('/'.preg_quote($unit).'/i', $sig_unit)) {
                        $parsed['sig_qty'] += $sig_qty / $value;
                        $found_unit = true;
                        break;
                    }
                }
                // If no unit was found, assign the qty as is but decrease the total confidence score?
                if (!$found_unit) {
                    $parsed['sig_qty'] += $sig_qty;
                    // $this->scores[] = 0.8;
                }
                // If no sig unit was assigned, give it the unit of the first dose
                if (!$parsed['sig_unit']) {
                    $parsed['sig_unit'] = $sig_unit;
                    // $this->scores[] = 0.8;
                }
            }
        }

        // If no dosage was found, return 1 and decrease the total confidence score?
        if ($parsed["sig_qty"] == 0) {
            $parsed["sig_qty"] = 1;
            // $this->scores[] = 0.5;
        }
        return $parsed;
    }


    /**
     * Given an array of AWS CH Attributes, it filters the durations and returns
     * an integer, taking into account the time period of each one.
     * 
     * @param array<Attribute> $split AWS CH Attribute array of the sig
     * @return int  The result of the parsed durations. If no duration attr was found, returns 0.
     */
    private function parse_durations($split) {
        $durations = $this->filter_attributes($split, "DURATION");
        if (count($durations) == 0) {
            return 0;
        }

        $total_duration = [];
        foreach ($durations as $dur) {
            preg_match('/(\d+)(.*)day/i', $dur, $match);
            if ($match AND $match[1]) {
                $total_duration[] = (int)$match[1];
            }

            preg_match('/(\d+)(.*)week/i', $dur, $match);
            if ($match AND $match[1]) {
                $total_duration[] = 7 * (int)$match[1];
            }

            preg_match('/(\d+)(.*)month/i', $dur, $match);
            if ($match AND $match[1]) {
                $total_duration[] = 30 * (int)$match[1];
            }
        }
        if (count($total_duration) == 0) {
            return 0;
        }
        return array_product($total_duration);
    }
}

?>
