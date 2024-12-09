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
        Schema::create('hm_user_session', function (Blueprint $table) {
            $table->string('hm_id', 255)->primary();
            $table->longBlob('data');
            $table->timestamp('date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hm_user_session');
    }
};
