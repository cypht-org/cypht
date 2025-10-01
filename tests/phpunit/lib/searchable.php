<?php

/**
 * Unit tests for Searchable trait
 * @package lib/tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for the Searchable trait
 */
class Hm_Test_Searchable extends TestCase {

    public function setUp(): void {
        require __DIR__.'/../bootstrap.php';
        Searchable_Wrapper::resetTestData(); 
    }

    /**
     * Test getBy with ID search (default column)
     * 
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_with_id_search() {
        $results = Searchable_Wrapper::getBy(1);
        
        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals(1, $results[0]['id']);
        $this->assertEquals('John Doe', $results[0]['name']);
    }

    /**
     * Test getBy with custom column search
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_with_custom_column() {
        $results = Searchable_Wrapper::getBy('active', 'status');
        
        $this->assertIsArray($results);
        $this->assertCount(3, $results); // John, Bob, Charlie
        
        foreach ($results as $result) {
            $this->assertEquals('active', $result['status']);
        }
    }

    /**
     * Test getBy with returnFirst = true
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_return_first_match() {
        $result = Searchable_Wrapper::getBy('active', 'status', true);
        
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('John Doe', $result['name']);
        $this->assertEquals('active', $result['status']);
    }

    /**
     * Test getBy with no matches
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_no_matches() {
        $results = Searchable_Wrapper::getBy(999);
        
        $this->assertIsArray($results);
        $this->assertCount(0, $results);
    }

    /**
     * Test getBy with no matches and returnFirst = true
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_no_matches_return_first() {
        $result = Searchable_Wrapper::getBy(999, 'id', true);
        
        $this->assertNull($result);
    }

    /**
     * Test getBy with non-existent column
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_non_existent_column() {
        $results = Searchable_Wrapper::getBy('test', 'non_existent_column');
        
        $this->assertIsArray($results);
        $this->assertCount(0, $results);
    }

    /**
     * Test getBy with multiple matches
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_multiple_matches() {
        $results = Searchable_Wrapper::getBy(30, 'age');
        
        $this->assertIsArray($results);
        $this->assertCount(2, $results); // John and Charlie both age 30
        
        $names = array_column($results, 'name');
        $this->assertContains('John Doe', $names);
        $this->assertContains('Charlie Wilson', $names);
    }

    /**
     * Test getBy with string search
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_string_search() {
        $results = Searchable_Wrapper::getBy('john@example.com', 'email');
        
        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]['name']);
    }

    /**
     * Test with empty dataset
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_empty_dataset() {
        $results = Empty_Searchable_Wrapper::getBy(1);
        
        $this->assertIsArray($results);
        $this->assertCount(0, $results);
    }

    /**
     * Test with empty dataset and returnFirst = true
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_empty_dataset_return_first() {
        $result = Empty_Searchable_Wrapper::getBy(1, 'id', true);
        
        $this->assertNull($result);
    }

    /**
     * Test getBy with null value search
     * Note: isset() returns false for null values, so null values won't be found
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_null_value_search() {
        Searchable_Wrapper::setTestData([
            ['id' => 1, 'name' => 'Test', 'email' => null, 'status' => 'active']
        ]);
        
        $results = Searchable_Wrapper::getBy(null, 'email');
        
        $this->assertIsArray($results);
        $this->assertCount(0, $results);
    }

    /**
     * Test getBy with missing vs null column
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_missing_vs_null_column() {
        Searchable_Wrapper::setTestData([
            ['id' => 1, 'name' => 'Test1', 'email' => null],
            ['id' => 2, 'name' => 'Test2']
        ]);
        
        $nullResults = Searchable_Wrapper::getBy(null, 'email');
        $missingResults = Searchable_Wrapper::getBy('anything', 'missing_column');
        
        $this->assertCount(0, $nullResults);
        $this->assertCount(0, $missingResults);
    }

    /**
     * Test getBy with boolean value search
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_boolean_value_search() {
        Searchable_Wrapper::setTestData([
            ['id' => 1, 'name' => 'Test1', 'active' => true],
            ['id' => 2, 'name' => 'Test2', 'active' => false],
            ['id' => 3, 'name' => 'Test3', 'active' => true]
        ]);
        
        $results = Searchable_Wrapper::getBy(true, 'active');
        
        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        
        foreach ($results as $result) {
            $this->assertTrue($result['active']);
        }
    }

    /**
     * Test getBy with numeric string search
     * @preserveGlobalState disabled
     * @runInSeparateProcess 
     */
    public function test_getBy_numeric_string_search() {
        Searchable_Wrapper::setTestData([
            ['id' => 1, 'name' => 'Test', 'code' => '123'],
            ['id' => 2, 'name' => 'Test2', 'code' => 123]
        ]);
        
        $results = Searchable_Wrapper::getBy('123', 'code');
        
        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals('Test', $results[0]['name']);
    }
}