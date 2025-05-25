<?php
ob_start(); // Start output buffering
/**
 * Fuel Ordering Module - Process Payment
 *
 * This page allows users to record payments for purchase orders.
 * Supports full payment, partial payment, and payment history.
 * Integrates with petroleum account for automatic deduction.
 * Allows uploading and viewing payment receipts.
 */

// Initialize session if not already started (important for flash messages)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set page title
$page_title = "Process Payment";

// Set breadcrumbs (will be updated later if order details are loaded)
$initial_breadcrumbs = '<a href="../../index.php" class="text-blue-600 hover:text-blue-800">Home</a> /
                       <a href="index.php" class="text-blue-600 hover:text-blue-800">Fuel Ordering</a> /
                       <a href="view_orders.php" class="text-blue-600 hover:text-blue-800">View Orders</a> /
                       Process Payment';

// Include header (ensure session is started before this if header uses sessions)
include_once '../../includes/header.php';

// Include necessary functions and connections
require_once 'functions.php'; // Fuel ordering functions
require_once '../../includes/db.php'; // Database connection
require_once '../../modules/petroleum_account/check_balance.php'; // Petroleum account functions

// <<< ADDED: Define the upload directory (relative to this script's location)
// IMPORTANT: Make sure this directory exists and is writable by the web server!
// Adjust the path if needed (e.g., '../../uploads/receipts/')
define('RECEIPT_UPLOAD_DIR', 'uploads/receipts/');
// Create the directory if it doesn't exist
if (!file_exists(RECEIPT_UPLOAD_DIR)) {
    mkdir(RECEIPT_UPLOAD_DIR, 0777, true); // Adjust permissions as needed for your server
}
// <<< END ADDED

// Check for permissions (assuming $user_data is set in header.php)
if (!isset($user_data) || !in_array($user_data['role'], ['admin', 'manager'])) {
    // Use session flash message for better user experience
    $_SESSION['flash_message'] = "You do not have permission to access this module.";
    $_SESSION['flash_type'] = 'error';
    // Redirect or display minimal page with error and footer
    echo '<div class="container mx-auto px-4 py-4">';
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p>' . htmlspecialchars($_SESSION['flash_message']) . '</p>
          </div>';
    echo '</div>';
    unset($_SESSION['flash_message']); // Clear after displaying
    unset($_SESSION['flash_type']);
    include_once '../../includes/footer.php';
    exit;
}

// Get order ID from URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id <= 0) {
    $_SESSION['flash_message'] = "Invalid order ID specified.";
    $_SESSION['flash_type'] = 'error';
    header("Location: view_orders.php"); // Redirect to orders list
    exit;
}

// Get currency symbol using the function from functions.php
$currency_symbol = get_currency_symbol(); // Assumes this function exists

// Initialize message variables
$success_message = '';
$error_message = '';
$warning_message = ''; // <-- Initialize the warning message variable HERE

