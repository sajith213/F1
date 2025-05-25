<?php
ob_start();
/**
 * Receive General Products Page
 *
 * This page handles receiving products from general purchase orders
 * and updates the main product inventory.
 */
$page_title = "Receive Products";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="index.php">Purchase Orders</a> / <span class="text-gray-700">Receive Products</span>';
include_once '../../includes/header.php';
require_once '../../includes/db.php';
// *** IMPORTANT: Make sure purchase_functions.php is included and defines receivePurchaseOrder ***
require_once 'purchase_functions.php';

// Check permission - Add appropriate permission check if needed
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Fetch 'ordered' or 'partial' purchase orders for general products
$purchase_orders = [];
$stmt = $conn->prepare("
    SELECT po.po_id, po.po_number, po.order_date, s.supplier_name
    FROM product_purchase_orders po -- Changed table
    LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
    WHERE po.status IN ('ordered', 'partial') -- Changed statuses to match receivePurchaseOrder function requirement
    ORDER BY po.order_date DESC
");

// Removed the supplier exclusion - add it back if needed for general products
// $supplier_id_to_exclude = 2;
// $stmt->bind_param("i", $supplier_id_to_exclude);

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $purchase_orders[] = $row;
    }
    $stmt->close();
}

// Handle form submission for receiving products
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_delivery'])) {
    if (isset($_POST['po_id']) && isset($_POST['received_quantities']) && is_array($_POST['received_quantities'])) {
        $po_id = (int)$_POST['po_id'];
        $received_quantities = $_POST['received_quantities']; // Array [item_id => quantity]
        $notes = $_POST['notes'] ?? ''; // Optional notes for the receiving transaction

        // Format received items for the receivePurchaseOrder function
        $formatted_received_items = [];
        foreach ($received_quantities as $item_id => $qty_received) {
            $qty = (float)$qty_received;
            if ($qty > 0) {
                $formatted_received_items[(int)$item_id] = ['received_quantity' => $qty];
            }
        }

        if (!empty($formatted_received_items)) {
            // Call the function from purchase_functions.php
            $success = receivePurchaseOrder($po_id, $formatted_received_items, $notes);

            if ($success) {
                $_SESSION['success_message'] = "Products received successfully for Purchase Order #{$po_id}. Stock updated.";
                header("Location: receive_products.php"); // Redirect to refresh
                exit();
            } else {
                $_SESSION['error_message'] = "Error processing product receiving for PO #{$po_id}. Please check logs or contact support.";
            }
        } else {
             $_SESSION['error_message'] = "No quantities entered for receiving.";
        }

    } else {
        $_SESSION['error_message'] = "Invalid data submitted for receiving.";
    }
     // Redirect back if errors occurred before exit()
     header("Location: receive_products.php");
     exit();
}

// The AJAX part is now handled by get_product_po_items.php
// The old AJAX handling code here is removed.

?>

