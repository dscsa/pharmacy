<?php

namespace GoodPill\Models;

trait ChangeableTrait
{
    protected $gp_changes;

    public function setChanges(array $changes) : void
    {
        $this->gp_changes = $changes;
    }

    public function hasChanged(?string $field = null) : bool
    {
        // you have to setChanges first
        if (!isset($this->gp_changes)) {
            throw new Exception('You must add a set of changes before you can test for changes');
        }

        // If a field isn't provided, do we have any changed fields
        if (is_null($field)) {
            return (!empty($this->listChangedFields()));
        }

        // If there isn't an old value assume it hasn't changed
        if (!isset($this->gp_changes["old_{$field}"])) {
            return false;
        }

        return $this->gp_changes[$field] != $this->gp_changes["old_{$field}"];
    }

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

    public function oldValue($field)
    {
        return $this->gp_changes["old_{$field}"];
    }

    public function newValue($field)
    {
        return $this->gp_changes[$field];
    }
}
