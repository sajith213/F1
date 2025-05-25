<?php
// api/tank_api.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

require_once '../includes/db.php';
require_once '../includes/functions.php';
// You might need functions specific to tank management
require_once '../modules/tank_management/functions.php'; // Contains tank-related logic

// --- Simple Token Validation (Example) ---
function get_user_id_from_token($conn, $token) {
    // Example: Assumes 'api_tokens' table
    $stmt = $conn->prepare("SELECT user_id FROM api_tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['user_id'];
    }
    return null; // Token invalid or expired
}

function validate_token($conn) {
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? null; // Expect "Bearer YOUR_TOKEN"
    if ($auth_header && preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
        $token = $matches[1];
        return get_user_id_from_token($conn, $token);
    }
    return null; // No valid token found
}
// --- End Token Helpers ---


$response = ['status' => 'error', 'message' => 'Invalid Request'];

// --- Authentication Check ---
$authenticated_user_id = validate_token($conn);
if (!$authenticated_user_id) {
    $response['message'] = 'Authentication Required';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit;
}
// --- End Authentication Check ---


// Proceed only if authenticated
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $action = $_GET['action'] ?? 'getAllTanks'; // Default action

    try {
        if ($action == 'getAllTanks') {
            // Reuse logic similar to your web dashboard or tank list page
            // Adapt the query from index.php or modules/tank_management/index.php
            $query = "SELECT t.tank_id, t.tank_name, t.current_volume, t.capacity, t.low_level_threshold, t.status, ft.fuel_name
                      FROM tanks t
                      JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id
                      ORDER BY t.tank_name";
            $result = $conn->query($query);
            $tanks = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $row['percentage'] = ($row['capacity'] > 0) ? round(($row['current_volume'] / $row['capacity']) * 100, 1) : 0;
                    $tanks[] = $row;
                }
                 $response = [
                    'status' => 'success',
                    'message' => 'Tanks fetched successfully.',
                    'data' => $tanks
                 ];
            } else {
                throw new Exception("Database query failed: " . $conn->error);
            }

        } elseif ($action == 'getTankDetails' && isset($_GET['id'])) {
            $tank_id = (int)$_GET['id'];
            // Add logic to fetch details for a single tank (similar query with WHERE tank_id = ?)
             $response['message'] = 'Get Tank Details action not fully implemented yet.';
             // Implement fetching single tank details here...
        } else {
             $response['message'] = 'Invalid action specified.';
        }
    } catch (Exception $e) {
        $response['message'] = "An error occurred: " . $e->getMessage();
        error_log("API Error (tank_api.php): " . $e->getMessage()); // Log error
    }
}

echo json_encode($response);
?>