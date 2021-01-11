<?php
namespace Sirum;

use Sirum\Storage\Goodpill;

/**
 * A simple model class for quickly accessing data.  Right now it only implements
 * read opperations. Write operations currently have to be implemented on the
 * individual classes
 */
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
    protected $fields = [];

    /**
     * Have we loaded any data from the database
     * @var array
     */
    protected $loaded = false;

    /**
     * I'm not dead yet.  I feel happy.
     */
    public function __construct($params = [])
    {
        $this->gpdb = Goodpill::getConnection();

        if (!empty($params)) {
            $this->load($params);
        }
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

        if (property_exists($this, $name)) {
            return $this->$name;
        }

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
        if (in_array($name, $this->fields)) {
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
            if (in_array($name, $this->fields)) {
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
        return in_array($name, $this->fields);
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
     * Load all of the data via parameters
     *
     * @param   string|array    $params     The primary key value or an
     *                                      array of property and values
     * @return array | false                was a record found
     */
    public function load($params)
    {
        $this->loaded = true;

        if (empty($this->table_name) || empty($this->fields)) {
            $this->log->error('The properties {table_name}, and {fields}
                must be defined before data can be loaded');
            throw new Exception('The properties {table_name}, and {field_desc}
                must be defined before data can be loaded');
        }

        $sql = 'SELECT ';
        $sql .= implode(', ', $this->fields);
        $sql .=  ' FROM ' . $this->table_name . ' WHERE ';

        foreach ($params as $property => $value) {
            if (is_null($params[$property])) {
                $sql .= $property . ' IS NULL AND ';
            } else {
                $sql .= $property . ' = :' . $property . ' AND ';
            }
        }

        $sql = trim($sql, 'AND ');

        if (is_array($params)) {
            asort($params);
        }

        $stmt = $this->gpdb->prepare($sql);

        foreach (array_keys($params) as $property) {
            if (is_int($params[$property])) {
                $stmt->bindParam(':'.$property, $params[$property], \PDO::PARAM_INT);
            } else {
                $stmt->bindParam(':'.$property, $params[$property], \PDO::PARAM_STR);
            }
        }

        $stmt->execute();

        if ($stmt->rowCount() > 1) {
            throw new \Exception('Parameters can only match one record.  Your parameters matched ' . $stmt->rowCount());
        }

        if ($stmt->rowCount() > 0) {
            $this->data = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->loaded = true;
        }

        return ( !empty($this->data) );
    }
}
