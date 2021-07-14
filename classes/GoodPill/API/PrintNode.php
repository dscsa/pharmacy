<?php

namespace GoodPill\API;

use PrintNode\Credentials\ApiKey;
use PrintNode\Client;
use PrintNode\Entity\PrintJob;
use GoodPill\Logging\GPLog;

/**
 * A convenince class to reduce the effort needed to print a document to print node
 */
class PrintNode
{

    /**
     * The API key to access printnode.com
     * @var string
     */
    protected $api_key = PRINTNODE_API_KEY;

    /**
     * The default printer to use
     * @var string
     */
    protected $default_printer = PRINTNODE_DEFAULT_PRINTER;

    /**
     * Print a pdf from raw data. The function expects the PDF to
     * be passed in as data stored in a var
     * @param  string $data      Base64_encoded contents of the pdf to print.
     * @param  string $type      Either label or invoice.
     * @param  string $job_title The title to represent the job in printnode.  It is useful for
     *      this title to be unique to the content being printed.
     * @param  string $printer   The pringnode id of the printer.
     *
     * @return int               The print job id returned by printnode.
     */
    public function printPdf(
        string $data,
        string $type,
        string $job_title,
        ?string $printer = null
    ) : int {

        GPLog::debug("Sending {$job_title} to printnode for printing");

        switch (strtolower($type)) {
            case 'label':
                $options = [
                    "duplex" => "one-sided",
                    "bin"    => "Tray1"
                ];
                break;
            case 'invoice':
            default:
                $options = [
                    "duplex" => "short-edge",
                    "bin"    => "Tray3"
                ];
        }

        $printer                = ($printer) ?:$this->default_printer;
        $credentials            = new ApiKey($this->api_key);
        $client                 = new Client($credentials);
        $print_job              = new PrintJob($client);
        $print_job->title       = $job_title;
        $print_job->source      = 'pharmacy-app';
        $print_job->printer     = $printer;
        $print_job->contentType = 'pdf_base64';
        $print_job->content     = $data;
        $print_job->options     = $options;
        $job_id                 = $client->createPrintJob($print_job);

        return (int) $job_id;
    }
}
