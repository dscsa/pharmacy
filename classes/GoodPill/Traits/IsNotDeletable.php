<?php

namespace GoodPill\Traits;

/**
 * Trait to make it impossible to delete an object
 */
trait IsNotDeleteable
{
    /**
     * Override the save function so that if the object came out of the database,
     * we can't change it anymore
     * @param  array  $options Array of options compatible with Eloquent/Model::save()
     * @return bool
     */
    public function delete()
    {
        throw new \Exception('This object cannot be deleted');
    }
}
