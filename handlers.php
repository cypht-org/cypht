<?php

class Hm_Request_Handler {

    protected $modules = array();

    public $page = false;
    public $request = false;
    public $session = false;
    public $config = false;
    public $response = array();

    public function process_request($page, $request, $session, $config) {
        $this->page = $page;
        $this->request = $request;
        $this->session = $session;
        $this->config = $config;
        $this->modules = Hm_Handler_Modules::get_for_page($page);
        $this->process_request_actions();
        return $this->response();
    }
    public function response() {
        return $this->response;
    }

    protected function run_modules() {
        foreach ($this->modules as $name => $args) {
            $input = false;
            $name = 'Hm_Handler_Module_'.ucfirst($name);
            if (class_exists($name)) {
                if (!$args['logged_in'] || ($args['logged_in'] && $this->session->active)) {
                    $mod = new $name( $this, $args['logged_in'], $args['args'] );
                    $input = $mod->process($this->response);
                }
            }
            else {
                Hm_Msgs::add(sprintf('Handler module %s activated but not found', $name));
            }
            if ($input) {
                $this->response = $input;
            }
        }
    }
    protected function process_request_actions() {
        $this->run_modules();
    }
}

?>
