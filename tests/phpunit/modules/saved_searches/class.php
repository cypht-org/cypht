<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for Hm_Saved_Searches class
 * Comprehensive coverage of both search types (simple and advanced):
 */
class Hm_Test_Saved_Searches_Class extends TestCase {

    private $saved_searches;
    private $sample_data;
    private $advanced_search_data;

    public function setUp(): void {
        $this->sample_data = array(
            'Simple Search' => array(
                'test terms',
                'SINCE 1-Jan-2023', 
                'TEXT',
                'Simple Search'
            ),
            'Another Simple' => array(
                'more terms',
                'SINCE 1-Jun-2023',
                'SUBJECT', 
                'Another Simple'
            )
        );

        $this->advanced_search_data = array(
            'terms' => array(
                array('term' => 'project meeting', 'condition' => 'and'),
                array('term' => 'urgent deadline', 'condition' => 'or'),
                array('term' => 'quarterly report', 'condition' => 'and')
            ),
            'targets' => array(
                array('target' => 'SUBJECT', 'orig' => 'TEXT', 'condition' => 'and'),
                array('target' => 'FROM', 'orig' => 'SUBJECT', 'condition' => 'or'),
                array('target' => 'TO', 'orig' => 'FROM', 'condition' => 'and')
            ),
            'sources' => array(
                array('source' => 'imap_0_INBOX', 'label' => 'Gmail - Inbox'),
                array('source' => 'imap_1_INBOX.Sent', 'label' => 'Yahoo - Sent Mail'),
                array('source' => 'imap_2_Work.Projects', 'label' => 'Work Email - Projects')
            ),
            'times' => array(
                array('from' => '2024-01-01', 'to' => '2024-12-31')
            ),
            'other' => array(
                'charset' => 'UTF-8',
                'limit' => 100,
                'flags' => array('SEEN')
            )
        );

        $this->saved_searches = new Hm_Saved_Searches($this->sample_data);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_constructor_and_dump() {
        $result = $this->saved_searches->dump();

        $keys = array_keys($result);
        $this->assertEquals('Another Simple', $keys[0]);
        $this->assertEquals('Simple Search', $keys[1]);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_simple_search() {
        $searches = new Hm_Saved_Searches(array());
        $search_data = array('new terms', 'SINCE 1-Jan-2024', 'FROM', 'New Search');

        $result = $searches->add('New Search', $search_data);

        $this->assertTrue($result);
        $this->assertEquals($search_data, $searches->get('New Search'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_duplicate_search() {
        $search_data = array('duplicate', 'SINCE 1-Jan-2024', 'TEXT', 'Simple Search');

        $result = $this->saved_searches->add('Simple Search', $search_data);

        $this->assertFalse($result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_update_existing_search() {
        $new_data = array('updated terms', 'SINCE 1-Jan-2024', 'FROM', 'Simple Search');

        $result = $this->saved_searches->update('Simple Search', $new_data);

        $this->assertTrue($result);
        $this->assertEquals($new_data, $this->saved_searches->get('Simple Search'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_update_nonexistent_search() {
        $new_data = array('updated terms', 'SINCE 1-Jan-2024', 'FROM', 'Nonexistent');

        $result = $this->saved_searches->update('Nonexistent', $new_data);

        $this->assertFalse($result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_existing_search() {
        $result = $this->saved_searches->delete('Simple Search');

        $this->assertTrue($result);
        $this->assertFalse($this->saved_searches->get('Simple Search'));
        $this->assertEquals(1, count($this->saved_searches->dump()));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_nonexistent_search() {
        $result = $this->saved_searches->delete('Nonexistent');

        $this->assertFalse($result);
        $this->assertEquals(2, count($this->saved_searches->dump()));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_rename_search() {
        $result = $this->saved_searches->rename('Simple Search', 'Renamed Search');

        $this->assertTrue($result);
        $this->assertFalse($this->saved_searches->get('Simple Search'));
        $this->assertNotFalse($this->saved_searches->get('Renamed Search'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_advanced_search() {
        $searches = new Hm_Saved_Searches(array());

        $result = $searches->add_advanced('My Advanced Search', $this->advanced_search_data);

        $this->assertTrue($result);
        $stored = $searches->get('My Advanced Search');
        $this->assertEquals('advanced', $stored['type']);
        $this->assertEquals($this->advanced_search_data, $stored['data']);
        $this->assertEquals('My Advanced Search', $stored['name']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_update_advanced_search() {
        $searches = new Hm_Saved_Searches(array());
        $searches->add_advanced('Advanced Search', $this->advanced_search_data);

        $updated_data = $this->advanced_search_data;
        $updated_data['terms'][0]['term'] = 'updated';

        $result = $searches->update_advanced('Advanced Search', $updated_data);

        $this->assertTrue($result);
        $stored = $searches->get_advanced('Advanced Search');
        $this->assertEquals('updated', $stored['terms'][0]['term']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_update_advanced_search_that_is_not_advanced() {
        $result = $this->saved_searches->update_advanced('Simple Search', $this->advanced_search_data);

        $this->assertFalse($result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_is_advanced() {
        $this->saved_searches->add_advanced('Advanced Search', $this->advanced_search_data);

        $this->assertFalse($this->saved_searches->is_advanced('Simple Search'));
        $this->assertTrue($this->saved_searches->is_advanced('Advanced Search'));
        $this->assertFalse($this->saved_searches->is_advanced('Nonexistent'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_advanced() {
        $this->saved_searches->add_advanced('Advanced Search', $this->advanced_search_data);

        $result = $this->saved_searches->get_advanced('Advanced Search');
        $this->assertEquals($this->advanced_search_data, $result);

        $result = $this->saved_searches->get_advanced('Simple Search');
        $this->assertFalse($result);

        $result = $this->saved_searches->get_advanced('Nonexistent', 'default');
        $this->assertEquals('default', $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_by_type() {
        $this->saved_searches->add_advanced('Advanced Search 1', $this->advanced_search_data);
        $this->saved_searches->add_advanced('Advanced Search 2', $this->advanced_search_data);

        $result = $this->saved_searches->get_by_type();

        $this->assertArrayHasKey('simple', $result);
        $this->assertArrayHasKey('advanced', $result);
        $this->assertEquals(2, count($result['simple']));
        $this->assertEquals(2, count($result['advanced']));

        $this->assertArrayHasKey('Simple Search', $result['simple']);
        $this->assertArrayHasKey('Another Simple', $result['simple']);

        $this->assertArrayHasKey('Advanced Search 1', $result['advanced']);
        $this->assertArrayHasKey('Advanced Search 2', $result['advanced']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_by_type_empty() {
        $searches = new Hm_Saved_Searches(array());

        $result = $searches->get_by_type();

        $this->assertEquals(array('simple' => array(), 'advanced' => array()), $result);
    }
}