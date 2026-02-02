<?php

/**
 * Spam reporting outputs (Phase 1 stub)
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Adds a "Report spam" action to message view controls
 * @subpackage spam_reporting/output
 */
class Hm_Output_spam_report_action extends Hm_Output_Module {
    protected function output() {
        $button = '<a class="spam_report_action hlink text-decoration-none btn btn-sm btn-outline-danger" href="#">'.
            $this->trans('Report spam').'</a>';
        $this->concat('message_actions_extra', $button);
    }
}

/**
 * Modal UI for spam reporting
 * @subpackage spam_reporting/output
 */
class Hm_Output_spam_report_modal extends Hm_Output_Module {
    protected function output() {
        if ($this->format !== 'HTML5') {
            return;
        }
        return spam_reporting_modal_markup([$this, 'trans']);
    }
}

/**
 * Inline modal injection for AJAX message content
 * @subpackage spam_reporting/output
 */
class Hm_Output_spam_report_modal_inline extends Hm_Output_Module {
    protected function output() {
        if ($this->format !== 'JSON') {
            return;
        }
        $this->concat('msg_headers', spam_reporting_modal_markup([$this, 'trans']));
    }
}

/**
 * Output spam report preview JSON
 * @subpackage spam_reporting/output
 */
class Hm_Output_spam_report_preview extends Hm_Output_Module {
    protected function output() {
        $this->out('spam_report_targets', $this->get('spam_report_targets', array()));
        $this->out('spam_report_preview', $this->get('spam_report_preview', array()));
        if ($this->get('spam_report_error')) {
            $this->out('spam_report_error', $this->get('spam_report_error'));
        }
    }
}