// Process payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    // Validate form data
    $payment_date = isset($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
    // Ensure amount is treated as float
    $amount = isset($_POST['amount']) ? filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT) : 0;
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    $reference_number = isset($_POST['reference_number']) ? trim($_POST['reference_number']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    $receipt_path = null; // <<< ADDED: Initialize receipt path variable

    // Validate amount
    if ($amount === false || $amount <= 0) { // Check filter_var result and value
        $error_message = "Payment amount must be a valid number greater than zero.";
    } else {

        // <<< ADDED: Handle file upload >>>
        if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] == UPLOAD_ERR_OK) {
            $file_tmp_path = $_FILES['receipt_image']['tmp_name'];
            $file_name = $_FILES['receipt_image']['name'];
            $file_size = $_FILES['receipt_image']['size'];
            $file_type = $_FILES['receipt_image']['type'];
            $file_name_parts = explode('.', $file_name);
            $file_ext = strtolower(end($file_name_parts));

            // Allowed extensions
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf','jfif'];
            // Max file size (e.g., 5MB)
            $max_file_size = 5 * 1024 * 1024;

            if (in_array($file_ext, $allowed_extensions)) {
                if ($file_size <= $max_file_size) {
                    // Create a unique filename to prevent overwriting
                    $new_file_name = 'receipt_' . $order_id . '_' . time() . '.' . $file_ext;
                    $dest_path = RECEIPT_UPLOAD_DIR . $new_file_name;

                    if (move_uploaded_file($file_tmp_path, $dest_path)) {
                        $receipt_path = $dest_path; // Store the relative path
                    } else {
                        $error_message = "Error moving uploaded file. Check permissions for " . RECEIPT_UPLOAD_DIR;
                        // Continue without receipt, but show error later maybe? Or stop here? Let's stop.
                    }
                } else {
                    $error_message = "File is too large. Maximum size allowed is " . ($max_file_size / 1024 / 1024) . " MB.";
                }
            } else {
                $error_message = "Invalid file type. Allowed types: " . implode(', ', $allowed_extensions);
            }
        } elseif (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] != UPLOAD_ERR_NO_FILE) {
            // Handle other upload errors if needed
            $error_message = "Error uploading file. Code: " . $_FILES['receipt_image']['error'];
        }
        // <<< END ADDED: Handle file upload >>>

        // Proceed only if there was no file upload error
        if (empty($error_message)) {
            // Begin database transaction
            $conn->begin_transaction();

            try {
                // 1. Insert payment record into payment_history
                // <<< MODIFIED: Added receipt_path column and parameter >>>
                $insert_sql = "INSERT INTO payment_history (po_id, payment_date, amount, payment_method, reference_number, notes, receipt_path, recorded_by)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_payment = $conn->prepare($insert_sql);
                if (!$stmt_payment) {
                     throw new Exception("Failed to prepare payment history statement: " . $conn->error);
                }
                // <<< MODIFIED: Added 's' for receipt_path string and the variable >>>
                $stmt_payment->bind_param("isdssssi", $order_id, $payment_date, $amount, $payment_method, $reference_number, $notes, $receipt_path, $_SESSION['user_id']);
                if (!$stmt_payment->execute()) {
                     throw new Exception("Failed to record payment: " . $stmt_payment->error);
                }
                $stmt_payment->close(); // Close statement

                // 2. Attempt automatic deduction from Petroleum Account
                $po_number = null; // Initialize PO number
                $supplier_name = null; // Initialize supplier name for reference

                // Fetch the PO number and supplier info for withdrawal reference
                $order_details_query = "SELECT po.po_number, s.supplier_name
                                       FROM purchase_orders po
                                       JOIN suppliers s ON po.supplier_id = s.supplier_id
                                       WHERE po.po_id = ?";
                $stmt_po = $conn->prepare($order_details_query);
                if (!$stmt_po) {
                    throw new Exception("Failed to prepare PO details statement: " . $conn->error);
                }
                $stmt_po->bind_param("i", $order_id);
                $stmt_po->execute();
                $result_po = $stmt_po->get_result();
                if ($result_po->num_rows > 0) {
                    $order_details = $result_po->fetch_assoc();
                    $po_number = $order_details['po_number'];
                    $supplier_name = $order_details['supplier_name'];
                } else {
                    // This case should ideally not happen if we are processing payment for a valid order_id
                    throw new Exception("Associated Purchase Order (ID: $order_id) not found during payment processing.");
                }
                $stmt_po->close(); // Close statement

                // Proceed with payment only if PO number was found
                if ($po_number !== null) {
                    if (function_exists('process_partial_payment')) {
                        // Generate a detailed description for the withdrawal
                        $description = "Payment for purchase order {$po_number} to {$supplier_name}";

                        // Execute the partial payment process
                        $payment_result = process_partial_payment($conn, $order_id, $amount, $po_number, $description);

                        if ($payment_result['status'] === 'error') {
                            // Log error and set user warning
                            error_log("Petroleum Account Error: " . $payment_result['message']);
                            $warning_message = "Payment recorded, but automatic processing with petroleum account failed: " . $payment_result['message'];
                        } else if ($payment_result['status'] === 'pending') {
                            // No balance available, added to pending topups
                            $warning_message = "Payment recorded, but full amount ({$currency_symbol}" . number_format($amount, 2) .
                                              ") added to pending top-ups due to insufficient petroleum account balance.";
                        } else if ($payment_result['status'] === 'partial') {
                            // Partial payment processed
                            $success_note = "Partial payment of {$currency_symbol}" . number_format($payment_result['deducted'], 2) .
                                           " deducted from petroleum account.";
                            $warning_message = "Remaining amount of {$currency_symbol}" . number_format($payment_result['remaining'], 2) .
                                              " added to pending top-ups for future processing.";
                        } else {
                            // Full payment processed
                            $success_note = "Full payment of {$currency_symbol}" . number_format($amount, 2) .
                                           " successfully deducted from petroleum account.";
                        }
                    } else {
                        // Log error and set user warning if function is missing
                        error_log("Petroleum Account Error: Function 'process_partial_payment' not found. Ensure check_balance.php is included correctly.");
                        $warning_message = "Payment recorded, but automatic processing function is unavailable. Please check system configuration.";
                    }
                } // End if po_number found

                // 3. Update Purchase Order Status

                // Get total amount paid so far (including current payment)
                $total_paid_query = "SELECT SUM(amount) AS total_paid FROM payment_history WHERE po_id = ?";
                $stmt_paid = $conn->prepare($total_paid_query);
                if (!$stmt_paid) {
                    throw new Exception("Failed to prepare total paid query: " . $conn->error);
                }
                $stmt_paid->bind_param("i", $order_id);
                $stmt_paid->execute();
                $result_paid = $stmt_paid->get_result();
                $total_paid_row = $result_paid->fetch_assoc();
                $total_paid = $total_paid_row['total_paid'] ?? 0; // Default to 0 if no payments yet
                $stmt_paid->close(); // Close statement

                // Get order total amount
                $order_total_query = "SELECT total_amount FROM purchase_orders WHERE po_id = ?";
                $stmt_total = $conn->prepare($order_total_query);
                if (!$stmt_total) {
                    throw new Exception("Failed to prepare order total query: " . $conn->error);
                }
                $stmt_total->bind_param("i", $order_id);
                $stmt_total->execute();
                $result_total = $stmt_total->get_result();
                $order_total_row = $result_total->fetch_assoc();
                // Ensure total_amount is treated as float, default to 0 if null/not found
                $order_total = isset($order_total_row['total_amount']) ? (float)$order_total_row['total_amount'] : 0;
                $stmt_total->close(); // Close statement

                // Determine payment status based on total paid and petroleum account status
                $payment_status = 'pending'; // Default status
                $tolerance = 0.001; // Small tolerance for float comparison
                if ($order_total > 0) {
                    if ($total_paid >= ($order_total - $tolerance)) {
                        $payment_status = 'paid';
                    } elseif ($total_paid > $tolerance) {
                        // If we have a partial payment from petroleum account
                        if (isset($payment_result) && $payment_result['status'] === 'partial') {
                            $payment_status = 'partial';
                        } else {
                            $payment_status = 'partial';
                        }
                    }
                } elseif ($total_paid <= $tolerance) {
                    // If order total is 0 or less, and nothing paid, consider it paid
                    $payment_status = 'paid';
                }

                // Update order payment status and potentially the payment date in purchase_orders
                $update_sql = "UPDATE purchase_orders SET payment_status = ?, payment_date = ?, payment_reference = ? WHERE po_id = ?";
                $stmt_update_po = $conn->prepare($update_sql);
                if (!$stmt_update_po) {
                    throw new Exception("Failed to prepare PO update statement: " . $conn->error);
                }

                $payment_date_to_update = null; // Default to null unless fully paid
                if ($payment_status === 'paid') {
                    $payment_date_to_update = $payment_date; // Set payment date only when fully paid
                }
                $stmt_update_po->bind_param("sssi", $payment_status, $payment_date_to_update, $reference_number, $order_id);
                if (!$stmt_update_po->execute()) {
                    throw new Exception("Failed to update purchase order status: " . $stmt_update_po->error);
                }
                $stmt_update_po->close(); // Close statement

                // 4. Commit the transaction
                $conn->commit();

                // Set success message for redirection
                $success_message = "Payment of {$currency_symbol}" . number_format($amount, 2) . " has been recorded successfully.";
                if ($receipt_path) {
                    $success_message .= " Receipt uploaded.";
                }

                // Add petroleum account deduction confirmation if successful
                if (isset($success_note)) {
                    $success_message .= " " . $success_note;
                }

                $_SESSION['flash_message'] = $success_message;
                $_SESSION['flash_type'] = 'success';

                // Store warning message in session if it was set
                if (!empty($warning_message)) {
                    $_SESSION['flash_message_warning'] = $warning_message;
                }

                // Redirect to order details page
                header("Location: order_details.php?id=$order_id&payment_success=1");
                exit;

            } catch (Exception $e) {
                // Rollback transaction on any error
                $conn->rollback();
                // Log the detailed error for debugging
                error_log("Payment Processing Error for PO ID $order_id: " . $e->getMessage());
                // Set a user-friendly error message
                $error_message = "An error occurred while processing the payment. Please try again. Details: " . $e->getMessage();
                // Optionally store error in session for display after redirect
                $_SESSION['flash_message'] = $error_message;
                $_SESSION['flash_type'] = 'error';
                // Redirect back to payment page might be better than showing error here
                // header("Location: process_payment.php?id=$order_id");
                // exit;
                // For now, we let it fall through to display error on the current page below
            }
        } // End of check for file upload error
    } // End of amount validation else block
} // End of form submission check

