<?php

namespace Sirum\DataModels;

use Sirum\Storage\Goodpill;
use Sirum\GPModel;


use \PDO;
use \Exception;

/**
 * A class for loading Order data.  Right now we only have a few fields defined
 */
class GoodPillRxSingle extends GPModel
{
    /**
     * List of possible properties names for this object
     * @var array
     */
    protected $fields = [
        'rx_number',
        'patient_id_cp',
        'drug_generic',
        'drug_brand',
        'drug_name',
        'rx_message_key',
        'rx_message_text',
        'rx_gsn',
        'drug_gsns',
        'refills_left',
        'refills_original',
        'qty_left',
        'qty_original',
        'sig_actual',
        'sig_initial',
        'sig_clean',
        'sig_qty',
        'sig_days',
        'sig_qty_per_day_default',
        'sig_qty_per_day_actual',
        'sig_durations',
        'sig_qtys_per_time',
        'sig_frequencies',
        'sig_frequency_numerators',
        'sig_frequency_denominators',
        'rx_autofill',
        'refill_date_first',
        'refill_date_last',
        'refill_date_manual',
        'refill_date_default',
        'rx_status',
        'rx_stage',
        'rx_source',
        'rx_transfer',
        'rx_date_transferred',
        'provider_npi',
        'provider_first_name',
        'provider_last_name',
        'provider_clinic',
        'provider_phone',
        'rx_date_changed',
        'rx_date_expired'
    ];

    /**
     * The table name to store the notifications
     * @var string
     */
    protected $table_name = "gp_rxs_single";

}
