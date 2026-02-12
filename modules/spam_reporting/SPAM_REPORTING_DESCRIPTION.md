# Spam Reporting Feature — Description and How It Works

## Overview

The **Spam Reporting** module lets Cypht users report spam or abuse emails to external services (email abuse desks or API-based platforms) directly from the message view. Reports are built from the currently viewed message and sent either as an email (with full headers and optional body) or as an API payload (e.g. source IP only). The feature is **opt-in**: users must enable it and choose which platforms they allow in **Settings → Spam Reporting**. Administrators control which *target types* are available (e.g. AbuseIPDB, generic email targets) and, for email targets, configure a dedicated SMTP server and sender identity so reports are sent from a system address, not the user’s.

---

## High-Level Flow

1. **User** opens a message in the mail UI.
2. **UI** shows a “Report spam” action (only if the user has enabled spam reporting in settings).
3. **User** clicks “Report spam” → a modal opens.
4. **Front end** calls the **spam report preview** AJAX endpoint with `list_path` and `uid` to identify the message.
5. **Back end** fetches the message from IMAP, parses it into an `Hm_Spam_Report`, determines **effective targets** (from user config + allowed target types), optionally **detects providers** from headers and **suggests** targets, then returns targets, suggestion, platform catalog, and preview (headers/body).
6. **User** selects a target, optionally adds notes, and clicks “Send report”.
7. **Front end** calls the **spam report send** AJAX endpoint with `list_path`, `uid`, `target_id`, and `user_notes`.
8. **Back end** applies **rate limiting**, rebuilds the report, resolves the target to an adapter + instance config, builds the payload, and **delivers** via the adapter (email or API).
9. **User** sees success or error; on success the rate limit is incremented and the action is logged.

---

## Main Concepts

### 1. Report (message-side)

- **`Hm_Spam_Report`**  
  Internal model built from a raw MIME message (from IMAP). It exposes:
  - `raw_message`, `headers`, `body_text`, `body_html`
  - `get_source_ips()` — IPs from first-hop Received headers (for IP-based reporting, e.g. AbuseIPDB)
  - `get_raw_headers_string()` — formatted headers for preview/sending  
  Built via `Hm_Spam_Report::from_mailbox($mailbox, $folder, $uid)` or `from_raw_message()`.

### 2. Targets and adapters

- **Target** = one concrete destination the user can choose when reporting (e.g. “SpamCop”, “My AbuseIPDB”, “Google Abuse”).
- **Adapter** = PHP class that implements `Hm_Spam_Report_Target_Interface` and knows how to:
  - Expose `id()`, `label()`, `platform_id()`, `capabilities()`, `requirements()`
  - Define a **configuration schema** (e.g. destination email, API key)
  - Say if it’s **available** for a given report (e.g. has API key, has source IP, destination ≠ From/Reply-To)
  - **Build payload** (email payload or API payload) and **deliver** it.

Two adapter types are built-in:

| Type ID        | Class                           | Platform ID | Method | What it sends |
|----------------|----------------------------------|-------------|--------|----------------|
| `email_target` | `Hm_Spam_Report_Email_Target`    | (user-set)  | Email  | To/subject/body + attached full message (message/rfc822) |
| `abuseipdb`    | `Hm_Spam_Report_AbuseIPDB_Target`| `abuseipdb` | API    | IP (from Received), category 11, optional comment |

- **Email target**: uses **site-level SMTP** (`spam_reporting_smtp_*`, `spam_reporting_sender_*`) to send; destination and label come from **user-configured “target” instances** (or legacy adapter config).
- **AbuseIPDB**: sends one IP (first from `get_source_ips()`), category “Email Spam”, optional user notes; API key can be **per-user** (instance config) or legacy site-level.

### 3. Platform catalog (display only)

- **`spam_report_platforms.json`**  
  List of known reporting “platforms” (SpamCop, Spamhaus, Google Abuse, AbuseIPDB, etc.) with:
  - `id`, `name`, `description`
  - `methods` (e.g. `["email"]` or `["api"]`)
  - `required_data` / `allowed_data` / `never_send` (headers, body, ip, user_notes, user_identity)  
  Used for:
  - **Settings**: which platforms to show as toggles (derived from which platform_ids the loaded targets use).
  - **Report modal**: “Reporting platforms” list and “What will be sent” summary.  
  It does **not** define where to send; that comes from **targets**.

