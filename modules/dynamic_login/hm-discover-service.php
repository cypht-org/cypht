<?php

/**
 * Dynamic login modules
 * @package modules
 * @subpackage dynamic_login
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH.'modules/nux/modules.php';

/**
 * @subpackage dynamic_login/lib
 */
class Hm_Discover_Services {

    private $timeout = 1;
    private $domain = false;
    private $server = false;
    private $port = false;
    private $tls = false;
    private $type = false;
    private $smtp_port = false;
    private $smtp_tls = false;
    private $smtp_ports = array(587, 465, 25);
    private $imap_ports = array(993, 143);
    private $pop3_ports = array(995, 110);
    private $transports = array('tls://' => true, 'tcp://' => false);
    private $service = false;

    public function __construct($email, $service=false) {
        $this->service = $service;
        $this->domain = $this->get_domain($email);
    }

    public function get_host_details() {
        $details = $this->service_check();
        if (count($details) > 0) {
            return $details;
        }
        return $this->manual_host_check();
    }

    private function get_domain($email) {
        if (strpos($email, '@')) {
            $parts = explode('@', $email);
            $domain = $parts[1];
        }
        else {
            $domain = 'localhost';
        }
        return $domain;
    }

    private function manual_host_check() {
        if (!$this->domain) {
            return array();
        }
        $this->get_mx_host();
        if (!$this->server) {
            return array();
        }
        $this->check_imap_ports();
        if (!$this->port) {
            $this->check_pop3_ports();
        }
        if (!$this->port) {
            return array();
        }
        $this->check_ports($this->smtp_ports, true);
        return $this->host_details();
    }

    private function service_check() {
        if ($this->service) {
            return Nux_Quick_Services::details($this->service);
        }
        return array();
    }

    private function host_details() {
        return array(
            'type' => $this->type,
            'port' => $this->port,
            'server' => $this->server,
            'name' => $this->domain,
            'tls' => $this->tls,
            'smtp' => array(
                'port' => $this->smtp_port,
                'tls' => $this->smtp_tls,
                'server' => $this->server
            )
        );
    }

    private function get_mx_host() {
        getmxrr($this->domain, $hosts);
        if (is_array($hosts) && count($hosts) > 0) {
            $this->server = array_shift($hosts);
        }
    }

    private function check_imap_ports() {
        $this->check_ports($this->imap_ports);
        if ($this->port) {
            $this->type = 'imap';
        }
    }

    private function check_pop3_ports() {
        $this->check_ports($this->pop3_ports);
        if ($this->port) {
            $this->type = 'pop3';
        }
    }

    private function check_ports($ports, $smtp=false) {
        foreach ($ports as $port) {
            foreach ($this->transports as $trans => $tls) {
                $fp = $this->connect($port, $trans);
                if ($fp) {
                    fclose($fp);
                    if ($smtp) {
                        $this->smtp_port = $port;
                        $this->smtp_tls = $tls;
                    }
                    else {
                        $this->port = $port;
                        $this->tls = $tls;
                    }
                    break 2;
                }
            }
        }
    }

    private function connect($port, $trans) {
        $ctx = stream_context_create();
        stream_context_set_option($ctx, 'ssl', 'verify_peer_name', false);
        stream_context_set_option($ctx, 'ssl', 'verify_peer', false);
        return @stream_socket_client($trans.$this->server.':'.$port, $errn,
            $errs, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
    }
}
