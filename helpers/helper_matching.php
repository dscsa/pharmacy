<?php
require_once 'exports/export_wc_patients.php';
require_once 'helpers/helper_laravel.php';

use GoodPill\Logging\GPLog;
use GoodPill\Logging\AuditLog;
use GoodPill\Logging\CliLog;
use GoodPill\Models\GpPatient;
use Goodpill\Models\GpPatientsWc;
use GoodPill\Models\WordPress\WpUserMeta;


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
    // See if there is already a patient with the cp_id in WooCommerce.
    $patient_match = is_patient_matched_in_wc($patient_id_cp);


    if ($patient_match && @$patient_match['patient_id_wc'] != $patient_id_wc && !$force_match) {
        // If we are forcing this match, delete the other meta and log it
        return GPLog::critical(
            "Attempted to match a CP patient that was already matched in WC meta",
            [
                'patient_id_cp' => $patient_id_cp,
                'proposed_patient_id_wc' => $patient_id_wc,
                'existing_wc_meta_patient_id' => @$patient_match['patient_id_wc']
            ]
        );

    }

    if (!$patient_match || $force_match) {
        $forced_match_data = force_match($patient_id_cp, $patient_id_wc);

        GPLog::warning(
            "{$forced_match_data['patient_label']} was force updated with patient_id_cp: {$forced_match_data['patient_id_cp']} and patient_id_wc: {$forced_match_data['patient_id_wc']}. There may be invoices to update",
            [
                'patient_id_cp'               => @$patient_match['patient_id_wc'],
                'old_patient_id_cp'           => $patient_id_cp,
                'patients_marked_as_inactive' => $forced_match_data['invalidated_patients'],
                'forced_data'                 => $forced_match_data,
            ]
        );

        //  Add more information
        //  Get the patient's info to construct a label
        $subject = "Forced Patient Match";
        $body = "{$forced_match_data['patient_label']} was force updated with patient_id_cp: {$forced_match_data['patient_id_cp']} and patient_id_wc: {$forced_match_data['patient_id_wc']}. There may be invoices to update";
        $body .= "The patients that were marked inactive are : ";
        foreach($forced_match_data['invalidated_patients'] as $inactive_patient) {
            $body .= "{$inactive_patient['patient_id_wc']}, ";
        }
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

/**
 * Force updates a patient to specified cp and wc id
 * Will find any invalid meta keys and mark those users as invalid
 * Will insert a `patient_id_cp` meta key for the user if one cannot be found
 *
 * @param int $patient_id_cp
 * @param int $patient_id_wc
 * @return array
 */
function force_match(int $patient_id_cp, int $patient_id_wc) : array
{
    $invalidated_patients = [];
    $metas = WpUserMeta::where('meta_key', 'patient_id_cp')->where('meta_value', $patient_id_cp)->get();
    //  If there are meta keys found, loop through them to check that they are valid
    if ($metas->count() > 0)
    {
        $metas->each(function ($meta) use ($patient_id_wc, &$invalidated_patients) {
            //  If the CP id is not matched to the wc id we want, mark those users as invalid

            if ($meta->user_id !== $patient_id_wc) {
                //  Rewrite this meta key first
                $meta->meta_key = 'patient_id_cp_old';
                $meta->save();

                //  Find the existing patient and set them to `inactive`
                $patient_to_invalidate = GpPatientsWc::where('patient_id_wc', $meta->user_id)->first();

                $invalidated_patients[] = [
                    'patient_id_cp' => $patient_to_invalidate->patient_id_cp,
                    'patient_id_wc' => $patient_to_invalidate->patient_id_wc,
                    'patient' => $patient_to_invalidate->getPatientLabel()
                ];

                $patient_to_invalidate->patient_inactive = 'Inactive';
                $patient_to_invalidate->save();
                //$patient_to_mark->updateWcActiveStatus();

            }
        });
    }
    //  Check to see if there were no meta keys and none of the returned keys match the cp_id we want to set
    //  Insert the patient_id_cp into the meta table

    if (
        $metas
            ->where('meta_key', 'patient_id_cp')
            ->where('meta_value', $patient_id_cp)
            ->where('user_id', $patient_id_wc)
            ->count() === 0 ||
        $metas->count() === 0
    )
    {

        WpUserMeta::create([
            'user_id'    => $patient_id_wc,
            'meta_key'   => 'patient_id_cp',
            'meta_value' => $patient_id_cp,
        ]);

    }
    //  Doing a mass update could create an issue where two records get the same patient_id_cp primary key
    //  Instead opting to grab the first record returned and update that

    $patient_to_update = GpPatient::where('patient_id_cp', $patient_id_cp)
        ->orWhere('patient_id_wc', $patient_id_wc)
        ->first();

    $patient_to_update->patient_id_cp = $patient_id_cp;
    $patient_to_update->patient_id_wc = $patient_id_wc;
    $patient_to_update->save();


    return [
        'patient_label' => $patient_to_update->getPatientLabel(),
        'patient_id_cp' => $patient_to_update->patient_id_cp,
        'patient_id_wc' => $patient_to_update->patient_id_wc,
        'invalidated_patients' => $invalidated_patients,
    ];

}
