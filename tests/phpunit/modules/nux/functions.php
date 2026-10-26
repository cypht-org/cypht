<?php

use PHPUnit\Framework\TestCase;

require_once APP_PATH.'modules/nux/functions.php';

/**
 * Tests for nux module functions
 */
class Hm_Test_Nux_Functions extends TestCase {

    public function test_convert_to_boolean() {
        $this->assertTrue(convert_to_boolean('true'));
        $this->assertTrue(convert_to_boolean('TRUE'));
        $this->assertTrue(convert_to_boolean('Yes'));
        $this->assertTrue(convert_to_boolean(' 1 '));
        $this->assertFalse(convert_to_boolean('false'));
        $this->assertFalse(convert_to_boolean('FALSE'));
        $this->assertFalse(convert_to_boolean('No'));
        $this->assertFalse(convert_to_boolean('0'));
        $this->assertSame('maybe', convert_to_boolean('maybe'));
        $this->assertSame('', convert_to_boolean(''));
        $this->assertSame(1, convert_to_boolean(1));
        $this->assertNull(convert_to_boolean(null));
    }

    public function test_parse_csv_with_headers_converts_spreadsheet_booleans() {
        $file = tempnam(sys_get_temp_dir(), 'cypht_csv');
        file_put_contents($file, "name;server;hide_from_combined_view;tls\nBox 1;imap.example.com;FALSE;TRUE\nBox 2;imap.example.com;false;true\n");
        $rows = parse_csv_with_headers($file);
        unlink($file);

        $this->assertCount(2, $rows);
        $this->assertSame('Box 1', $rows[0]['name']);
        $this->assertFalse($rows[0]['hide_from_combined_view']);
        $this->assertTrue($rows[0]['tls']);
        $this->assertFalse($rows[1]['hide_from_combined_view']);
        $this->assertTrue($rows[1]['tls']);
    }
}
