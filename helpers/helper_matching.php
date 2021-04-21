<?php
require_once 'exports/export_wc_patients.php';
require_once 'helpers/helper_laravel.php';

use GoodPill\Logging\GPLog;
use GoodPill\Logging\AuditLog;
use GoodPill\Logging\CliLog;
use GoodPill\Models\GpPatient;


//TODO Implement Full Matching Algorithm that's in Salesforce and CP's SP
function is_patient_match($patient)
{

    $mysql = new Mysql_Wc();

    // If there is a Carepoint Id or a WC_id
    // we should load the patient by them
    $patient_identifiers = [];

    if (!empty($patient['patient_id_cp'])) {
        $patient_identifiers[] = ['patient_id_cp', '=', $patient['patient_id_cp']];
    }

    if (!empty($patient['patient_id_wc'])) {
        $patient_identifiers[] = ['patient_id_wc', '=', $patient['patient_id_wc']];
    }

    $gpPatient = GpPatient::where($patient_identifiers)->first();

    // We found an already mapped Patient, Lets stop looking
    // and return those details
    if ($gpPatient && $gpPatient->isMatched()) {
        GPLog::debug(
            "is_patient_match:  Found an existing matching patient based on the details provided",
            [
                'patient_id_cp' => $gpPatient->patient_id_cp,
                'patient_id_wc' => $gpPatient->patient_id_wc,
                'patient'       => $patient
            ]
        );
        return [
            'patient_id_cp' => $gpPatient->patient_id_cp,
            'patient_id_wc' => $gpPatient->patient_id_wc,
            'new'           => false
        ];
    }

    // Lets use the details from above to find our patient match
    $patient_cp = find_patient($mysql, $patient);
    $patient_wc = find_patient($mysql, $patient, 'gp_patients_wc');

    // There was only one of each one, so we can match these patients
    if (count($patient_cp) == 1 and count($patient_wc) == 1) {
        GPLog::debug(
            "is_patient_match:  Found a patient match",
            [
                'patient_id_cp' => $patient_cp[0]['patient_id_cp'],
                'patient_id_wc' => $patient_wc[0]['patient_id_wc'],
                'patient'       => $patient
             ]
        );

        return [
            'patient_id_cp' => $patient_cp[0]['patient_id_cp'],
            'patient_id_wc' => $patient_wc[0]['patient_id_wc'],
            'new'           => true
        ];
    }

    // If we have more than one match in either array, lets look through them and remove any matches
    // that already have wc and cp ids.  These are previously matched and should need to be matched
    // again
    if (count($patient_cp) > 1) {
        foreach ($patient_cp as $key => $patient) {
            if (!empty($patient['patient_id_cp']) && !empty($patient['patient_id_wc'])) {
                unset($patient_cp[$key]);
            }
        }
    }

    if (count($patient_wc) > 1) {
        foreach ($patient_wc as $key => $patient) {
            if (!empty($patient['patient_id_cp']) && !empty($patient['patient_id_wc'])) {
                unset($patient_wc[$key]);
            }
        }
    }

    $patient_cp = array_values($patient_cp);
    $patient_wc = array_values($patient_wc);

    // There is only one of each one, so we can match these patients
    if (count($patient_cp) == 1 and count($patient_wc) == 1) {
        GPLog::critical(
            sprintf(
                "is_patient_match: Found multiple patients that matche criteria %s %s %s,
                    but only one that wasn't matched.  We are using it, but the match may
                    be in error",
                @$patient[0]['first_name'],
                @$patient[0]['last_name'],
                @$patient[0]['birth_date']
            ),
            [
                'patient_cp' => $patient_cp,
                'patient_wc' => $patient_wc,
                'patient'    => $patient,
                'todo'       => "Add salesforce alert with the details of the patient"
            ]
        );


        return [
            'patient_id_cp' => $patient_cp[0]['patient_id_cp'],
            'patient_id_wc' => $patient_wc[0]['patient_id_wc'],
            'new'           => true
        ];
    }

    $alert = [
        'todo'              => "TODO Auto Delete Duplicate Patient AND Send Patient Comm of their login and password",
        'patient'           => $patient,
        'count(patient_cp)' => count($patient_cp),
        'count(patient_wc)' => count($patient_wc),
        'patient_cp'        => $patient_cp,
        'patient_wc'        => $patient_wc
    ];

    if (count($patient_cp) == 0) {
        $message = "We didn't find a matching Carepoint patient AND";
    } elseif (count($patient_cp) > 1) {
        $message = "We Found too many Carepoint patients AND";
    } else {
        $message = "Found the Carepoint patient AND";
    }

    if (count($patient_wc) == 0) {
        $message .= " We didn't find a matching WooCommerce patient.";
    } elseif (count($patient_wc) > 1) {
        $message .= " We found too many WooCommerce patients.";
    } else {
        $message .= " We found the WooCommerce patient.";
    }

    //TODO Auto Delete Duplicate Patient AND Send Comm of their login and password

    GPLog::critical(
        sprintf(
            "Couldn't find a Carepoint and WooCommerce Match. %s
            Most frequently this is a false message because the carepoint
            user hasn't been imported yet",
            $message
        ),
        $alert
    );
}

