<?php
namespace GoodPill;

use GoodPill\Storage\Goodpill;

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
     * The primary keys for this data type.  This will most frequently be a
     * single field but could be and array of multiple fields
     * @var string|array
     */
    protected $primary_key;

    /**
     * Have we loaded any data from the database
     * @var array
     */
    protected $loaded = false;

    /**
     * If this varilable contains anything, you cannot save the item.  The
     * varible should be set to a string explaining why you can't save the item
     * @var string
     */
    private $save_blocked;

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
        if (is_callable(array($this, 'get'.ucfirst($name)))) {
            return $this->{'get'.ucfirst($name)}();
        }

        if (property_exists($this, $name)) {
            return $this->$name;
        }

        if (!$this->isDefinedName($name)) {
            throw new \Exception("{$name} is not defined");
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
    public function __set($name, $value) : void
    {
        if (is_callable(array($this, 'set'.ucfirst($name)))) {
            $this->{'set'.ucfirst($name)}($value);
        }

        if (!$this->isDefinedName($name)) {
            throw new \Exception("{$name} is not defined");
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
    public function setDataArray(array $data = []) : bool
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
    public function isDefinedName(string $name) : bool
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
    public function toArray(array $fields = []) : array
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
     * @return boolean                was a record found
     */
    public function load(array $params) : bool
    {
        $this->loaded = false;

        if (empty($this->table_name) || empty($this->fields)) {
            $this->log->error('The properties {table_name}, and {fields}
                must be defined before data can be loaded');
            throw new Exception('The properties {table_name}, and {field_desc}
                must be defined before data can be loaded');
        }

        $sql = 'SELECT ';
        $sql .= implode(",\n", $this->fields);
        $sql .=  " FROM {$this->table_name}\n WHERE ";

        $where_conditions = $params;
        array_walk(
            $where_conditions,
            function (&$value, $property) {
                if (is_null($value)) {
                    $value = "{$property} IS NULL";
                } else {
                    $value = "{$property} = :{$property}";
                }
            }
        );

        $sql .= implode(
            ' AND ',
            $where_conditions
        );

        $stmt = $this->gpdb->prepare($sql);

        foreach ($params as $field => $value) {
            $bind_type = \PDO::PARAM_STR;

            // Check to see if it is an int, otherwise assume it is a string
            if (is_numeric($value)
                && $value == (int) $value) {
                $bind_type = \PDO::PARAM_INT;
            }

            $stmt->bindValue(":{$field}", $value, $bind_type);
        }

        $stmt->execute();

        if ($stmt->rowCount() > 1) {
            throw new \Exception('Parameters can only match one record.  Your parameters matched ' . $stmt->rowCount());
        }

        if ($stmt->rowCount() > 0) {
            if ($stmt->rowCount() > 1) {
                $this->save_blocked = "Loading data resulted in more than one match. "
                                    . " Saving via this data could cause corruption";
            }

            $this->data = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->loaded = true;
        }

        return (!empty($this->data));
    }


    /**
     * Persist data back to the database.  You must define a table, a set of
     * primary keys and some fields
     *
     * @param  array    $fields  (Optional) An array of fields to save if you
     *      don't want to save them all
     *
     * @return bool Did the save impact a single row?
     *
     * @throws Exception  You cannot use save on a model that isn't loaded
     * @throws Exception  The properties {table_name} and  {primary_key}
     * @throws Exception  Trying to save a document without a primary_key value
     */
    public function save(?array $fields = []) : bool
    {

        if (isset($this->save_blocked)) {
            throw new Exception("You cannot save this item:  {$this->save_blocked}");
        }

        if (!$this->loaded) {
            throw new Exception(
                "You cannot save a model until it has been loaded. "
                . "If you are trying to create an object, use ->create()"
            );
        }

        if (empty($this->table_name) || empty($this->primary_key)) {
            throw new Exception("The table name and the primary_key must be set");
        }

        $primary_keys = (is_array($this->primary_key) ? $this->primary_key : [ $this->primary_key ]);

        foreach ($primary_keys as $key) {
            if (!isset($this->data[$key])) {
                throw new Exception("The primary key must be have data set");
            }
        }

        // Use the default fields if non are specified
        $fields_to_save = (!empty($fields) ? $fields : $this->fields);

        $sql = "UPDATE {$this->table_name} SET ";

        // Don't bind the primary keys because we can't change those.
        // TODO but can't we.  There's nothing to say we can't, maybe
        // we shouldn't but that's a different question
        foreach ($fields_to_save as $field) {
            if (
                $field != in_array($field, $primary_keys)
                && in_array($field, $this->fields)
            ) {
                $sql .= "{$field} = :{$field}, ";
            }
        }

        // Remove the extra ', '
        $sql = trim($sql, ', ');

        $sql .= " WHERE ";

        $sql .= implode(
            ' AND',
            array_map(
                function ($key) {
                    return "{$key} = :{$key}";
                },
                $primary_keys
            )
        );

        $stmt = $this->gpdb->prepare($sql);

        // Bind all the params including the primary keys
        foreach (array_unique(array_merge($fields_to_save, $primary_keys)) as $field) {
            $value     = $this->data[$field];
            $bind_type = \PDO::PARAM_STR;

            // Check to see if it is an int, otherwise assume it is a string
            if (is_numeric($value)
                && $value == (int) $value) {
                $bind_type = \PDO::PARAM_INT;
            }

            $stmt->bindValue(":{$field}", $value, $bind_type);
        }

        $save_results = $stmt->execute();

        return ($stmt->rowCount() == 1);
    }

    /**
     * Persist data back to the database.  You must define a table, a set of
     * primary keys and some fields
     *
     * @param  array    $fields  (Optional) An array of fields to save if you
     *      don't want to save them all
     *
     * @return bool Did the save impact a single row?
     *
     * @throws Exception  You cannot use save on a model that isn't loaded
     * @throws Exception  The properties {table_name} and  {primary_key}
     * @throws Exception  Trying to save a document without a primary_key value
     */
    public function create() : bool
    {

        if (isset($this->save_blocked)) {
            throw new Exception("You cannot save this item:  {$this->save_blocked}");
        }

        if ($this->loaded) {
            throw new Exception(
                "You cannot create a model that has been loaded from the database."
                . "  Try the ->save() method instead"
            );
        }

        if (empty($this->table_name) || empty($this->primary_key)) {
            throw new Exception("The table name and the primary_key must be set");
        }

        $primary_keys = (is_array($this->primary_key) ? $this->primary_key : [ $this->primary_key ]);

        // Make sure that if the primary keys are set they don't already match an object
        $set_primary_keys = array_intersect_key($this->data, array_flip($primary_keys));
        if (
            count($primary_keys) > 1
            && count($set_primary_keys) != count($primary_keys)
        ) {
            throw new Exception(
                "If you have a compound primary key, you"
                . "must set the data before creating the object"
            );
        }
        if (count($set_primary_keys) > 0) {
            $test_keys = new static($set_primary_keys);
            if ($test_keys->loaded) {
                throw new Exception("Your primary key settings already match a record.");
            }
        }

        // Use the default fields if non are specified
        $fields_to_save = $this->fields;

        $fields_sql = "";
        $values_sql = "";

        // Don't bind the primary keys because we can't change those.
        // TODO but can't we.  There's nothing to say we can't, maybe
        // we shouldn't but that's a different question
        foreach ($fields_to_save as $field) {
            if (!isset($this->data[$field])) {
                continue;
            }

            $fields_sql .= "{$field}, ";
            $values_sql .= ":{$field}, ";
        }

        // Remove the extra ', '
        $fields_sql = trim($fields_sql, ', ');
        $values_sql = trim($values_sql, ', ');

        $stmt = $this->gpdb->prepare(
            "INSERT INTO
                {$this->table_name} ({$fields_sql})
                VALUES ({$values_sql})"
        );

        // Bind all the params including the primary keys
        foreach (array_unique(array_merge($fields_to_save, $primary_keys)) as $field) {
            if (!isset($this->data[$field])) {
                continue;
            }

            $value     = $this->data[$field];
            $bind_type = \PDO::PARAM_STR;

            // Check to see if it is an int or null,
            // otherwise assume it is a string
            if (
                (
                    is_numeric($value)
                    && $value == (int) $value
                )
                || is_null($value)
            ) {
                $bind_type = \PDO::PARAM_INT;
            }

            $stmt->bindValue(":{$field}", $value, $bind_type);
        }

        $save_results = $stmt->execute();

        // If we aren't using a compound key and the primary key isn't set,
        // we should attempt to load it by the lastInsertId
        if (
            count($primary_keys) > 1
            && count($set_primary_keys) != count($primary_keys)
        ) {
            $this->load([$this->primary_key => $stmt->lastInsertId()]);
        }

        return ($stmt->rowCount() == 1);
    }

    /**
     * Remove the data for this object from the database and reset the data fields
     * @return boolean
     */
    public function delete()
    {
        if (!$this->loaded) {
            throw new \Exception('A Model must be loaded before it can be deleted');
        }

        $primary_keys = (is_array($this->primary_key) ? $this->primary_key : [ $this->primary_key ]);

        $sql = " DELETE
                    FROM {$this->table_name}
                    WHERE ";

        $sql .= implode(
            ' AND',
            array_map(
                function ($key) {
                    return "{$key} = :{$key}";
                },
                $primary_keys
            )
        );

        $stmt = $this->gpdb->prepare($sql);

        // Bind all the params including the primary keys
        foreach ($primary_keys as $field) {
            $value     = $this->data[$field];
            $bind_type = \PDO::PARAM_STR;

            // Check to see if it is an int, otherwise assume it is a string
            if (is_numeric($value)
                && $value == (int) $value) {
                $bind_type = \PDO::PARAM_INT;
            }

            $stmt->bindValue(":{$field}", $value, $bind_type);
        }

        $stmt->execute();
        unset($this->data);
        $this->loaded = false;
        return ($stmt->rowCount() == 1);
    }
}
