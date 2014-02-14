<?php

abstract class Hm_Request_Handler {

    protected $request = false;
    protected $session = false;
    protected $config = false;
    protected $response = '';

    public function process_request($request, $session, $config) {
        $this->request = $request;
        $this->session = $session;
        $this->config = $config;
        $this->process_request_actions();
        return $this->response();
    }
    public function response() {
        return $this->response;
    }

    abstract protected function process_request_actions();
}

class Hm_Home extends Hm_Request_Handler {

    protected function process_request_actions() {
        $this->response = array(
            'title' => 'Home',
            'page' => 'home'
        );
    }
}

class Hm_Notfound extends Hm_Request_Handler {

    protected function process_request_actions() {
        $this->response = array(
            'title' => 'Not Found',
            'page' => 'notfound'
        );
    }
}

?>
