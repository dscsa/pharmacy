<?php

namespace GoodPill\Traits;

trait ArrayModelStorage
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
     * Don't check the properties for validity.  Set this to false for an easy
     * way to force a message creation
     */
    public $check_property = true;

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
        if (is_callable(array($this, 'get' . ucfirst($property)))) {
            $func_name   ='get' . ucfirst($property);
            $func_return = $this->$func_name();
            return $func_return;
        }

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

        if (is_callable(array($this, 'set' . ucfirst($property)))) {
            $func_name ='set' . ucfirst($property);
            return $this->$func_name($value);
        }

        //Check to see if the property is a persistable field
        if ($this->check_property && !in_array($property, $this->properties)) {
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
        if (isset($this->data) && isset($this->data[$property])) {
            return isset($this->data[$property]);
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
                throw new \Exception($strRequiredField . 'Missing required fields');
                return false;
            }
        }

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
            if ($this->check_property && !in_array($strKey, $this->properties)) {
                throw new \Exception("{$strKey} not an allowed property");
            }

            $this->data[$strKey] = $mixValue;
        }
    }
}