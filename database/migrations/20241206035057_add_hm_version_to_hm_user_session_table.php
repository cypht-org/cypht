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
