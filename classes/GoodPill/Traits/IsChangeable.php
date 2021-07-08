<?php

namespace GoodPill\Traits;

/**
 * Trait for Goodpill Objects that can have a changeset based on the import methods
 */
trait IsChangeable
{
    /**
     * An array to hold the various changes that can be applied to this object
     * @var array
     */
    protected $gp_changes;

    /**
     * Set an array of changes.  The data should include field_name and values.  Each field should
     * have a std field_name that contains the new value and the same name prefixed with the
     * world old_ that contains the old value
     *
     * @param array $changes The changes array.
     * @return void
     */
    public function setGpChanges(array $changes) : void
    {
        $this->gp_changes = $changes;

        // Apply These changes to the object, we aren't storing them and eventually this should
        // be replaced by the eloquent fields
        $this->fill($changes);
    }

    /**
     * Retrieve the data in the gp_changes array
     * @param bool $create_if_empty If the gpChanges array hasn't been set, then we will use the
     *      tracked_fields array and create an array of changes.
     * @return array
     */
    public function getGpChanges($create_if_empty = false)
    {
        if (!$create_if_empty || $this->hasGpChanges()) {
            return $this->gp_changes;
        }

        if (!isset($this->tracked_fields)) {
            throw new \Exception('You cannot create a changes array unles $tracked_fields is set');
        }

        $changes       = [];
        $dirty_changes = $this->getDirty();

        foreach ($this->tracked_fields as $field) {

            $value = $this->{$field};
            if (is_object($value)) {
                $value = (string) $value;
            }

            $changes[$field] = $value;
            if (isset($dirty_changes[$field])) {
                $old_value = $dirty_changes[$field];
                if (is_object($value)) {
                    $old_value = (string) $old_value;
                }
                $changes["old_{$field}"] = $old_value;
            }
        }

        return $changes;
    }

    /**
     * True if changes have been set
     * @return array
     */
    public function hasGpChanges()
    {
        return isset($this->gp_changes);
    }

    /**
     * Take an array of fields and look to see if any of the array of fields has changed.
     * @param  array $fields Optional. All the possible fields to check. If array is empty, we look
     *       to see if any field has changed.
     * @return boolean  true if any field in the $fields array has changed
     */
    public function hasAnyFieldChanged(?array $fields = []) : bool
    {

        if (empty($fields)) {
            return empty($this->listChangedFields());
        }

        foreach ($fields as $field) {
            if ($this->hasFieldChanged($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Take an array of fields and look to see if All of the fields has changed.
     * @param  array $fields All the fields to check.
     * @return boolean True if all of the specified fields have changed
     */
    public function haveAllFieldsChanged(array $fields) : bool
    {
        foreach ($fields as $field) {
            if (!$this->hasFieldChanged($field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks to see if a single field has changed
     * @param  string $field The field name to check.
     * @return boolean   True if the old value doesn't match the new value
     * @throws Exception You can not check for field changes if you haven't set the changes.
     */
    public function hasFieldChanged(string $field = null) : bool
    {
        // you have to setChanges first
        if (!isset($this->gp_changes)) {
            throw new Exception('You must add a set of changes before you can test for changes');
        }

        // If there isn't an old value assume it hasn't changed
        if (!isset($this->gp_changes["old_{$field}"])) {
            return false;
        }

        return $this->gp_changes[$field] !== $this->gp_changes["old_{$field}"];
    }

    /**
     * Return a list of all the fields that have a differnt new and old value
     * @return array
     */
    public function listChangedFields() : ?array
    {
        if (!isset($this->gp_changes)) {
            return null;
        }

        $changed_fields = [];

        $field_keys = array_filter(
            array_keys($this->gp_changes),
            function ($key) {
                return (preg_match('/^old_/', $key) == 0);
            }
        );

        foreach ($field_keys as $key) {
            if ($this->hasFieldChanged($key)) {
                $changed_fields[] = $key;
            }
        }

        return $changed_fields;
    }

    /**
     * Return the old value for a change
     * @param  string $field The field we want to look for.
     * @return mixed
     */
    public function oldValue(string $field)
    {
        return $this->gp_changes["old_{$field}"];
    }

    /**
     * Return the new value for the field
     * @param  string $field The field we want to look for.
     * @return mixed
     */
    public function newValue(string $field)
    {
        return $this->gp_changes[$field];
    }

    /**
     * Get the changes formatted as a list of chagnes with $old_value >>> $new_value
     * @return array
     */
    public function getChangeStrings() : array
    {
        $changed_fields = $this->listChangedFields();
        $changes = [];
        foreach ($changed_fields as $field) {
            $old_value = (!is_null($this->oldValue($field))) ? $this->oldValue($field) : 'NULL';
            $new_value = (!is_null($this->newValue($field))) ? $this->newValue($field) : 'NULL';
            $changes[$field] = "'{$old_value}' >>> '{$new_value}'";
        }

        return $changes;
    }
}
