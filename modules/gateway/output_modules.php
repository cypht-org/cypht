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
            $token = $this->get('gateway_sso_token', '');
            $iframe_url = '/gateway/' . ($token ? '#token=' . htmlspecialchars($token, ENT_QUOTES) : '');

            return '<div class="gateway_content px-0">'
                . '<div class="content_title d-flex align-items-center justify-content-between px-3 py-2">'
                . '<span><i class="bi bi-cpu-fill me-2"></i>' . $this->trans('API & MCP Gateway') . '</span>'
                . '<a href="' . $iframe_url . '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">'
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
                . '<script type="text/javascript">
(function() {
    function hydrate() {
        if (typeof applyCommonWrappedPageHandlers === "function") {
            applyCommonWrappedPageHandlers();
        }
        if (typeof Hm_Folders !== "undefined") {
            if (!Hm_Folders.folder_list_loaded()) {
                if (!Hm_Folders.load_from_local_storage()) {
                    Hm_Folders.update_folder_list();
                }
            }
            if (typeof Hm_Folders.hl_selected_menu === "function") {
                Hm_Folders.hl_selected_menu();
            }
        }
    }
    if (document.readyState === "complete" || document.readyState === "interactive") {
        setTimeout(hydrate, 10);
    } else {
        window.addEventListener("DOMContentLoaded", hydrate);
        window.addEventListener("load", hydrate);
    }
})();
</script>'
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