//TODO Implement Full Matching Algorithm that's in Salesforce and CP's SP
function name_tokens($first_name, $last_name)
{
    $first_array = preg_split('/ |-/', $first_name);

    //Ignore first part of hypenated last names just like they are double last names
    $last_array  = preg_split('/ |-/', $last_name);

    $first_name_token = substr(array_shift($first_array), 0, 3);
    $last_name_token  = array_pop($last_array);

    return ['first_name_token' => $first_name_token, 'last_name_token' => $last_name_token];
}

//TODO Implement Full Matching Algorithm that's in Salesforce and CP's SP
//Table can be gp_patients / gp_patients_wc / gp_patients_cp
function find_patient($mysql, $patient, $table = 'gp_patients')
{
    $tokens = name_tokens($patient['first_name'], $patient['last_name']);

    $first_name_token = escape_db_values($tokens['first_name_token']);
    $last_name_token  = escape_db_values($tokens['last_name_token']);

    $sql = "SELECT *
            FROM {$table}
            WHERE
              first_name LIKE '{$first_name_token}%'
              AND REPLACE(last_name, '*', '') LIKE '%{$last_name_token}'
              AND birth_date = '{$patient['birth_date']}'";

    if (! $first_name_token or ! $last_name_token or ! $patient['birth_date']) {
        log_error('export_wc_patients: find_patient. patient has no name!', [$sql, $patient]);
        return [];
    }

    GPLog::debug('export_wc_patients: find_patient', [$sql, $patient]);

    $res = $mysql->run($sql)[0];

    // Before we return multiple names, lets loop over and see if we can remove
    // items that don't match exatly
    //
    $exact_matches = [];
    if (count($res) > 1) {
        foreach ($res as $possible_match) {
            if (
                strtolower($patient['first_name']) == strtolower($possible_match['first_name'])
                && strtolower($patient['last_name']) == strtolower($possible_match['last_name'])
                && $patient['birth_date'] == $possible_match['birth_date']
            ) {
                $exact_matches[]  = $possible_match;
            }
        }
    }

    // If we have only one exact match, then return it
    if (count($exact_matches) == 1) {
        if (count($exact_matches) != count($res)) {
            GPLog::critical(
                'find_patient: Found multiple patients, but filtered down to one exact match',
                ['exact_matches' => $exact_matches, 'all_matches' => $res]
            );
        }

        return $exact_matches;
    }

    return $res;
}

/**
 * Create the association between the wp and the cp patient
 * this will overwrite a current association if it exists
 *
 * @param  array    $patient       The patient data
 * @param  int      $patient_id_cp The CP id for the patient
 * @param  bool     $force_match   Delete any previous WC matches and force this match
 * @return void
 */
