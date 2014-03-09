<?php

if (!class_exists('HM_Handler_http_headers')) {
class Hm_Handler_http_headers extends Hm_Handler_Module {
    public function process($data) {
        if (isset($data['language'])) {
            $data['http_headers'][] = 'Content-Language: '.substr($data['language'], 0, 2);
        }
        return $data;
    }
}}

if (!class_exists('Hm_Handler_title')) {
class Hm_Handler_title extends Hm_Handler_Module {
    public function process($data) {
        $data['title'] = ucfirst($this->page);
        return $data;
    }
}}

if (!class_exists('Hm_Handler_language')) {
class Hm_Handler_language extends Hm_Handler_Module {
    public function process($data) {
        $data['language'] = $this->session->get('language', 'en_US');
        return $data;
    }
}}

if (!class_exists('Hm_Handler_date')) {
class Hm_Handler_date extends Hm_Handler_Module {
    public function process($data) {
        $data['date'] = date('Y-m-d h:i:s');
        return $data;
    }
}}

if (!class_exists('Hm_Handler_login')) {
class Hm_Handler_login extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('username', 'password'));
        if ($success) {
            $this->session->check($this->request, $this->config, $form['username'], $form['password']);
        }
        else {
            $this->session->check($this->request, $this->config);
        }
        return $data;
    }
}}

if (!class_exists('Hm_Handler_logout')) {
class Hm_Handler_logout extends Hm_Handler_Module {
    public function process($data) {
        if (isset($this->request->post['logout']) && !$this->session->loaded) {
            $this->session->destroy();
            Hm_Msgs::add('session destroyed on logout');
        }
        return $data;
    }
}}

?>
