<?php
/**
 * CODE SNIPPET: Replace synchronous auto-block with queue
 * 
 * Location: modules/imap/handler_modules.php
 * Class: Hm_Handler_imap_report_spam
 * Method: process()
 * 
 * STEP 1: Add this after line 2278 (after loading spam_report_services.php)
 */

// Include queue integration functions
if (!function_exists('queue_spam_sender_for_blocking')) {
    require_once APP_PATH.'modules/imap/spam_queue_integration.php';
}

/**
 * STEP 2: Replace lines 2363-2437 with this code
 * 
 * Find this section (starts around line 2363):
 *   $move_result = $mailbox->message_action($form_folder, 'MOVE', array($uid), $junk_folder);
 * 
 * Replace everything from there until:
 *   $bulk_results[] = $result;
 * 
 * With the code below:
 */

// Move message to junk folder
$move_result = $mailbox->message_action($form_folder, 'MOVE', array($uid), $junk_folder);
if ($move_result['status']) {
    $result['success'] = true;
    
    // Queue sender for async Sieve synchronization (non-blocking)
    $queue_result = queue_spam_sender_for_blocking(
        $this->user_config,
        $form['imap_server_id'],
        $message_data,
        $spam_reason,
        $junk_folder
    );
    
    if ($queue_result['success'] && $queue_result['queued']) {
        // Successfully queued
        delayed_debug_log('Spam sender queued for Sieve sync', array(
            'entry_id' => $queue_result['entry_id'],
            'sender' => $queue_result['sender'],
            'server_id' => $form['imap_server_id']
        ));
        
        // Store queue info for UI feedback (optional)
        $result['queue_info'] = $queue_result;
        
    } else if (!$queue_result['success']) {
        // Queueing failed - log warning but don't fail the whole operation
        delayed_debug_log('Failed to queue sender for blocking', array(
            'error' => isset($queue_result['error']) ? $queue_result['error'] : 'Unknown error',
            'sender' => isset($message_data['from']) ? $message_data['from'] : 'unknown'
        ), 'warning');
        
        // Message is still in junk, just won't be auto-blocked
        $result['error'] .= 'Auto-block queuing failed (sender will not be blocked automatically); ';
    }
    // If success=true but queued=false: auto-blocking is disabled by user
    
} else {
    $result['error'] .= 'Move to junk failed; ';
}

$bulk_results[] = $result;

/**
 * OPTIONAL STEP 3: Enhance success message
 * 
 * Find this section (around line 2447-2451):
 *   if ($status) {
 *       Hm_Msgs::add('Message reported as spam and moved to junk folder', 'success');
 *   } else {
 *       Hm_Msgs::add('Failed to report message as spam', 'danger');
 *   }
 * 
 * Replace with:
 */

if ($status) {
    // Check if sender was queued for blocking
    if (isset($bulk_results[0]['queue_info']['queued']) && $bulk_results[0]['queue_info']['queued']) {
        Hm_Msgs::add(
            'Message reported as spam and moved to junk folder. Sender will be blocked automatically.',
            'success'
        );
    } else {
        Hm_Msgs::add('Message reported as spam and moved to junk folder', 'success');
    }
} else {
    Hm_Msgs::add('Failed to report message as spam', 'danger');
}

/**
 * BULK OPERATION VERSION (around line 2461-2467)
 * 
 * Find:
 *   if ($success_count === count($bulk_results)) {
 *       Hm_Msgs::add('All messages reported as spam and moved to junk folder', 'success');
 *   } elseif ($success_count > 0) {
 *       Hm_Msgs::add("$success_count of " . count($bulk_results) . " messages reported as spam", 'warning');
 *   } else {
 *       Hm_Msgs::add('Failed to report messages as spam', 'danger');
 *   }
 * 
 * Replace with:
 */

// Count queued senders
$queued_count = 0;
foreach ($bulk_results as $result) {
    if (isset($result['queue_info']['queued']) && $result['queue_info']['queued']) {
        $queued_count++;
    }
}

if ($success_count === count($bulk_results)) {
    if ($queued_count > 0) {
        $msg = sprintf(
            'All %d messages reported as spam and moved to junk folder. %d sender(s) queued for automatic blocking.',
            count($bulk_results),
            $queued_count
        );
    } else {
        $msg = 'All messages reported as spam and moved to junk folder';
    }
    Hm_Msgs::add($msg, 'success');
} elseif ($success_count > 0) {
    if ($queued_count > 0) {
        $msg = sprintf(
            '%d of %d messages reported as spam. %d sender(s) queued for blocking.',
            $success_count,
            count($bulk_results),
            $queued_count
        );
    } else {
        $msg = "$success_count of " . count($bulk_results) . " messages reported as spam";
    }
    Hm_Msgs::add($msg, 'warning');
} else {
    Hm_Msgs::add('Failed to report messages as spam', 'danger');
}

/**
 * THAT'S IT! 
 * 
 * Summary of changes:
 * 1. Added require_once for spam_queue_integration.php
 * 2. Replaced ~70 lines of synchronous Sieve code with ~30 lines of queue code
 * 3. Enhanced user messages to mention queuing
 * 
 * Result:
 * - Users get instant response (no waiting for Sieve)
 * - Senders are queued for blocking
 * - Cron job will process queue in background
 * - Automatic retries on failure
 * - No data loss
 */