<div class="container mx-auto pb-6">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?= htmlspecialchars($_SESSION['success_message']) ?></p>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?= htmlspecialchars($_SESSION['error_message']) ?></p>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Purchase Orders Awaiting Delivery</h2>
            <p class="text-sm text-gray-600">Select a purchase order to record received products</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Number</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($purchase_orders)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">No purchase orders awaiting delivery.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($purchase_orders as $po): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="view_order.php?id=<?= $po['po_id'] ?>" class="text-blue-600 hover:text-blue-800">
                                      <?= htmlspecialchars($po['po_number']) ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($po['supplier_name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= date('M d, Y', strtotime($po['order_date'])) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button type="button" onclick="openReceiveModal(<?= $po['po_id'] ?>)" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                        Receive Items
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="receiveModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form action="receive_products.php" method="POST" id="receiveForm">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                    Receive Products for PO #<span id="modal-po-number"></span>
                                </h3>
                                <input type="hidden" name="po_id" id="modal-po-id">

                                <div id="modal-item-list" class="space-y-4 mb-4 max-h-60 overflow-y-auto">
                                    <div class="text-center py-4"><i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i><p class="mt-2 text-gray-600">Loading items...</p></div>
                                </div>

                                <div class="mb-4">
                                    <label for="notes" class="block text-gray-700 text-sm font-medium mb-2">Receiving Notes (Optional)</label>
                                    <textarea id="notes" name="notes" rows="2"
                                              class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                              placeholder="e.g., Received by John Doe, Box 1 damaged"></textarea>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="record_delivery" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Record Received Products
                        </button>
                        <button type="button" onclick="closeReceiveModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
   function openReceiveModal(poId) {
        const modalItemList = document.getElementById('modal-item-list');
        modalItemList.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i><p class="mt-2 text-gray-600">Loading purchase order items...</p></div>';

        document.getElementById('receiveModal').classList.remove('hidden');
        document.getElementById('modal-po-id').value = poId;
        document.getElementById('modal-po-number').textContent = '...'; // Clear previous number

        // *** Fetch from the NEW AJAX endpoint ***
        fetch(`get_product_po_items.php?po_id=${poId}`)
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.error || 'Failed to load purchase order items');
                }

                document.getElementById('modal-po-number').textContent = data.po_number; // Set PO number

                let itemListHTML = ''; // Start empty

                if (data.items && data.items.length > 0) {
                     itemListHTML += '<h4 class="font-medium text-gray-900 mb-2 border-b pb-2">Products to Receive:</h4>';
                     data.items.forEach(item => {
                        // Calculate remaining quantity based on ordered and already received
                        const orderedQty = parseFloat(item.quantity || 0);
                        const receivedQty = parseFloat(item.received_quantity || 0);
                        const remainingQty = Math.max(0, orderedQty - receivedQty); // Ensure not negative

                        itemListHTML += `
                            <div class="p-3 border rounded-md bg-gray-50">
                                <p class="font-medium text-gray-800">${item.product_name} (${item.product_code})</p>
                                <p class="text-xs text-gray-600 mb-2">
                                    Ordered: ${orderedQty} ${item.purchase_unit || ''} |
                                    Received: ${receivedQty} ${item.purchase_unit || ''} |
                                    Remaining: <span class="font-semibold">${remainingQty}</span> ${item.purchase_unit || ''}
                                </p>
                                <label for="received_${item.item_id}" class="block text-gray-700 text-sm font-medium mb-1">
                                    Quantity Received Now:
                                </label>
                                <input type="number" id="received_${item.item_id}" name="received_quantities[${item.item_id}]"
                                    min="0" max="${remainingQty}" value="0" step="0.01" ${remainingQty <= 0 ? 'readonly' : ''}
                                    class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md ${remainingQty <= 0 ? 'bg-gray-200' : ''}">
                            </div>
                        `;
                    });
                } else {
                    itemListHTML = '<p class="text-red-500 text-sm text-center py-4">No items found or all items already received for this purchase order.</p>';
                }

                modalItemList.innerHTML = itemListHTML; // Update modal content
            })
            .catch(error => {
                console.error('Error fetching purchase order items:', error);
                modalItemList.innerHTML = `
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
                        <p class="font-bold">Error Loading Items</p>
                        <p>${error.message}</p>
                    </div>
                `;
            });
    }

    function closeReceiveModal() {
        document.getElementById('receiveModal').classList.add('hidden');
        // Optional: Reset form fields if needed when closing
         const receiveForm = document.getElementById('receiveForm');
         if(receiveForm) {
             receiveForm.reset();
             document.getElementById('modal-item-list').innerHTML = ''; // Clear item list
         }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Close modal on outside click
        const modal = document.getElementById('receiveModal');
        if(modal) {
             window.onclick = function(event) {
                if (event.target === modal) {
                    closeReceiveModal();
                }
            };
        }


        // Basic form validation: ensure at least one quantity > 0
        const receiveForm = document.getElementById('receiveForm');
        if (receiveForm) {
            receiveForm.addEventListener('submit', function(e) {
                const inputs = this.querySelectorAll('input[name^="received_quantities"]');
                let hasValue = false;
                let validQuantities = true;

                inputs.forEach(input => {
                    const qty = parseFloat(input.value);
                    const maxQty = parseFloat(input.getAttribute('max'));

                    if (qty > 0) {
                        hasValue = true;
                    }
                    if (qty > maxQty) {
                        validQuantities = false;
                        input.classList.add('border-red-500'); // Highlight error
                        // You could add an error message next to the input here
                    } else {
                         input.classList.remove('border-red-500');
                    }
                });

                if (!validQuantities) {
                    e.preventDefault();
                    alert('One or more entered quantities exceed the remaining amount.');
                    return;
                }

                if (!hasValue) {
                    e.preventDefault();
                    alert('Please enter a quantity greater than 0 for at least one item.');
                }
                 // If validation passes, the form submits normally
            });
        }
    });
    </script>
</div>

<?php include_once '../../includes/footer.php'; ?>