<?php

if (!defined('DEBUG_MODE')) { die(); }

if (class_exists('Hm_Output_Module')) {
    class Hm_Output_gateway_settings_link extends Hm_Output_Module {
        protected function output() {
            $res = '<li class="menu_gateway"><a class="unread_link" href="'.$this->build_page_url('gateway').'">';
            if (!$this->get('hide_folder_icons')) {
                $res .= '<i class="bi bi-cpu-fill menu-icon"></i>';
            }
            $res .= $this->trans('API & MCP Gateway').'</a></li>';
            if ($this->format == 'HTML5') {
                return $res;
            }
            $this->concat('formatted_folder_list', $res);
        }
    }

    class Hm_Output_gateway_page_content extends Hm_Output_Module {
        protected function output() {
            $username = $this->session->get('username', '');
            $hm_id = $_COOKIE['hm_id'] ?? '';
            $hm_session = $_COOKIE['hm_session'] ?? '';
            $bridge_key = env('GATEWAY_BRIDGE_KEY', '');
            $token = '';
            if ($username && $hm_id && $hm_session && $bridge_key) {
                $payload = json_encode(array(
                    'username' => $username,
                    'hm_id' => $hm_id,
                    'hm_session' => $hm_session
                ));
                $opts = array(
                    'http' => array(
                        'method'  => 'POST',
                        'header'  => "Content-Type: application/json\r\nX-Cypht-Gateway-Key: ".$bridge_key."\r\n",
                        'content' => $payload,
                        'timeout' => 2
                    )
                );
                $context = @stream_context_create($opts);
                $res = @file_get_contents('http://127.0.0.1:18080/api/v1/auth/sso', false, $context);
                if ($res) {
                    $data = @json_decode($res, true);
                    if (!empty($data['access_token'])) {
                        $token = $data['access_token'];
                    }
                }
            }
            $iframe_url = '/gateway/' . ($token ? '#token=' . htmlspecialchars($token, ENT_QUOTES) : '');

            return '<div class="gateway_content px-0">'
                . '<div class="content_title d-flex align-items-center justify-content-between px-3 py-2">'
                . '<span><i class="bi bi-cpu-fill me-2"></i>' . $this->trans('API & MCP Gateway') . '</span>'
                . '<a href="/gateway/" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">'
                . $this->trans('Open Full Console') . '</a>'
                . '</div>'
                . '<div class="px-3 pb-3">'
                . '<div class="alert alert-info mb-3">'
                . '<strong>REST API:</strong> <code>/api/v1</code> &nbsp;|&nbsp; '
                . '<strong>MCP Streamable HTTP:</strong> <code>/mcp</code> &nbsp;|&nbsp; '
                . '<strong>Health:</strong> <code>/healthz</code>'
                . '</div>'
                . '<iframe src="' . $iframe_url . '" title="Cypht Gateway Console" '
                . 'style="width:100%;min-height:78vh;border:1px solid rgba(0,0,0,.125);border-radius:0.375rem;background:#fff;"></iframe>'
                . '</div>'
                . '</div>';
        }
    }

    class Hm_Output_gateway_settings_section extends Hm_Output_Module {
        protected function output() {
            return '<tr><td data-target=".gateway_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2">'
                . '<i class="bi bi-cpu-fill fs-5 me-2"></i>' . $this->trans('API & MCP Gateway') . '</td></tr>'
                . '<tr class="gateway_setting">'
                . '<td class="d-block d-md-table-cell"><label>' . $this->trans('Status & Endpoints') . '</label></td>'
                . '<td class="d-block d-md-table-cell">'
                . '<div class="d-flex align-items-center mb-2">'
                . '<span class="badge bg-success me-2">Active</span>'
                . '<code class="me-3">REST /api/v1</code>'
                . '<code class="me-3">MCP /mcp</code>'
                . '<a href="' . $this->build_page_url('gateway') . '" class="btn btn-sm btn-outline-primary">' . $this->trans('Manage Tokens & Keys') . '</a>'
                . '</div>'
                . '<div class="setting_description text-muted small">'
                . $this->trans('Connect AI assistants (Claude, Cursor, Codex) via Model Context Protocol or automate email workflows via REST API.')
                . '</div>'
                . '</td></tr>';
        }
    }
}
