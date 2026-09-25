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
                . '<iframe src="/gateway/" title="Cypht Gateway Console" '
                . 'style="width:100%;min-height:78vh;border:1px solid rgba(0,0,0,.125);border-radius:0.375rem;background:#fff;"></iframe>'
                . '</div>'
                . '</div>';
        }
    }
}