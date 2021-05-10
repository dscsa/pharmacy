<?php

namespace GoodPill\Traits;

/**
 * Trait to make it impossible to save an object after it loads or has been saved to the database
 */
trait IsImmutable
{
    /**
     * Override the save function so that if the object came out of the database,
     * we can't change it anymore
     * @param  array  $options Array of options compatible with Eloquent/Model::save()
     * @return bool
     */
    public function save(array $options = [])
    {
        if ($this->exists) {
            throw new \Exception('This object cannot be changed once created');
        }

        return parent::save($options);
    }
}