### 4. Provider mapping (suggestions)

- **`spam_report_provider_mapping.json`**  
  Maps **provider signals** (domains/hosts in Received, Authentication-Results, Return-Path, From) to **platform_ids** and provider names (e.g. Gmail → `google_abuse`, Microsoft → `microsoft_abuse`).
- **Provider detection** (`spam_reporting_detect_providers`) scans the message headers, scores providers by signal strength, and returns detected providers with their `platform_ids`.
- **Suggestion logic** maps those `platform_ids` to **target ids** the user actually has. Those targets are shown first in the modal (e.g. “Recommended platforms”) and an explanation like “Suggested because the message appears to come from Gmail” is shown.
- If the **user’s mailbox** is detected as the same provider as the message, a **self-report note** is shown (e.g. “This message appears to originate from the same provider as your mailbox”).

---

## Configuration

### Site (admin) — `config/app.php` and `.env`

- **`spam_reporting_allowed_target_types`**  
  Comma-separated adapter type IDs, e.g. `abuseipdb,email_target`.  
  Env: `SPAM_REPORTING_ALLOWED_TARGET_TYPES=abuseipdb,email_target`  
  Drives which target types appear in Settings “Add target” and which adapters are instantiated. Empty = no spam reporting targets.

- **Platform / provider data**
  - `spam_reporting_platforms_file` → `data/spam_report_platforms.json`
  - `spam_reporting_provider_mapping_file` → `data/spam_report_provider_mapping.json`

- **Email targets (site SMTP)**  
  Used by the email adapter to send reports; not the user’s SMTP:
  - `spam_reporting_smtp_server`, `spam_reporting_smtp_port`, `spam_reporting_smtp_tls`
  - `spam_reporting_smtp_user`, `spam_reporting_smtp_pass` (or no auth)
  - `spam_reporting_sender_address`, `spam_reporting_sender_name`, `spam_reporting_reply_to`

- **Rate limit**  
  - `spam_reporting_rate_limit_count` (default 5)
  - `spam_reporting_rate_limit_window` (default 3600 seconds)

- **Legacy (deprecated)**  
  - `spam_reporting_targets` — list of target class configs (ignored when `spam_reporting_allowed_target_types` is set).
  - `spam_reporting_abuseipdb_api_key` — site-wide AbuseIPDB key (still used if user does not set a per-target API key).

### User (Settings → Spam Reporting)

- **Enable**  
  Checkbox “Enable external spam reporting”. Stored as `spam_reporting_enabled_setting`.

- **Allowed platforms**  
  One checkbox per platform that appears in the targets registry (from catalog + allowed target types). Stored as `spam_reporting_allowed_platforms_setting` (array of `platform_id`s). Only targets whose `platform_id()` is in this list are offered in the report modal.

- **Reporting targets (instances)**  
  User-defined list of “targets”: each has:
  - `id` (unique), `adapter_id` (e.g. `email_target`, `abuseipdb`), `label`
  - `settings` — adapter-specific (e.g. `to`, `label`, `subject_prefix` for email; `api_key` for AbuseIPDB).  
  Stored as `spam_reporting_target_configurations`. Secrets (e.g. `api_key`) are not sent to the client in settings UI; `__KEEP__` is used when saving so existing secrets are preserved.

If the user has **no** target configurations, the module falls back to **one virtual target per allowed adapter type** (adapter id/label, no instance config). Once the user adds at least one target, only those configured targets are used.

---

## Request Flow (Technical)

### Preview (ajax_spam_report_preview)

1. **Input**: `list_path`, `uid` (from POST).
2. **Resolve message**: `get_request_params()` → `server_id`, `uid`, `folder`, `msg_id`; get mailbox from IMAP list; fetch message content.
3. **Build report**: `spam_reporting_build_report($mailbox, $folder, $uid)` → `Hm_Spam_Report`.
4. **Effective targets**: `spam_reporting_get_effective_targets($config, $user_config, $report)` (filters by `is_available`), then `spam_reporting_filter_targets_by_user_settings()` (enabled + allowed platforms).
5. **Provider detection**: `spam_reporting_load_provider_mapping()`, `spam_reporting_detect_providers($message, $mappings)`.
6. **Suggestions**: `spam_reporting_suggested_target_ids($detected, $targets)`; reorder targets (suggested first); build explanation and self-report note.
7. **Output**: Public target descriptors (no adapter/instance_config), suggestion, platform catalog, preview (headers, body_text, body_html). Optional error/debug.

