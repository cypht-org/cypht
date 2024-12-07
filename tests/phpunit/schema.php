<?php

use Database\Core\Schema;
use Database\Core\Blueprint;
use PHPUnit\Framework\TestCase;
use Database\Core\MigrationRunner;

/**
 * tests for Schema
 */
class Hm_Test_Schema extends TestCase {

    public $config;

    protected $pdo;

    public function setUp(): void {
        require 'bootstrap.php';
        $this->config = new Hm_Mock_Config();
        setup_db($this->config);
        $this->pdo = Hm_DB::connect($this->config);
        Schema::setConnection($this->pdo);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_set_connection() {
        Schema::setConnection($this->pdo);
        $this->assertContains(Schema::getDriver(), ['sqlite', 'mysql','pgsql']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_has_table() {
        $this->assertTrue(Schema::hasTable('hm_user'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_migrate_table() {
        Schema::dropIfExists('hello_test');
        $this->assertFalse(Schema::hasTable('hello_test'));
        $tableName = 'hello_test';//'_' . substr(bin2hex(random_bytes(4)), 0, 8);
        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
        });
        $this->assertTrue(Schema::hasTable($tableName));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_rollback_table() {
        $migrationRunner = new MigrationRunner($this->pdo);
        $migrationRunner->run('rollback');
        $this->assertTrue(Schema::hasTable('hello_test'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_rename_table() {
        $destination_table_name  = 'hello_test_renamed_'.substr(bin2hex(random_bytes(4)), 0, 8);
        Schema::rename('hello_test', $destination_table_name);
        $this->assertTrue(Schema::hasTable($destination_table_name));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_all_tables_table() {
        $this->assertTrue(count(Schema::getAllTables()) > 0);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_drop_column_table() {
        $this->assertTrue(count(Schema::getAllTables()) > 0);
    }
}
