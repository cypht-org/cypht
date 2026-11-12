<?php

use PHPUnit\Framework\TestCase;

require_once APP_PATH.'modules/imap/hm-imap-bodystructure.php';
require_once APP_PATH.'modules/core/message_functions.php';

/**
 * Tests for searching a parsed IMAP BODYSTRUCTURE response
 */
class Hm_Test_IMAP_Bodystructure extends TestCase {

    private function struct($tokens) {
        $imap = new class {
            public function decode_fld($string) {
                return decode_fld($string);
            }
        };
        return new Hm_IMAP_Struct($tokens, $imap);
    }

    /* multipart/related with an HTML part and an inline image */
    private function relatedMessage() {
        return $this->struct([
            '(',
                '(', 'text', 'html', '(', 'charset', 'utf-8', ')', 'NIL', 'NIL', 'quoted-printable', '120', '4', 'NIL', 'NIL', 'NIL', 'NIL', ')',
                '(', 'image', 'jpeg', '(', 'name', 'logo.jpg', ')', '<logo@example.com>', 'NIL', 'base64', '2048', 'NIL',
                    '(', 'inline', '(', 'filename', 'logo.jpg', ')', ')', 'NIL', 'NIL', ')',
                'related', '(', 'boundary', 'b1', ')', 'NIL', 'NIL', 'NIL',
            ')',
        ]);
    }

    /* multipart/alternative with an AMP part before the HTML part */
    private function ampMessage() {
        return $this->struct([
            '(',
                '(', 'text', 'plain', '(', 'charset', 'utf-8', ')', 'NIL', 'NIL', '7bit', '10', '1', 'NIL', 'NIL', 'NIL', 'NIL', ')',
                '(', 'text', 'x-amp-html', '(', 'charset', 'utf-8', ')', 'NIL', 'NIL', '7bit', '20', '1', 'NIL', 'NIL', 'NIL', 'NIL', ')',
                '(', 'text', 'html', '(', 'charset', 'utf-8', ')', 'NIL', 'NIL', '7bit', '30', '1', 'NIL', 'NIL', 'NIL', 'NIL', ')',
                'alternative', '(', 'boundary', 'b2', ')', 'NIL', 'NIL', 'NIL',
            ')',
        ]);
    }

    public function test_search_finds_image_by_content_id_in_angle_brackets() {
        $res = $this->relatedMessage()->search(['id' => 'logo@example.com', 'type' => 'image']);
        $this->assertSame(['2'], array_map('strval', array_keys($res)));
        $this->assertSame('jpeg', $res[2]['subtype']);
    }

    public function test_search_prefers_exact_subtype_over_substring() {
        $res = $this->ampMessage()->search(['type' => 'text', 'subtype' => 'html'], false);
        $this->assertSame(['3'], array_map('strval', array_keys($res)));
    }

    public function test_search_by_part_number() {
        $res = $this->ampMessage()->search(['imap_part_number' => '2']);
        $this->assertSame(['2'], array_map('strval', array_keys($res)));
        $this->assertSame('x-amp-html', $res[2]['subtype']);
    }
}
