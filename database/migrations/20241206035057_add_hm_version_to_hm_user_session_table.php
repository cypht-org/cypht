<?php

use Database\Core\Schema;
use Database\Core\Blueprint;
use Database\Core\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hm_user_session', function (Blueprint $table) {
            $table->integer('hm_version')->default(1);
            $table->addColumnIf('integer', 'lock', ['default' => 0], function () {
                return env('DB_DRIVER') === 'sqlite';
            })->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hm_user_session', function (Blueprint $table) {
            $table->dropColumn('hm_version');
        });
    }
};
