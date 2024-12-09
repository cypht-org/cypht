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
        Schema::create('hm_user', function (Blueprint $table) {
            $table->string('username', 255)->primary();
            $table->string('hash', 255);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hm_user');
    }
};