function match_patient($patient_id_cp, $patient_id_wc, $force_match = false)
{

    $mysql = new Mysql_Wc();

    // See if there is already a patient with the cp_id in WooCommerce.
    // If there is, we need to log an alert and skip this step.
    // Update the patients table
    $patient_match = is_patient_matched_in_wc($patient_id_cp);


    if ($patient_match && @$patient_match['patient_id_wc'] != $patient_id_wc) {
        // If we are forcing this match, delete the other meta and log it
        if ($force_match) {
            //  A patient with a $patient_id_cp has a patient_id_wc that is not what we want.
            //  Delete any patient_id_cp entries in the meta
            //  Create 2 metas for the old patient_id_cp and one for the new patient_id_cp
            //  Old patient_id_cp is from the original `$patient_match`
            //  New patient_id_cp is the id we pass into the function `patient_id_cp`
            $mysql = GoodPill\Storage\Goodpill::getConnection();
            $pdo   = $mysql->prepare(
                "DELETE
                     FROM wp_usermeta
                     WHERE meta_key = 'patient_id_cp'
                        AND meta_value = :patient_id_cp");
            $pdo->bindValue(':patient_id_cp', $patient_id_cp, \PDO::PARAM_INT);
            $pdo->execute();

            //  Add the old patient_id_cp
            wc_upsert_patient_meta(
                $mysql,
                $patient_id_wc,
                'old_patient_id_cp',
                $patient_match['patient_id_cp']

            );

            //  Update the current patient_id_cp to the new value
            wc_upsert_patient_meta(
                $mysql,
                $patient_id_wc,
                'patient_id_cp',
                $patient_id_cp
            );

            GPLog::warning("A patient CP ID was force updated from {$patient_match['patient_id_wc']} to $patient_id_cp. There may be invoices to update",
                [
                    'patient_id_cp' => @$patient_match['patient_id_wc'],
                    'old_patient_id_cp' => $patient_id_cp,
                ]
            );

            //  Add more information
            //  Get the patient's info to construct a label
            $subject = "Forced Patient Match";
            $body = "patient_id_cp {$patient_match['patient_id_cp']} was updated to $patient_id_cp. Are there any invoices that need to be updated?";
            $salesforce = [
                "subject"   => $subject,
                "body"      => $body,
                "assign_to" => '.Testing',
            ];

            $message_as_string = implode('_', $salesforce);
            $notification = new \GoodPill\Notifications\Salesforce(sha1($message_as_string), $message_as_string);

            if (!$notification->isSent()) {
                GPLog::debug($subject, ['body' => $body]);

                create_event($body, [$salesforce]);
            } else {
                GPLog::warning("DUPLICATE Saleforce Message".$subject, ['body' => $body]);
            }

        } else {
            return GPLog::critical(
                "Attempted to match a CP patient that was already matched in WC meta",
                [
                    'patient_id_cp' => $patient_id_cp,
                    'proposed_patient_id_wc' => $patient_id_wc,
                    'existing_wc_meta_patient_id' => @$patient_match['patient_id_wc']
                ]
            );
        }
    }

    if (!$patient_match || $force_match) {

        $sql = "UPDATE
          gp_patients
        SET
          patient_id_cp = '{$patient_id_cp}',
          patient_id_wc = '{$patient_id_wc}'
        WHERE
          patient_id_cp = '{$patient_id_cp}' OR
          patient_id_wc = '{$patient_id_wc}'";

        $mysql->run($sql);

        GPLog::notice(
            sprintf(
                "Matched patient_id_cp:%s with patient_id_wc:%s %s",
                $patient_id_cp,
                $patient_id_wc,
                ($force_match) ? 'WITH FORCE' : ''
            ),
            [
                'patient_id_cp' => $patient_id_cp,
                'patient_id_wc' => $patient_id_wc,
                'force_match'   => $force_match
            ]
        );

        // Insert the patient_id_cp if it doesn't already exist
        wc_upsert_patient_meta(
            $mysql,
            $patient_id_wc,
            'patient_id_cp',
            $patient_id_cp
        );
    }
}

/**
 * Looks to see if there is already a patient matched for this cp id.
 * If a match is found return the matched ids
 *
 * @param  int  $patinent_cp_id Carepoint Patient ID
 * @return boolean|array                  Array of ids on matche, false on no match
 */
function is_patient_matched_in_wc($patient_id_cp)
{
    $mysql = GoodPill\Storage\Goodpill::getConnection();
    $pdo   = $mysql->prepare(
        "SELECT *
             FROM wp_usermeta
             WHERE meta_key = 'patient_id_cp'
                AND meta_value = :patient_id_cp"
    );
    $pdo->bindParam(':patient_id_cp', $patient_id_cp, \PDO::PARAM_INT);
    $pdo->execute();

    if ($meta = $pdo->fetch()) {
        return [
            'patient_id_cp' => $patient_id_cp,
            'patient_id_wc' => $meta['user_id']
          ];
    }

    return false;
}
