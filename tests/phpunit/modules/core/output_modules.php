<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Core_Output_Modules extends TestCase {

    public function setUp(): void {
        require __DIR__.'/../../bootstrap.php';
        require __DIR__.'/../../helpers.php';
        require APP_PATH.'modules/core/modules.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_from_folder_list() {
        $test = new Output_Test('search_from_folder_list', 'core');
        $test->run();
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals('<li class="menu_search mb-2"><form method="get"><div class="input-group"><a href="?page=search" class="input-group-text" id="basic-addon1"><i class="bi bi-search"></i></a><input type="hidden" name="page" value="search" /><input type="search" class="search_terms form-control form-control-sm" aria-describedby="basic-addon1" name="search_terms" placeholder="Search" /></div></form></li>', $res->output_data['formatted_folder_list']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_content_start() {
        $test = new Output_Test('search_content_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="search_content px-0"><div class="content_title px-3 d-flex align-items-center"><a class="toggle_link" href="#"><i class="bi bi-check-square-fill"></i></a><div class="msg_controls fs-6 d-none gap-1 align-items-center"><div class="dropdown on_mobile"><button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" id="coreMsgControlDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="true">Actions</button><ul class="dropdown-menu" aria-labelledby="coreMsgControlDropdown"><li><a class="dropdown-item msg_read core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="read">Read</a></li><li><a class="dropdown-item msg_unread core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="unread">Unread</a></li><li><a class="dropdown-item msg_flag core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="flag">Flag</a></li><li><a class="dropdown-item msg_unflag core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="unflag">Unflag</a></li><li><a class="dropdown-item msg_delete core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="delete">Delete</a></li><li><a class="dropdown-item msg_archive core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="archive">Archive</a></li></ul></div><a class="msg_read core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="read">Read</a><a class="msg_unread core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="unread">Unread</a><a class="msg_flag core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="flag">Flag</a><a class="msg_unflag core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="unflag">Unflag</a><a class="msg_delete core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="delete">Delete</a><a class="msg_archive core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="archive">Archive</a></div>Search'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_content_end() {
        $test = new Output_Test('search_content_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_reminder() {
        $test = new Output_Test('save_reminder', 'core');
        $res = $test->run();
        $this->assertEquals(array(), $res->output_response);
        $test->handler_response = array('changed_settings' => array('foo', 'bar'));
        $res = $test->run();
        $this->assertEquals(array('<div class="save_reminder"><a title="You have unsaved changes" href="?page=save"><i class="bi bi-save2-fill fs-4"></i></a></div>'), $res->output_response);
        $test->handler_response = array('single_server_mode' => true);
        $res = $test->run();
        $this->assertEquals(array('single_server_mode' => true), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_form_start() {
        $test = new Output_Test('search_form_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="search_form"><form class="d-flex align-items-center" method="get">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_form_content() {
        $test = new Output_Test('search_form_content', 'core');
        $res = $test->run();
        $this->assertEquals(array('<input type="hidden" name="page" value="search" /> <label class="screen_reader" for="search_terms">Search Terms</label><input required placeholder="Search Terms" id="search_terms" type="search" class="search_terms form-control form-control-sm" name="search_terms" value="" /> <label class="screen_reader" for="search_fld">Search Field</label><select class="form-select form-select-sm w-auto" id="search_fld" name="search_fld"><option selected="selected" value="TEXT">Entire message</option><option value="BODY">Message body</option><option value="SUBJECT">Subject</option><option value="FROM">From</option><option value="TO">To</option><option value="CC">Cc</option></select> <label class="screen_reader" for="search_since">Search Since</label><select name="search_since" id="search_since" class="message_list_since form-select form-select-sm w-auto" data-default-value="-1 week"><option value="today">Today</option><option selected="selected" value="-1 week">Last 7 days</option><option value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select><select name="sort" style="width: 150px" class="combined_sort form-select form-select-sm"><option value="4">Arrival Date &darr;</option><option value="-4">Arrival Date &uarr;</option><option value="2">From &darr;</option><option value="-2">From &uarr;</option><option value="3">Subject &darr;</option><option value="-3">Subject &uarr;</option></select> | <input type="submit" class="search_update btn btn-primary btn-sm" value="Update" /> <input type="button" class="search_reset btn btn-light border btn-sm" value="Reset" />'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_form_end() {
        $test = new Output_Test('search_form_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</form></div><div class="list_controls no_mobile d-flex gap-3 align-items-center"><a class="refresh_link ms-3" title="Refresh" href="#"><i class="bi bi-arrow-clockwise refresh_list"></i></a><a href="#" title="Sources" class="source_link"><i class="bi bi-folder-fill refresh_list"></i></a></div>
    <div class="list_controls on_mobile">
        <i class="bi bi-filter-circle" onclick="listControlsMenu()"></i>
        <div id="list_controls_menu" classs="list_controls_menu"><a class="refresh_link ms-3" title="Refresh" href="#"><i class="bi bi-arrow-clockwise refresh_list"></i></a><a href="#" title="Sources" class="source_link"><i class="bi bi-folder-fill refresh_list"></i></a></div>
    </div><div class="list_sources"><div class="src_title fs-5 mb-2">Sources</div></div></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_results_table_end() {
        $test = new Output_Test('search_results_table_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</tbody></table>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_js_search_data() {
        $test = new Output_Test('js_search_data', 'core');
        $res = $test->run();
        $this->assertEquals(array('<script type="text/javascript" id="search-data">var hm_search_terms = function() { return ""; };var hm_run_search = function() { return "0"; };</script>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_login_end() {
        $test = new Output_Test('login_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</form>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_fancy_login_end() {
        $test = new Output_Test('login_end', 'core');
        $test->handler_response = array('fancy_login_allowed' => true);
        $res = $test->run();
        $this->assertEquals(array('</form></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_login_start() {
        $test = new Output_Test('login_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<form class="login_form" method="POST">'), $res->output_response);
        $test->handler_response = array('router_login_state' => true);
        $res = $test->run();
        $this->assertEquals(array('<form class="logout_form" method="POST">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_fancy_login_start() {
        $test = new Output_Test('login_start', 'core');
        $test->handler_response = array('fancy_login_allowed' => true);
        $res = $test->run();
        $this->assertEquals(array('<style type="text/css">body,html{max-width:100vw !important; max-height:100vh !important; overflow:hidden !important;}.form-container{background-color:#f1f1f1;background: linear-gradient( rgba(4, 26, 0, 0.85), rgba(4, 26, 0, 0.85)), url(modules/core/assets/images/cloud.jpg);background-attachment: fixed;background-position: center;background-repeat: no-repeat;background-size: cover;display:grid; place-items:center; height:100vh; width:100vw;} .logged_out{display:block !important;}.sys_messages{position:fixed;right:20px;top:15px;min-height:30px;display:none;background-color:#fff;color:teal;margin-top:0px;padding:15px;padding-bottom:5px;white-space:nowrap;border:solid 1px #999;border-radius:5px;filter:drop-shadow(4px 4px 4px #ccc);z-index:101;}.g-recaptcha{margin:0px 10px 10px 10px;}.mobile .g-recaptcha{margin:0px 10px 5px 10px;}.title{font-weight:normal;padding:0px;margin:0px;margin-left:20px;margin-bottom:20px;letter-spacing:-1px;color:#999;}html,body{min-width:100px !important;background-color:#fff;}body{background:linear-gradient(180deg,#faf6f5,#faf6f5,#faf6f5,#faf6f5,#fff);font-size:1em;color:#333;font-family:Arial;padding:0px;margin:0px;min-width:700px;font-size:100%;}input,option,select{font-size:100%;padding:3px;}textarea,select,input{border:solid 1px #ddd;background-color:#fff;color:#333;border-radius:3px;}.screen_reader{position:absolute;top:auto;width:1px;height:1px;overflow:hidden;}.login_form{display:flex; justify-content:space-evenly; align-items:center; flex-direction:column;font-size:90%;padding-top:60px;height:360px;border-radius:20px 20px 20px 20px;margin:0px;background-color:rgba(0,0,0,.6);min-width:300px;}.login_form input{clear:both;float:left;padding:4px;margin-top:10px;margin-bottom:10px;}#username,#password{width:200px; height:25px;} .err{color:red !important;}.long_session{float:left;}.long_session input{padding:0px;float:none;font-size:18px;}.mobile .long_session{float:left;clear:both;} @media screen and (min-width:400px){.login_form{min-width:400px;}}.user-icon_signin{display:block; background-color:white; border-radius:100%; padding:10px; height:40px; margin-top:-120px; box-shadow: #6eb549 .4px 2.4px 6.2px; }.label_signin{width:210px; margin:0px 0px -18px 0px;color:#fff;opacity:0.7;} @media (max-height : 500px){ .user-icon_signin{display:none;}}
                    </style><div class="form-container"><form class="login_form" method="POST">'), $res->output_response);
        $test->handler_response = array('router_login_state' => true, 'fancy_login_allowed' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="form-container"><form class="logout_form" method="POST">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_login() {
        $test = new Output_Test('login', 'core');
        $test->handler_response = array('allow_long_session' => true, 'router_login_state' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="modal fade" id="confirmLogoutModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="confirmLogoutModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                    <h5 class="modal-title" id="confirmLogoutModalLabel">Do you want to log out?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="hm_page_key" value="" />
                        <p class="text-wrap">Unsaved changes will be lost! Re-enter your password to save and exit. <a href="?page=save">More info</a></p>
                        <input type="text" value="cypht_user" autocomplete="username" style="display: none;"/>
                        <div class="my-3 form-floating">
                            <input id="logout_password" autocomplete="current-password" name="password" class="form-control warn_on_paste" type="password" placeholder="Password">
                            <label for="logout_password" class="form-label screen-reader">Password</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input class="cancel_logout save_settings btn btn-secondary" data-bs-dismiss="modal" type="button" value="Cancel" />
                        <input class="save_settings btn btn-primary" id="logout_without_saving" type="submit" name="logout" value="Just Logout" />
                        <input class="save_settings btn btn-primary" type="submit" name="save_and_logout" value="Save and Logout" />
                    </div>
                </div>
                </div>
            </div>'), $res->output_response);
        $test->handler_response = array('allow_long_session' => true, 'router_login_state' => false);
        $res = $test->run();
        $this->assertEquals(array('<div class="bg-light"><div class="d-flex align-items-center justify-content-center vh-100 p-3">
                    <div class="card col-12 col-md-6 col-lg-4 p-3">
                        <div class="card-body">
                            <p class="text-center"><img class="w-50" src="modules/core/assets/images/logo_dark.svg"></p>
                            <div class="mt-5">
                                <div class="mb-3 form-floating">
                                    <input autofocus required type="text" placeholder="Username" id="username" name="username" class="form-control">
                                    <label for="username" class="form-label screen-reader">Username</label>
                                </div>
                                <div class="mb-3 form-floating">
                                    <input required type="password" id="password" placeholder="Password" name="password" class="form-control">
                                    <label for="password" class="form-label screen-reader">Password</label>
                                </div><div class="d-grid"><div class="form-check form-switch long-session">
                <input type="checkbox" id="stay_logged_in" value="1" name="stay_logged_in" class="form-check-input">
                <label class="form-check-label" for="stay_logged_in">Stay logged in</label>
            </div><input type="hidden" name="hm_page_key" value="" />
                                    <input type="submit" id="login" class="btn btn-primary btn-lg" value="Login">
                                </div>
                            </div>
                        </div>
                    </div>
                </div></div>'), $res->output_response);
        $test->handler_response = array('changed_settings' => array('foo'), 'router_login_state' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="modal fade" id="confirmLogoutModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="confirmLogoutModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                    <h5 class="modal-title" id="confirmLogoutModalLabel">Do you want to log out?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="hm_page_key" value="" />
                        <p class="text-wrap">Unsaved changes will be lost! Re-enter your password to save and exit. <a href="?page=save">More info</a></p>
                        <input type="text" value="cypht_user" autocomplete="username" style="display: none;"/>
                        <div class="my-3 form-floating">
                            <input id="logout_password" autocomplete="current-password" name="password" class="form-control warn_on_paste" type="password" placeholder="Password">
                            <label for="logout_password" class="form-label screen-reader">Password</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input class="cancel_logout save_settings btn btn-secondary" data-bs-dismiss="modal" type="button" value="Cancel" />
                        <input class="save_settings btn btn-primary" id="logout_without_saving" type="submit" name="logout" value="Just Logout" />
                        <input class="save_settings btn btn-primary" type="submit" name="save_and_logout" value="Save and Logout" />
                    </div>
                </div>
                </div>
            </div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_fancy_login() {
        $test = new Output_Test('login', 'core');
        $test->handler_response = array('allow_long_session' => true, 'router_login_state' => true, 'fancy_login_allowed' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="modal fade" id="confirmLogoutModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="confirmLogoutModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                    <h5 class="modal-title" id="confirmLogoutModalLabel">Do you want to log out?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="hm_page_key" value="" />
                        <p class="text-wrap">Unsaved changes will be lost! Re-enter your password to save and exit. <a href="?page=save">More info</a></p>
                        <input type="text" value="cypht_user" autocomplete="username" style="display: none;"/>
                        <div class="my-3 form-floating">
                            <input id="logout_password" autocomplete="current-password" name="password" class="form-control warn_on_paste" type="password" placeholder="Password">
                            <label for="logout_password" class="form-label screen-reader">Password</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input class="cancel_logout save_settings btn btn-secondary" data-bs-dismiss="modal" type="button" value="Cancel" />
                        <input class="save_settings btn btn-primary" id="logout_without_saving" type="submit" name="logout" value="Just Logout" />
                        <input class="save_settings btn btn-primary" type="submit" name="save_and_logout" value="Save and Logout" />
                    </div>
                </div>
                </div>
            </div>'), $res->output_response);
        $test->handler_response = array('allow_long_session' => true, 'router_login_state' => false, 'fancy_login_allowed' => true);
        $res = $test->run();
        $this->assertEquals(array('<svg class="user-icon_signin" viewBox="0 0 20 20"><path d="M12.075,10.812c1.358-0.853,2.242-2.507,2.242-4.037c0-2.181-1.795-4.618-4.198-4.618S5.921,4.594,5.921,6.775c0,1.53,0.884,3.185,2.242,4.037c-3.222,0.865-5.6,3.807-5.6,7.298c0,0.23,0.189,0.42,0.42,0.42h14.273c0.23,0,0.42-0.189,0.42-0.42C17.676,14.619,15.297,11.677,12.075,10.812 M6.761,6.775c0-2.162,1.773-3.778,3.358-3.778s3.359,1.616,3.359,3.778c0,2.162-1.774,3.778-3.359,3.778S6.761,8.937,6.761,6.775 M3.415,17.69c0.218-3.51,3.142-6.297,6.704-6.297c3.562,0,6.486,2.787,6.705,6.297H3.415z"></path></svg><img src="modules/core/assets/images/logo.svg" style="height:90px;"><!--h1 class="title"></h1--> <input type="hidden" name="hm_page_key" value="" /> <label class="label_signin" for="username">Username</label><input autofocus required type="text" placeholder="Username" id="username" name="username" value=""> <label class="label_signin" for="password">Password</label><input required type="password" id="password" placeholder="Password" name="password"><div class="form-check form-switch long-session">
                <input type="checkbox" id="stay_logged_in" value="1" name="stay_logged_in" class="form-check-input">
                <label class="form-check-label" for="stay_logged_in">Stay logged in</label>
            </div> <input style="cursor:pointer; display:block; width:210px; background-color:#6eb549; color:white; height:40px;" type="submit" id="login" value="Login" />'), $res->output_response);
        $test->handler_response = array('changed_settings' => array('foo'), 'router_login_state' => true, 'fancy_login_allowed' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="modal fade" id="confirmLogoutModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="confirmLogoutModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                    <h5 class="modal-title" id="confirmLogoutModalLabel">Do you want to log out?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="hm_page_key" value="" />
                        <p class="text-wrap">Unsaved changes will be lost! Re-enter your password to save and exit. <a href="?page=save">More info</a></p>
                        <input type="text" value="cypht_user" autocomplete="username" style="display: none;"/>
                        <div class="my-3 form-floating">
                            <input id="logout_password" autocomplete="current-password" name="password" class="form-control warn_on_paste" type="password" placeholder="Password">
                            <label for="logout_password" class="form-label screen-reader">Password</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input class="cancel_logout save_settings btn btn-secondary" data-bs-dismiss="modal" type="button" value="Cancel" />
                        <input class="save_settings btn btn-primary" id="logout_without_saving" type="submit" name="logout" value="Just Logout" />
                        <input class="save_settings btn btn-primary" type="submit" name="save_and_logout" value="Save and Logout" />
                    </div>
                </div>
                </div>
            </div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_server_content_start() {
        $test = new Output_Test('server_content_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="content_title">Servers</div><div class="server_content">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_server_content_end() {
        $test = new Output_Test('server_content_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_date() {
        $test = new Output_Test('date', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="date"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_msgs() {
        Hm_Msgs::add('ERRfoo');
        Hm_Msgs::add('foo');
        $test = new Output_Test('msgs', 'core');
        $test->handler_response = array('router_login_state' => false);
        $res = $test->run();
        $this->assertEquals(array('<div class="d-none position-fixed top-0 end-0 mt-3 me-3 sys_messages logged_out"><div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="bi bi-exclamation-triangle me-2"></i><span class="danger">foo</span><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><div class="alert alert-success alert-dismissible fade show" role="alert"><i class="bi bi-check-circle me-2"></i><span class="info">foo</span><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_header_start() {
        $test = new Output_Test('header_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<!DOCTYPE html><html dir="ltr" class="ltr_page" lang=en><head><meta name="apple-mobile-web-app-capable" content="yes" /><meta name="mobile-web-app-capable" content="yes" /><meta name="apple-mobile-web-app-status-bar-style" content="black" /><meta name="theme-color" content="#888888" /><meta charset="utf-8" />'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_header_end() {
        $test = new Output_Test('header_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</head>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_content_start() {
        $test = new Output_Test('content_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<body class=""><noscript class="noscript">You need to have Javascript enabled to use , sorry about that!</noscript><script type="text/javascript">sessionStorage.clear();</script><div class="cypht-layout">'), $res->output_response);
        $test->handler_response = array('changed_settings' => array(0), 'router_login_state' => true);
        $res = $test->run();
        $this->assertEquals(array('<body class=""><noscript class="noscript">You need to have Javascript enabled to use , sorry about that!</noscript><input type="hidden" id="hm_page_key" value="" /><a class="unsaved_icon" href="?page=save" title="Unsaved Changes"><i class="bi bi-save2-fill fs-5 unsaved_reminder"></i></a><div class="cypht-layout">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_header_content() {
        $test = new Output_Test('header_content', 'core');
        $res = $test->run();
        $this->assertEquals(array('<title></title><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0"><link rel="icon" class="tab_icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3NkkEKACEMA2d92fpzfVn3oHhYqAF7qIFeSpImUMjGA1jEoEQTFKAC/UDbp3bhBRqj0m7a5C78F56Rx5MEdUBHFMlkV09ogN3xB7kG+fgA0tc160Jy09wAAAAASUVORK5CYII=" ><base href="" />'), $res->output_response);
        $test->handler_response = array('router_login_state' => true, 'page_title' => 'foo');
        $res = $test->run();
        $this->assertEquals(array('<title>foo</title><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0"><link rel="icon" class="tab_icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3NkkEKACEMA2d92fpzfVn3oHhYqAF7qIFeSpImUMjGA1jEoEQTFKAC/UDbp3bhBRqj0m7a5C78F56Rx5MEdUBHFMlkV09ogN3xB7kG+fgA0tc160Jy09wAAAAASUVORK5CYII=" ><base href="" />'), $res->output_response);
        $test->handler_response = array('router_login_state' => true, 'mailbox_list_title' => array('foo'));
        $res = $test->run();
        $this->assertEquals(array('<title>foo</title><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0"><link rel="icon" class="tab_icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3NkkEKACEMA2d92fpzfVn3oHhYqAF7qIFeSpImUMjGA1jEoEQTFKAC/UDbp3bhBRqj0m7a5C78F56Rx5MEdUBHFMlkV09ogN3xB7kG+fgA0tc160Jy09wAAAAASUVORK5CYII=" ><base href="" />'), $res->output_response);
        $test->handler_response = array('router_page_name' => 'home', 'router_login_state' => true, 'list_path' => 'message_list');
        $res = $test->run();
        //$this->assertEquals(array('<title>Message List</title><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0"><link rel="icon" class="tab_icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3NkkEKACEMA2d92fpzfVn3oHhYqAF7qIFeSpImUMjGA1jEoEQTFKAC/UDbp3bhBRqj0m7a5C78F56Rx5MEdUBHFMlkV09ogN3xB7kG+fgA0tc160Jy09wAAAAASUVORK5CYII=" ><base href="" />'), $res->output_response);
        //$test->handler_response = array('router_login_state' => true, 'router_page_name' => 'notfound');
        //$res = $test->run();
        //$this->assertEquals(array('<title>Nope</title><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0"><link rel="icon" class="tab_icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3NkkEKACEMA2d92fpzfVn3oHhYqAF7qIFeSpImUMjGA1jEoEQTFKAC/UDbp3bhBRqj0m7a5C78F56Rx5MEdUBHFMlkV09ogN3xB7kG+fgA0tc160Jy09wAAAAASUVORK5CYII=" ><base href="" />'), $res->output_response);
        //$test->handler_response = array('router_login_state' => true, 'router_page_name' => 'home');
        //$res = $test->run();
        //$this->assertEquals(array('<title>Home</title><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0"><link rel="icon" class="tab_icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3NkkEKACEMA2d92fpzfVn3oHhYqAF7qIFeSpImUMjGA1jEoEQTFKAC/UDbp3bhBRqj0m7a5C78F56Rx5MEdUBHFMlkV09ogN3xB7kG+fgA0tc160Jy09wAAAAASUVORK5CYII=" ><base href="" />'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_header_css() {
        $test = new Output_Test('header_css', 'core');
        $res = $test->run();
        $this->assertEquals(array('<link href="modules/themes/assets/default/css/default.css?v=asdf" media="all" rel="stylesheet" type="text/css" /><link href="site.css?v=asdf" media="all" rel="stylesheet" type="text/css" /><style type="text/css">@font-face {font-family:"Behdad";src:url("modules/core/assets/fonts/Behdad/Behdad-Regular.woff2") format("woff2"),url("modules/core/assets/fonts/Behdad/Behdad-Regular.woff") format("woff");</style>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_header_css_integrity() {
        define('CSS_HASH', 'foo');
        $test = new Output_Test('header_css', 'core');
        $test->handler_response = array('router_module_list', array('core'));
        $res = $test->run();
        $this->assertEquals(array('<link href="modules/themes/assets/default/css/default.css?v=asdf" media="all" rel="stylesheet" type="text/css" /><link href="site.css?v=asdf" integrity="foo" media="all" rel="stylesheet" type="text/css" /><style type="text/css">@font-face {font-family:"Behdad";src:url("modules/core/assets/fonts/Behdad/Behdad-Regular.woff2") format("woff2"),url("modules/core/assets/fonts/Behdad/Behdad-Regular.woff") format("woff");</style>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_page_js_integrity() {
        define('JS_HASH', 'foo');
        $test = new Output_Test('page_js', 'core');
        $res = $test->run();
        $this->assertEquals(array('<script type="text/javascript" integrity="foo" src="site.js?v=asdf" async></script>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_page_js() {
        $test = new Output_Test('page_js', 'core');
        $res = $test->run();
        $this->assertEquals(array('<script type="text/javascript" src="site.js?v=asdf" async></script>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_content_end() {
        $test = new Output_Test('content_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</div></body></html>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_js_data() {
        $test = new Output_Test('js_data', 'core');
        $test->handler_response = array('disable_delete_prompt' => true);
        $res = $test->run();
        $this->assertStringStartsWith('<script type="text/javascript" id="data-store">var globals = {};var hm_is_logged = function () { return 0; };var hm_empty_folder = function() { return "So alone"; };var hm_mobile = function() { return 0; };var hm_debug = function() { return "0"; };var hm_mailto = function() { return 0; };var hm_page_name = function() { return ""; };var hm_language_direction = function() { return "ltr"; };var hm_list_path = function() { return ""; };var hm_list_parent = function() { return ""; };var hm_msg_uid = function() { return Hm_Utils.get_from_global("msg_uid", ""); };var hm_encrypt_ajax_requests = function() { return ""; };var hm_encrypt_local_storage = function() { return ""; };var hm_web_root_path = function() { return ""; };var hm_flag_image_src = function() { return "<i class=\\"bi bi-star-half\\"></i>"; };var hm_check_dirty_flag = function() { return 0; };var hm_data_sources = function() { return []; };var hm_delete_prompt = function() { return true; };', implode($res->output_response));
        $test->handler_response = array();
        $res = $test->run();
        $this->assertStringStartsWith('<script type="text/javascript" id="data-store">var globals = {};var hm_is_logged = function () { return 0; };var hm_empty_folder = function() { return "So alone"; };var hm_mobile = function() { return 0; };var hm_debug = function() { return "0"; };var hm_mailto = function() { return 0; };var hm_page_name = function() { return ""; };var hm_language_direction = function() { return "ltr"; };var hm_list_path = function() { return ""; };var hm_list_parent = function() { return ""; };var hm_msg_uid = function() { return Hm_Utils.get_from_global("msg_uid", ""); };var hm_encrypt_ajax_requests = function() { return ""; };var hm_encrypt_local_storage = function() { return ""; };var hm_web_root_path = function() { return ""; };var hm_flag_image_src = function() { return "<i class=\\"bi bi-star-half\\"></i>"; };var hm_check_dirty_flag = function() { return 0; };var hm_data_sources = function() { return []; };var hm_delete_prompt = function() { return confirm("Are you sure?"); };', implode($res->output_response));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_loading_icon() {
        $test = new Output_Test('loading_icon', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="loading_icon"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_settings_form() {
        $test = new Output_Test('start_settings_form', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="user_settings px-0"><div class="content_title px-3">Site Settings</div><form method="POST"><input type="hidden" name="hm_page_key" value="" /><div class="px-3"><table class="settings_table table table-borderless"><colgroup><col class="label_col"><col class="setting_col"></colgroup>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_page_setting() {
        $test = new Output_Test('start_page_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="start_page">First page after login</label></td><td><select class="form-select form-select-sm w-auto" id="start_page" name="start_page"><option selected="selected" value="none">None</option><option value="page=home">Home</option><option value="page=message_list&list_path=combined_inbox">Everything</option><option value="page=message_list&list_path=unread">Unread</option><option value="page=message_list&list_path=flagged">Flagged</option><option value="page=compose">Compose</option></select></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('start_page' => 'page=message_list&list_path=unread'));
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="start_page">First page after login</label></td><td><select class="form-select form-select-sm w-auto" id="start_page" name="start_page"><option value="none">None</option><option value="page=home">Home</option><option value="page=message_list&list_path=combined_inbox">Everything</option><option selected="selected" value="page=message_list&list_path=unread">Unread</option><option value="page=message_list&list_path=flagged">Flagged</option><option value="page=compose">Compose</option></select><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_select"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_list_style_setting() {
        $test = new Output_Test('list_style_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="list_style">Message list style</label></td><td><select class="form-select form-select-sm w-auto" id="list_style" name="list_style" data-default-value="email_style"><option selected="selected" value="email_style">Email</option><option value="news_style">News</option></select></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('list_style' => 'email_style'));
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="list_style">Message list style</label></td><td><select class="form-select form-select-sm w-auto" id="list_style" name="list_style" data-default-value="email_style"><option selected="selected" value="email_style">Email</option><option value="news_style">News</option></select></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_mailto_handler_setting() {
        $test = new Output_Test('mailto_handler_setting', 'core');
        $test->handler_response = array('user_settings' => array());
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label class="form-check-label" for="mailto_handler">Allow handling of mailto links</label></td><td><input class="form-check-input" type="checkbox"  value="1" id="mailto_handler" name="mailto_handler" data-default-value="false" /></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('mailto_handler' => true));
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label class="form-check-label" for="mailto_handler">Allow handling of mailto links</label></td><td><input class="form-check-input" type="checkbox"  checked="checked" value="1" id="mailto_handler" name="mailto_handler" data-default-value="false" /><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_checkbox"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_no_folder_icon_setting() {
        $test = new Output_Test('no_folder_icon_setting', 'core');
        $test->handler_response = array('user_settings' => array());
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label class="form-check-label" for="no_folder_icons">Hide folder list icons</label></td><td><input class="form-check-input" type="checkbox"  value="1" id="no_folder_icons" name="no_folder_icons" data-default-value="false" /></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('no_folder_icons' => true));
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label class="form-check-label" for="no_folder_icons">Hide folder list icons</label></td><td><input class="form-check-input" type="checkbox"  checked="checked" value="1" id="no_folder_icons" name="no_folder_icons" data-default-value="false" /><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_checkbox"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_no_password_setting() {
        $test = new Output_Test('no_password_setting', 'core');
        $test->handler_response = array('user_settings' => array());
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label class="form-check-label" for="no_password_save">Don\'t save account passwords between logins</label></td><td><input class="form-check-input" type="checkbox"  value="1" id="no_password_save" name="no_password_save" data-default-value="false" /></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('no_password_save' => true));
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label class="form-check-label" for="no_password_save">Don\'t save account passwords between logins</label></td><td><input class="form-check-input" type="checkbox"  checked="checked" value="1" id="no_password_save" name="no_password_save" data-default-value="false" /><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_checkbox"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_prompt_setting() {
        $test = new Output_Test('delete_prompt_setting', 'core');
        $test->handler_response = array('user_settings' => array());
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label class="form-check-label" for="disable_delete_prompt">Disable prompts when deleting</label></td><td><input class="form-check-input" type="checkbox"  value="1" id="disable_delete_prompt" name="disable_delete_prompt" data-default-value="false" /><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_checkbox"></i></span></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('disable_delete_prompt' => true));
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label class="form-check-label" for="disable_delete_prompt">Disable prompts when deleting</label></td><td><input class="form-check-input" type="checkbox"  checked="checked" value="1" id="disable_delete_prompt" name="disable_delete_prompt" data-default-value="false" /><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_checkbox"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_flagged_settings() {
        $test = new Output_Test('start_flagged_settings', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr><td data-target=".flagged_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2"><i class="bi bi-flag-fill fs-5 me-2"></i>Flagged</td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_everything_settings() {
        $test = new Output_Test('start_everything_settings', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr><td data-target=".all_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2"><i class="bi bi-box2-fill fs-5 me-2"></i>Everything</td></tr>'), $res->output_response);
        $test->handler_response = array('single_server_mode' => true);
        $res = $test->run();
        $this->assertEquals(array('single_server_mode' => true), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_junk_settings() {
        $test = new Output_Test('start_junk_settings', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr><td data-target=".junk_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2"><i class="bi bi-envelope-x-fill fs-5 me-2"></i>Junk</td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_unread_settings() {
        $test = new Output_Test('start_unread_settings', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr><td data-target=".unread_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2"><i class="bi bi-envelope-fill fs-5 me-2"></i>Unread</td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_all_email_settings() {
        $test = new Output_Test('start_all_email_settings', 'core');
        $test->handler_response = array('router_module_list' => array());
        $res = $test->run();
        $this->assertEquals(array('router_module_list' => array()), $res->output_response);
        $test->handler_response = array('router_module_list' => array('imap'));
        $res = $test->run();
        $this->assertEquals(array('<tr><td data-target=".email_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2"><i class="bi bi-envelope-fill fs-5 me-2"></i>All Email</td></tr>'), $res->output_response);
        $test->handler_response = array('router_module_list' => array('imap'), 'single_server_mode' => true);
        $res = $test->run();
        $this->assertEquals(array('router_module_list' => array('imap'), 'single_server_mode' => true), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_general_settings() {
        $test = new Output_Test('start_general_settings', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr><td data-target=".general_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2"><i class="bi bi-gear-wide-connected fs-5 me-2"></i>General</td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_unread_source_max_setting() {
        $test = new Output_Test('unread_source_max_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="unread_setting"><td><label for="unread_per_source">Max messages per source</label></td><td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="unread_per_source" name="unread_per_source" value="20" data-default-value="20" /></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('unread_per_source' => 10));
        $res = $test->run();
        $this->assertEquals(array('<tr class="unread_setting"><td><label for="unread_per_source">Max messages per source</label></td><td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="unread_per_source" name="unread_per_source" value="10" data-default-value="20" /><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_input"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_unread_since_setting() {
        $test = new Output_Test('unread_since_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="unread_setting"><td><label for="unread_since">Show messages received since</label></td><td><select name="unread_since" id="unread_since" class="message_list_since form-select form-select-sm w-auto" data-default-value="-1 week"><option value="today">Today</option><option selected="selected" value="-1 week">Last 7 days</option><option value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('unread_since' => '-2 weeks'));
        $res = $test->run();
        $this->assertEquals(array('<tr class="unread_setting"><td><label for="unread_since">Show messages received since</label></td><td><select name="unread_since" id="unread_since" class="message_list_since form-select form-select-sm w-auto" data-default-value="-1 week"><option value="today">Today</option><option value="-1 week">Last 7 days</option><option selected="selected" value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_select"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_junk_since_setting() {
        $test = new Output_Test('junk_since_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="junk_setting"><td><label for="junk_since">Show junk messages since</label></td><td><select name="junk_since" id="junk_since" class="message_list_since form-select form-select-sm w-auto" data-default-value="-1 week"><option value="today">Today</option><option selected="selected" value="-1 week">Last 7 days</option><option value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('junk_since' => '-2 weeks'));
        $res = $test->run();
        $this->assertEquals(array('<tr class="junk_setting"><td><label for="junk_since">Show junk messages since</label></td><td><select name="junk_since" id="junk_since" class="message_list_since form-select form-select-sm w-auto" data-default-value="-1 week"><option value="today">Today</option><option value="-1 week">Last 7 days</option><option selected="selected" value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_select"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_drafts_since_setting() {
        $test = new Output_Test('drafts_since_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="drafts_setting"><td><label for="drafts_since">Show draft messages since</label></td><td><select name="drafts_since" id="drafts_since" class="message_list_since form-select form-select-sm w-auto" data-default-value="-1 week"><option value="today">Today</option><option selected="selected" value="-1 week">Last 7 days</option><option value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('drafts_since' => '-2 weeks'));
        $res = $test->run();
        $this->assertEquals(array('<tr class="drafts_setting"><td><label for="drafts_since">Show draft messages since</label></td><td><select name="drafts_since" id="drafts_since" class="message_list_since form-select form-select-sm w-auto" data-default-value="-1 week"><option value="today">Today</option><option value="-1 week">Last 7 days</option><option selected="selected" value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_select"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_trash_since_setting() {
        $test = new Output_Test('trash_since_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="trash_setting"><td><label for="trash_since">Show trash messages since</label></td><td><select name="trash_since" id="trash_since" class="message_list_since form-select form-select-sm w-auto" data-default-value="-1 week"><option value="today">Today</option><option selected="selected" value="-1 week">Last 7 days</option><option value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('trash_since' => '-2 weeks'));
        $res = $test->run();
        $this->assertEquals(array('<tr class="trash_setting"><td><label for="trash_since">Show trash messages since</label></td><td><select name="trash_since" id="trash_since" class="message_list_since form-select form-select-sm w-auto" data-default-value="-1 week"><option value="today">Today</option><option value="-1 week">Last 7 days</option><option selected="selected" value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_select"></i></span></td></tr>'), $res->output_response);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_junk_source_max_setting() {
        $test = new Output_Test('junk_source_max_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="junk_setting"><td><label for="junk_per_source">Max messages per source</label></td><td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="junk_per_source" name="junk_per_source" value="20" data-default-value="20" /></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('junk_per_source' => 10));
        $res = $test->run();
        $this->assertEquals(array('<tr class="junk_setting"><td><label for="junk_per_source">Max messages per source</label></td><td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="junk_per_source" name="junk_per_source" value="10" data-default-value="20" /><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_input"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_drafts_source_max_setting() {
        $test = new Output_Test('drafts_source_max_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="drafts_setting"><td><label for="drafts_per_source">Max messages per source</label></td><td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="drafts_per_source" name="drafts_per_source" value="20" data-default-value="20" /></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('drafts_per_source' => 10));
        $res = $test->run();
        $this->assertEquals(array('<tr class="drafts_setting"><td><label for="drafts_per_source">Max messages per source</label></td><td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="drafts_per_source" name="drafts_per_source" value="10" data-default-value="20" /><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_input"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_trash_source_max_setting() {
        $test = new Output_Test('trash_source_max_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="trash_setting"><td><label for="trash_per_source">Max messages per source</label></td><td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="trash_per_source" name="trash_per_source" value="20" data-default-value="20" /></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('trash_per_source' => 10));
        $res = $test->run();
        $this->assertEquals(array('<tr class="trash_setting"><td><label for="trash_per_source">Max messages per source</label></td><td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="trash_per_source" name="trash_per_source" value="10" data-default-value="20" /><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_input"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_flagged_source_max_setting() {
        $test = new Output_Test('flagged_source_max_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="flagged_setting"><td><label for="flagged_per_source">Max messages per source</label></td><td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="flagged_per_source" name="flagged_per_source" value="20" data-default-value="20" /></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('flagged_per_source' => 10));
        $res = $test->run();
        $this->assertEquals(array('<tr class="flagged_setting"><td><label for="flagged_per_source">Max messages per source</label></td><td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="flagged_per_source" name="flagged_per_source" value="10" data-default-value="20" /><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_input"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_flagged_since_setting() {
        $test = new Output_Test('flagged_since_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="flagged_setting"><td><label for="flagged_since">Show messages received since</label></td><td><select name="flagged_since" id="flagged_since" class="message_list_since form-select form-select-sm w-auto" data-default-value="-1 week"><option value="today">Today</option><option selected="selected" value="-1 week">Last 7 days</option><option value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('flagged_since' => '-2 weeks'));
        $res = $test->run();
        $this->assertEquals(array('<tr class="flagged_setting"><td><label for="flagged_since">Show messages received since</label></td><td><select name="flagged_since" id="flagged_since" class="message_list_since form-select form-select-sm w-auto" data-default-value="-1 week"><option value="today">Today</option><option value="-1 week">Last 7 days</option><option selected="selected" value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_select"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_all_email_source_max_setting() {
        $test = new Output_Test('all_email_source_max_setting', 'core');
        $test->handler_response = array('user_settings' => array('all_email_per_source' => 10), 'router_module_list' => array());
        $res = $test->run();
        $this->assertEquals(array('user_settings' => array('all_email_per_source' => 10), 'router_module_list' => array()), $res->output_response);
        $test->handler_response = array('user_settings' => array('all_email_per_source' => 10), 'router_module_list' => array('imap'));
        $res = $test->run();
        $this->assertEquals(array('<tr class="email_setting"><td><label for="all_email_per_source">Max messages per source</label></td><td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="all_email_per_source" name="all_email_per_source" value="10" data-default-value="20" /><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_input"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_all_source_max_setting() {
        $test = new Output_Test('all_source_max_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="all_setting"><td><label for="all_per_source">Max messages per source</label></td><td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="all_per_source" name="all_per_source" value="20" data-default-value="20" /></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('all_per_source' => 10));
        $res = $test->run();
        $this->assertEquals(array('<tr class="all_setting"><td><label for="all_per_source">Max messages per source</label></td><td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="all_per_source" name="all_per_source" value="10" data-default-value="20" /><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_input"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_all_email_since_setting() {
        $test = new Output_Test('all_email_since_setting', 'core');
        $test->handler_response = array('user_settings' => array('all_email_since' => '-1 week'), 'router_module_list' => array());
        $res = $test->run();
        $this->assertEquals(array('user_settings' => array('all_email_since' => '-1 week'), 'router_module_list' => array()), $res->output_response);
        $test->handler_response = array('user_settings' => array('all_email_since' => '-2 weeks'), 'router_module_list' => array('imap'));
        $res = $test->run();
        $this->assertEquals(array('<tr class="email_setting"><td><label for="all_email_since">Show messages received since</label></td><td><select name="all_email_since" id="all_email_since" class="message_list_since form-select form-select-sm w-auto" data-default-value="-1 week"><option value="today">Today</option><option value="-1 week">Last 7 days</option><option selected="selected" value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_select"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_all_since_setting() {
        $test = new Output_Test('all_since_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="all_setting"><td><label for="all_since">Show messages received since</label></td><td><select name="all_since" id="all_since" class="message_list_since form-select form-select-sm w-auto" data-default-value="-1 week"><option value="today">Today</option><option selected="selected" value="-1 week">Last 7 days</option><option value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('all_since' => '-2 weeks'));
        $res = $test->run();
        $this->assertEquals(array('<tr class="all_setting"><td><label for="all_since">Show messages received since</label></td><td><select name="all_since" id="all_since" class="message_list_since form-select form-select-sm w-auto" data-default-value="-1 week"><option value="today">Today</option><option value="-1 week">Last 7 days</option><option selected="selected" value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_select"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_language_setting() {
        $test = new Output_Test('language_setting', 'core');
        $test->handler_response = array('language'=> 'en');
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="language">Language</label></td><td><select id="language" class="form-select form-select-sm w-auto" name="language"><option value="az">Azerbaijani</option><option value="pt-BR">Brazilian Portuguese</option><option value="zh-Hans">Chinese Simplified</option><option value="nl">Dutch</option><option selected="selected" value="en">English</option><option value="et">Estonian</option><option value="fa">Farsi</option><option value="fr">French</option><option value="de">German</option><option value="hu">Hungarian</option><option value="id">Indonesian</option><option value="it">Italian</option><option value="ja">Japanese</option><option value="ro">Romanian</option><option value="ru">Russian</option><option value="es">Spanish</option><option value="zh-TW">Traditional Chinese</option></select></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_timezone_setting() {
        $test = new Output_Test('timezone_setting', 'core');
        $res = $test->run();
        $this->assertTrue(mb_strlen($res->output_response[0]) > 0);
        $test->handler_response = array('user_settings' => array('timezone' => 'America/Chicago'));
        $res = $test->run();
        $this->assertTrue(mb_strlen($res->output_response[0]) > 0);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_msg_list_icons_setting() {
        $test = new Output_Test('msg_list_icons_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label class="form-check-label" for="show_list_icons">Show icons in message lists</label></td><td><input class="form-check-input" type="checkbox"  id="show_list_icons" name="show_list_icons" data-default-value="false" value="1" /></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('show_list_icons' => true));
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label class="form-check-label" for="show_list_icons">Show icons in message lists</label></td><td><input class="form-check-input" type="checkbox"  checked="checked" id="show_list_icons" name="show_list_icons" data-default-value="false" value="1" /><span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_checkbox"></i></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_end_settings_form() {
        $test = new Output_Test('end_settings_form', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr><td class="submit_cell" colspan="2"><input class="save_settings btn btn-primary" type="submit" name="save_settings" value="Save" /></td></tr></table></div></form><div class="px-3 d-flex justify-content-end"><form method="POST"><input type="hidden" name="hm_page_key" value="" /><input class="reset_factory_button btn btn-light border" type="submit" name="reset_factory" value="Restore Defaults" /></form></div></div>'), $res->output_response);
        $test->handler_response = array('single_server_mode' => true);
        $res = $test->run();
        $this->assertEquals(array('<tr><td class="submit_cell" colspan="2"><input class="save_settings btn btn-primary" type="submit" name="save_settings" value="Save" /></td></tr></table></div></form><div class="px-3 d-flex justify-content-end"><form method="POST"><input type="hidden" name="hm_page_key" value="" /><input class="reset_factory_button btn btn-light border" type="submit" name="reset_factory" value="Restore Defaults" /></form></div></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_folder_list_start() {
        $test = new Output_Test('folder_list_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<nav class="folder_cell"><div class="folder_list">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_folder_list_content_start() {
        $test = new Output_Test('folder_list_content_start', 'core');
        $res = $test->run();
        $this->assertEquals(array(), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => ''), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_main_menu_start() {
        $test = new Output_Test('main_menu_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<a href="?page=home" class="menu_home"><img class="app-logo" src="modules/core/assets/images/logo_dark.svg"></a><div class="main"><ul class="folders">'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<a href="?page=home" class="menu_home"><img class="app-logo" src="modules/core/assets/images/logo_dark.svg"></a><div class="main"><ul class="folders">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_main_menu_content() {
        $test = new Output_Test('main_menu_content', 'core');
        $test->handler_response = array('folder_sources' => array(array('email_folders', 'baz')));
        $res = $test->run();
        $this->assertEquals(array('<li class="menu_unread d-flex align-items-center"><a class="unread_link d-flex align-items-center" href="?page=message_list&amp;list_path=unread"><i class="bi bi-envelope-fill menu-icon"></i><span class="nav-label">Unread</span></a><span class="total_unread_count badge rounded-pill text-bg-info ms-2 px-1"></span></li><li class="menu_flagged"><a class="unread_link" href="?page=message_list&amp;list_path=flagged"><i class="bi bi-flag-fill menu-icon"></i><span class="nav-label">Flagged</span></a> <span class="flagged_count"></span></li><li class="menu_junk"><a class="unread_link" href="?page=message_list&amp;list_path=junk"><i class="bi bi-envelope-x-fill menu-icon"></i><span class="nav-label">Junk</span></a></li><li class="menu_trash"><a class="unread_link" href="?page=message_list&amp;list_path=trash"><i class="bi bi-trash3-fill menu-icon"></i><span class="nav-label">Trash</span></a></li><li class="menu_drafts"><a class="unread_link" href="?page=message_list&amp;list_path=drafts"><i class="bi bi-pencil-square menu-icon"></i><span class="nav-label">Drafts</span></a></li><li class="menu_snoozed"><a class="unread_link" href="?page=message_list&amp;list_path=snoozed"><i class="bi bi-clock-fill menu-icon"></i><span class="nav-label">Snoozed</span></a></li>'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('folder_sources' => array(array('email_folders', 'baz')), 'formatted_folder_list' => '<li class="menu_unread d-flex align-items-center"><a class="unread_link d-flex align-items-center" href="?page=message_list&amp;list_path=unread"><i class="bi bi-envelope-fill menu-icon"></i><span class="nav-label">Unread</span></a><span class="total_unread_count badge rounded-pill text-bg-info ms-2 px-1"></span></li><li class="menu_flagged"><a class="unread_link" href="?page=message_list&amp;list_path=flagged"><i class="bi bi-flag-fill menu-icon"></i><span class="nav-label">Flagged</span></a> <span class="flagged_count"></span></li><li class="menu_junk"><a class="unread_link" href="?page=message_list&amp;list_path=junk"><i class="bi bi-envelope-x-fill menu-icon"></i><span class="nav-label">Junk</span></a></li><li class="menu_trash"><a class="unread_link" href="?page=message_list&amp;list_path=trash"><i class="bi bi-trash3-fill menu-icon"></i><span class="nav-label">Trash</span></a></li><li class="menu_drafts"><a class="unread_link" href="?page=message_list&amp;list_path=drafts"><i class="bi bi-pencil-square menu-icon"></i><span class="nav-label">Drafts</span></a></li><li class="menu_snoozed"><a class="unread_link" href="?page=message_list&amp;list_path=snoozed"><i class="bi bi-clock-fill menu-icon"></i><span class="nav-label">Snoozed</span></a></li>'), $res->output_response);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_main_menu_end() {
        $test = new Output_Test('main_menu_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</ul></div>'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '</ul></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_email_menu_content() {
        $test = new Output_Test('email_menu_content', 'core');
        $test->handler_response = array('single_server_mode' => true, 'folder_sources' => array(array('email_folders', 'baz')));
        $res = $test->run();
        $this->assertEquals(array('<div class="email_folders"><ul class="folders">baz</ul></div>'), $res->output_response);
        $test->handler_response = array('folder_sources' => array(array('email_folders', 'baz')));
        $res = $test->run();
        $this->assertEquals(array('<div class="src_name d-flex justify-content-between pe-2" data-bs-toggle="collapse" role="button" data-bs-target=".email_folders">Email<i class="bi bi-chevron-down"></i></div><div class="email_folders collapse"><ul class="folders">baz</ul></div>'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('folder_sources' => array(array('email_folders', 'baz')), 'formatted_folder_list' => '<div class="src_name d-flex justify-content-between pe-2" data-bs-toggle="collapse" role="button" data-bs-target=".email_folders">Email<i class="bi bi-chevron-down"></i></div><div class="email_folders collapse"><ul class="folders">baz</ul></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_settings_menu_start() {
        $test = new Output_Test('settings_menu_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="src_name d-flex justify-content-between pe-2" data-bs-toggle="collapse" role="button" data-bs-target=".settings">Settings<i class="bi bi-chevron-down"></i></div><ul class="collapse settings folders">'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<div class="src_name d-flex justify-content-between pe-2" data-bs-toggle="collapse" role="button" data-bs-target=".settings">Settings<i class="bi bi-chevron-down"></i></div><ul class="collapse settings folders">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_form() {
        $test = new Output_Test('save_form', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="save_settings_page p-0"><div class="content_title px-3">Save Settings</div><div class="save_details p-3">Settings are not saved permanently on the server unless you explicitly allow it. If you don\'t save your settings, any changes made since you last logged in will be deleted when your session expires or you logout. You must re-enter your password for security purposes to save your settings permanently.<div class="save_subtitle mt-3"><b>Unsaved Changes</b></div><ul class="unsaved_settings"><li>No changes need to be saved</li></ul></div><div class="save_perm_form px-3"><form method="post"><input type="hidden" name="hm_page_key" value="" /><input type="text" value="cypht_user" autocomplete="username" style="display: none;"/><label class="screen_reader" for="password">Password</label><input required id="password" name="password" autocomplete="current-password" class="save_settings_password form-control mb-2 warn_on_paste" type="password" placeholder="Password" /><input class="save_settings btn btn-primary me-2" type="submit" name="save_settings_permanently" value="Save" /><input class="save_settings btn btn-outline-secondary me-2" type="submit" name="save_settings_permanently_then_logout" value="Save and Logout" /></form><form method="post"><input type="hidden" name="hm_page_key" value="" /><input class="save_settings btn btn-outline-secondary" type="submit" name="logout" value="Just Logout" /></form></div></div>'), $res->output_response);
        $test->handler_response = array('changed_settings' => array('foo'));
        $res = $test->run();
        $this->assertEquals(array('<div class="save_settings_page p-0"><div class="content_title px-3">Save Settings</div><div class="save_details p-3">Settings are not saved permanently on the server unless you explicitly allow it. If you don\'t save your settings, any changes made since you last logged in will be deleted when your session expires or you logout. You must re-enter your password for security purposes to save your settings permanently.<div class="save_subtitle mt-3"><b>Unsaved Changes</b></div><ul class="unsaved_settings"><li>foo (1X)</li></ul></div><div class="save_perm_form px-3"><form method="post"><input type="hidden" name="hm_page_key" value="" /><input type="text" value="cypht_user" autocomplete="username" style="display: none;"/><label class="screen_reader" for="password">Password</label><input required id="password" name="password" autocomplete="current-password" class="save_settings_password form-control mb-2 warn_on_paste" type="password" placeholder="Password" /><input class="save_settings btn btn-primary me-2" type="submit" name="save_settings_permanently" value="Save" /><input class="save_settings btn btn-outline-secondary me-2" type="submit" name="save_settings_permanently_then_logout" value="Save and Logout" /></form><form method="post"><input type="hidden" name="hm_page_key" value="" /><input class="save_settings btn btn-outline-secondary" type="submit" name="logout" value="Just Logout" /></form></div></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_settings_servers_link() {
        $test = new Output_Test('settings_servers_link', 'core');
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<li class="menu_servers"><a class="unread_link" href="?page=servers"><i class="bi bi-pc-display-horizontal menu-icon"></i>Servers</a></li>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_settings_site_link() {
        $test = new Output_Test('settings_site_link', 'core');
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<li class="menu_settings"><a class="unread_link" href="?page=settings"><i class="bi bi-gear-wide-connected menu-icon"></i>Site</a></li>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_settings_save_link() {
        $test = new Output_Test('settings_save_link', 'core');
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<li class="menu_save"><a class="unread_link" href="?page=save"><i class="bi bi-download menu-icon"></i>Save</a></li>'), $res->output_response);
        $test->handler_response = array('single_server_mode' => true);
        $res = $test->run();
        $this->assertEquals(array('single_server_mode' => 1), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_settings_menu_end() {
        $test = new Output_Test('settings_menu_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</ul>'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '</ul>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_folder_list_content_end() {
        $test = new Output_Test('folder_list_content_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="sidebar-footer"><a class="logout_link" href="#" title="Logout"><i class="bi bi-power menu-icon"></i><span class="nav-label">Logout</span></a><a href="#" class="update_message_list" title="Reload"><i class="bi bi-arrow-clockwise menu-icon"></i><span class="nav-label">Reload</span></a><div class="menu-toggle rounded-pill fw-bold cursor-pointer"><i class="bi bi-list fs-5 fw-bold"></i></div>'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<div class="sidebar-footer"><a class="logout_link" href="#" title="Logout"><i class="bi bi-power menu-icon"></i><span class="nav-label">Logout</span></a><a href="#" class="update_message_list" title="Reload"><i class="bi bi-arrow-clockwise menu-icon"></i><span class="nav-label">Reload</span></a><div class="menu-toggle rounded-pill fw-bold cursor-pointer"><i class="bi bi-list fs-5 fw-bold"></i></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_folder_list_end() {
        $test = new Output_Test('folder_list_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</div></nav>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_content_section_start() {
        $test = new Output_Test('content_section_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<main class="container-fluid content_cell" id="cypht-main"><div class="offline">Offline</div><div class="row m-0 position-relative">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_content_section_end() {
        $test = new Output_Test('content_section_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</div></main>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_modals() {
        $test = new Output_Test('modals', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="modal fade" id="shareFolderModal" tabindex="-1" aria-labelledby="shareFolderModalLabel" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="shareFolderModalLabel">Edit Permissions</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><div class="row"><div class="col-lg-8 col-md-12"><div id="loadingSpinner" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div><table class="table table-striped" id="permissionTable" style="display:none;"><thead><tr><th>User</th><th>Permissions</th><th>Actions</th></tr></thead><tbody></tbody></table></div><div class="col-lg-4 col-md-12"><form id="shareForm" action="" method="POST"><input type="hidden" name="server_id" id="server_id" value=""><input type="hidden" name="folder_uid" id="folder_uid" value=""><input type="hidden" name="folder" id="folder" value=""><div class="mb-3 row"><div class="col-12"><label class="form-label">Identifier</label><div><input type="radio" name="identifier" value="user" id="identifierUser" checked><label for="identifierUser">User:</label><input type="text" class="form-control d-inline-block" id="email" name="email" required placeholder="Enter email"></div><div><input type="radio" name="identifier" value="all" id="identifierAll"><label for="identifierAll">All users (anyone)</label></div><div><input type="radio" name="identifier" value="guests" id="identifierGuests"><label for="identifierGuests">Guests (anonymous)</label></div></div></div><div class="mb-3 row"><div class="col-12"><label class="form-label">Access Rights</label><div><input type="checkbox" name="access_read" id="accessRead" checked><label for="accessRead">Read</label></div><div><input type="checkbox" name="access_write" id="accessWrite"><label for="accessWrite">Write</label></div><div><input type="checkbox" name="access_delete" id="accessDelete"><label for="accessDelete">Delete</label></div><div><input type="checkbox" name="access_other" id="accessOther"><label for="accessOther">Other</label></div></div></div><div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button></div></form></div></div></div></div></div></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_start() {
        $test = new Output_Test('message_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="content_title"></div><div class="msg_text">'), $res->output_response);
        $test->handler_response = array('list_parent' => 'sent');
        $res = $test->run();
        $this->assertEquals(array('<div class="content_title"><a href="?page=message_list&amp;list_path=sent">Sent</a></div><div class="msg_text">'), $res->output_response);
        $test->handler_response = array('list_parent' => 'combined_inbox');
        $res = $test->run();
        $this->assertEquals(array('<div class="content_title"><a href="?page=message_list&amp;list_path=combined_inbox">Everything</a></div><div class="msg_text">'), $res->output_response);
        $test->handler_response = array('list_parent' => 'advanced_search');
        $res = $test->run();
        $this->assertEquals(array('<div class="content_title"><a href="?page=advanced_search&amp;list_path=advanced_search">Advanced Search</a></div><div class="msg_text">'), $res->output_response);
        $test->handler_response = array('list_parent' => 'email');
        $res = $test->run();
        $this->assertEquals(array('<div class="content_title"><a href="?page=message_list&amp;list_path=email">All Email</a></div><div class="msg_text">'), $res->output_response);
        $test->handler_response = array('list_parent' => 'unread');
        $res = $test->run();
        $this->assertEquals(array('<div class="content_title"><a href="?page=message_list&amp;list_path=unread">Unread</a></div><div class="msg_text">'), $res->output_response);
        $test->handler_response = array('list_parent' => 'search', 'list_page' => 1, 'list_filter' => 'foo', 'list_sort' => 'bar', 'uid' => 5, 'mailbox_list_title' => array('Search', 'bar'));
        $res = $test->run();
        $this->assertEquals(array('<input type="hidden" class="msg_uid" value="5" /><div class="content_title"><a href="?page=search&amp;list_path=search">Search</a><i class="bi bi-caret-right-fill path_delim"></i><a href="?page=message_list&amp;list_path=">bar</a></div><div class="msg_text">'), $res->output_response);
        $test->handler_response = array('list_parent' => 'search', 'list_page' => 1, 'list_filter' => 'foo', 'list_sort' => 'bar', 'uid' => 5, 'mailbox_list_title' => array('search', 'bar'));
        $res = $test->run();
        $this->assertEquals(array('<input type="hidden" class="msg_uid" value="5" /><div class="content_title"><a href="?page=search&amp;list_path=search">Search</a><i class="bi bi-caret-right-fill path_delim"></i><a href="?page=message_list&amp;list_path=">search<i class="bi bi-caret-right-fill path_delim"></i>bar</a></div><div class="msg_text">'), $res->output_response);
        $test->handler_response = array('list_page' => 1, 'list_filter' => 'foo', 'list_sort' => 'bar', 'uid' => 5, 'mailbox_list_title' => array('foo', 'bar'));
        $res = $test->run();
        $this->assertEquals(array('<input type="hidden" class="msg_uid" value="5" /><div class="content_title"><a href="?page=message_list&amp;list_path=&list_page=1&filter=foo&sort=bar">foo<i class="bi bi-caret-right-fill path_delim"></i>bar</a></div><div class="msg_text">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_end() {
        $test = new Output_Test('message_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_notfound_content() {
        $test = new Output_Test('notfound_content', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="content_title">Page Not Found!</div><div class="empty_list"><br />Nothingness</div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_start() {
        $test = new Output_Test('message_list_start', 'core');
        $test->handler_response = array('message_list_fields' => array('foo', 'bar'));
        $res = $test->run();
        $this->assertEquals(array('<div class="p-3"><table class="message_table table"><colgroup><col class="f"><col class="b"></colgroup><thead><tr><th class="o">o</th><th class="a">r</th></tr></thead><tbody class="message_table_body">'), $res->output_response);
        $test->handler_response = array('message_list_fields' => array(array(false, true, false)));
        $res = $test->run();
        $this->assertEquals(array('<div class="p-3"><table class="message_table table"><thead><tr><th></th></tr></thead><tbody class="message_table_body">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_home_heading() {
        $test = new Output_Test('home_heading', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="content_title">Home</div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_home_password_dialogs() {
        $test = new Output_Test('home_password_dialogs', 'core');
        $res = $test->run();
        $this->assertEquals(array(), $res->output_response);
        $test->handler_response = array('missing_pw_servers' => array(array('server' => 'host', 'user' => 'test', 'type' => 'foo', 'id' => 1, 'name' => 'bar')));
        $res = $test->run();
        $this->assertEquals(array('<div class="home_password_dialogs mt-3 col-lg-6 col-md-5 col-sm-12"><div class="card"><div class="card-body"><div class="card_title"><h4>Passwords</h4></div><p>You have elected to not store passwords between logins. Enter your passwords below to gain access to these services during this session.</p><div class="div_foo_1 mt-3">foo bar test host <div class="input-group mt-2"><input placeholder="Password" type="password" class="form-control pw_input" id="update_pw_foo_1" /> <input type="button" class="pw_update btn btn-primary" data-id="foo_1" value="Update" /></div></div></div></div></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_heading() {
        $test = new Output_Test('message_list_heading', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="message_list p-0 _list"><div class="content_title d-flex gap-3 justify-content-between px-3 align-items-center"><div class="d-flex align-items-center gap-1"><a class="toggle_link" href="#"><i class="bi bi-check-square-fill"></i></a><div class="msg_controls fs-6 d-none gap-1 align-items-center"><div class="dropdown on_mobile"><button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" id="coreMsgControlDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="true">Actions</button><ul class="dropdown-menu" aria-labelledby="coreMsgControlDropdown"><li><a class="dropdown-item msg_read core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="read">Read</a></li><li><a class="dropdown-item msg_unread core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="unread">Unread</a></li><li><a class="dropdown-item msg_flag core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="flag">Flag</a></li><li><a class="dropdown-item msg_unflag core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="unflag">Unflag</a></li><li><a class="dropdown-item msg_delete core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="delete">Delete</a></li><li><a class="dropdown-item msg_archive core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="archive">Archive</a></li></ul></div><a class="msg_read core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="read">Read</a><a class="msg_unread core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="unread">Unread</a><a class="msg_flag core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="flag">Flag</a><a class="msg_unflag core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="unflag">Unflag</a><a class="msg_delete core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="delete">Delete</a><a class="msg_archive core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="archive">Archive</a></div><div class="mailbox_list_title"></div><select name="sort" style="width: 150px" class="combined_sort form-select form-select-sm"><option value="4">Arrival Date &darr;</option><option value="-4">Arrival Date &uarr;</option><option value="2">From &darr;</option><option value="-2">From &uarr;</option><option value="3">Subject &darr;</option><option value="-3">Subject &uarr;</option></select></div><div class="list_controls no_mobile d-flex gap-3 align-items-center"><a class="refresh_link" title="Refresh" href="#"><i class="bi bi-arrow-clockwise refresh_list"></i></a><a href="#" title="Sources" class="source_link"><i class="bi bi-folder-fill refresh_list"></i></a><a title="Configure" href="?page=settings#_setting"><i class="bi bi-gear-wide refresh_list"></i></a></div>
    <div class="list_controls on_mobile">
        <i class="bi bi-filter-circle" onclick="listControlsMenu()"></i>
        <div id="list_controls_menu" classs="list_controls_menu"><a class="refresh_link" title="Refresh" href="#"><i class="bi bi-arrow-clockwise refresh_list"></i></a><a href="#" title="Sources" class="source_link"><i class="bi bi-folder-fill refresh_list"></i></a><a title="Configure" href="?page=settings#_setting"><i class="bi bi-gear-wide refresh_list"></i></a></div>
    </div><div class="list_sources"><div class="src_title fs-5 mb-2">Sources</div></div></div>'), $res->output_response);
        $test->handler_response = array('custom_list_controls' => 'foo');
        $res = $test->run();
        $this->assertEquals(array('<div class="message_list p-0 _list"><div class="content_title d-flex gap-3 justify-content-between px-3 align-items-center"><div class="d-flex align-items-center gap-1"><a class="toggle_link" href="#"><i class="bi bi-check-square-fill"></i></a><div class="msg_controls fs-6 d-none gap-1 align-items-center"><div class="dropdown on_mobile"><button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" id="coreMsgControlDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="true">Actions</button><ul class="dropdown-menu" aria-labelledby="coreMsgControlDropdown"><li><a class="dropdown-item msg_read core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="read">Read</a></li><li><a class="dropdown-item msg_unread core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="unread">Unread</a></li><li><a class="dropdown-item msg_flag core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="flag">Flag</a></li><li><a class="dropdown-item msg_unflag core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="unflag">Unflag</a></li><li><a class="dropdown-item msg_delete core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="delete">Delete</a></li><li><a class="dropdown-item msg_archive core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="archive">Archive</a></li></ul></div><a class="msg_read core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="read">Read</a><a class="msg_unread core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="unread">Unread</a><a class="msg_flag core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="flag">Flag</a><a class="msg_unflag core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="unflag">Unflag</a><a class="msg_delete core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="delete">Delete</a><a class="msg_archive core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="archive">Archive</a></div><div class="mailbox_list_title"></div><select name="sort" style="width: 150px" class="combined_sort form-select form-select-sm"><option value="4">Arrival Date &darr;</option><option value="-4">Arrival Date &uarr;</option><option value="2">From &darr;</option><option value="-2">From &uarr;</option><option value="3">Subject &darr;</option><option value="-3">Subject &uarr;</option></select></div><div class="list_controls no_mobile d-flex gap-3 align-items-center"><a class="refresh_link" title="Refresh" href="#"><i class="bi bi-arrow-clockwise refresh_list"></i></a>foo</div>
    <div class="list_controls on_mobile">
        <i class="bi bi-filter-circle" onclick="listControlsMenu()"></i>
        <div id="list_controls_menu" classs="list_controls_menu"><a class="refresh_link" title="Refresh" href="#"><i class="bi bi-arrow-clockwise refresh_list"></i></a>foo</div>
    </div><div class="list_sources"><div class="src_title fs-5 mb-2">Sources</div></div></div>'), $res->output_response);
        $test->handler_response = array('no_list_controls' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="message_list p-0 _list"><div class="content_title d-flex gap-3 justify-content-between px-3 align-items-center"><div class="d-flex align-items-center gap-1"><a class="toggle_link" href="#"><i class="bi bi-check-square-fill"></i></a><div class="msg_controls fs-6 d-none gap-1 align-items-center"><div class="dropdown on_mobile"><button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" id="coreMsgControlDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="true">Actions</button><ul class="dropdown-menu" aria-labelledby="coreMsgControlDropdown"><li><a class="dropdown-item msg_read core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="read">Read</a></li><li><a class="dropdown-item msg_unread core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="unread">Unread</a></li><li><a class="dropdown-item msg_flag core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="flag">Flag</a></li><li><a class="dropdown-item msg_unflag core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="unflag">Unflag</a></li><li><a class="dropdown-item msg_delete core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="delete">Delete</a></li><li><a class="dropdown-item msg_archive core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="archive">Archive</a></li></ul></div><a class="msg_read core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="read">Read</a><a class="msg_unread core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="unread">Unread</a><a class="msg_flag core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="flag">Flag</a><a class="msg_unflag core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="unflag">Unflag</a><a class="msg_delete core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="delete">Delete</a><a class="msg_archive core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="archive">Archive</a></div><div class="mailbox_list_title"></div><select name="sort" style="width: 150px" class="combined_sort form-select form-select-sm"><option value="4">Arrival Date &darr;</option><option value="-4">Arrival Date &uarr;</option><option value="2">From &darr;</option><option value="-2">From &uarr;</option><option value="3">Subject &darr;</option><option value="-3">Subject &uarr;</option></select></div><div class="list_controls no_mobile d-flex gap-3 align-items-center"></div>
    <div class="list_controls on_mobile">
        <i class="bi bi-filter-circle" onclick="listControlsMenu()"></i>
        <div id="list_controls_menu" classs="list_controls_menu"></div>
    </div><div class="list_sources"><div class="src_title fs-5 mb-2">Sources</div></div></div>'), $res->output_response);
        $test->handler_response = array('list_path' => 'combined_inbox');
        $res = $test->run();
        $this->assertEquals(array('<div class="message_list p-0 combined_inbox_list"><div class="content_title d-flex gap-3 justify-content-between px-3 align-items-center"><div class="d-flex align-items-center gap-1"><a class="toggle_link" href="#"><i class="bi bi-check-square-fill"></i></a><div class="msg_controls fs-6 d-none gap-1 align-items-center"><div class="dropdown on_mobile"><button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" id="coreMsgControlDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="true">Actions</button><ul class="dropdown-menu" aria-labelledby="coreMsgControlDropdown"><li><a class="dropdown-item msg_read core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="read">Read</a></li><li><a class="dropdown-item msg_unread core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="unread">Unread</a></li><li><a class="dropdown-item msg_flag core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="flag">Flag</a></li><li><a class="dropdown-item msg_unflag core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="unflag">Unflag</a></li><li><a class="dropdown-item msg_delete core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="delete">Delete</a></li><li><a class="dropdown-item msg_archive core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="archive">Archive</a></li></ul></div><a class="msg_read core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="read">Read</a><a class="msg_unread core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="unread">Unread</a><a class="msg_flag core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="flag">Flag</a><a class="msg_unflag core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="unflag">Unflag</a><a class="msg_delete core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="delete">Delete</a><a class="msg_archive core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="archive">Archive</a></div><div class="mailbox_list_title"></div><select name="sort" style="width: 150px" class="combined_sort form-select form-select-sm"><option value="4">Arrival Date &darr;</option><option value="-4">Arrival Date &uarr;</option><option value="2">From &darr;</option><option value="-2">From &uarr;</option><option value="3">Subject &darr;</option><option value="-3">Subject &uarr;</option></select></div><div class="list_controls no_mobile d-flex gap-3 align-items-center"><a class="refresh_link" title="Refresh" href="#"><i class="bi bi-arrow-clockwise refresh_list"></i></a><a href="#" title="Sources" class="source_link"><i class="bi bi-folder-fill refresh_list"></i></a><a title="Configure" href="?page=settings#all_setting"><i class="bi bi-gear-wide refresh_list"></i></a></div>
    <div class="list_controls on_mobile">
        <i class="bi bi-filter-circle" onclick="listControlsMenu()"></i>
        <div id="list_controls_menu" classs="list_controls_menu"><a class="refresh_link" title="Refresh" href="#"><i class="bi bi-arrow-clockwise refresh_list"></i></a><a href="#" title="Sources" class="source_link"><i class="bi bi-folder-fill refresh_list"></i></a><a title="Configure" href="?page=settings#all_setting"><i class="bi bi-gear-wide refresh_list"></i></a></div>
    </div><div class="list_sources"><div class="src_title fs-5 mb-2">Sources</div></div></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_end() {
        $test = new Output_Test('message_list_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</tbody></table></div></div>'), $res->output_response);
    }
}