// --- Load data for displaying the page (even if form processing failed) ---

// Get order details
$order_query = "
    SELECT po.po_id, po.po_number, po.order_date, po.status, po.total_amount, po.payment_status,
           po.payment_date, po.payment_reference, s.supplier_name
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.supplier_id
    WHERE po.po_id = ?
";

$stmt_load_order = $conn->prepare($order_query);
// Handle potential prepare error
if (!$stmt_load_order) {
     echo "Error preparing statement to load order details: " . $conn->error;
     include_once '../../includes/footer.php';
     exit;
}
$stmt_load_order->bind_param("i", $order_id);
$stmt_load_order->execute();
$result_load_order = $stmt_load_order->get_result();

if ($result_load_order->num_rows === 0) {
    echo '<div class="container mx-auto px-4 py-4"><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>Order not found. Please <a href="view_orders.php" class="underline">return to the orders list</a>.</p>
          </div></div>';
    include_once '../../includes/footer.php';
    exit;
}
$order = $result_load_order->fetch_assoc();
$stmt_load_order->close();

// <<< MODIFIED: Select receipt_path as well >>>
// Get payment history
$payments_query = "
    SELECT ph.payment_id, ph.payment_date, ph.amount, ph.payment_method, ph.reference_number,
           ph.notes, ph.created_at, u.full_name as recorded_by, ph.receipt_path
    FROM payment_history ph
    LEFT JOIN users u ON ph.recorded_by = u.user_id
    WHERE ph.po_id = ?
    ORDER BY ph.payment_date DESC, ph.payment_id DESC
