<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PERMANENTFLAGS keyword gating (mirrors Hm_IMAP::supports_permanent_keyword).
 */
class Hm_Test_Supports_Permanent_Keyword extends TestCase {

    /**
     * Keep in sync with Hm_IMAP::supports_permanent_keyword()
     */
    private function supports_permanent_keyword_match($pflags, $keyword) {
        if (!is_array($pflags)) {
            return false;
        }
        foreach ($pflags as $flag) {
            if ($flag === '\*' || $flag === '*') {
                return true;
            }
            if (strcasecmp($flag, $keyword) === 0) {
                return true;
            }
        }
        return false;
    }

    public function test_supports_permanent_keyword_wildcard() {
        $this->assertTrue($this->supports_permanent_keyword_match(array('\*'), '$NotJunk'));
        $this->assertTrue($this->supports_permanent_keyword_match(array('*'), '$NotJunk'));
    }

    public function test_supports_permanent_keyword_explicit() {
        $this->assertTrue($this->supports_permanent_keyword_match(array('$NotJunk'), '$NotJunk'));
        $this->assertFalse($this->supports_permanent_keyword_match(array('\Seen', '\Draft'), '$NotJunk'));
    }

    public function test_supports_permanent_keyword_in_source() {
        $src = file_get_contents(APP_PATH.'modules/imap/hm-imap.php');
        $this->assertStringContainsString('function supports_permanent_keyword', $src);
        $this->assertStringContainsString('REMOVE_KEYWORD', $src);
        $this->assertStringNotContainsString('debug_log_uid_flags', $src);
        $handler = file_get_contents(APP_PATH.'modules/imap/handler_modules.php');
        $this->assertStringContainsString("supports_permanent_keyword('\$NotJunk')", $handler);
        $this->assertStringNotContainsString('junk_flags:', $handler);
    }
}
