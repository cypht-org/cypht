# Predefined Service Edit Buttons Implementation

## Overview
This document outlines the implementation of edit buttons for the predefined spam services (SpamCop, AbuseIPDB, StopForumSpam, CleanTalk) and the disabling of the "Add New Service" button.

## Changes Made

### 1. Disabled "Add New Service" Button
**Location:** `modules/imap/output_modules.php` - `Hm_Output_spam_service_management` class

**Change:** Commented out the "Add New Service" button to prevent users from adding custom services for now.

```php
// Add new service button (disabled for now)
// $res .= '<div class="mb-3">';
// $res .= '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addServiceModal">';
// $res .= '<i class="bi bi-plus-circle me-1"></i>'.$this->trans('Add New Service');
// $res .= '</button>';
// $res .= '</div>';
```

### 2. Added Edit Buttons to Service Settings
**Location:** `modules/imap/output_modules.php` - `Hm_Output_spam_services_setting` class

**Change:** Added edit buttons next to each service checkbox in the spam services settings.

```php
$res .= '<td>';
$res .= '<input class="form-check-input" type="checkbox"'.$checked.' value="1" id="'.$key.'" name="'.$key.'" data-default-value="'.($defaults[$key] ? 'true' : 'false').'"/>';
// Add Edit button for each service
$service_id = str_replace('enable_', '', $key);
$res .= '<button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="editService(\''.$service_id.'\')">';
$res .= '<i class="bi bi-pencil"></i> '.$this->trans('Edit');
$res .= '</button>';
$res .= '</td></tr>';
```

### 3. Created Predefined Service Modals
**Location:** `modules/imap/output_modules.php` - `Hm_Output_predefined_service_modals` class

**Features:**
- **SpamCop Modal:** Simple email endpoint configuration
- **AbuseIPDB Modal:** Full API configuration with authentication
- **StopForumSpam Modal:** Full API configuration with authentication
- **CleanTalk Modal:** Full API configuration with authentication

**Each modal includes:**
- Service-specific fields (email endpoint for SpamCop, API fields for others)
- Current values loaded from the spam service manager
- Enable/disable checkbox
- Form validation
- Bootstrap styling

### 4. Updated JavaScript for Modal Handling
**Location:** `modules/imap/output_modules.php` - `Hm_Output_spam_service_management_js` class

**Change:** Modified the `editService()` function to handle predefined services.

```javascript
function editService(serviceId) {
    // Check if it's a predefined service
    if (["spamcop", "abuseipdb", "stopforumspam", "cleantalk"].includes(serviceId)) {
        // Show predefined service modal
        var modal = new bootstrap.Modal(document.getElementById(serviceId + "Modal"));
        modal.show();
        return;
    }
    
    // Handle custom services (existing logic)
    // ...
}
```

### 5. Added Handler for Predefined Service Updates
**Location:** `modules/imap/handler_modules.php` - `Hm_Handler_update_predefined_service` class

**Features:**
- Processes form submissions from predefined service modals
- Validates service-specific fields
- Updates service configuration in the spam service manager
- Provides success/error feedback

**Supported Services:**
- **SpamCop:** Email endpoint and enabled status
- **AbuseIPDB:** API endpoint, method, auth type, auth header, auth value, payload template, enabled status
- **StopForumSpam:** API endpoint, method, auth type, auth header, auth value, payload template, enabled status
- **CleanTalk:** API endpoint, method, auth type, auth header, auth value, payload template, enabled status

## Service-Specific Configurations

### SpamCop (Email Service)
- **Type:** Email
- **Required Fields:** Email endpoint
- **Default Endpoint:** `submit.u4GqXFse5hLoqP34@spam.spamcop.net`
- **Default Status:** Enabled

### AbuseIPDB (API Service)
- **Type:** API
- **Required Fields:** API endpoint, HTTP method, payload template
- **Optional Fields:** Authentication configuration
- **Default Endpoint:** `https://api.abuseipdb.com/api/v2/report`
- **Default Method:** POST
- **Default Auth Type:** Header
- **Default Auth Header:** Key
- **Default Payload:** `{"ip": "{{ ip }}", "categories": [3], "comment": "{{ reason }}"}`
- **Default Status:** Disabled

### StopForumSpam (API Service)
- **Type:** API
- **Required Fields:** API endpoint, HTTP method, payload template
- **Optional Fields:** Authentication configuration
- **Default Endpoint:** `https://www.stopforumspam.com/add`
- **Default Method:** POST
- **Default Auth Type:** Header
- **Default Auth Header:** api_key
- **Default Payload:** `{"email": "{{ email }}", "ip": "{{ ip }}", "evidence": "{{ reason }}"}`
- **Default Status:** Disabled

### CleanTalk (API Service)
- **Type:** API
- **Required Fields:** API endpoint, HTTP method, payload template
- **Optional Fields:** Authentication configuration
- **Default Endpoint:** `https://moderate.cleantalk.org/api2.0`
- **Default Method:** POST
- **Default Auth Type:** Header
- **Default Auth Header:** auth_key
- **Default Payload:** `{"auth_key": "{{ auth_value }}", "method_name": "spam_check", "message": "{{ body }}", "sender_email": "{{ email }}", "sender_ip": "{{ ip }}"}`
- **Default Status:** Disabled

## Benefits

1. **Simplified Interface:** Users can only edit predefined services, reducing complexity
2. **Service-Specific Forms:** Each service has a tailored form with relevant fields
3. **Current Value Loading:** Modals populate with existing configuration values
4. **Easy Configuration:** Users can quickly configure API keys and endpoints
5. **Consistent UX:** All services follow the same edit pattern
6. **Validation:** Form validation ensures proper configuration

## Usage

1. Navigate to IMAP Settings in the admin interface
2. Scroll to the "External Spam Reporting Services" section
3. Click the "Edit" button next to any service
4. Configure the service-specific fields
5. Click "Update" to save changes

## Future Enhancements

- Re-enable "Add New Service" button when needed
- Add service testing functionality
- Add service status indicators
- Add bulk service management
- Add service import/export functionality 