<?php
require_once 'exports/export_wc_patients.php';

use GoodPill\Logging\GPLog;
use GoodPill\Logging\AuditLog;
use GoodPill\Logging\CliLog;
use GoodPill\DataModels\GoodPillPatient;

//TODO Implement Full Matching Algorithm that's in Salesforce and CP's SP
function is_patient_match($mysql, $patient)
{
    // If there is a Carepoint Id or a WC_id
    // we should load the patient by them
    $patient_identifiers = [];

    if (!empty($patient['patient_id_cp'])) {
        $patient_identifiers['patient_id_cp'] = $patient['patient_id_cp'];
    }

    if (!empty($patient['patient_id_wc'])) {
        $patient_identifiers['patient_id_wc'] = $patient['patient_id_wc'];
    }

    $gpPatient = new GoodPillPatient($patient_identifiers);

    // We found an already mapped Patient, Lets stop looking
    // and return those details
    if ($gpPatient->isMatched()) {
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
            'patient_id_wc' => $gpPatient->patient_id_wc
        ];
    }

    // Lets use the details from above to find our patient match
    $patient_cp = find_patient($mysql, $patient);
    $patient_wc = find_patient($mysql, $patient, 'gp_patients_wc');

    // There was only one of each one, so we can match these patients
    if (count($patient_cp) == 1 and count($patient_wc) == 1) {
        GPLog::critical(
            "is_patient_match:  Found a patient match",
            [
                'patient_id_cp' => $patient_cp[0]['patient_id_cp'],
                'patient_id_wc' => $patient_wc[0]['patient_id_wc'],
                'patient'       => $patient
             ]
        );

        return [
            'patient_id_cp' => $patient_cp[0]['patient_id_cp'],
            'patient_id_wc' => $patient_wc[0]['patient_id_wc']
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
        GPLog::alert(
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
            'patient_id_wc' => $patient_wc[0]['patient_id_wc']
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

    //TODO Auto Delete Duplicate Patient AND Send Comm of their login and password

    GPLog::alert(
        sprintf(
            "helper_matching: is_patient_match FALSE %s %s %s",
            @$patient[0]['first_name'],
            @$patient[0]['last_name'],
            @$patient[0]['birth_date']
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
 * @param  Mysql_Wc $mysql         The GP Mysql Connection
 * @param  array    $patient       The patient data
 * @param  int      $patient_id_cp The CP id for the patient
 * @return void
 */
function match_patient($mysql, $patient_id_cp, $patient_id_wc)
{
    // See if there is already a patient with the cp_id in WooCommerce.
    // If there is, we need to log an alert and skip this step.
    // Update the patientes table
    $patient_match = is_patient_matched_in_wc($patient_id_cp);

    if (!$patient_match) {
        $sql = "UPDATE
          gp_patients
        SET
          patient_id_cp = '{$patient_id_cp}',
          patient_id_wc = '{$patient_id_wc}'
        WHERE
          patient_id_cp = '{$patient_id_cp}' OR
          patient_id_wc = '{$patient_id_wc}'";

        $mysql->run($sql);

        GPLog::notice("helper_matching: match_patient() matched patient_id_cp:$patient_id_cp
                         with patient_id_wc:$patient_id_wc");

        // Insert the patient_id_cp if it deosnt' already exist
        wc_upsert_patient_meta(
            $mysql,
            $patient_id_wc,
            'patient_id_cp',
            $patient_id_cp
        );
    } elseif (@$patient_match['patient_id_wc'] != $patient_id_wc) {
        GPLog::critical(
            "Attempted to match a CP patient that was already matched in WC",
            [
                'patient_id_cp' => $patient_id_cp,
                'patient_id_wc' => $patient_id_wc,
                'proposed_patient_id_wc' => @$patient_match['patient_id_wc']
            ]
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
