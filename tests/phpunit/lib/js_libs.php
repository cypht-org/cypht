<?php

/**
 * Unit tests for js_libs.php (get_js_libs, get_js_libs_content)
 * @package lib/tests
 */

use PHPUnit\Framework\TestCase;

class Hm_Test_JS_Libs extends TestCase {

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_js_libs_returns_script_tags_for_all_libs(): void {
        $result = get_js_libs();

        foreach (JS_LIBS as $path) {
            $this->assertStringContainsString(
                '<script type="text/javascript" src="'.$path.'"></script>',
                $result
            );
        }
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_js_libs_excludes_specified_paths(): void {
        $allLibs    = JS_LIBS;
        $firstPath  = reset($allLibs);
        $secondPath = next($allLibs);

        $result = get_js_libs([$firstPath, $secondPath]);

        $this->assertStringNotContainsString($firstPath, $result);
        $this->assertStringNotContainsString($secondPath, $result);

        // all others are still present
        foreach (array_slice(array_values($allLibs), 2) as $path) {
            $this->assertStringContainsString($path, $result);
        }
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_js_libs_returns_empty_string_when_all_excluded(): void {
        $result = get_js_libs(array_values(JS_LIBS));
        $this->assertSame('', $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_js_libs_includes_web_root_prefix(): void {
        // WEB_ROOT is defined as '' in the test bootstrap, but the function
        // always concatenates it, so each src starts with WEB_ROOT (empty here).
        $result = get_js_libs();
        $this->assertStringContainsString('<script type="text/javascript" src="', $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_js_libs_content_returns_non_empty_string(): void {
        $result = get_js_libs_content();
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_js_libs_content_excludes_by_key(): void {
        $allKeys = array_keys(JS_LIBS);
        $firstKey = $allKeys[0];

        $withAll     = get_js_libs_content();
        $withExclude = get_js_libs_content([$firstKey]);

        $this->assertLessThan(strlen($withAll), strlen($withExclude));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_js_libs_content_returns_empty_string_when_all_excluded(): void {
        $result = get_js_libs_content(array_keys(JS_LIBS));
        $this->assertSame('', $result);
    }

    /**
     * Content grows when fewer libraries are excluded.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_js_libs_content_grows_with_fewer_exclusions(): void {
        $keys = array_keys(JS_LIBS);

        $excludeTwo = get_js_libs_content([$keys[0], $keys[1]]);
        $excludeOne = get_js_libs_content([$keys[0]]);

        $this->assertGreaterThan(strlen($excludeTwo), strlen($excludeOne));
    }
}
