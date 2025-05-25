<?php
// get_product_po_items.php - AJAX endpoint for general product PO items
session_start();
require_once '../../includes/db.php';

// Basic security check (ensure user is logged in, adjust as needed)
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only process AJAX requests with a valid numeric po_id
if (isset($_GET['po_id']) && is_numeric($_GET['po_id'])) {
    $po_id = (int)$_GET['po_id'];
    $items = [];

    try {
        // --- Get PO Number ---
        $stmt_po = $conn->prepare("SELECT po_number FROM product_purchase_orders WHERE po_id = ?");
        if (!$stmt_po) {
            throw new Exception("Error preparing PO statement: " . $conn->error);
        }
        $stmt_po->bind_param("i", $po_id);
        $stmt_po->execute();
        $result_po = $stmt_po->get_result();

        if ($result_po->num_rows === 0) {
            throw new Exception("Purchase order not found");
        }
        $po_data = $result_po->fetch_assoc();
        $po_number = $po_data['po_number'];
        $stmt_po->close();

        // --- Get PO Items ---
        // Fetches product details along with ordered and received quantities
        $stmt_items = $conn->prepare("
            SELECT
                pi.item_id,          -- Use the primary key of product_purchase_items
                pi.product_id,
                pi.quantity,         -- Ordered quantity
                pi.unit_price,
                COALESCE(pi.received_quantity, 0) as received_quantity, -- Already received quantity from PO item table
                p.product_code,
                p.product_name,
                COALESCE(u.unit_symbol, p.unit) as purchase_unit -- Get unit symbol if available
            FROM product_purchase_items pi
            JOIN products p ON pi.product_id = p.product_id
            LEFT JOIN units u ON p.purchase_unit_id = u.unit_id -- Join units table based on product's purchase_unit_id
            WHERE pi.po_id = ?
            ORDER BY p.product_name
        ");

        if (!$stmt_items) {
            throw new Exception("Error preparing items statement: " . $conn->error);
        }

        $stmt_items->bind_param("i", $po_id);
        $stmt_items->execute();
        $result_items = $stmt_items->get_result();

        while ($row = $result_items->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt_items->close();

        // Return success response
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'po_number' => $po_number, 'items' => $items]);
        exit;

    } catch (Exception $e) {
        // Return error response
        header('Content-Type: application/json');
        // In production, consider logging the detailed error and returning a generic message
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// If request is invalid
header('Content-Type: application/json');
echo json_encode(['success' => false, 'error' => 'Invalid request']);
exit;
?>