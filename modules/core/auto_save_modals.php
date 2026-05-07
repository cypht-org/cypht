<?php
/**
 * Auto-save modals and JavaScript
 * This file contains the modal HTML and JavaScript for auto-save functionality
 */

?>
<!-- Auto-save configuration modal -->
<div class="modal fade" id="autoSaveConfigModal" tabindex="-1" aria-labelledby="autoSaveConfigModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="autoSaveConfigModalLabel">
          <i class="bi bi-gear-fill text-primary me-2"></i>
          Configure Auto-Save Settings
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning" role="alert">
          <h6 class="alert-heading"><i class="bi bi-shield-exclamation me-2"></i>Security Warning</h6>
          <p class="mb-2">This feature will automatically save your settings at regular intervals. Please understand the following security implications:</p>
          <ul class="mb-0">
            <li>Your password will be stored securely to enable automatic saving</li>
            <li>Settings will be saved without explicit confirmation</li>
            <li>This feature should only be used on trusted devices</li>
          </ul>
        </div>
        <form id="autoSaveConfigForm">
          <div class="mb-3">
            <label for="autoSaveInterval" class="form-label">Auto-save interval (seconds):</label>
            <input type="number" class="form-control" id="autoSaveInterval" name="auto_save_interval" min="10" max="3600" value="60" required>
            <div class="form-text">Interval between automatic saves (10-3600 seconds)</div>
          </div>
          <div class="mb-3">
            <label for="autoSavePassword" class="form-label">Enter your password to confirm:</label>
            <input type="password" class="form-control" id="autoSavePassword" name="auto_save_password" required>
            <div class="form-text">This is required to enable the auto-save feature</div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveAutoSaveConfig()">
          <i class="bi bi-shield-check me-2"></i>Save Configuration
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Auto-save disable confirmation modal -->
<div class="modal fade" id="autoSaveDisableModal" tabindex="-1" aria-labelledby="autoSaveDisableModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="autoSaveDisableModalLabel">
          <i class="bi bi-shield-x text-danger me-2"></i>
          Disable Automatic Settings Save
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info" role="alert">
          <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Confirm Disabling</h6>
          <p class="mb-0">Are you sure you want to disable automatic settings save? Your stored password will be removed and settings will no longer be saved automatically.</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="disableAutoSave()">
          <i class="bi bi-shield-x me-2"></i>Disable Auto-Save
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function showAutoSaveConfigModal() {
    // Get current values
    var currentInterval = '60';
    var intervalElement = document.querySelector('tr:has([data-label*="Auto-save interval"]) span');
    if (intervalElement) {
        currentInterval = intervalElement.textContent.trim();
    }
    
    document.getElementById('autoSaveInterval').value = currentInterval;
    document.getElementById('autoSavePassword').value = '';
    
    // Show modal
    var modal = new bootstrap.Modal(document.getElementById('autoSaveConfigModal'));
    modal.show();
}

function saveAutoSaveConfig() {
    var interval = document.getElementById('autoSaveInterval').value;
    var password = document.getElementById('autoSavePassword').value;
    
    if (!password) {
        alert('Please enter your password');
        return;
    }
    
    if (!interval || interval < 10 || interval > 3600) {
        alert('Please enter a valid interval (10-3600 seconds)');
        return;
    }
    
    // Send AJAX request to save configuration
    Hm_Ajax.request([
        {'name': 'hm_ajax_hook', 'value': 'ajax_validate_auto_save_password'},
        {'name': 'auto_save_password', 'value': password},
        {'name': 'auto_save_enable', 'value': '1'},
        {'name': 'auto_save_interval', 'value': interval}
    ], function(res) {
      if (res.is_saved_auto_save_key !== undefined && res.is_saved_auto_save_key === true) {
        // close modal
        var modal = bootstrap.Modal.getInstance(document.getElementById('autoSaveConfigModal'));
        modal.hide();
        
        // update user interface
        updateAutoSaveDisplay(true, interval);
      }
    }, function(err) {
        Hm_Notices.show('Failed to save configuration', 'error');
    });
}

function showDisableAutoSaveModal() {
    var modal = new bootstrap.Modal(document.getElementById('autoSaveDisableModal'));
    modal.show();
}

function disableAutoSave() {
    // Send AJAX request to disable
    Hm_Ajax.request([
        {'name': 'hm_ajax_hook', 'value': 'ajax_disable_auto_save'}
    ], function(res) {
      if (res.is_saved_auto_save_key !== undefined && res.is_saved_auto_save_key === true) {
        // close modal
        var modal = bootstrap.Modal.getInstance(document.getElementById('autoSaveDisableModal'));
        modal.hide();
        
        // update user interface
        updateAutoSaveDisplay(false, '60');
      }
    }, function(err) {
        Hm_Notices.show('Failed to disable auto-save', 'error');
    });
}

function updateAutoSaveDisplay(enabled, interval) {
    // Update status display
    var rows = document.querySelectorAll('tr.general_setting');
    rows.forEach(function(row) {
        var label = row.querySelector('label');
        if (!label) return;
        
        var labelText = label.textContent.trim();
        var cell = row.querySelector('td:last-child div');
        if (!cell) return;
        
        if (labelText.includes('Enable automatic settings save')) {
            cell.innerHTML = enabled ? 
                '<span class="badge bg-success me-2">Enabled</span>' +
                '<button type="button" class="btn btn-sm btn-danger" onclick="showDisableAutoSaveModal()">' +
                '<i class="bi bi-shield-lock"></i> Disable Auto-Save</button>' :
                '<span class="badge bg-secondary me-2">Disabled</span>' +
                '<button type="button" class="btn btn-sm btn-primary" onclick="showAutoSaveConfigModal()">' +
                '<i class="bi bi-shield-check"></i> Configure Auto-Save</button>';
        }
        else if (labelText.includes('Auto-save interval')) {
            cell.innerHTML = enabled ?
                '<span class="me-2">' + interval + '</span>' +
                '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="showAutoSaveConfigModal()">' +
                '<i class="bi bi-pencil"></i> Edit</button>' :
                '<span class="text-muted">' + interval + '</span>';
        }
    });
}
</script>
