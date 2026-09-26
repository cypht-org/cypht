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
            $username = $this->get('username', '');
            $standalone_url = '/gateway/' . ($token ? '#token=' . htmlspecialchars($token, ENT_QUOTES) : '');

            $scope_categories = array(
                array(
                    'title' => $this->trans('Mail & Accounts'),
                    'scopes' => array(
                        array('id' => 'accounts.read', 'label' => $this->trans('Read accounts')),
                        array('id' => 'mail.read', 'label' => $this->trans('Read mail')),
                        array('id' => 'mail.search', 'label' => $this->trans('Search mail')),
                        array('id' => 'attachments.read', 'label' => $this->trans('Download attachments')),
                        array('id' => 'mail.send', 'label' => $this->trans('Send mail')),
                        array('id' => 'mail.modify', 'label' => $this->trans('Modify/flag/move mail')),
                        array('id' => 'mail.delete', 'label' => $this->trans('Delete mail')),
                    )
                ),
                array(
                    'title' => $this->trans('Contacts'),
                    'scopes' => array(
                        array('id' => 'contacts.read', 'label' => $this->trans('Read contacts')),
                        array('id' => 'contacts.write', 'label' => $this->trans('Write contacts')),
                    )
                ),
                array(
                    'title' => $this->trans('Tags'),
                    'scopes' => array(
                        array('id' => 'tags.read', 'label' => $this->trans('Read tags')),
                        array('id' => 'tags.write', 'label' => $this->trans('Write tags')),
                    )
                ),
                array(
                    'title' => $this->trans('Saved Searches'),
                    'scopes' => array(
                        array('id' => 'searches.read', 'label' => $this->trans('Read searches')),
                        array('id' => 'searches.write', 'label' => $this->trans('Write searches')),
                    )
                ),
                array(
                    'title' => $this->trans('Calendar'),
                    'scopes' => array(
                        array('id' => 'calendar.read', 'label' => $this->trans('Read calendar')),
                        array('id' => 'calendar.write', 'label' => $this->trans('Write calendar')),
                    )
                ),
                array(
                    'title' => $this->trans('Feeds'),
                    'scopes' => array(
                        array('id' => 'feeds.read', 'label' => $this->trans('Read feeds')),
                        array('id' => 'feeds.write', 'label' => $this->trans('Write feeds')),
                    )
                ),
                array(
                    'title' => $this->trans('Admin & Sieve'),
                    'scopes' => array(
                        array('id' => 'sieve.read', 'label' => $this->trans('Read sieve status')),
                        array('id' => 'tokens.manage', 'label' => $this->trans('Manage tokens')),
                        array('id' => 'audit.read', 'label' => $this->trans('Read audit logs')),
                    )
                )
            );

            $scopes_html = '';
            foreach ($scope_categories as $cat) {
                $scopes_html .= '<div class="col-12 mt-3"><div class="fw-bold text-primary small text-uppercase pb-1 border-bottom">' . $cat['title'] . '</div></div>';
                foreach ($cat['scopes'] as $s) {
                    $checked = in_array($s['id'], array('accounts.read', 'mail.read', 'mail.search', 'attachments.read')) ? ' checked' : '';
                    $scopes_html .= '<div class="col-md-6 col-lg-4 col-xl-3 mt-2">'
                        . '<div class="form-check">'
                        . '<input class="form-check-input native-scope-check" type="checkbox" value="' . $s['id'] . '" id="scope_' . str_replace('.', '_', $s['id']) . '"' . $checked . '>'
                        . '<label class="form-check-label small" for="scope_' . str_replace('.', '_', $s['id']) . '"><code>' . $s['id'] . '</code> <span class="text-muted">(' . $s['label'] . ')</span></label>'
                        . '</div></div>';
                }
            }

            $res = '<div class="gateway_page p-0">'
                . '<div class="content_title d-flex align-items-center justify-content-between px-3 py-2">'
                . '<span><i class="bi bi-cpu-fill me-2"></i>' . $this->trans('API & MCP Gateway') . '</span>'
                . '<a href="' . $standalone_url . '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">'
                . '<i class="bi bi-box-arrow-up-right me-1"></i>' . $this->trans('Open Standalone Console') . '</a>'
                . '</div>'
                . '<div class="p-3">'
                . '<div class="card mb-3 shadow-sm border-0"><div class="card-body p-3">'
                . '<div class="d-flex flex-wrap align-items-center gap-3">'
                . '<div><span class="badge bg-success fs-6"><i class="bi bi-check-circle-fill me-1"></i>' . $this->trans('Active') . '</span></div>'
                . '<div class="small"><b>' . $this->trans('Session') . ':</b> <span class="badge bg-secondary">' . htmlspecialchars($username) . '</span></div>'
                . '<div class="small"><b>REST API:</b> <code>/api/v1</code></div>'
                . '<div class="small"><b>MCP Streamable HTTP:</b> <code>/mcp</code></div>'
                . '<div class="small"><b>Health:</b> <code>/healthz</code></div>'
                . '</div></div></div>'
                . '<div class="card mb-3 shadow-sm border-0"><div class="card-body p-3">'
                . '<h5 class="card-title fw-bold mb-3"><i class="bi bi-key-fill text-primary me-2"></i>' . $this->trans('Create API Token (PAT)') . '</h5>'
                . '<div class="row g-2 mb-3">'
                . '<div class="col-md-8"><input type="text" id="nativePatName" class="form-control form-control-sm" placeholder="' . $this->trans('Token name, e.g. Claude Assistant / Cursor') . '"></div>'
                . '<div class="col-md-4"><div class="input-group input-group-sm"><input type="number" id="nativePatDays" class="form-control" value="365" min="1" max="3650"><span class="input-group-text">' . $this->trans('Days') . '</span></div></div>'
                . '</div>'
                . '<div class="d-flex flex-wrap align-items-center gap-2 mb-2">'
                . '<span class="small fw-bold me-1">' . $this->trans('Presets') . ':</span>'
                . '<button type="button" class="btn btn-sm btn-primary" id="btnPresetAll"><i class="bi bi-check-all me-1"></i>' . $this->trans('All Scopes') . '</button>'
                . '<button type="button" class="btn btn-sm btn-outline-secondary" id="btnPresetRw">' . $this->trans('Standard Read/Write') . '</button>'
                . '<button type="button" class="btn btn-sm btn-outline-secondary" id="btnPresetAi">' . $this->trans('AI Assistant (Read-only)') . '</button>'
                . '<button type="button" class="btn btn-sm btn-outline-secondary" id="btnPresetNone">' . $this->trans('Clear All') . '</button>'
                . '</div>'
                . '<div class="row g-2 bg-light p-3 rounded border">' . $scopes_html . '</div>'
                . '<div class="mt-3 d-flex align-items-center gap-2">'
                . '<button type="button" class="btn btn-primary btn-sm" id="btnCreatePat"><i class="bi bi-plus-lg me-1"></i>' . $this->trans('Generate Token') . '</button>'
                . '<span id="patActionSpinner" class="spinner-border spinner-border-sm text-primary d-none" role="status"></span>'
                . '</div>'
                . '<div id="patNewTokenAlert" class="alert alert-success mt-3 d-none">'
                . '<div class="fw-bold mb-1"><i class="bi bi-shield-check me-1"></i>' . $this->trans('Token Created Successfully (Copy it now, it will not be shown again)') . ':</div>'
                . '<div class="d-flex align-items-center gap-2"><code id="patNewTokenVal" class="user-select-all p-2 bg-white rounded border flex-grow-1 font-monospace"></code>'
                . '<button type="button" class="btn btn-sm btn-outline-success" id="btnCopyPat"><i class="bi bi-clipboard me-1"></i>' . $this->trans('Copy') . '</button>'
                . '</div></div>'
                . '</div></div>'
                . '<div class="card mb-3 shadow-sm border-0"><div class="card-body p-3">'
                . '<div class="d-flex align-items-center justify-content-between mb-2">'
                . '<h5 class="card-title fw-bold m-0"><i class="bi bi-shield-lock-fill text-primary me-2"></i>' . $this->trans('Active Personal Access Tokens') . '</h5>'
                . '<button type="button" class="btn btn-sm btn-outline-secondary" id="btnRefreshTokens"><i class="bi bi-arrow-clockwise me-1"></i>' . $this->trans('Refresh') . '</button>'
                . '</div>'
                . '<div id="patTokensContainer" class="table-responsive"><div class="text-muted small py-2">' . $this->trans('Loading tokens...') . '</div></div>'
                . '</div></div>'
                . '<div class="card mb-3 shadow-sm border-0"><div class="card-body p-3">'
                . '<h5 class="card-title fw-bold mb-2"><i class="bi bi-robot text-primary me-2"></i>' . $this->trans('Model Context Protocol (MCP) Integration') . '</h5>'
                . '<p class="text-muted small mb-2">' . $this->trans('Connect AI assistants (Claude, Cursor, Codex) to your webmail securely:') . '</p>'
                . '<div class="bg-light p-2 rounded border font-monospace small mb-2 user-select-all"><code>' . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : 'http://127.0.0.1:8088') . '/mcp</code></div>'
                . '<p class="text-muted small mb-0">' . $this->trans('Pass your generated token in the Authorization header: Bearer <PAT>') . '</p>'
                . '</div></div>'
                . '</div>'
                . '<div id="gatewayPageData" class="d-none" data-token="' . htmlspecialchars($token, ENT_QUOTES) . '"></div>'
                . '</div>';

            return $res;
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
