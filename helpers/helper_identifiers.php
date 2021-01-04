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
