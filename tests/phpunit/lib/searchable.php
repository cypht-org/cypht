<?php

/**
 * Unit tests for the Searchable trait
 * @package lib/tests
 */

use PHPUnit\Framework\TestCase;

class Hm_Test_Searchable extends TestCase {

    private static array $default_data = [
        ['id' => 1, 'name' => 'John Doe',      'status' => 'active',   'age' => 30],
        ['id' => 2, 'name' => 'Jane Smith',     'status' => 'inactive', 'age' => 25],
        ['id' => 3, 'name' => 'Bob Johnson',    'status' => 'active',   'age' => 35],
        ['id' => 4, 'name' => 'Alice Brown',    'status' => 'pending',  'age' => 28],
        ['id' => 5, 'name' => 'Charlie Wilson', 'status' => 'active',   'age' => 30],
    ];

    public function setUp(): void {
        Searchable_Wrapper::load(self::$default_data);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_default_column_returns_single_match(): void {
        $results = Searchable_Wrapper::getBy(1);

        $this->assertCount(1, $results);
        $this->assertSame(1, $results[0]['id']);
        $this->assertSame('John Doe', $results[0]['name']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_custom_column_returns_all_matches(): void {
        $results = Searchable_Wrapper::getBy('active', 'status');

        $this->assertCount(3, $results);
        foreach ($results as $item) {
            $this->assertSame('active', $item['status']);
        }
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_multiple_rows_share_a_value(): void {
        $results = Searchable_Wrapper::getBy(30, 'age');

        $this->assertCount(2, $results);
        $names = array_column($results, 'name');
        $this->assertContains('John Doe', $names);
        $this->assertContains('Charlie Wilson', $names);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_return_first_returns_item_not_array(): void {
        $result = Searchable_Wrapper::getBy('active', 'status', true);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertSame('active', $result['status']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_returns_empty_array_when_no_match(): void {
        $this->assertSame([], Searchable_Wrapper::getBy(999));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_return_first_returns_null_when_no_match(): void {
        $this->assertNull(Searchable_Wrapper::getBy(999, 'id', true));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_missing_column_returns_empty(): void {
        $results = Searchable_Wrapper::getBy('anything', 'nonexistent_column');

        $this->assertSame([], $results);
    }

    /**
     * Null values are skipped because isset() returns false for null.
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_null_column_value_is_not_matched(): void {
        Searchable_Wrapper::load([['id' => 1, 'name' => null]]);

        $this->assertSame([], Searchable_Wrapper::getBy(null, 'name'));
    }

    /**
     * getBy uses strict comparison (===), so '1' !== 1.
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_uses_strict_type_comparison(): void {
        Searchable_Wrapper::load([
            ['id' => 1, 'code' => '1'],
            ['id' => 2, 'code' => 1],
        ]);

        $results = Searchable_Wrapper::getBy('1', 'code');
        $this->assertCount(1, $results);
        $this->assertSame(1, $results[0]['id']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getBy_returns_empty_for_empty_dataset(): void {
        Searchable_Wrapper::load([]);

        $this->assertSame([], Searchable_Wrapper::getBy(1));
        $this->assertNull(Searchable_Wrapper::getBy(1, 'id', true));
    }
}
