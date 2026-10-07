<?php

use PHPUnit\Framework\TestCase;

/**
 * Clear Chunks and the settings counter must stay inside the current
 * user's attachment directory. Assembled files must not be deleted.
 *
 * These tests load modules/smtp/modules.php, which defines Hm_SMTP_List.
 * Other suites already define a mock of that class via helpers.php in this
 * process, so this class must run isolated with global state discarded.
 *
 * @runClassInSeparateProcess
 * @preserveGlobalState disabled
 */
class Hm_Test_Smtp_Attachment_Chunks extends TestCase {

    private $attachment_dir;
    private $alice_dir;
    private $bob_dir;

    public function setUp(): void {
        require_once APP_PATH.'modules/smtp/modules.php';
        $this->attachment_dir = sys_get_temp_dir().'/cypht-chunk-test-'.bin2hex(random_bytes(8));
        mkdir($this->attachment_dir, 0700, true);
        $this->alice_dir = user_attachment_dir($this->attachment_dir, 'alice');
        $this->bob_dir = user_attachment_dir($this->attachment_dir, 'bob');
        mkdir($this->alice_dir, 0700, true);
        mkdir($this->bob_dir, 0700, true);
    }

    public function tearDown(): void {
        if (is_dir($this->attachment_dir)) {
            $this->rrmdir($this->attachment_dir);
        }
    }

    public function test_user_attachment_dir_is_md5_child_of_base() {
        $this->assertSame($this->attachment_dir.DIRECTORY_SEPARATOR.md5('alice'), $this->alice_dir);
        $this->assertNotSame($this->alice_dir, $this->bob_dir);
        $this->assertFalse(user_attachment_dir($this->attachment_dir, ''));
        $this->assertFalse(user_attachment_dir($this->attachment_dir, false));
        $this->assertFalse(user_attachment_dir('', 'alice'));
    }

    public function test_count_only_includes_current_user_part_files() {
        $this->seedPausedUpload($this->alice_dir, 'chunks-alice-upload', 'big.bin', 2048);
        $this->seedPausedUpload($this->bob_dir, 'chunks-bob-upload', 'other.bin', 4096);
        file_put_contents($this->alice_dir.'/assembled.bin', str_repeat('A', 512));
        file_put_contents($this->alice_dir.'/orphan.part1', 'should-not-count');
        mkdir($this->attachment_dir.'/chunks-global', 0700, true);
        file_put_contents($this->attachment_dir.'/chunks-global/leaked.part1', 'global');

        list($alice_count, $alice_bytes) = count_user_attachment_chunk_parts($this->alice_dir);
        list($bob_count, $bob_bytes) = count_user_attachment_chunk_parts($this->bob_dir);

        $this->assertSame(1, $alice_count);
        $this->assertSame(2048, $alice_bytes);
        $this->assertSame(1, $bob_count);
        $this->assertSame(4096, $bob_bytes);
        $this->assertSame([0, 0], count_user_attachment_chunk_parts(false));
        $this->assertSame([0, 0], count_user_attachment_chunk_parts($this->attachment_dir.'/missing'));
    }

    public function test_clear_only_removes_current_user_chunk_dirs() {
        $this->seedPausedUpload($this->alice_dir, 'chunks-alice-upload', 'big.bin', 2048);
        $this->seedPausedUpload($this->alice_dir, 'chunks-alice-upload_UNUSED', 'stale.bin', 128);
        $this->seedPausedUpload($this->bob_dir, 'chunks-bob-upload', 'other.bin', 4096);
        file_put_contents($this->alice_dir.'/assembled.bin', 'keep-me');
        mkdir($this->attachment_dir.'/chunks-global', 0700, true);
        file_put_contents($this->attachment_dir.'/chunks-global/leaked.part1', 'global');

        foreach (user_attachment_chunk_dirs($this->alice_dir) as $chunk_dir) {
            rrmdir($chunk_dir);
        }

        $this->assertDirectoryDoesNotExist($this->alice_dir.'/chunks-alice-upload');
        $this->assertDirectoryDoesNotExist($this->alice_dir.'/chunks-alice-upload_UNUSED');
        $this->assertFileExists($this->alice_dir.'/assembled.bin');
        $this->assertSame('keep-me', file_get_contents($this->alice_dir.'/assembled.bin'));
        $this->assertDirectoryExists($this->bob_dir.'/chunks-bob-upload');
        $this->assertFileExists($this->bob_dir.'/chunks-bob-upload/other.bin.part1');
        $this->assertDirectoryExists($this->attachment_dir.'/chunks-global');
        $this->assertSame([0, 0], count_user_attachment_chunk_parts($this->alice_dir));
        $this->assertSame(1, count_user_attachment_chunk_parts($this->bob_dir)[0]);
    }

    public function test_settings_output_counts_parts_without_bool_typecast() {
        $this->seedPausedUpload($this->alice_dir, 'chunks-alice-upload', 'matt_test.pdf', 2048);
        $mod = new Hm_Output_attachment_setting(array('user_attachment_dir' => $this->alice_dir), array());
        $html = $mod->output_content('Hm_Format_HTML5', array());
        $this->assertStringContainsString('(1 Chunks) 2 KB', $html);
    }

    public function test_chunk_dirs_are_immediate_children_named_chunks() {
        mkdir($this->alice_dir.'/not-chunks', 0700, true);
        file_put_contents($this->alice_dir.'/not-chunks/x.part1', 'nope');
        mkdir($this->alice_dir.'/nested', 0700, true);
        mkdir($this->alice_dir.'/nested/chunks-hidden', 0700, true);
        file_put_contents($this->alice_dir.'/nested/chunks-hidden/y.part1', 'hidden');
        $this->seedPausedUpload($this->alice_dir, 'chunks-real', 'z.bin', 16);

        $dirs = user_attachment_chunk_dirs($this->alice_dir);
        $this->assertCount(1, $dirs);
        $this->assertSame($this->alice_dir.DIRECTORY_SEPARATOR.'chunks-real', $dirs[0]);
        $this->assertSame(1, count_user_attachment_chunk_parts($this->alice_dir)[0]);
    }

    private function seedPausedUpload($user_dir, $chunk_dirname, $filename, $bytes) {
        $dir = $user_dir.'/'.$chunk_dirname;
        mkdir($dir, 0700, true);
        file_put_contents($dir.'/'.$filename.'.part1', str_repeat('x', $bytes));
    }

    private function rrmdir($dir) {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
