<?php

use Sirum\Storage\Goodpill;

/**
 * Get The details need to for a patient ID from the database using the invoice_number
 * @param  int $invoice_number The gp invoice number
 * @return array               Empty if no match found
 */
function getPatientByInvoice($invoice_number)
{

    $mysql = Goodpill::getConnection();
    $pdo   = $mysql->prepare(
        "SELECT birth_date, first_name, last_name
            FROM gp_orders o
                JOIN gp_patients p on o.patient_id_cp = p.patient_id_cp
            WHERE invoice_number = :invoice_number
            LIMIT 1;"
    );

    $pdo->bindParam(':invoice_number', $invoice_number, \PDO::PARAM_INT);
    $pdo->execute();

    if ($patient = $pdo->fetch()) {
        return $patient;
    }

    return [];
}

/**
 * Get The details need to for a patient ID from the database using the invoice_number
 * @param  int $rx_number The gp invoice number
 * @return array          Empty if no match found
 */
function getPatientByRx($rx_number)
{
    $mysql = Goodpill::getConnection();
    $pdo   = $mysql->prepare(
        "SELECT birth_date, first_name, last_name
            FROM gp_rxs_single rx
                JOIN gp_patients p on rx.patient_id_cp = p.patient_id_cp
            WHERE rx_number = :rx_number
            LIMIT 1;"
    );

    $pdo->bindParam(':rx_number', $rx_number, \PDO::PARAM_INT);
    $pdo->execute();

    if ($patient = $pdo->fetch()) {
        return $patient;
    }

    return [];
}

/**
 * Get The details need to for a patient ID from the database using the patient_id_cp
 * @param  int $patient_id_cp The Carepoint Id
 * @return array          Empty if no match found
 */
function getPatientByCpId($patient_id_cp)
{
    $mysql = Goodpill::getConnection();
    $pdo   = $mysql->prepare(
        "SELECT birth_date, first_name, last_name
            FROM gp_patients
            WHERE patient_id_cp = :patient_id_cp
            LIMIT 1;"
    );

    $pdo->bindParam(':patient_id_cp', $patient_id_cp, \PDO::PARAM_INT);
    $pdo->execute();

    if ($patient = $pdo->fetch()) {
        return $patient;
    }

    return [];
}

/**
 * Get The details need to for a patient ID from the database using the patient_id_wc
 * @param  int $patient_id_wc The WooCommerce id
 * @return array          Empty if no match found
 */
function getPatientByWcId($patient_id_wc)
{
    $mysql = Goodpill::getConnection();
    $pdo   = $mysql->prepare(
        "SELECT birth_date, first_name, last_name
            FROM gp_patients
            WHERE patient_id_wc = :patient_id_wc
            LIMIT 1;"
    );

    $pdo->bindParam(':patient_id_wc', $patient_id_wc, \PDO::PARAM_INT);
    $pdo->execute();

    if ($patient = $pdo->fetch()) {
        return $patient;
    }

    return [];
}
