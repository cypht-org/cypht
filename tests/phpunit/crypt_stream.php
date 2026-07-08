<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Crypt_Stream_Writer / Hm_Crypt_Stream_Reader
 */
class Hm_Test_Crypt_Stream extends TestCase {

    private $path;

    public function setUp(): void {
        $this->path = sys_get_temp_dir().'/hm_crypt_stream_test_'.bin2hex(random_bytes(8));
    }

    public function tearDown(): void {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }

    private function read_all(Hm_Crypt_Stream_Reader $reader) {
        $out = '';
        while (($chunk = $reader->read()) !== false) {
            $out .= $chunk;
        }
        return $out;
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_round_trip_empty() {
        $writer = new Hm_Crypt_Stream_Writer($this->path, 'testkey');
        $writer->write('');
        $writer->close();

        $reader = new Hm_Crypt_Stream_Reader($this->path, 'testkey');
        $this->assertEquals('', $this->read_all($reader));
        $reader->close();
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_round_trip_single_byte() {
        $writer = new Hm_Crypt_Stream_Writer($this->path, 'testkey');
        $writer->write('a');
        $writer->close();

        $reader = new Hm_Crypt_Stream_Reader($this->path, 'testkey');
        $this->assertEquals('a', $this->read_all($reader));
        $reader->close();
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_round_trip_exact_chunk_multiple() {
        $data = str_repeat('x', Hm_Crypt_Stream_Writer::CHUNK_SIZE * 2);
        $writer = new Hm_Crypt_Stream_Writer($this->path, 'testkey');
        $writer->write($data);
        $writer->close();

        $reader = new Hm_Crypt_Stream_Reader($this->path, 'testkey');
        $this->assertEquals($data, $this->read_all($reader));
        $reader->close();
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_round_trip_random_multi_chunk() {
        $data = random_bytes((int) (Hm_Crypt_Stream_Writer::CHUNK_SIZE * 2.5) + 137);
        $writer = new Hm_Crypt_Stream_Writer($this->path, 'testkey');
        /* feed it in small, uneven pieces to exercise the internal buffer */
        $offset = 0;
        $len = strlen($data);
        while ($offset < $len) {
            $piece = substr($data, $offset, 777);
            $writer->write($piece);
            $offset += strlen($piece);
        }
        $writer->close();

        $reader = new Hm_Crypt_Stream_Reader($this->path, 'testkey');
        $this->assertEquals($data, $this->read_all($reader));
        $reader->close();
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_wrong_key_fails() {
        $writer = new Hm_Crypt_Stream_Writer($this->path, 'testkey');
        $writer->write('secret data');
        $writer->close();

        $reader = new Hm_Crypt_Stream_Reader($this->path, 'wrongkey');
        $this->expectException(Exception::class);
        $this->read_all($reader);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_tampered_ciphertext_fails() {
        $writer = new Hm_Crypt_Stream_Writer($this->path, 'testkey');
        $writer->write('secret data');
        $writer->close();

        $contents = file_get_contents($this->path);
        /* flip a byte well past the salt, inside the first chunk's ciphertext */
        $contents[20] = chr(ord($contents[20]) ^ 0xFF);
        file_put_contents($this->path, $contents);

        $reader = new Hm_Crypt_Stream_Reader($this->path, 'testkey');
        $this->expectException(Exception::class);
        $this->read_all($reader);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_truncated_stream_fails() {
        $data = str_repeat('y', Hm_Crypt_Stream_Writer::CHUNK_SIZE + 10);
        $writer = new Hm_Crypt_Stream_Writer($this->path, 'testkey');
        $writer->write($data);
        $writer->close();

        /* drop the final chunk (which carries the final-flag) */
        $contents = file_get_contents($this->path);
        $truncated = substr($contents, 0, 16 + 4 + Hm_Crypt_Stream_Writer::CHUNK_SIZE + 1 + 32);
        file_put_contents($this->path, $truncated);

        $reader = new Hm_Crypt_Stream_Reader($this->path, 'testkey');
        $this->expectException(Exception::class);
        $this->read_all($reader);
    }
}
