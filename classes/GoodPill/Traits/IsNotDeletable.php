<?php

namespace GoodPill\Traits;

/**
 * Trait to make it impossible to delete an object
 */
trait IsNotDeletable
{
    /**
     * Override the save function so that if the object came out of the database,
     * we can't change it anymore
     * @throws Exception if trying to access delete
     * @return void
     */
     public function delete()
     {
         throw new Exception('Cannot directly delete this object,  Deletes must be handled by accessors');
     }
}