";
$stmt_load_payments = $conn->prepare($payments_query);
 if (!$stmt_load_payments) {
      echo "Error preparing statement to load payment history: " . $conn->error;
      // Continue gracefully, maybe show empty history
      $all_payments = [];
 } else {
      $stmt_load_payments->bind_param("i", $order_id);
      $stmt_load_payments->execute();
      $payments_result = $stmt_load_payments->get_result();
      $all_payments = $payments_result->fetch_all(MYSQLI_ASSOC); // Fetch all rows
      $stmt_load_payments->close();
 }

// Calculate total paid and remaining balance from fetched history
$total_paid = 0;
foreach ($all_payments as $payment) {
    $total_paid += $payment['amount'];
}
// Ensure order total is float for calculation
$order_total_for_calc = isset($order['total_amount']) ? (float)$order['total_amount'] : 0;
$remaining_balance = $order_total_for_calc - $total_paid;

// Update breadcrumbs with the fetched order number
$breadcrumbs = '<a href="../../index.php" class="text-blue-600 hover:text-blue-800">Home</a> /
               <a href="index.php" class="text-blue-600 hover:text-blue-800">Fuel Ordering</a> /
               <a href="view_orders.php" class="text-blue-600 hover:text-blue-800">View Orders</a> /
               <a href="order_details.php?id=' . $order_id . '" class="text-blue-600 hover:text-blue-800">' . htmlspecialchars($order['po_number']) . '</a> /
               Process Payment';

// Status colors for badges
$status_classes = [
    'draft' => 'bg-gray-100 text-gray-800',
    'submitted' => 'bg-blue-100 text-blue-800',
    'approved' => 'bg-green-100 text-green-800',
    'in_progress' => 'bg-yellow-100 text-yellow-800',
    'delivered' => 'bg-emerald-100 text-emerald-800',
    'cancelled' => 'bg-red-100 text-red-800'
];
$payment_classes = [
    'pending' => 'bg-yellow-100 text-yellow-800',
    'partial' => 'bg-blue-100 text-blue-800',
    'paid' => 'bg-green-100 text-green-800'
];
?>

