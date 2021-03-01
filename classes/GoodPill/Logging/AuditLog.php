<?php

namespace GoodPill\Logging;

use GoodPill\Logging\{
    GPLog,
    AuditLog,
    CliLog
};
use Google\Cloud\Logging\LoggingClient;

require_once 'helpers/helper_identifiers.php';

/**
 * This is a simple logger that maintains a single instance of the cloud $logger
 * This should not be stored in other code, but for the short term it's here.
 */
class AuditLog
{

  /**
   * Property to store the logger for reuse.  shoudl be a PSR-3 compatible logger
   */
    public static $logger;

    /**
     * The application ID for the google logger
     * @var string
     */
    public static $application_id = 'pa-audit';

    /**
     * Function to add an item to the audit log in google cloud
     * @param  string $message              The message to display
     * @param  array  $orderitem_or_patient The item that the message is about
     *      This can be an order item or a patien.  And entire order will cause
     *      and error
     * @return void
     */
    public static function log($message, $orderitem_or_patient)
    {

        // Make sure we have a logger
        if (!isset(self::$logger)) {
            self::getLogger();
        }

        /* Set the values based on what we should have */
        $context = [
            'birth_date' => @$orderitem_or_patient['birth_date'],
            'last_name'  => @$orderitem_or_patient['last_name'],
            'first_name' => @$orderitem_or_patient['first_name']
        ];


        if (@$orderitem_or_patient['invoice_number']) {
            $context['invoice_number'] = @$orderitem_or_patient['invoice_number'];
        }

        //If we have an invoice but not a patient, we want to get those details
        if (empty($context['birth_date']) &&
            @$orderitem_or_patient['invoice_number']) {
            $patient = getPatientByInvoice($orderitem_or_patient['invoice_number']);

            if (!empty($patient)) {
                $context = [
                    'birth_date' => $patient['birth_date'],
                    'last_name'  => $patient['last_name'],
                    'first_name' => $patient['first_name'],
                    'invoice_number' => $orderitem_or_patient['invoice_number']
                ];
            }
        }

        // If we still don't have a birthdate, try the Carepoint Id
        if (empty($context['birth_date']) &&
            @$orderitem_or_patient['patient_id_cp']) {
            $patient = getPatientByCpId($orderitem_or_patient['patient_id_cp']);

            if (!empty($patient)) {
                $context = [
                    'birth_date' => $patient['birth_date'],
                    'last_name'  => $patient['last_name'],
                    'first_name' => $patient['first_name'],
                    'invoice_number' => @$orderitem_or_patient['invoice_number']
                ];
            }
        }

        // If we still don't have a birthdate, try the WooCmmerce Id
        if (empty($context['birth_date']) &&
            @$orderitem_or_patient['patient_id_wc']) {
            $patient = getPatientByWcId($orderitem_or_patient['patient_id_wc']);

            if (!empty($patient)) {
                $context = [
                    'birth_date' => $patient['birth_date'],
                    'last_name'  => $patient['last_name'],
                    'first_name' => $patient['first_name'],
                    'invoice_number' => @$orderitem_or_patient['invoice_number']
                ];
            }
        }



        $context['execution_id'] = GPLog::$exec_id;

        if (!is_null(GPLog::$subroutine_id)) {
            $context['subroutine_id'] = GPLog::$subroutine_id;
        }

        try {
            self::$logger->info($message, $context);
        } catch (\Exception $e) {
            // The logger is broken.  We need to recycle it.
            self::$logger->flush();
            self::resetLogger();
        }
    }

    /**
     * Rebuild the logger.  Sometimes an error can cause the logger
     * to stop working.  This should trash the current logger and crated a new
     * logger object
     *
     * @return void
     */
    public static function resetLogger()
    {
        self::$logger = null;
        return self::getLogger(self::$application_id, self::$exec_id);
    }

    /**
     * A method to load an store the logger.  We will create a logging instance
     * and a execution id.
     *
     * @param  string $application The name of the application
     * @param  string $execution   (Optional) An id to group the log entries.  If
     *    one isn't passed, we will create one
     *
     * @return LoggingClient  A PSR-3 compatible logger
     */
    public static function getLogger()
    {
        if (!isset(self::$logger) or is_null(self::$logger)) {
            $logging  = new LoggingClient(['projectId' => 'unified-logging-292316']);

            self::$logger = $logging->psrLogger(
                self::$application_id,
                [
                    'batchEnabled' => true,
                    'batchOptions' => [
                        'batchSize' => 100,
                        'callPeriod' => 2.0,
                        'numWorkers' => 2
                    ]
                ]
            );
        }

        return self::$logger;
    }
}
