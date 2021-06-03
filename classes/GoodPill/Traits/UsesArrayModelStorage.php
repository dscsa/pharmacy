<?php

namespace GoodPill\Traits;

/**
 * Trait that creates an array based storage model for quick data definition and validation of required elements
 * Mostly used for sloppy models that can be buil quickly.
 */
trait UsesArrayModelStorage
{
    /**
     * An array to store data locally to the instance but not
     * attempting to save it.
     * @var array
     */
    protected $stored_data = [];

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
     * Don't check the properties for validity.  Set this to false for an easy
     * way to force a message creation
     * @var boolean
     */
    public $check_property = true;

    /**
     * The magic getter that will return the data from the $data array.  The getter looks to
     * see if a get_ function exists before retrieving the data.  If the model does have a get_ function
     * that function is used instead of the default logic
     *
     * @param  string $property The name of the property.
     *
     * @return mixed Whatever data is stored for the property
     */
    public function &__get(string $property)
    {
        if (is_callable(array($this, 'get' . ucfirst($property)))) {
            $func_name   ='get' . ucfirst($property);
            $func_return = $this->$func_name();
            return $func_return;
        }

        if (!isset($this->stored_data[$property])) {
            $this->stored_data[$property] = null;
        }

        return $this->stored_data[$property];
    }

    /**
     * The magic setter will store the data if its a field that can be saved.  The setter looks to
     * see if a set_ function exists before storing the data.  If the model does have a set_ function
     * that function is used instead of the default logic
     *
     * @param string $property The name of the property.
     * @param mixed  $value    The value to set.
     *
     * @return mixed
     *
     * @throws \Exception When a property that is not defined is used and check_property is TRUe.
     */
    public function __set(string $property, $value)
    {
        if (property_exists($this, $property)) {
            return $this->$property = $value;
        }

        if (is_callable(array($this, 'set' . ucfirst($property)))) {
            $func_name ='set' . ucfirst($property);
            return $this->$func_name($value);
        }

        //Check to see if the property is a persistable field
        if ($this->check_property && !in_array($property, $this->properties)) {
            throw new \Exception("{$property} not an allowed property");
        }

        return $this->stored_data[$property] = $value;
    }

    /**
     * Implement tne magic metod to check if it isset the data
     *
     * @param string $property The name of the property to check.
     * @return boolean Is the property set
     */
    public function __isset(string $property) : bool
    {
        if (isset($this->stored_data) && isset($this->stored_data[$property])) {
            return isset($this->stored_data[$property]);
        }

        return false;
    }

    /**
     * An easy method for accessing the data in the array
     *
     * @param array $fields A (Optional) list of field_names that you can use
     *    to filter the returned array.
     *
     * @return array
     */
    public function toArray(array $fields = [])
    {
        if (empty($fields) || !is_array($fields)) {
            return $this->stored_data;
        } else {
            return array_intersect_key($this->stored_data, array_flip($fields));
        }
    }

    /**
     * Get a JSON string of the object
     *
     * @param boolean $pretty Set to true if json should be encoded with JSON_PRETTY_PRINT.
     *
     * @return string The data JSON encoded
     *
     * @throws \Exception A field defined as required was not set.
     */
    public function toJSON(bool $pretty = false) : string
    {

        // Check to make sure all the required fields have a value.  Throw an
        // exception if a value is missing
        if (! $this->requiredFieldsComplete()) {
            throw new \Exception('Missing required fields');
        }

        $flags = ($pretty) ? JSON_PRETTY_PRINT : 0;

        return json_encode($this->stored_data, $flags);
    }

    /**
     * Check to see if the required fields have been completed
     * @return boolean True if all required fields are complete
     * @throws \Exception A field defined as required was not set.
     */
    protected function requiredFieldsComplete() : bool
    {
        foreach ($this->required as $strRequiredField) {
            if (! isset($this->stored_data[$strRequiredField])) {
                throw new \Exception($strRequiredField . 'Missing required fields');
                return false;
            }
        }

        return true;
    }

    /**
     * Take a json string and load the data
     * @param  string $strJSON A JSON encoded string to load.
     * @return boolean Was it a success
     */
    public function fromJSON(string $strJSON) : bool
    {
        $arrJSONData = (array) json_decode($strJSON);
        return $this->fromArray($arrJSONData);
    }


    /**
     * Load the data from an array.  Verify the the property isan
     * allowed property.
     *
     * @param  array $arrData An array of data.
     * @throws \Exception An array was not passed into the syste.
     * @return void
     */
    public function fromArray(array $arrData)
    {
        foreach ($arrData as $strKey => $mixValue) {
            if ($this->check_property && !in_array($strKey, $this->properties)) {
                throw new \Exception("{$strKey} not an allowed property");
            }

            $this->stored_data[$strKey] = $mixValue;
        }
    }
}
