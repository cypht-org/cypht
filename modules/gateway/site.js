'use strict';

var Hm_Gateway = {
    sso_token: '',

    get_headers: function() {
        var h = { 'Content-Type': 'application/json' };
        if (Hm_Gateway.sso_token) {
            h['Authorization'] = 'Bearer ' + Hm_Gateway.sso_token;
        }
        return h;
    },

    escape_html: function(str) {
        var d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    },

    load_tokens: function() {
        var container = document.getElementById('patTokensContainer');
        if (!container) return;
        if (!Hm_Gateway.sso_token) {
            container.innerHTML = '<div class="text-muted small py-2">Session ready. Click Refresh to load tokens.</div>';
            return;
        }
        fetch('/api/v1/tokens', { method: 'GET', headers: Hm_Gateway.get_headers() })
            .then(function(r) { return r.json(); })
            .then(function(rows) {
                if (!rows || !rows.length) {
                    container.innerHTML = '<div class="text-muted small py-2">No tokens created yet.</div>';
                    return;
                }
                var html = '<table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Name</th><th>Prefix</th><th>Scopes</th><th>Created</th><th></th></tr></thead><tbody>';
                rows.forEach(function(t) {
                    var safeName = Hm_Gateway.escape_html(t.name);
                    var safePrefix = Hm_Gateway.escape_html(t.prefix + '...');
                    var safeScopes = Hm_Gateway.escape_html((t.scopes || []).join(', '));
                    var date = t.created_at ? (typeof t.created_at === 'number' ? new Date(t.created_at * 1000).toISOString().slice(0, 10) : String(t.created_at).slice(0, 10)) : '';
                    html += '<tr>';
                    html += '<td class="fw-bold">' + safeName + '</td>';
                    html += '<td><code>' + safePrefix + '</code></td>';
                    html += '<td class="small text-muted">' + safeScopes + '</td>';
                    html += '<td class="small text-muted">' + date + '</td>';
                    var actionCell = t.revoked_at ? '<span class="badge bg-secondary">Revoked</span>' : '<button type="button" class="btn btn-sm btn-outline-danger btn-revoke-token" data-id="' + t.id + '"><i class="bi bi-trash me-1"></i>Revoke</button>';
                    html += '<td class="text-end">' + actionCell + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                container.innerHTML = html;
            })
            .catch(function(err) {
                container.innerHTML = '<div class="text-danger small">Failed to load tokens: ' + err.message + '</div>';
            });
    },

    bind_events: function() {
        $(document).off('.gateway');
        $(document).on('click.gateway', '#btnRefreshTokens', Hm_Gateway.load_tokens);
        $(document).on('click.gateway', '.btn-revoke-token', function() {
            var id = $(this).attr('data-id') || $(this).data('id');
            if (!confirm('Revoke this token permanently?')) return;
            fetch('/api/v1/tokens/' + encodeURIComponent(id) + '?confirm=true', { method: 'DELETE', headers: Hm_Gateway.get_headers() })
                .then(function() { Hm_Gateway.load_tokens(); })
                .catch(function(err) { alert('Revoke failed: ' + err.message); });
        });
        $(document).on('click.gateway', '#btnPresetAll', function() { $('.native-scope-check').prop('checked', true); });
        $(document).on('click.gateway', '#btnPresetRw', function() {
            var rw = ['accounts.read','mail.read','mail.search','attachments.read','mail.send','mail.modify','contacts.read','contacts.write','tags.read','tags.write','searches.read','searches.write','calendar.read','calendar.write','feeds.read','feeds.write','sieve.read'];
            $('.native-scope-check').each(function() { $(this).prop('checked', rw.indexOf($(this).val()) !== -1); });
        });
        $(document).on('click.gateway', '#btnPresetAi', function() {
            var ai = ['accounts.read','mail.read','mail.search','attachments.read','contacts.read','tags.read','searches.read','calendar.read','sieve.read'];
            $('.native-scope-check').each(function() { $(this).prop('checked', ai.indexOf($(this).val()) !== -1); });
        });
        $(document).on('click.gateway', '#btnPresetNone', function() { $('.native-scope-check').prop('checked', false); });
        $(document).on('click.gateway', '#btnCopyPat', function() {
            var val = $('#patNewTokenVal').text();
            if (navigator.clipboard) { navigator.clipboard.writeText(val); alert('Copied to clipboard!'); }
        });
        $(document).on('click.gateway', '#btnCreatePat', function() {
            var name = $('#nativePatName').val().trim();
            if (!name) { alert('Please enter a token name.'); $('#nativePatName').focus(); return; }
            var days = parseInt($('#nativePatDays').val(), 10) || 365;
            var scopes = [];
            $('.native-scope-check:checked').each(function() { scopes.push($(this).val()); });
            if (!scopes.length) { alert('Please select at least one permission scope.'); return; }
            $('#patActionSpinner').removeClass('d-none');
            fetch('/api/v1/tokens', {
                method: 'POST',
                headers: Hm_Gateway.get_headers(),
                body: JSON.stringify({ name: name, scopes: scopes, expires_in_days: days })
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                $('#patActionSpinner').addClass('d-none');
                if (res.error) { alert('Create failed: ' + res.error.message); return; }
                $('#patNewTokenVal').text(res.token);
                $('#patNewTokenAlert').removeClass('d-none');
                $('#nativePatName').val('');
                Hm_Gateway.load_tokens();
            })
            .catch(function(err) {
                $('#patActionSpinner').addClass('d-none');
                alert('Create token failed: ' + err.message);
            });
        });
    }
};

function applyGatewayPageHandlers() {
    var tokenHolder = document.getElementById('gatewayPageData');
    if (tokenHolder) {
        Hm_Gateway.sso_token = tokenHolder.getAttribute('data-token') || '';
    }
    Hm_Gateway.bind_events();
    Hm_Gateway.load_tokens();
    return function() {
        $(document).off('.gateway');
    };
}

$(function() {
    if (typeof routes !== 'undefined' && !routes.find(function(r) { return r.page === 'gateway'; })) {
        routes.push({ page: 'gateway', handler: 'applyGatewayPageHandlers' });
    }
    if (typeof ROUTES !== 'undefined' && !ROUTES.find(function(r) { return r.page === 'gateway'; })) {
        ROUTES.push({
            page: 'gateway',
            handler: applyGatewayPageHandlers,
            commonHandler: window.applyCommonWrappedPageHandlers
        });
    }
});