### Send (ajax_spam_report_send)

1. **Input**: `list_path`, `uid`, `target_id`, `user_notes`.
2. **Rate limit**: `spam_reporting_rate_limit($session, $config, false)`; if not allowed, return error and optional retry_after.
3. **Resolve message**: same as preview; get mailbox and build report.
4. **Resolve target**: `spam_reporting_resolve_target_id($config, $user_config, $target_id)` → adapter + instance_config.
5. **Checks**: target exists; user has spam reporting enabled; target’s platform is in user’s allowed platforms; `$target->is_available($report, $user_config, $instance_config)`.
6. **Build & deliver**: `$target->build_payload($report, ['user_notes' => $notes], $instance_config)`; `Hm_Spam_Report_Delivery_Context` with site_config, user_config, session, instance_config; `$target->deliver($payload, $context)` → `Hm_Spam_Report_Result`.
7. **On success**: call `spam_reporting_rate_limit(..., true)` to record the send; debug log (user, target_id, msg id).
8. **Output**: `spam_report_send_ok`, `spam_report_send_message`.

---

## UI Integration

- **Message view**  
  Output module adds a “Report spam” link/button with `data-uid` and `data-list-path`. It is only rendered when `spam_reporting_enabled_setting` is true.
- **Modal**  
  One modal (id `spamReportModal`) is output on the message page (and injected for AJAX message content). It contains:
  - Target dropdown (filled from preview response), suggestion text, self-report note
  - “What will be sent” summary (from platform catalog)
  - Reporting platforms list (catalog)
  - Preview: headers, plain body, notes, optional HTML body
  - “Send report” and “Close”
- **Settings**  
  Section “Spam Reporting”: enable checkbox, per-platform checkboxes, “Reporting targets” list (add/edit/remove), hidden field for JSON target configurations; modal for adding/editing a target (adapter type, label, schema fields). JS syncs the list to the hidden field and handles “Add target” and schema-based form.

---

## Data and Privacy

- **Email target**  
  Sends full original message as `message/rfc822` plus optional user notes. Destination is user- or admin-configured. The adapter forbids using the message’s From/Reply-To as the report destination to avoid self-reporting to the apparent sender.
- **AbuseIPDB**  
  Sends only: one source IP, category 11 (Email Spam), optional comment (user notes). No headers, body, or user identity.
- **Platform catalog**  
  Defines what *can* be sent (required_data, allowed_data, never_send) for transparency; the actual payload is built by the adapter.

---

## Files Reference

| Area            | Files |
|-----------------|--------|
| Config          | `config/app.php` (spam_reporting_* keys), `.env` (SPAM_REPORTING_*) |
| Data            | `data/spam_report_platforms.json`, `data/spam_report_provider_mapping.json` |
| Module entry    | `modules/spam_reporting/modules.php`, `setup.php` |
| Logic           | `modules/spam_reporting/functions.php` |
| Handlers        | `modules/spam_reporting/handler_modules.php` (preview, send, settings) |
| Output          | `modules/spam_reporting/output_modules.php` (action, modal, settings section, AJAX outputs) |
| Adapters        | `modules/spam_reporting/adapters/` (interface, abstract, registry, Email, AbuseIPDB, AbstractApi) |
| Front end       | `modules/spam_reporting/site.js`, `site.css` |

---

## Summary

Spam reporting is an **opt-in, target-based** feature: the admin enables **adapter types** (e.g. AbuseIPDB, email) and configures SMTP for email; the user enables the feature, selects **allowed platforms**, and optionally adds **target instances** (e.g. abuse@example.org, or AbuseIPDB with their API key). When reporting, the app builds a report from the current message, suggests targets based on provider detection, and sends either an email (full message + notes) or an API payload (e.g. IP + category + comment) via the chosen adapter, subject to rate limiting and availability checks.
