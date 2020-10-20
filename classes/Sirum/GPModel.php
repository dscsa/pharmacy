<?php
namespace Sirum;

use Sirum\Storage\Goodpill;

class GPModel
{

    /**
     * The storage for the database object
     * @var PDO
     */
    protected $gpdb;
    /**
       * An array to hold the data pulled from the database and set
       * by the user
       * @var array
       */
    protected $data = [];

    /**
     * The list of fields that are allowed as properties
     * @var array
     */
    protected $field_names = [];

    /**
     * I'm not dead yet.  I feel happy.
     */
    public function __construct()
    {
        $this->gpdb = Goodpill::getConnection();
    }
    /**
     * Check to see if the data isset using the magic issetter
     *
     * @param  string  $name   The name of the field
     *
     * @return boolean  True if the $name isset in $this->data
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * Delete the value using the magic unsetter
     *
     * @param  string   $name   The name of the field
     *
     * @return void
     */
    public function __unset($name)
    {
        unset($this->data[$name]);
    }


    /**
     * This is a magic getter that will returne data in the $this->data array.
     * It will look for a getter function first if none is found it will store
     * it in the $thsi->data array
     *
     * ie:
     *    $var = $obj->firstName;
     *
     *    $var = $obj->get_firstName() will be called if it exists otherwise
     *    $var = $this->data['firstName'] will be used
     *
     * @param  string  $name   The name of the field
     *
     * @return mixed  Whatever is stored in the location in $this->data
     */
    public function __get($name)
    {
        if (!$this->isDefinedName($name)) {
            throw new \Exception("{$name} is not defined");
        }

        if (is_callable(array($this, 'get_'.$name))) {
            return $this->{'get_'.$name}();
        }

        if (isset($this->data) && isset($this->data[$name])) {
            return $this->data[$name];
        }
    }

    /**
     * This is a magic setter that will store data in the $this->data array.
     * It will look for a setter function first if none is found it will store
     * it in the $thsi->data array
     *
     * ie:
     *    $obj->firstName = "Ben";
     *
     *    function get_firstName("Ben") will be called if it exists otherwise
     *    $this->data['firstName'] = "Ben" will be used
     *
     * @param string $name   The name of the field
     * @param mixed  $value  The value to set
     *
     * @return void
     */
    public function __set($name, $value)
    {
        if (!$this->isDefinedName($name)) {
            throw new \Exception("{$name} is not defined");
        }

        if (is_callable(array($this, 'set_'.$name))) {
            return $this->{'set_'.$name}($value);
        }

        // Check to see if the property is a persistable field
        // and make sure it's not an object
        if (in_array($name, $this->field_names)) {
            $this->data[$name] = $value;
        }
    }

    /**
     * Convienence function for setting the data into the data array in bulk.
     * It will use the magic setter so we get full access to the set ladder stack.
     *
     * @param array $data The key=>value array to use to populate data.
     *
     * @return bool Did we set any values at all
     *
     */
    public function setDataArray($data = [])
    {
        foreach ($data as $name => $value) {
            if (in_array($name, $this->field_names)) {
                // use the setter so we take adavantage
                // of all features
                $this->__set($name, $value);
            }
        }

        return (!empty($this->data));
    }

    /**
     * Verify a Name has been defined in the model
     *
     * @param  string  $name The property to check
     *
     * @return boolean  Return true if the Name is defined in the $field_names array
     */
    public function isDefinedName($name)
    {
        return in_array($name, $this->field_names);
    }

    /**
     * An easy method for accessing the data in the array
     *
     * @param array $field_names (Optional) A list of field_names that you can use
     *    to filter the returned array
     *
     * @return array
     */
    public function toArray($field_names = [])
    {
        if (empty($field_names) || !is_array($field_names)) {
            return $this->data;
        } else {
            return array_intersect_key($this->data, array_flip($field_names));
        }
    }
}
