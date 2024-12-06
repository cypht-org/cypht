<?php

namespace Database\Core;

/**
 * The Migration class provides methods for creating and modifying database tables
 */
abstract class Migration
{
    /**
     * Run the migrations (to apply schema changes).
     *
     * @return void
     */
    abstract public function up();

    /**
     * Reverse the migrations (to rollback schema changes).
     *
     * @return void
     */
    abstract public function down();

    /**
     * Run the migration's `up()` method.
     *
     * @return void
     */
    public function runUp()
    {
        $this->up();
    }

    /**
     * Run the migration's `down()` method.
     *
     * @return void
     */
    public function runDown()
    {
        $this->down();
    }
}
