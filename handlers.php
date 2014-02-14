<?php

abstract class Hm_Page_Handler {

    protected $request = false;
    protected $session = false;
    protected $response = '';

    public function process_request(Hm_Request $request, Hm_Session $session) {
        $this->request = $request;
        $this->session = $session;
        $this->process_request_actions();
        return $this->response();
    }
    public function response() {
        return $this->response;
    }

    abstract protected function process_request_actions();
}

class Hm_Home extends Hm_Page_Handler {

    protected function process_request_actions() {
        $this->response = array(
            'title' => 'Home',
            'page' => 'home'
        );
    }
}

class Hm_Notfound extends Hm_Page_Handler {

    protected function process_request_actions() {
        $this->response = array(
            'title' => 'Not Found',
            'page' => 'notfound'
        );
    }
}

?>
