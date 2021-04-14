<?php

namespace GoodPill\Models;

trait ChangeableTrait
{
    protected $gp_changes;

    /**
     * Set an array of changes.  The data should include field_name and values.  Each field should
     * have a std field_name that contains the new value and the same name prefixed with the
     * world old_ that contains the old value
     *
     * @param array $changes The changes array
     */
    public function setChanges(array $changes) : void
    {
        $this->gp_changes = $changes;
    }

    /**
     * Retrieve the data in the gp_changes array
     * @return array
     */
    public function getChanges()
    {
        return $this->gp_changes;
    }

    /**
     * Take an array of fields and look to see if any of the array of fields has changed.
     * @param  array  $fields (optional) All the possible fields to check. If array is empty, we look
     *       to see if any field has changed
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
     * @param  array   $fields All the fields to check
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
     * @param  string $field The field name to check
     * @return bool   True if the old value doesn't match the new value
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

        return $this->gp_changes[$field] != $this->gp_changes["old_{$field}"];
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
            if ($this->hasChanged($key)) {
                $changed_fields[] = $key;
            }
        }

        return $changed_fields;
    }

    /**
     * Return the old value for a change
     * @param  string $field The field we want to look for
     * @return mixed
     */
    public function oldValue(string $field)
    {
        return $this->gp_changes["old_{$field}"];
    }

    /**
     * Return the new value for the field
     * @param  string $field The field we want to look for
     * @return mixed
     */
    public function newValue(string $field)
    {
        return $this->gp_changes[$field];
    }
}
