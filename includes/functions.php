<?php
/**
 * Common Functions
 * 
 * This file contains utility functions used throughout the application
 */

/**
 * Load system settings from database into global array
 * 
 * @param mysqli $conn Database connection
 * @return array Array of settings
 */
function load_settings($conn) {
    global $app_settings;
    
    // Initialize settings array if it doesn't exist
    if (!isset($app_settings)) {
        $app_settings = [];
    }
    
    // Query all settings from database
    $query = "SELECT setting_name, setting_value FROM system_settings";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $app_settings[$row['setting_name']] = $row['setting_value'];
        }
    }
    
    return $app_settings;
}

/**
 * Get a system setting value
 * 
 * @param string $setting_name Name of the setting
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value or default if not found
 */
function get_setting($setting_name, $default = null) {
    global $app_settings;
    
    if (isset($app_settings[$setting_name])) {
        return $app_settings[$setting_name];
    }
    
    return $default;
}

/**
 * Format a number as currency
 * 
 * @param float $amount Amount to format
 * @param bool $include_symbol Whether to include currency symbol
 * @return string Formatted currency
 */
function format_currency($amount, $include_symbol = true) {
    $currency_symbol = get_setting('currency_symbol', '$');
    $formatted = number_format((float)$amount, 2, '.', ',');
    
    if ($include_symbol) {
        return $currency_symbol . $formatted;
    }
    
    return $formatted;
}

/**
 * Format date for display
 * 
 * @param string $date Date string
 * @param string $format Format (short, medium, long)
 * @return string Formatted date
 */
function format_date($date, $format = 'medium') {
    if (empty($date)) {
        return '';
    }
    
    $date_obj = new DateTime($date);
    
    switch ($format) {
        case 'short':
            return $date_obj->format('m/d/Y');
        case 'medium':
            return $date_obj->format('M j, Y');
        case 'long':
            return $date_obj->format('F j, Y');
        case 'datetime':
            return $date_obj->format('M j, Y g:i A');
        default:
            return $date_obj->format('Y-m-d');
    }
}

/**
 * Check if a value is empty but allow zero
 * 
 * @param mixed $value Value to check
 * @return bool True if empty and not zero
 */
function is_empty_excluding_zero($value) {
    return $value === null || $value === '' || (is_string($value) && trim($value) === '');
}