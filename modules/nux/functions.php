<?php

/**
 * NUX modules
 * @package modules
 * @subpackage nux
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Build a source list for sent folders
 * @subpackage nux/functions
 * @param string $file_path file path
 * @param string $delimiter csv delimiter with default ;
 * @return array
 */
if (!hm_exists('parse_csv_with_headers')) {
function parse_csv_with_headers($file_path, $delimiter = ';') {
    // Open the file
    $file = new SplFileObject($file_path);
    // Set the file to read as CSV
    $file->setFlags(SplFileObject::DROP_NEW_LINE);

    // Initialize an array to hold the rows
    $rows = [];
    $is_first_line = true;
    $header = [];
    // Loop through each line in the CSV file
    while (!$file->eof()) {
        // Get the line as a string
        $line = $file->fgets();

        // Skip empty lines (e.g., due to trailing newlines)
        if ($line === [null] || $line === false) {
            continue;
        }

        // Split the line into an array using the specified delimiter
        $fields = explode($delimiter, $line);
        // Process the header line
        if ($is_first_line) {
            $header = $fields;
            $is_first_line = false;
        } else {
            // Ensure that the number of fields matches the header count
            if (count($fields) === count($header)) {
                // Combine the header and fields into an associative array
                $line_data = array_combine($header, $fields);
                foreach ($line_data as $key => $value) {
                    $line_data[$key] = convert_to_boolean($value);
                }
                $rows[] = $line_data;
            }
        }
    }
    return $rows;
}}

if (!hm_exists('convert_to_boolean')) {
function convert_to_boolean($value) {
    if (in_array($value, ['true', '1', 'yes'])) {
        return true;
    } elseif (in_array($value, ['false', '0', 'no'])) {
        return false;
    }
    return $value;
}}

/**
 * @subpackage nux/functions
 */
if (!hm_exists('oauth2_form')) {
    function oauth2_form($details, $mod)
    {
        $oauth2 = new Hm_Oauth2($details['client_id'], $details['client_secret'], $details['redirect_uri']);
        $url = $oauth2->request_authorization_url($details['auth_uri'], $details['scope'], 'nux_authorization', $details['email']);
        $res = '<input type="hidden" name="nux_service" value="' . $mod->html_safe($details['id']) . '" />';
        $res .= '<div class="nux_step_two_title fw-bold">' . $mod->html_safe($details['name']) . '</div><div class="mb-3">';
        $res .= $mod->trans('This provider supports Oauth2 access to your account.');
        $res .= $mod->trans(' This is the most secure way to access your E-mail. Click "Enable" to be redirected to the provider site to allow access.');
        $res .= '</div><div class="mb-3"><a class="enable_auth2 btn btn-sm btn-success me-2" data-external="true" href="' . $url . '">' . $mod->trans('Enable') . '</a>';
        $res .= '<a href="" class="reset_nux_form btn btn-sm btn-secondary">Reset</a></div>';
        return $res;
    }
}

/**
 * @subpackage nux/functions
 */
if (!hm_exists('credentials_form')) {
    function credentials_form($details, $mod)
    {
        $res = '<input type="hidden" id="nux_service" name="nux_service" value="' . $mod->html_safe($details['id']) . '" />';
        $res .= '<input type="hidden" name="nux_name" class="nux_name" value="' . $mod->html_safe($details['name']) . '" />';
        $res .= '<div class="nux_step_two_title"><b>' . $mod->html_safe($details['name']) . '</b></div>';
        $res .= $mod->trans('Enter your password for this E-mail provider to complete the connection process');

        $res .= '<div class="row"><div class="col col-lg-4">';
        // E-mail Address Field
        $res .= '<div class="form-floating mb-3 mt-3">';
        $res .= '<input type="text" class="form-control" id="nux_email" name="nux_email" placeholder="' . $mod->trans('E-mail Address') . '" value="' . $mod->html_safe($details['email']) . '">';
        $res .= '<label for="nux_email">' . $mod->trans('E-mail Address') . '</label></div>';

        // E-mail Password Field
        $res .= '<div class="form-floating mb-3">';
        $res .= '<input type="password" class="form-control nux_password" id="nux_password" name="nux_password" placeholder="' . $mod->trans('E-Mail Password') . '">';
        $res .= '<label for="nux_password">' . $mod->trans('E-mail Password') . '</label></div>';
        $res .= '<div class="d-flex flex-md-row gap-3 mt-3">';
        // Connect Button
        $res .= '<input type="button" class="nux_submit px-5 btn btn-primary w-100 w-md-auto" value="' . $mod->trans('Connect') . '">';

        // Reset Link
        $res .= '<a href="" class="reset_nux_form px-5 btn btn-secondary w-100 w-md-auto">Reset</a>';

        $res .= '</div></div></div>';

        return $res;
    }
}

/**
 * @subpackage nux/functions
 */
if (!hm_exists('data_source_available')) {
    function data_source_available($mods, $types)
    {
        if (!is_array($types)) {
            $types = array($types);
        }
        return count(array_intersect($types, $mods)) == count($types);
    }
}
