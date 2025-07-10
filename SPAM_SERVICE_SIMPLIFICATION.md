# Spam Service Input Fields Simplification

## Overview
This document outlines the simplification of input fields for the spam reporting services in Cypht, removing unnecessary complexity and focusing on fields that are actually used and needed.

## Changes Made

### 1. Removed DNS Service Type
**Reason:** DNS service was not implemented in the actual reporting logic and was unused.

**Removed Fields:**
- `query_format` (text)
- `response_type` (select)
- `timeout` (number)

### 2. Simplified Email Service Type
**Reason:** Only the endpoint field was actually used in the reporting logic.

**Before:**
- `endpoint` (email) - **REQUIRED** ✅
- `subject_template` (text) - **OPTIONAL** ❌
- `body_template` (textarea) - **OPTIONAL** ❌
- `require_headers` (checkbox) - **OPTIONAL** ❌
- `require_body` (checkbox) - **OPTIONAL** ❌

**After:**
- `endpoint` (email) - **REQUIRED** ✅

### 3. Streamlined API Service Type
**Reason:** Removed optional fields that were rarely used and added complexity.

**Before:**
- `endpoint` (url) - **REQUIRED** ✅
- `method` (select) - **REQUIRED** ✅
- `auth_type` (select) - **OPTIONAL** ✅
- `auth_header` (text) - **OPTIONAL** ✅
- `auth_value` (password) - **OPTIONAL** ✅
- `payload_template` (json) - **REQUIRED** ✅
- `response_code` (number) - **OPTIONAL** ❌
- `timeout` (number) - **OPTIONAL** ❌

**After:**
- `endpoint` (url) - **REQUIRED** ✅
- `method` (select) - **REQUIRED** ✅
- `auth_type` (select) - **OPTIONAL** ✅
- `auth_header` (text) - **OPTIONAL** ✅
- `auth_value` (password) - **OPTIONAL** ✅
- `payload_template` (json) - **REQUIRED** ✅

### 4. Kept Custom Service Type
**Reason:** Useful for future extensibility and custom integrations.

**Fields:**
- `custom_fields` (json) - **REQUIRED** ✅

## Files Modified

1. **`modules/imap/spam_service_manager.php`**
   - Simplified `getServiceTypes()` method
   - Updated `validateServiceConfig()` method
   - Removed unused fields from default services

2. **`modules/imap/output_modules.php`**
   - Removed DNS service from modal generation
   - Updated JavaScript to remove DNS and email template field handling
   - Simplified fallback service type definitions

## Benefits

1. **Reduced Complexity:** Fewer input fields mean less confusion for users
2. **Better Performance:** Less JavaScript processing and validation
3. **Easier Maintenance:** Fewer fields to maintain and test
4. **Focused Functionality:** Only fields that are actually used are presented
5. **Improved UX:** Cleaner, more intuitive interface

## Current Service Types

### Email Service
- **Purpose:** Send spam reports via email
- **Required Fields:** Email address (endpoint)
- **Use Case:** Services like SpamCop that accept email submissions

### API Service
- **Purpose:** Send spam reports via REST API
- **Required Fields:** API endpoint, HTTP method, payload template
- **Optional Fields:** Authentication configuration
- **Use Case:** Services like AbuseIPDB, StopForumSpam, CleanTalk

### Custom Service
- **Purpose:** Custom integration with custom fields
- **Required Fields:** Custom configuration (JSON)
- **Use Case:** Future extensibility and custom integrations

## Template Variables

The following template variables are available for API services:
- `{{ ip }}` - Sender IP address
- `{{ email }}` - Sender email address
- `{{ domain }}` - Sender domain
- `{{ subject }}` - Message subject
- `{{ reason }}` - Spam report reason
- `{{ message_id }}` - Message ID
- `{{ date }}` - Report date (ISO format)
- `{{ timestamp }}` - Unix timestamp
- `{{ headers }}` - Message headers (JSON)
- `{{ body }}` - Message body content 