<div class="container mx-auto px-4 py-4">

    <?php // Display error message if form processing failed or file upload failed
    if (!empty($error_message)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p><?= htmlspecialchars($error_message) ?></p>
    </div>
    <?php endif; ?>

    <?php // Display flash messages if redirected here
        if (isset($_SESSION['flash_message'])) {
            $message_type = $_SESSION['flash_type'] ?? 'info';
            $color_class = 'blue';
             if ($message_type === 'success') $color_class = 'green';
             if ($message_type === 'error') $color_class = 'red';
             if ($message_type === 'warning') $color_class = 'yellow';

            echo '<div class="bg-' . $color_class . '-100 border-l-4 border-' . $color_class . '-500 text-' . $color_class . '-700 p-4 mb-6" role="alert">
                 <p>' . htmlspecialchars($_SESSION['flash_message']) . '</p>
               </div>';
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_type']);
        }
         // Display session warning message if set
         if (isset($_SESSION['flash_message_warning'])) {
             echo '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
                  <p>' . htmlspecialchars($_SESSION['flash_message_warning']) . '</p>
                </div>';
             unset($_SESSION['flash_message_warning']);
         }
    ?>

    <div class="mb-6 flex flex-wrap gap-3">
        <a href="order_details.php?id=<?= $order_id ?>" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
            <i class="fas fa-arrow-left mr-2"></i> Back to Order Details
        </a>
        <a href="view_orders.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
            <i class="fas fa-list mr-2"></i> View All Orders
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Order Summary</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div>
                <p class="text-sm text-gray-600 mb-1">Purchase Order:</p>
                <p class="font-medium"><?= htmlspecialchars($order['po_number']) ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-600 mb-1">Supplier:</p>
                <p class="font-medium"><?= htmlspecialchars($order['supplier_name']) ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-600 mb-1">Order Date:</p>
                <p class="font-medium"><?= isset($order['order_date']) ? date('M d, Y', strtotime($order['order_date'])) : 'N/A' ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-600 mb-1">Status:</p>
                <span class="px-2 py-1 text-xs rounded-full <?= $status_classes[$order['status']] ?? 'bg-gray-100 text-gray-800' ?>">
                    <?= ucfirst(str_replace('_', ' ', $order['status'] ?? 'Unknown')) ?>
                </span>
            </div>
            <div>
                <p class="text-sm text-gray-600 mb-1">Payment Status:</p>
                <span class="px-2 py-1 text-xs rounded-full <?= $payment_classes[$order['payment_status']] ?? 'bg-gray-100 text-gray-800' ?>">
                    <?= ucfirst($order['payment_status'] ?? 'Unknown') ?>
                </span>
            </div>
            <div>
                <p class="text-sm text-gray-600 mb-1">Total Amount:</p>
                <p class="font-medium"><?= $currency_symbol ?><?= number_format($order_total_for_calc, 2) ?></p>
            </div>
        </div>
        <div class="mt-6 pt-4 border-t border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-1">
                    <p class="text-sm text-gray-600 mb-1">Total Paid:</p>
                    <p class="text-lg font-bold text-green-600"><?= $currency_symbol ?><?= number_format($total_paid, 2) ?></p>
                </div>
                <div class="md:col-span-1">
                    <p class="text-sm text-gray-600 mb-1">Remaining Balance:</p>
                    <p class="text-lg font-bold <?= $remaining_balance > 0.001 ? 'text-red-600' : 'text-green-600' ?>">
                        <?= $currency_symbol ?><?= number_format($remaining_balance, 2) ?>
                    </p>
                </div>
                <div class="md:col-span-1">
                    <?php if ($order_total_for_calc > 0): ?>
                    <p class="text-sm text-gray-600 mb-1">Payment Progress:</p>
                    <div class="w-full bg-gray-200 rounded-full h-4 mt-2">
                        <?php $progress_percentage = min(100, ($total_paid / $order_total_for_calc) * 100); ?>
                        <div class="bg-blue-600 h-4 rounded-full" style="width: <?= $progress_percentage ?>%"></div>
                    </div>
                    <p class="text-xs text-right mt-1"><?= number_format($progress_percentage, 1) ?>% paid</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Get current petroleum account balance
    $petroleum_balance = 0;
    $has_sufficient_balance = false;
    $query = "SELECT balance_after FROM petroleum_account_transactions WHERE status = 'completed' ORDER BY transaction_id DESC LIMIT 1";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $petroleum_balance = $row['balance_after'];
        // Consider remaining balance for sufficiency check
        $has_sufficient_balance = ($petroleum_balance >= max(0.01, $remaining_balance)); // Use remaining balance
    }
    ?>

    <?php if ($order['payment_status'] !== 'paid' && $order['status'] !== 'cancelled'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
         <div class="lg:col-span-2 bg-white rounded-lg shadow-md p-6 mb-4">
            <div class="flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800">Petroleum Account Status</h2>
                <a href="../petroleum_account/index.php" class="text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fas fa-external-link-alt mr-1"></i> View Account
                </a>
            </div>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Current Balance:</p>
                    <p class="text-xl font-bold <?= $has_sufficient_balance ? 'text-green-600' : 'text-red-600' ?>">
                        <?= $currency_symbol ?><?= number_format($petroleum_balance, 2) ?>
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 mb-1">Payment Amount (Remaining):</p>
                    <p class="text-xl font-bold text-gray-800">
                        <?= $currency_symbol ?><?= number_format($remaining_balance, 2) ?>
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 mb-1">Status:</p>
                    <?php if ($has_sufficient_balance): ?>
                        <p class="text-green-600">
                            <i class="fas fa-check-circle mr-1"></i> Sufficient Balance
                        </p>
                        <p class="text-xs text-gray-500 mt-1">Amount will be automatically deducted from petroleum account.</p>
                    <?php else: ?>
                        <p class="text-red-600">
                            <i class="fas fa-exclamation-circle mr-1"></i> Insufficient Balance
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                             <a href="../petroleum_account/add_transaction.php?type=deposit" class="text-blue-600 hover:text-blue-800">
                                 Add deposit to petroleum account
                             </a>
                             (Amount may be added to pending top-ups)
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Process Payment</h2>
             <form action="process_payment.php?id=<?= $order_id ?>" method="post" enctype="multipart/form-data">
                <div class="space-y-4">
                    <div>
                        <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-1">Payment Date <span class="text-red-500">*</span></label>
                        <input type="date" id="payment_date" name="payment_date" value="<?= date('Y-m-d') ?>" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    </div>
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount <span class="text-red-500">*</span></label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm"><?= $currency_symbol ?></span>
                            </div>
                            <input type="number" id="amount" name="amount" value="<?= max(0.01, number_format($remaining_balance, 2, '.', '')) // Default to remaining, ensure minimum 0.01 ?>" min="0.01" step="0.01" required
                                class="w-full pl-7 pr-12 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" placeholder="0.00">
                        </div>
                        <div class="mt-1 flex justify-between">
                            <button type="button" id="pay-partial" class="text-xs text-blue-600 hover:text-blue-800">Pay 50%</button>
                            <button type="button" id="pay-full" class="text-xs text-blue-600 hover:text-blue-800">Pay Full Amount</button>
                        </div>
                    </div>
                    <div>
                        <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method <span class="text-red-500">*</span></label>
                        <select id="payment_method" name="payment_method" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="check">Check</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label for="reference_number" class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
                        <input type="text" id="reference_number" name="reference_number" placeholder="e.g., Transaction ID, Check Number"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    </div>
                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Any additional information about this payment"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"></textarea>
                    </div>
                     <div>
                        <label for="receipt_image" class="block text-sm font-medium text-gray-700 mb-1">Receipt Image (Optional)</label>
                        <input type="file" id="receipt_image" name="receipt_image" accept="image/*,.pdf"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="mt-1 text-xs text-gray-500">Attach a receipt if available (JPG, PNG, GIF, PDF - Max 5MB).</p>
                    </div>
                    <div class="pt-2">
                        <button type="submit" name="process_payment"
                            class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                            Process Payment
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-800">Payment History</h2>
            </div>
            <?php if (!empty($all_payments)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Receipt</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($all_payments as $payment): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm"><?= isset($payment['payment_date']) ? date('M d, Y', strtotime($payment['payment_date'])) : 'N/A' ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium"><?= $currency_symbol ?><?= number_format($payment['amount'], 2) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm"><?= ucfirst(str_replace('_', ' ', $payment['payment_method'] ?? 'N/A')) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm"><?= htmlspecialchars($payment['reference_number'] ?: '-') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php if (!empty($payment['receipt_path']) && file_exists($payment['receipt_path'])): ?>
                                    <a href="<?= htmlspecialchars($payment['receipt_path']) ?>" target="_blank" class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-receipt mr-1"></i> View Slip
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="px-6 py-4 text-center text-gray-500">
                <p>No payment records found for this order.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: // Order is paid or cancelled ?>
        <div class="bg-<?= $order['payment_status'] === 'paid' ? 'green' : ($order['status'] === 'cancelled' ? 'red' : 'gray') ?>-50 border border-<?= $order['payment_status'] === 'paid' ? 'green' : ($order['status'] === 'cancelled' ? 'red' : 'gray') ?>-200 rounded-lg p-6 mb-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-<?= $order['payment_status'] === 'paid' ? 'check-circle text-green-500' : ($order['status'] === 'cancelled' ? 'times-circle text-red-500' : 'info-circle text-gray-500') ?> text-3xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-medium text-<?= $order['payment_status'] === 'paid' ? 'green' : ($order['status'] === 'cancelled' ? 'red' : 'gray') ?>-800">
                        <?= $order['payment_status'] === 'paid' ? 'Payment Completed' : ($order['status'] === 'cancelled' ? 'Order Cancelled' : 'Order Status') ?>
                    </h3>
                    <p class="mt-1 text-<?= $order['payment_status'] === 'paid' ? 'green' : ($order['status'] === 'cancelled' ? 'red' : 'gray') ?>-700">
                        <?php if ($order['payment_status'] === 'paid'): ?>
                            This order has been fully paid<?php if(!empty($order['payment_date'])) { echo ' on ' . date('F d, Y', strtotime($order['payment_date'])); } ?>.
                            <?= !empty($order['payment_reference']) ? "Reference: " . htmlspecialchars($order['payment_reference']) : "" ?>
                        <?php elseif ($order['status'] === 'cancelled'): ?>
                            This order has been cancelled and cannot receive payments.
                        <?php else: ?>
                             This order is currently in '<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $order['status']))) ?>' status.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php if (!empty($all_payments)): ?>
            <div class="mt-6">
                <h4 class="text-md font-medium text-gray-700 mb-2">Payment History</h4>
                <div class="overflow-x-auto bg-white rounded-md shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                         <thead class="bg-gray-50">
                              <tr>
                                   <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                   <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                   <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                                   <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                   <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Receipt</th>
                              </tr>
                         </thead>
                         <tbody class="bg-white divide-y divide-gray-200">
                              <?php foreach ($all_payments as $payment): ?>
                              <tr>
                                   <td class="px-6 py-4 whitespace-nowrap text-sm"><?= isset($payment['payment_date']) ? date('M d, Y', strtotime($payment['payment_date'])) : 'N/A' ?></td>
                                   <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium"><?= $currency_symbol ?><?= number_format($payment['amount'], 2) ?></td>
                                   <td class="px-6 py-4 whitespace-nowrap text-sm"><?= ucfirst(str_replace('_', ' ', $payment['payment_method'] ?? 'N/A')) ?></td>
                                   <td class="px-6 py-4 whitespace-nowrap text-sm"><?= htmlspecialchars($payment['reference_number'] ?: '-') ?></td>
                                   <td class="px-6 py-4 whitespace-nowrap text-sm">
                                       <?php if (!empty($payment['receipt_path']) && file_exists($payment['receipt_path'])): ?>
                                           <a href="<?= htmlspecialchars($payment['receipt_path']) ?>" target="_blank" class="text-blue-600 hover:text-blue-800">
                                               <i class="fas fa-receipt mr-1"></i> View Slip
                                           </a>
                                       <?php else: ?>
                                           <span class="text-gray-400">N/A</span>
                                       <?php endif; ?>
                                   </td>
                              </tr>
                              <?php endforeach; ?>
                         </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const amountInput = document.getElementById('amount');
    const payPartialBtn = document.getElementById('pay-partial');
    const payFullBtn = document.getElementById('pay-full');

    if (amountInput && payPartialBtn && payFullBtn) {
        // Get remaining balance from PHP, ensure it's a number, default to 0
        const remainingBalance = parseFloat(<?= json_encode($remaining_balance) ?>) || 0;

        payPartialBtn.addEventListener('click', function() {
            // Calculate 50%, ensure minimum 0.01
            let partialAmount = (remainingBalance / 2).toFixed(2);
            amountInput.value = Math.max(0.01, partialAmount);
        });

        payFullBtn.addEventListener('click', function() {
            // Set full amount, ensure minimum 0.01
            amountInput.value = Math.max(0.01, remainingBalance).toFixed(2);
        });
    }
});
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
ob_end_flush(); // Send output buffer content
?>