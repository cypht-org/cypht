<?php

use Database\Core\Blueprint;
use PHPUnit\Framework\TestCase;

/**
 * tests for Schema
 */
class Hm_Test_Blueprint extends TestCase {

    public $table;

    public function setUp(): void {
        require 'bootstrap.php';
        $this->table = new Blueprint();
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_columns() {
        $this->table->addColumn('integer', 'id', ['primary' => true]);
        $columns = $this->table->getColumns();
        $this->assertCount(1, $columns);
        $this->assertArrayHasKey('name', $columns[0]);
        $this->assertSame('integer', $columns[0]['type']);
        $this->assertSame('id', $columns[0]['name']);
        $this->assertTrue(isset($columns[0]['primary']) && $columns[0]['primary'] == 1);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_column_if() {
        $this->table->addColumnIf('integer', 'id', ['default' => 0], function () {
            return 'john' === 'doe';
        });
        $columns = $this->table->getColumns();
        $this->assertCount(0, $columns);
        $this->table->addColumnIf('integer', 'id', ['default' => 0], function () {
            return 'doe' === 'doe';
        });
        $columns = $this->table->getColumns();
        $this->assertCount(1, $columns);
        $this->assertArrayHasKey('name', $columns[0]);
        $this->assertSame('integer', $columns[0]['type']);
        $this->assertSame('id', $columns[0]['name']);
        $this->assertTrue(isset($columns[0]['default']) && $columns[0]['default'] == 0);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */    
    public function test_add_column_then_modify_column()
    {
        $this->table->addColumn('integer', 'id', ['primary' => true]);
        $columns = $this->table->getColumns(); 
        $this->assertCount(1, $columns);
        $this->assertArrayHasKey('name', $columns[0]);
        $this->assertSame('integer', $columns[0]['type']);
        $this->assertSame('id', $columns[0]['name']);
        $this->assertTrue(isset($columns[0]['primary']) && $columns[0]['primary'] == 1);

        $this->table->modifyColumn('id', 'bigint', ['nullable' => false, 'default' => 100]);
        $modifiedColumns = $this->table->getModifiedColumns();
        $this->assertCount(1, $modifiedColumns);
        $this->assertSame('id', $modifiedColumns[0]['name']);
        $this->assertSame('bigint', $modifiedColumns[0]['type']);
        $this->assertSame(['nullable' => false, 'default' => 100], $modifiedColumns[0]['options']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_column_then_drop_column()
    {
        $this->table->addColumn('string', 'title', ['nullable' => true]);
        $columns = $this->table->getColumns();
        $this->assertCount(1, $columns);
        $this->assertArrayHasKey('name', $columns[0]);
        $this->assertSame('string', $columns[0]['type']);
        $this->assertSame('title', $columns[0]['name']);
        $this->assertTrue(isset($columns[0]['nullable']) && $columns[0]['nullable'] == 1);

        $this->table->dropColumn('name');
        $droppedColumns = $this->table->getDroppedColumns();
        $this->assertCount(1, $droppedColumns);
        $this->assertSame('name', $droppedColumns[0]);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_id()
    {
        $this->table->id();
        $columns = $this->table->getColumns();
        $this->assertCount(1, $columns);
        $this->assertArrayHasKey('name', $columns[0]);
        $this->assertSame('integer', $columns[0]['type']);
        $this->assertSame('id', $columns[0]['name']);
        $this->assertTrue(isset($columns[0]['autoIncrement']) && $columns[0]['autoIncrement'] == 1);
        $this->assertTrue(isset($columns[0]['primary']) && $columns[0]['primary'] == 1);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_string()
    {
        $this->table->string('subject');
        $columns = $this->table->getColumns();
        $this->assertCount(1, $columns);
        $this->assertArrayHasKey('name', $columns[0]);
        $this->assertSame('string', $columns[0]['type']);
        $this->assertSame('subject', $columns[0]['name']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_blob()
    {
        $this->table->blob('image');
        $columns = $this->table->getColumns();
        $this->assertCount(1, $columns);
        $this->assertArrayHasKey('name', $columns[0]);
        $this->assertSame('blob', $columns[0]['type']);
        $this->assertSame('image', $columns[0]['name']);
    }
}
