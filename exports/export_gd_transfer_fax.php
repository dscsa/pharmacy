<?php

require_once 'helpers/helper_appsscripts.php';

use GoodPill\Notifications\Salesforce;
use GoodPill\Logging\{
    GPLog,
    AuditLog,
    CliLog
};
use GoodPill\Notifications\Transfer;

function export_gd_transfer_fax($item, $source)
{
    if ( ! is_will_transfer($item)) {
      return;
    }

    if ($item['rx_transfer']) {
      return log_error("WebForm export_gd_transfer_fax NOT SENT, ALREADY TRANSFERRED $item[invoice_number] $item[drug_name] $source", get_defined_vars());
    }

    if ( ! $item['pharmacy_name']) {
        //If we don't have Pharmacy Fax, still send it and Rph can look it up. But if no pharmacy_name than an unregistered user so we don't know where to send it

        //  Check to see if there is an invoice number
        //  This can trigger on a rxs_single subroutine and might not be for an order yet
        if (isset($item['invoice_number'])) {
            $subject = 'Transfer Fax Error';
            $body = sprintf(
                "Problem with sending a transfer fax for order # %s,
            they do not have a pharmacy set to send to. This user probably has not registered in the
            patient portal yet and needs to be followed up with",
                @$item['invoice_number'],
            );
            $salesforce = [
                "subject"   => $subject,
                "body"      => $body,
                "assign_to" => '.Testing',
            ];

            $message_as_string = implode('_', $salesforce);
            $notification = new Salesforce(sha1($message_as_string), $message_as_string);

            if (!$notification->isSent()) {
                create_event($subject, [$salesforce]);
            } else {
                GPLog::warning("DUPLICATE Saleforce Message: ".$subject, ['body' => $body]);
            }

            $notification->increment();
            return GPLog::warning("WebForm export_gd_transfer_fax NOT SENT, NOT REGISTERED $item[invoice_number] $item[drug_name] $source", get_defined_vars());
        }

        return GPLog::notice("WebForm export_gd_transfer_fax NOT SENT, NOT REGISTERED $item[invoice_number] $item[drug_name] $source", get_defined_vars());
    }

    $to = '8882987726'; //$item['pharmacy_fax'] ?: '8882987726';

    $token = implode(
        '_',
        [
            $item['patient_id_cp'],
            $item['patient_id_wc'],
            @$item['invoice_number'],
            $item['drug_name'],
            $item['rx_transfer'],
            $source
        ]
    );

    // Create a hash for quicker compares
    $hash         = sha1($token);
    $notification = new Transfer($hash, $token);

    if (!$notification->isSent()) {
        GPLog::debug(
            "Transfer Fax Sent",
            [
                'invoice_number' => @$item['invoice_number'],
                'drug_number'    => $item['drug_name'],
                'rx_transfer'    => $item['rx_transfer'],
                'rx_message_key' => $item['rx_message_key'],
                'source'         => $source,
                'item'           => $item
            ]
        );

        $args = [
            'method'   => 'mergeDoc',
            'template' => 'Transfer Template v1',
            'file'     => "Transfer Out #".(@$item['invoice_number'])." Rx:$item[best_rx_number] Fax:$to",
            'folder'   => TRANSFER_OUT_FOLDER_NAME,
            'order'    => [$item]
         ];

        $result = gdoc_post(GD_MERGE_URL, $args);
    } else {
        GPLog::warning(
            "Duplicate Transfer Fax Sent",
            [
                'invoice_number' => @$item['invoice_number'],
                'drug_number'    => $item['drug_name'],
                'source'         => $source,
                'rx_transfer'    => $item['rx_transfer'],
                'rx_message_key' => $item['rx_message_key'],
                'item'           => $item
            ]
        );
    }

    $notification->increment();
}

function was_transferred($item) {
  if ($item['rx_message_key']  == 'NO ACTION WAS TRANSFERRED')
    return true;
}

function is_will_transfer($item) {
  if ($item['rx_message_key']  == 'NO ACTION WILL TRANSFER')
    return true;

  if ($item['rx_message_key'] == 'NO ACTION WILL TRANSFER CHECK BACK')
    return true;

  if($item['rx_message_key'] == 'NO ACTION NEW GSN')
    return true;
}
