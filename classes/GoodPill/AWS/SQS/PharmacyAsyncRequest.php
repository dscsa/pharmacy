<?php

namespace  GoodPill\AWS\SQS;

use GoodPill\AWS\SQS\Request;

class PharmacyAsyncRequest extends Request
{
    protected $properties = [
        'changes_to',
        'changes',
        'execution_id'
    ];

    protected $required = [
        'changes_to',
        'changes'
    ];

    /**
     * Set the method to the default value for a delete request
     * @param object $request  (Optional) The initial data
     */
    public function __construct($request)
    {
        parent::__construct([
            "MD5OfBody" => $request["MD5OfBody"],
            "Body" => $request["Body"],
        ]);
    }

    /**
     * Use a getter so we can uncompress the data on the way out of the object.
     * @return null|array null if the data isn't set, otherwise an array.
     */
    public function getChanges() : ?array
    {
        if (isset($this->data['changes'])) {
            return unserialize(
                gzuncompress(
                    base64_decode(
                        ($this->data['changes'])
                    )
                )
            );
        }

        return null;
    }

    public function sanitizeMessage() {
        if (isset($this->message_id)) {
            echo "clearing message id";
        }
    }
}
