<?php
// Turn on all error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Create a custom error log file in a writable directory
$custom_log = __DIR__ . '/custom_error_log.txt';
ini_set('error_log', $custom_log);

// Test if we can write to this file
file_put_contents($custom_log, date('Y-m-d H:i:s') . " - Error check started\n", FILE_APPEND);

// Check PHP configuration
echo "<h2>PHP Error Configuration</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "display_errors: " . ini_get('display_errors') . "<br>";
echo "error_reporting: " . ini_get('error_reporting') . "<br>";
echo "log_errors: " . ini_get('log_errors') . "<br>";
echo "error_log: " . ini_get('error_log') . "<br>";
echo "Custom log location: " . $custom_log . "<br>";

// Test error logging with different error types
echo "<h2>Testing Error Logging</h2>";
echo "Generating test errors...<br>";

// Test writing to custom log
error_log("Test message to error log");
echo "Written test message to error log<br>";

// Test user error
trigger_error("Test user warning", E_USER_WARNING);
echo "Generated E_USER_WARNING<br>";

// Test notice error
echo $undefined_variable;
echo "Generated undefined variable notice<br>";

// Check if log file is writable and view recent errors
echo "<h2>Log File Check</h2>";
if (is_writable($custom_log)) {
    echo "Custom log file is writable<br>";
    echo "Last 5 lines of custom log:<br>";
    echo "<pre>";
    $log_content = file_get_contents($custom_log);
    $lines = explode("\n", $log_content);
    $last_lines = array_slice($lines, -6, 5);
    echo htmlspecialchars(implode("\n", $last_lines));
    echo "</pre>";
} else {
    echo "WARNING: Custom log file is NOT writable<br>";
}

echo "<h2>Next Steps</h2>";
echo "1. Review the custom error log file at: " . $custom_log . "<br>";
echo "2. Add the following code at the top of functions.php to capture specific errors there:<br>";
echo "<pre>";
echo htmlspecialchars('<?php
// Add this at the top of functions.php
ini_set(\'error_log\', \'' . $custom_log . '\');
function my_error_handler($errno, $errstr, $errfile, $errline) {
    error_log("METER ERROR [$errno] $errstr in $errfile on line $errline");
    return false; // Continue with PHP\'s internal error handler
}
set_error_handler("my_error_handler");
');
echo "</pre>";
?>