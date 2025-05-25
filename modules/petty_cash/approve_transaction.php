<?php
/**
 * Petty Cash Management - Approve Transaction
 * 
 * Approve or reject petty cash transactions
 */

// Start output buffering to prevent "headers already sent" errors
ob_start();

// Set page title
$page_title = "Approve Petty Cash Transaction";
$breadcrumbs = "Home > Finance > Petty Cash > Approve Transaction";

// Include header
include '../../includes/header.php';

// Include database connection
require_once '../../includes/db.php';

// Include auth functions
require_once '../../includes/auth.php';

// Check permission
if (!has_permission('approve_petty_cash')) {
    // Redirect to dashboard or show error
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">';
    echo '<p>You do not have permission to access this page.</p>';
    echo '</div>';
    include '../../includes/footer.php';
    exit;
}

// Initialize variables
$transaction = null;
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error_message = '';
$success_message = '';

// Check if transaction exists
if ($transaction_id <= 0) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">';
    echo '<p>Invalid transaction ID. <a href="index.php" class="font-semibold underline">Go back to transactions</a></p>';
    echo '</div>';
    include '../../includes/footer.php';
    exit;
}

// Only fetch data if database connection is successful
if (isset($conn) && $conn) {
    try {
        // Get transaction data with related information
        $query = "SELECT t.*, 
                  c.category_name, 
                  creator.full_name as created_by_name 
                  FROM petty_cash t
                  LEFT JOIN petty_cash_categories c ON t.category_id = c.category_id
                  LEFT JOIN users creator ON t.created_by = creator.user_id
                  WHERE t.transaction_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $transaction = $result->fetch_assoc();
            
            // Check if the transaction is already processed
            if ($transaction['status'] !== 'pending') {
                echo '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4" role="alert">';
                echo '<p>This transaction has already been ' . $transaction['status'] . '. <a href="index.php" class="font-semibold underline">Go back to transactions</a></p>';
                echo '</div>';
                include '../../includes/footer.php';
                exit;
            }
        } else {
            echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">';
            echo '<p>Transaction not found. <a href="index.php" class="font-semibold underline">Go back to transactions</a></p>';
            echo '</div>';
            include '../../includes/footer.php';
            exit;
        }
        
        $stmt->close();
    } catch (Exception $e) {
        // Log error
        error_log("Petty cash transaction fetch error: " . $e->getMessage());
        $error_message = "Error fetching transaction: " . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if ($action === 'approve' || $action === 'reject') {
        try {
            $status = ($action === 'approve') ? 'approved' : 'rejected';
            $user_id = get_current_user_id();
            
            $stmt = $conn->prepare("UPDATE petty_cash SET status = ?, approved_by = ?, updated_at = NOW() WHERE transaction_id = ?");
            $stmt->bind_param("sii", $status, $user_id, $transaction_id);
            
            if ($stmt->execute()) {
                $success_message = "Transaction successfully " . ($status === 'approved' ? 'approved' : 'rejected') . ".";
                echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">';
                echo '<p>' . $success_message . '</p>';
                echo '<p class="mt-2 text-sm">Redirecting to transaction list...</p>';
                echo '</div>';
                
                // Use JavaScript for redirection instead of PHP header
                echo '<script>
                        setTimeout(function() {
                            window.location.href = "index.php";
                        }, 2000);
                      </script>';
            } else {
                $error_message = "Error updating transaction status: " . $stmt->error;
            }
            
            $stmt->close();
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
            error_log("Petty cash approval error: " . $e->getMessage());
        }
    } else {
        $error_message = "Invalid action. Please choose either approve or reject.";
    }
}

// Rest of your approve_transaction.php code...

// Include footer
include '../../includes/footer.php';

// Flush the output buffer
ob_end_flush();
?>