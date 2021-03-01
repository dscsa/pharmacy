<?php

namespace  GoodPill\AWS\SQS;

use GoodPill\AWS\SQS\Request;

/**
 * Base level class for all Google Doc requests
 */
class PharmacySyncRequest extends Request
{
    protected $properties = [
        'changes_to',
        'changes'
    ];

    protected $required = [
        'changes_to',
        'changes'
    ];

    /**
     * Set the method to the default value for a delete request
     * @param string $requests  (Optional) The initial data
     */
    public function __construct($request = null)
    {
        parent::__construct($request);
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

    /**
     * Use a setter so we can compress the data.  SQS is limited to 256Kb, so we want to
     * make sure we get it all in there
     * @param array $changes An array of the change data
     */
    public function setChanges(array $changes) : void
    {
        $this->data['changes'] = (
            base64_encode(
                gzcompress(
                    serialize($changes)
                )
            )
        );
    }
}
