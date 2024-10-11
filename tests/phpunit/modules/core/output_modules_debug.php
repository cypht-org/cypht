<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Core_Output_Modules_Debug extends TestCase {
    public function setUp(): void {
        define('DEBUG_MODE', true);
        require __DIR__.'/../../bootstrap.php';
        require __DIR__.'/../../helpers.php';
        require APP_PATH.'modules/core/modules.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_header_css_debug() {
        $test = new Output_Test('header_css', 'core');
        $test->handler_response = array('router_module_list' => array('core'));
        $res = $test->run();
        $this->assertEquals(array('<link href="modules/themes/assets/default/css/default.css?v=asdf" media="all" rel="stylesheet" type="text/css" /><link href="vendor/twbs/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" type="text/css" /><link href="modules/core/site.css" media="all" rel="stylesheet" type="text/css" /><style type="text/css">@font-face {font-family:"Behdad";src:url("modules/core/assets/fonts/Behdad/Behdad-Regular.woff2") format("woff2"),url("modules/core/assets/fonts/Behdad/Behdad-Regular.woff") format("woff");</style>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     * @dataProvider router_module_list_provider
     */
    public function test_page_js_debug($given_router_module_list) {
        $test = new Output_Test('page_js', 'core');
        $test->handler_response = array('encrypt_ajax_requests' => true, 'router_module_list' => $given_router_module_list);
        $res = $test->run();
        $dependant_scripts = array('vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js');
        $third_party_scripts = array('cash.min.js', 'resumable.min.js', 'ays-beforeunload-shim.js', 'jquery.are-you-sure.js', 'sortable.min.js', 'forge.min.js');
        $expected_scripts = array_merge($dependant_scripts, array_map(function($script) { return 'third_party/'.$script; }, $third_party_scripts));
        
        // The navigation utils and core's site.js should be included before any other module
        $expected_scripts[] = 'modules/core/navigation/utils.js';
        $expected_scripts[] = 'modules/core/site.js';

        foreach (glob(APP_PATH.'modules'.DIRECTORY_SEPARATOR.'**', GLOB_ONLYDIR | GLOB_MARK) as $module) {
            $name = str_replace(array(APP_PATH, 'modules', DIRECTORY_SEPARATOR), '', $module);
            if (in_array($name, $given_router_module_list)) {
                // js_modules
                $directoriesPattern = str_replace('/', DIRECTORY_SEPARATOR, "{*,*/*}");
                foreach (glob($module.'js_modules' . DIRECTORY_SEPARATOR . $directoriesPattern . "*.js", GLOB_BRACE) as $js) {
                    $expected_scripts[] = WEB_ROOT.str_replace(APP_PATH, '', $js);
                }
                if ($name === 'core') {
                    continue;
                }
                if (is_readable($module.'site.js')) {
                    $expected_scripts[] = 'modules/' . $name . '/site.js';
                }
            }
        }

        // core navigation modules included at the end when handlers have been processed
        $expected_scripts[] = 'modules/core/navigation/routes.js';
        $expected_scripts[] = 'modules/core/navigation/navigation.js';

        $expected_output = '';
        foreach ($expected_scripts as $script) {
            $expected_output .= '<script type="text/javascript" src="'.$script.'"></script>';
        }

        $this->assertEquals(array($expected_output), $res->output_response);
    }

    static function router_module_list_provider() {
        return [
            'one module' => [['core']],
            'two modules' => [['core', 'imap']],
            'several modules' => [['core', 'imap', 'inline_message', 'local_contacts']]
        ];
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_main_menu_start_debug() {
        $test = new Output_Test('main_menu_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="src_name main_menu d-flex justify-content-between pe-2" data-source=".main">Main <span title="Running in debug mode. See https://cypht.org/install.html Section 6 for more detail." class="debug_title">Debug</span><i class="bi bi-chevron-down"></i></div><div class="main"><ul class="folders">'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<div class="src_name main_menu d-flex justify-content-between pe-2" data-source=".main">Main <span title="Running in debug mode. See https://cypht.org/install.html Section 6 for more detail." class="debug_title">Debug</span><i class="bi bi-chevron-down"></i></div><div class="main"><ul class="folders">'), $res->output_response);
    }
}
