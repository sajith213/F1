<?php
// api/auth_api.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow requests from any origin (adjust for production)
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests for CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

require_once '../includes/db.php'; // Database connection
require_once '../includes/functions.php'; // Common functions

// --- Simple Token Generation/Validation (Example - Consider JWT for production) ---
function generate_token($user_id) {
    // In a real app, use a more secure method like JWT or store tokens securely
    // This is a basic example
    return bin2hex(random_bytes(32)) . '_uid_' . $user_id;
}

// --- Store/Retrieve Token (Example - Store in a dedicated table) ---
function store_token($conn, $user_id, $token) {
    // Example: Assumes an 'api_tokens' table with user_id, token, expires_at
    $expires_at = date('Y-m-d H:i:s', strtotime('+2 hours')); // Token valid for 2 hours
    $stmt = $conn->prepare("INSERT INTO api_tokens (user_id, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)");
    $stmt->bind_param("iss", $user_id, $token, $expires_at);
    return $stmt->execute();
}
// --- End Token Helpers ---


$response = ['status' => 'error', 'message' => 'Invalid Request'];

// Only accept POST requests for login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $username = $input['username'] ?? null;
    $password = $input['password'] ?? null;

    if ($username && $password) {
        // Reuse your existing login logic structure from login.php / auth.php
        $stmt = $conn->prepare("SELECT user_id, password_hash, full_name, role FROM users WHERE username = ? AND status = 'active'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            // Verify password using the hash from your database
            // Assuming your web app uses password_hash()
            if (password_verify($password, $user['password_hash'])) {
                // Password is correct - Generate and store a token
                $token = generate_token($user['user_id']);
                if (store_token($conn, $user['user_id'], $token)) {
                     $response = [
                        'status' => 'success',
                        'message' => 'Login successful',
                        'token' => $token,
                        'user' => [ // Send some basic user info back
                            'user_id' => $user['user_id'],
                            'full_name' => $user['full_name'],
                            'role' => $user['role']
                        ]
                    ];
                } else {
                     $response['message'] = 'Could not store authentication token.';
                }

            } else {
                $response['message'] = 'Invalid username or password.';
            }
        } else {
            $response['message'] = 'Invalid username or password.';
        }
        $stmt->close();
    } else {
        $response['message'] = 'Username and password are required.';
    }
}

echo json_encode($response);
?>