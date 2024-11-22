<?php

/*
 * Plugin Name:      Sheet to Meta
 * Plugin URI:        https://github.com/shakib6472/
 * Description:       Fetch Google Sheets data using the Google Sheets API.
 * Version:           1.0.0
 * Author:            Shakib Shown
 * Text Domain:       shakib
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add the admin menu
add_action('admin_menu', 'sheet_to_meta_setup_menu');

function sheet_to_meta_setup_menu() {
    add_menu_page(
        'Sheet to Meta', // Page title
        'Sheet to Meta', // Menu title
        'manage_options', // Capability
        'sheet-to-meta', // Menu slug
        'sheet_to_meta_admin_page' // Callback function
    );
}

// Main page content
function sheet_to_meta_admin_page() {
    error_log('Sheet to Meta: Starting update process.');

    $spreadsheet_id = get_option('sheet_to_meta_spreadsheet_id', '');
    $range = get_option('sheet_to_meta_range', 'Sheet1!D1:BA2'); // Only fetch rows 3 and 5 from columns D to BA

    if (empty($spreadsheet_id) || empty($range)) {
        error_log('Sheet to Meta: Spreadsheet ID or range not configured.');
        return;
    }

    $data = sheet_to_meta_fetch_data($spreadsheet_id, $range);

    if (is_array($data)) {
        $meta_keys_row_index = 0; // Row 1 corresponds to the 2nd fetched row (index 1-based)
        $meta_values_row_index = 1; // Row 2 corresponds to the 1st fetched row (index 0-based)

        $front_page_id = get_option('page_on_front');
        if (!$front_page_id) {
            error_log('Sheet to Meta: No front page set.');
            return;
        }

        // Ensure fetched rows exist
        if (!isset($data[$meta_keys_row_index]) || !isset($data[$meta_values_row_index])) {
            error_log('Sheet to Meta: Row indices out of range in fetched data.');
            return;
        }

        $meta_keys = $data[$meta_keys_row_index]; // Meta keys row (row 5)
        $meta_values = $data[$meta_values_row_index]; // Meta values row (row 3)

        // Log fetched rows for debugging
        error_log('Sheet to Meta: Meta Keys: ' . print_r($meta_keys, true));
        error_log('Sheet to Meta: Meta Values: ' . print_r($meta_values, true));

        // Iterate through all columns in the fetched range
        foreach (array_keys($meta_keys) as $col) {
            $meta_key = $meta_keys[$col] ?? null;
            $meta_value = $meta_values[$col] ?? null;

            if (!empty($meta_key)) {
                // Sanitize the value
                $sanitized_value = sanitize_meta_value($meta_value);

                // Update meta key with sanitized value
                update_post_meta($front_page_id, $meta_key, $sanitized_value);

                error_log("Sheet to Meta: Updated meta key '{$meta_key}' with sanitized value '{$sanitized_value}'.");
            } else {
                error_log("Sheet to Meta: Skipping column {$col} as meta key is empty.");
            }
        }

        error_log('Sheet to Meta: All relevant meta keys and values have been updated.');
    } else {
        error_log('Sheet to Meta: Unable to fetch data.');
    }
}

/**
 * Sanitize meta values, including links
 *
 * @param mixed $value The meta value to sanitize.
 * @return mixed The sanitized value.
 */
function sanitize_meta_value($value) {
    // If it's a URL, sanitize it as a URL
    if (filter_var($value, FILTER_VALIDATE_URL)) {
        return esc_url_raw($value);
    }

    // Otherwise, sanitize as a plain text string
    return sanitize_text_field($value);
}



function sheet_to_meta_fetch_data($spreadsheet_id, $range) {
    $service_account_key_path = plugin_dir_path(__FILE__) . 'service-account-key.json';

    if (!file_exists($service_account_key_path)) {
        error_log('Sheet to Meta: Service account key file not found.');
        return [];
    }

    require_once __DIR__ . '/vendor/autoload.php';

    try {
        $client = new \Google_Client();
        $client->setAuthConfig($service_account_key_path);
        $client->addScope(\Google_Service_Sheets::SPREADSHEETS_READONLY);

        $service = new \Google_Service_Sheets($client);

        error_log("Sheet to Meta: Fetching data from range: {$range}");
        $response = $service->spreadsheets_values->get($spreadsheet_id, $range);
        $values = $response->getValues();

        if (empty($values)) {
            error_log('Sheet to Meta: No data found in the specified range.');
            return [];
        }

        error_log('Sheet to Meta: Data fetched successfully.');
        return $values;
    } catch (Exception $e) {
        error_log('Sheet to Meta: Error fetching data - ' . $e->getMessage());
        return [];
    }
}

// Settings page
add_action('admin_menu', 'sheet_to_meta_settings_menu');

function sheet_to_meta_settings_menu() {
    add_submenu_page(
        'sheet-to-meta', // Parent slug
        'Sheet to Meta Settings', // Page title
        'Settings', // Menu title
        'manage_options', // Capability
        'sheet-to-meta-settings', // Menu slug
        'sheet_to_meta_settings_page' // Callback function
    );
}

function sheet_to_meta_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        update_option('sheet_to_meta_spreadsheet_id', sanitize_text_field($_POST['spreadsheet_id']));
        update_option('sheet_to_meta_range', sanitize_text_field($_POST['sheet_range']));
        echo '<div class="updated"><p>Settings saved!</p></div>';
    }

    $spreadsheet_id = get_option('sheet_to_meta_spreadsheet_id', '');
    $sheet_range = get_option('sheet_to_meta_range', '');

    echo '<div class="wrap">';
    echo '<h1>Sheet to Meta Settings</h1>';
    echo '<form method="POST">';
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row"><label for="spreadsheet_id">Spreadsheet ID</label></th>';
    echo '<td><input type="text" id="spreadsheet_id" name="spreadsheet_id" value="' . esc_attr($spreadsheet_id) . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="sheet_range">Sheet Range</label></th>';
    echo '<td><input type="text" id="sheet_range" name="sheet_range" value="' . esc_attr($sheet_range) . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '</table>';
    echo '<p class="submit"><input type="submit" class="button-primary" value="Save Changes" /></p>';
    echo '</form>';
    echo '</div>';
}



