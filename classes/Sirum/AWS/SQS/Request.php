<?php

namespace  Sirum\AWS\SQS;

abstract class Request
{

    /**
     * An array to store data locally to the instance but not
     * attempting to save it.
     * @var array
     */
    protected $data = [];

    /**
     * The name of all allowed properties
     * @var array
     */
    protected $properties = [];

    /**
     * The list of the required properties
     * @var array
     */
    protected $required = [];

    /**
     * A string that should represent a group so requests are proccessed
     * in a specific order.  When this is
     * @var string
     */
    protected $group_id;

    /**
     * dedupe_id
     * @var [type]
     */
    protected $dedup_id;

    protected $receipt_handle;

    protected $message_id;

    /**
     * I'm not dead yet.  I feel happy.
     */
    public function __construct($initialize_date = null)
    {
        $this->dedup_id = uniqid();

        if (is_array($initialize_date)) {
            $this->fromSQS($initialize_date);
        } else if (is_string($initialize_date)) {
            $this->fromJSON($initialize_date);
        }
    }

    /**
     * The magic getter that will return the data from the $data array.  The getter looks to
     * see if a get_ function exists before retrieving the data.  If the model does have a get_ function
     * that function is used instead of the default logic
     *
     * @param  string $property The name of the property
     *
     * @return mixed Whatever data is stored for the property
     */
    public function &__get($property)
    {
        if (!isset($this->data[$property])) {
            $this->data[$property] = null;
        }

        return $this->data[$property];
    }

    /**
     * The magic setter will store the data if its a field that can be saved.  The setter looks to
     * see if a set_ function exists before storing the data.  If the model does have a set_ function
     * that function is used instead of the default logic
     *
     * @param string $property The name of the property
     * @param mixed  $value     The value to set
     *
     * @return void
     */
    public function __set($property, $value)
    {
        if (property_exists($this, $property)) {
            return $this->$property = $value;
        }

        if (is_callable(array($this, 'set_'.$property))) {
            return $this->{'set_'.$property}($value);
        }

        // Check to see if the property is a persistable field
        if (! in_array($property, $this->properties)) {
            throw new \Exception("{$property} not an allowed property");
        }

        return $this->data[$property] = $value;
    }

    /**
     * Implement tne magic metod to check if it isset the data
     *
     * @param string $property The name of the property to check
     */
    public function __isset($property)
    {
        if (in_array($property, $this->properties)) {
            if (isset($this->data) && isset($this->data[$property])) {
                return isset($this->data[$property]);
            }
        }
        return false;
    }

    /**
     * An easy method for accessing the data in the array
     *
     * @param array $field_names (Optional) A list of field_names that you can use
     *    to filter the returned array
     *
     * @return array
     */
    public function toArray($fields = [])
    {
        if (empty($fields) || !is_array($fields)) {
            return $this->data;
        } else {
            return array_intersect_key($this->data, array_flip($fields));
        }
    }

    /**
     * Get a JSON string of the object
     * @return string The data JSON encoded
     */
    public function toJSON()
    {

        // Check to make sure all the required fields have a value.  Throw an
        // exception if a value is missing
        if (! $this->requiredFiledsComplete()) {
            throw new \Exception('Missing required fields');
        }

        if (! $this->arePropertiesValid()) {
            throw new \Exception('Invalid property values found');
        }

        return json_encode($this->data);
    }

    /**
     * Check to see if the required fields have been completed
     * @return bool True if all required fields are complete
     */
    protected function requiredFiledsComplete()
    {
        foreach ($this->required as $strRequiredField) {
            if (! isset($this->data[$strRequiredField])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Perform an logic neccessary to test field values
     *
     * @return boolean True if the tests have passed
     */
    protected function arePropertiesValid()
    {
        return true;
    }

    /**
     * Take a json string and load the data
     * @param  string $strJSON A JSON encoded string to load
     * @return bool Was it a success
     */
    public function fromJSON($strJSON)
    {
        $arrJSONData = json_decode($strJSON);
        return $this->fromArray($arrJSONData);
    }


    /**
     * Load the data from an array.  Verify the the property isan
     * allowed property.
     *
     * @param  array $arrData An array of data
     * @throws Expception An array was not passed into the syste
     * @return void
     */
    public function fromArray($arrData)
    {
        foreach ($arrData as $strKey => $mixValue) {
            if (! in_array($strKey, $this->properties)) {
                throw new \Exception("{$strKey} not an allowed property");
            }

            $this->data[$strKey] = $mixValue;
        }
    }

    /**
     * Create an array of the data needed to delete an SQS message
     *
     * @return array       ['Id', 'ReceiptHandle']
     */
    public function toSQSDelete()
    {
        return [
            'Id'            => $this->message_id,
            'ReceiptHandle' => $this->receipt_handle
        ];
    }

    /**
     * Creat an SQS message that can be added to an sys Queue
     *
     * @return array
     */
    public function toSQS()
    {
        if (isset($this->message_id)) {
            throw new \Exception('This message has already been sent to SQS');
        }

        $SQSMessage = [
            'Id'                     => uniqid(),
            'MessageBody'            => $this->toJSON(),
            'MessageDeduplicationId' => $this->dedup_id
        ];

        if (isset($this->group_id)) {
            $SQSMessage['MessageGroupId'] = $this->group_id;
        }

        return $SQSMessage;
    }

    /**
     * Populate the data for the object form a SQS message
     * @param  array $message  A message from an SQS queue
     * @return boolean
     */
    public function fromSQS($message)
    {

        $this->receipt_handle = $message['ReceiptHandle'];
        $this->message_id     = $message['MessageId'];

        if (md5($message['Body']) != $message['MD5OfBody']) {
            throw new \Exception('The message body is malformed');
        }

        return $this->fromJSON($message['Body']);
    }

    /**
     * See if this message can be sent to a fifo Queue
     * @return boolean Does it have attributes neccessary to be a fifo message
     */
    public function isFifo()
    {
        return isset($this->group_id);
    }
}
