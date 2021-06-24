<?php

namespace GoodPill\Utilities;

use Aws\ComprehendMedical\ComprehendMedicalClient;
use Aws\Credentials\Credentials;
use GoodPill\Logging\GPLog;


/**
 * A class for parsing attributes of sigs with their drug name,
 * using the AWS Comprehend Medical API.
 */
class SigParser
{

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
     * True if $ch_test_file was provided.
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
     * @param string $ch_test_file path to the json test file
     */
    function __construct($ch_test_file = '') {
        $credentials = new Credentials(AWS_KEY, AWS_SECRET);
        $this->client = new ComprehendMedicalClient([
            'credentials' => $credentials,
            'region' => SIG_PARSER_AWS_COMPREHEND_REGION,
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
     * @return array<string,mixed> ['sig_unit', 'sig_days', 'sig_qty', 'sig_conf_score', 'frequencies', 'dosages', 'durations', 'preprocessing', 'scores']
     * 
     * @throws SigParserAWSComprehendException if an error ocurred while requesting to AWS Comprehend Medical
     * @throws SigParserMalformedInputException if the input sig, after preprocessing, is empty
     * @throws SigParserUnexpectedException any other unexpected error. logs the result
     */
    function parse($sig, $drugname = '') {
        if ($this->early_return($sig, $drugname, $result)) {
            return $result;
        }

        $this->scores = array();
        $sig_pre = $this->preprocessing($sig);
        if (empty($sig_pre)) {
            throw new SigParserMalformedInputException();
        }

        $attributes = $this->get_attributes($sig_pre);
        $attributes_drug = $this->get_attributes($drugname);

        try {
            return $this->postprocessing($attributes, $attributes_drug, $sig_pre);
        } catch (\Exception $e) {
            GPLog::warning("Unexpected error while parsing \"$sig_pre\": ".$e->getMessage()."\n");
            GPLog::debug("$e\n");
            throw new SigParserUnexpectedException();
        }
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
        // See helper_constants.php
        foreach (SIG_PARSER_FIXED_DRUGNAMES as $regex => $qty_per_day) {
            if (preg_match($regex, $drugname, $match)) {
                $result = [
                    'sig_unit' => trim($match[1]),
                    'sig_qty' => $qty_per_day * DAYS_STD,
                    'sig_days' => DAYS_STD,
                    'sig_conf_score' => 1,
                    'frequencies' => '',
                    'dosages' => '',
                    'durations' => '',
                    'scores' => '',
                    'preprocessing' => $sig
                ];
                return true;
            }
        }

        // If the dose has a maximum, return that result.
        // e.g. "Take 2 per day, max of 3 if needed" => "Take 3 if needed"
        $splits = preg_split('/(max (of)?|may go up to|may increase(.*)to|may gradually increase(.*)to)/i', $sig);
        if (count($splits) > 1) {
            // Get the first word of the sig + everything after the split
            $sig = explode(' ',trim($sig))[0].' '.$splits[1];
            $result = $this->parse($sig, $drugname);

            // Only return the new result if it obtained a result from AWS
            return $result AND $result['sig_conf_score'] > 0;
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
            GPLog::debug("Requesting DetectEntitiesV2 for ".$text."\n");
        }

        // Try atleast 3 times before we give up.
        $exp = null;
        do {
            $tries = (isset($tries)) ? $tries + 1 : 1;
            try {
                $result = $this->client->detectEntitiesV2(['Text' => $text]);
            } catch (\Exception $e) {
                $exp = $e;
                sleep(1);
                continue;
            }

            if (!isset($result['Entities'])
                OR !gettype($result['Entities']) == 'array'
                OR !isset($result['UnmappedAttributes'])
                OR !gettype($result['UnmappedAttributes']) == 'array'
            ) {
                throw new SigParserAWSComprehendException('Malformed output result');
            }

            if ($this->save_test_results) {
                static::$defaultsigs[$text] = $result->toArray();
                file_put_contents($this->ch_test_file, json_encode(static::$defaultsigs));
            }

            return $result;
        } while (!isset($result) && $tries < 3);

        throw new SigParserAWSComprehendException($exp ? $exp->getMessage() : 'Malformed output result');
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
                    $this->scores[] = round($attr["Score"], 3);
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
    private function cmp_attrs_by_offset($attr1, $attr2) {
        return $attr1['EndOffset'] - $attr2['EndOffset'];
    }

    /**
     * Compares two arrays of AWS CH attributes by how many durations
     * they have.
     *
     * @param Attribute $attr1
     * @param Attribute $attr2
     * @return int
     */
    private function cmp_sections_by_durations($sect1, $sect2) {
        $count1 = DAYS_STD - sizeof($this->filter_attributes($sect1, "DURATION"));
        $count2 = DAYS_STD - sizeof($this->filter_attributes($sect2, "DURATION"));
        return $count1 - $count2;
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

        usort($sections, array('GoodPill\Utilities\SigParser', 'cmp_attrs_by_offset'));
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
            $attrs_len += strlen($section['Text']);

            // Only add relevant types
            if (in_array($section['Type'], ['FREQUENCY', 'DOSAGE', 'DURATION'])) {
                $subsections[$splits_idx - 1][] = $section;
            }
        }

        // To fix some sigs which specify a duration only after the second split,
        // sort the subsections by the duration count (descending).
        usort($subsections, array('GoodPill\Utilities\SigParser', 'cmp_sections_by_durations'));

        // Reduce the confidence score based on unmapped attributes
        $this->scores[] = round($attrs_len / strlen($text), 3);
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
        $all_freq = array();
        $all_dose = array();
        $all_dur = array();

        foreach ($splits as $split) {
            $freq = $this->parse_frequencies($split, $found_freq);
            $dose = $this->parse_dosages($split, $attributes_drug, $found_dosage);
            $parsed_dur = $this->parse_durations($split);

            // If no frequency or dosage was found in the split, ignore it
            if (!$found_freq AND !$found_dosage) {
                continue;
            }

            $final_days += $parsed_dur;
            $dur = ($parsed_dur ? $parsed_dur : (DAYS_STD - $final_days));

            $valid_unit = (strlen($valid_unit) > 0 ? $valid_unit : $dose['sig_unit']);

            // If a unit of a split doesn't match, use the last_sig_qty.
            $similarity = 0;
            similar_text($valid_unit, $dose['sig_unit'], $similarity);
            if ($similarity > 60) {
                $last_sig_qty = $dose['sig_qty'];
            }

            if ($freq == $dose['sig_qty'] AND $split[0]['Type'] == 'FREQUENCY') {
                $freq = 1;
            }

            // Check for repeated splits (same freq, dose and duration as last split).
            if (end($all_freq) == $freq AND end($all_dose) == $dose['sig_qty'] AND end($all_dur) == $dur) {
                continue;
            }

            $all_freq[] = $freq;
            $all_dose[] = $dose['sig_qty'];
            $all_dur[] = $dur;

            $final_qty += $last_sig_qty * $freq * $dur;
        }

        // If the last split didn't have a duration, set $final_days to DAYS_STD
        if ($dur != $parsed_dur OR $final_days == 0) {
            $final_days = DAYS_STD;
        }

        // Reduce confidence score if the qty_per_day exceeds SIG_PARSER_EXCESS_QTY_PER_DAY
        $qty_per_day = $final_qty / $final_days;
        if ($qty_per_day > SIG_PARSER_EXCESS_QTY_PER_DAY) {
            $this->scores[] = round(SIG_PARSER_EXCESS_QTY_PER_DAY / $qty_per_day, 3);
        }

        $score = empty($this->scores) ? 0 : array_product($this->scores);

        // TODO: Remove once sig_conf_score is checked in the UI
        // Set the qty_per_day to 1 if sig_conf_score is less than a fixed threshold
        if ($score < SIG_PARSER_CONF_SCORE_CUTOFF) {
            $final_days = DAYS_STD;
            $final_qty = DAYS_STD;
        }

        return [
            'sig_unit' => $valid_unit,
            'sig_qty' => $final_qty,
            'sig_days' => $final_days,
            'sig_conf_score' => $score,
            'frequencies' => implode(',', $all_freq),
            'dosages' => implode(',', $all_dose),
            'durations' => implode(',', $all_dur),
            'scores' => implode(',', $this->scores),
            'preprocessing' => $text
        ];
    }


    /**
     * Given an array of AWS CH Attributes, it filters the frequencies and returns
     * an array, taking into account the time interval of each frequency.
     *
     * @param array<Attribute> $split         AWS CH Attribute array of the sig
     * @param boolean          &$found_freq   set to true if at least one dosage was found
     * @return int             The result of the parsed frequencies. If no frequency attr was found, returns 1.
     */
    private function parse_frequencies($split, &$found_freq) {
        $frequencies = $this->filter_attributes($split, "FREQUENCY");
        if (count($frequencies) == 0) {
            $found_freq = false;
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
            $new_freq = 1;
            preg_match('/(\d+)(?!.*\d)(.*)/i', $freq, $match);
            if ($match AND $match[1]) {
                $new_freq = (float)$match[1];
            }

            // Hours match
            // If it's "every N hours before X", don't normalize it to 24hrs
            if (preg_match('/(hour|hr)/i', $freq, $match) AND !preg_match('/(before|prior|about|after|period|at)/i', $freq)) {
                $new_freq = 24 / $new_freq;
            }

            // Weeks match
            else if (preg_match('/week/i', $freq, $match)) {
                $new_freq = $new_freq / 7;
            }

            // If the frequency starts with "every", invert it. "every 2 days" should be freq=0.5
            else if (preg_match('/^every/i', $freq, $match)) {
                $new_freq = 1 / $new_freq;
            }

            $total_freq *= $new_freq;

            if ($new_freq != 1) {
                break;
            }
        }
        $found_freq = true;
        return $total_freq;
    }


    /**
     * Given an array of AWS CH Attributes, it filters the dosages and returns
     * an array, taking into account the unit and value of each dosage.
     *
     * @param array<Attribute> $split           AWS CH Attribute array of the sig
     * @param array<Attribute> $attributes_drug AWS CH Attribute array of the drug
     * @param boolean          &$found_dosage   set to true if at least one dosage was found
     * @return array<string|mixed> ['sig_qty', 'sig_unit']. If no dosage was found, 'sig_qty' => 1.
     */
    private function parse_dosages($split, $attributes_drug, &$found_dosage) {
        $dosages = $this->filter_attributes($split, "DOSAGE");
        if (count($dosages) == 0) {
            $found_dosage = false;
            return 1;
        }

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
            $new_unit = trim($match[3]);
            if (in_array($new_unit, ['TAB', 'CAP'])) {
                continue;
            }
            $equivalences[trim($match[3])] = (float) $match[1];
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
                    if ($unit AND preg_match('/'.preg_quote($unit).'/i', $sig_unit) AND $value) {
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

                // Take only the first dosage
                break;
            }
        }

        // If no dosage was found, return 1 and decrease the total confidence score?
        if ($parsed["sig_qty"] == 0) {
            $parsed["sig_qty"] = 1;
            // $this->scores[] = 0.5;
        }
        $found_dosage = true;
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


    /**
     * Preprocessing stage, cleans the $sig text in order to
     * facilitate the rest of the parsing process.
     * It replaces complicated wording, changes words to numbers,
     * deletes unnecessary information, and more.
     *
     * @param string $sig
     * @return string
     */
    function preprocessing($sig) {
        //Cleanup
        $sig = $this->preprocessing_cleanup($sig);

        //Spanish
        $sig = preg_replace('/\\btomar\\b/i', 'take', $sig);
        $sig = preg_replace('/\\bcada\\b/i', 'each', $sig);
        $sig = preg_replace('/\\bhoras\\b/i', 'hours', $sig);

        //Abreviations
        $sig = preg_replace('/\\bhrs\\b/i', 'hours', $sig);
        $sig = preg_replace('/\\b(prn|at onset|when)\\b/i', 'as needed', $sig);
        $sig = preg_replace('/\\bdays per week\\b/i', 'times per week', $sig);

        $sig = $this->preprocessing_number_substitution($sig);

        $sig = $this->preprocessing_duration_substitution($sig);

        $sig = $this->preprocessing_alternative_units($sig);

        $sig = $this->preprocessing_alternative_wording($sig);

        //Cleanup
        $sig = preg_replace('/  +/i', ' ', $sig); //Remove double spaces for aesthetics

        return trim($sig);
    }

    private function preprocessing_cleanup($sig) {
        //Cleanup
        $sig = preg_replace('/\(.*?\)/', '', $sig); //get rid of parenthesis // "Take 1 capsule (300 mg total) by mouth 3 (three) times daily."
        $sig = preg_replace('/\\\/', '', $sig);   //get rid of backslashes and single quotes (latter for sql savings errors)
        $sig = preg_replace('/\\band\\b/i', '&', $sig);   // & is easier tp search in regex than "and"
        $sig = preg_replace('/\\bDr\./i', '', $sig);   // The period in Dr. will mess up our split for durations
        $sig = preg_replace('/\\bthen call 911/i', '', $sig); //"then call 911" is not a duration
        $sig = preg_replace('/\\bas directed/i', '', $sig); //< Diriection. As Directed was triggering a 2nd duration

        $sig = preg_replace('/(\d+)([a-z]+)/i', '$1 $2', $sig); // Add a space when needed (1tablet => 1 tablet)
        $sig = preg_replace('/([a-z]+)(\d+)/i', '$1 $2', $sig); // Add a space when needed (Take1 => Take 1)

        $sig = preg_replace('/ +(mc?g)\\b| +(ml)\\b/i', '$1$2', $sig);   //get rid of extra spaces
        $sig = preg_replace('/[\w ]*replaces[\w ]*/i', '$1', $sig); //Take 2 tablets (250 mcg total) by mouth daily. This medication REPLACES Levothyroxine 112 mcg",

        //Interpretting as 93 qty per day. Not sure if its best to just get rid of it here or fix the issue further down
        $sig = preg_replace('/ 90 days?$/i', '', $sig); //TAKE 1 CAPSULE(S) 3 TIMES A DAY BY ORAL ROUTE AS NEEDED. 90 days

        $sig = preg_replace('/xdaily/i', ' times per day', $sig);

        // Split with commas "then" so that durations/frequencies/dosages are recognized separately by AWS CH
        $sig = preg_replace('/(?<!,) then /i', ', then ', $sig);

        return $sig;
    }

    private function preprocessing_number_substitution($sig) {
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
    
        $sig = preg_replace('/\\b(\d+)?( ?& ?)?(\.5|-?1 ?\/2|1-half|1 half)\\b/i', '$1.5', $sig); //Take 1 1/2 tablets
        $sig = preg_replace('/(\d+) .5\\b/i', '$1.5', $sig);
        $sig = preg_replace('/(^| )(\.5|1\/2|1-half|1 half|half a)\\b/i', ' 0.5', $sig);


        //Take First (Min?) of Numeric Ranges, except 0.5 to 1 in which case we use 1
        $sig = preg_replace('/\\b0.5 *(or|to|-) *1\\b/i', '1', $sig); //Special case of the below where we want to round up rather than down
        // $sig = preg_replace('/\\b([0-9]*\.[0-9]+|[1-9][0-9]*) *(or|to|-) *([0-9]*\.[0-9]+|[1-9][0-9]*)\\b/i', '$1', $sig); //Take 1 or 2 every 3 or 4 hours. Let's convert that to Take 1 every 3 hours (no global flag).  //Take 1 capsule by mouth twice a day as needed Take one or two twice a day as needed for anxiety

        return $sig;
    }

    private function _replace_time_interval($sig, $word, $time_in_days) {
        for ($x = 1; $x <= 15; $x++) {
            $regex = '/\\bfor '.$x.' '.$word.'?|'.$word.'? \d+/i';
            $replace = 'for '.$time_in_days*$x.' days';
            $sig = preg_replace($regex, $replace, $sig);
        }
        return $sig;
    }

    private function preprocessing_duration_substitution($sig) {

        $sig = preg_replace('/\\bx ?(\d+)\\b/i', 'for $1', $sig); // X7 Days == for 7 days

        $sig = preg_replace('/\\bon the (first|second|third|fourth|fifth|sixth|seventh) day/i', 'for 1 days', $sig);

        // Normalizes the intervals to days. "for 3 months" => "for 90 days"
        $sig = $this->_replace_time_interval($sig, "doses", 1);
        $sig = $this->_replace_time_interval($sig, "months", 30);
        $sig = $this->_replace_time_interval($sig, "weeks", 7);

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

        $weekdays = 'mondays?|mon|tuesdays?|tues?|wed|wednesdays?|thursdays?|thur?s?|fridays?|fri|saturdays?|sat|sundays?|sun';
        $sig = preg_replace('/\\b(\d) days? a week\\b/i', '$1 times per week', $sig);
        $sig = preg_replace('/\\b('.$weekdays.')[, &]*('.$weekdays.')[, &]*('.$weekdays.')\\b/i', '3 times per week', $sig);
        $sig = preg_replace('/\\b('.$weekdays.')[, &]*('.$weekdays.')\\b/i', '2 times per week', $sig);
        $sig = preg_replace('/\\b('.$weekdays.')\\b/i', '1 time per week', $sig);

        //echo "6 $sig";

        $sig = preg_replace('/\\bmonthly\\b/i', 'per month', $sig);

        $sig = preg_replace('/\\b(breakfast|mornings?)[, & @]*(dinner|night|evenings?|noon)\\b/i', '2 times per day', $sig);
        $sig = preg_replace('/\\b(1 (in|at) )?\d* ?(am|pm)[, &]*(1 (in|at) )?\d* ?(am|pm)\\b/i', '2 times per day', $sig); // Take 1 tablet by mouth twice a day 1 in am and 1 at 3pm was causing issues
        $sig = preg_replace('/\\b(in|at) \d\d\d\d?[, &]*(in|at)?\d\d\d\d?\\b/i', '2 times per day', $sig); //'Take 2 tablets by mouth twice a day at 0800 and 1700'
        $sig = preg_replace('/\\b(with)?in (a )?\d+ (minutes?|days?|weeks?|months?)\\b|/i', '', $sig); // Remove "in 5 days|hours|weeks" so that we don't confuse frequencies
        $sig = preg_replace('/\\b(\d+ (in |at )??(am|pm|noon|p|mn|hr))(,| )/i', '', $sig);

        $time_of_day = 'evenings? |mornings? |afternoon |noon |night ';
        $sig = preg_replace('/\\b(every|in the) ('.$time_of_day.')[, &]*('.$time_of_day.')[, &]*('.$time_of_day.')/i', '3 times per day ', $sig);
        $sig = preg_replace('/\\b(every|in the) ('.$time_of_day.')[, &]*('.$time_of_day.')/i', '2 times per day ', $sig);
        $sig = preg_replace('/\\b(every|in the) ('.$time_of_day.')/i', '1 time per day ', $sig);
        // $sig = preg_replace('/\\b('.$time_of_day.')/i', '$1, ', $sig);

        // $sig = preg_replace('/\\bevery +5 +min\w*/i', '3 times per day', $sig); //Nitroglycerin

        //echo "7 $sig";
        //Latin and Appreviations
        $sig = preg_replace('/\\bSUB-Q\\b/i', 'subcutaneous', $sig);
        $sig = preg_replace('/\\bBID\\b/i', '2 times per day', $sig);
        $sig = preg_replace('/\\bTID\\b/i', '3 times per day', $sig);
        $sig = preg_replace('/\\bQID\\b/i', '4 times per day', $sig);
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

    private function preprocessing_alternative_units($sig) {
        $sig = preg_replace('/\\b4,?000ml\\b/i', '1', $sig); //We count Polyethylene Gylcol (PEG) as 1 unit not 4000ml.  TODO Maybe replace this rule with a more generalized rule?
        $sig = preg_replace('/\\b1 vial\\b/i', '3ml', $sig);
        $sig = preg_replace('/\\b2 vials?\\b/i', '6ml', $sig);
        $sig = preg_replace('/\\b3 vials?\\b/i', '9ml', $sig);
        $sig = preg_replace('/\\b4 vials?\\b/i', '12ml', $sig);
        $sig = preg_replace('/\\b5 vials?\\b/i', '15ml', $sig);
        $sig = preg_replace('/\\b6 vials?\\b/i', '18ml', $sig);

        return $sig;
    }

    private function _delete_all_after($sig, $regexp, $unless_exp = null) {
        preg_match($regexp, $sig, $match, PREG_OFFSET_CAPTURE);
        if ($match) {
            if ($unless_exp AND preg_match($unless_exp, substr($sig, $match[0][1]))) {
                return $sig;
            }
            $sig = substr($sig, 0, $match[0][1]);
        }
        return $sig;
    }

    private function preprocessing_alternative_wording($sig) {
        $sig = preg_replace('/\\bin (an|\d) hours?/i', '', $sig); //Don't catch "in an hour" from "Take 2 tablets by mouth as needed of gout & 1 more in an hour as needed"
        $sig = preg_replace('/\\bin \d+ minutes?/i', '', $sig);   //Don't use "in 10 minutes" for the frequency
        $sig = preg_replace('/\\b(an|\d) hours? later/i', '', $sig); //Don't catch "in an hour" from "Take 2 tablets by mouth as needed of gout & 1 more in an hour as needed"
        $sig = preg_replace('/\\b\d+ minutes? later/i', '', $sig);   //Don't use "in 10 minutes" for the frequency

        $sig = preg_replace('/\\bInject \d+ units?\\b/i', 'Inject 1', $sig); //INJECT 18 UNITS
        $sig = preg_replace('/\\bInject [.\d]+ *ml?\\b/i', 'Inject 1', $sig); //Inject 0.4 mL (40 mg total) under the skin daily for 18 days.
        $sig = preg_replace('/\\b\d+ units?(.*?subcutan)|\\b(subcutan.*?)\d+ units?\\b/i', 'Inject 1 $1$2', $sig); // "15 units at bedtime 1 time per day Subcutaneous 90 days":

        // Delete everything after the first ocurrance of "total of"
        $sig = $this->_delete_all_after($sig, '/total of/i', '/(day|week)/i');

        // Delete everything after the first ocurrance of "max"
        $sig = $this->_delete_all_after($sig, '/ max/i', '/(day|week)/i');
    
        // Delete everything after the first ocurrance of "total"
        $sig = $this->_delete_all_after($sig, '/ total/i', '/(day|week)/i');
    
        // Delete everything after the first ocurrance of "may repeat"
        $sig = $this->_delete_all_after($sig, '/may repeat /i');

        // Delete everything after the first ocurrance of "hold" (it's usually about another sig/prescription)
        $sig = $this->_delete_all_after($sig, '/hold /i');

        // Delete everything after the first ocurrance of "combination" (it's usually about another sig/prescription)
        $sig = $this->_delete_all_after($sig, '/combination/i');

        // Delete everything after the first ocurrance of "with" and a number
        $sig = $this->_delete_all_after($sig, '/ with.?(\d)/i');

        // Delete everything after the first ocurrance of "up to" (to ignore both total allowed per day and increases in dosages)
        $sig = $this->_delete_all_after($sig, '/ up.?to/i', '/(day|week)/i');
    
        // Delete everything after the first ocurrance of "extra" (to ignore extra dosages)
        $sig = $this->_delete_all_after($sig, '/ extra (\d+)/i');

        // Delete everything after the first ocurrance of ", as needed" (to prevent additional splits)
        $sig = $this->_delete_all_after($sig, '/, ?(as )?(needed|directed)/i', '/(day|week)/i');

        // Insert the word "unit" so that AWS CH gives a result
        $sig = preg_replace('/\\b(\d+) (ORAL|per|\d+|as directed|as needed)\\b/i', '$1 unit $2', $sig);

        $sig = trim($sig);

        // Insert the word "take" so that AWS CH gives a result
        $sig = preg_replace('/\\b^(\d+)\\b/i', 'Take $1', $sig);
    
        return $sig;
    }
}


/**
 * Exception thrown when the AWS Comprehend Medical request fails
 */
class SigParserAWSComprehendException extends \Exception
{

}

/**
 * Exception thrown when the sig to parsed is invalid
 */
class SigParserMalformedInputException extends \Exception
{

}

/**
 * Exception thrown when an unexpected error ocurred
 */
class SigParserUnexpectedException extends \Exception
{

}

?>
