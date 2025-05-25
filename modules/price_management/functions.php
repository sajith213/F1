<?php
/**
 * Price Management Module Functions
 * * This file contains helper functions specific to the price management module
 */

// Include database connection
require_once __DIR__ . '/../../includes/db.php';

/**
 * Get current active prices for all fuel types
 * * @return array Array of current fuel prices
 */
function getCurrentFuelPrices() {
    global $conn;
    
    $sql = "SELECT fp.*, ft.fuel_name 
            FROM fuel_prices fp
            JOIN fuel_types ft ON fp.fuel_type_id = ft.fuel_type_id
            WHERE fp.status = 'active'
            ORDER BY ft.fuel_name";
    
    $result = $conn->query($sql);
    
    $prices = [];
    if ($result && $result->num_rows > 0) { // Added check for query success
        while ($row = $result->fetch_assoc()) {
            $prices[] = $row;
        }
    } elseif (!$result) { // Log error if query failed
         error_log("Error getting current fuel prices: " . $conn->error);
    }
    
    return $prices;
}

/**
 * Get planned (future) fuel prices
 * * @return array Array of planned fuel prices
 */
function getPlannedFuelPrices() {
    global $conn;
    
    $sql = "SELECT fp.*, ft.fuel_name 
            FROM fuel_prices fp
            JOIN fuel_types ft ON fp.fuel_type_id = ft.fuel_type_id
            WHERE fp.status = 'planned' AND fp.effective_date > CURDATE()
            ORDER BY fp.effective_date, ft.fuel_name";
    
    $result = $conn->query($sql);
    
    $prices = [];
    if ($result && $result->num_rows > 0) { // Added check for query success
        while ($row = $result->fetch_assoc()) {
            $prices[] = $row;
        }
    } elseif (!$result) { // Log error if query failed
         error_log("Error getting planned fuel prices: " . $conn->error);
    }
    
    return $prices;
}

/**
 * Get price history for a specific fuel type
 * * @param int $fuel_type_id Fuel type ID
 * @param string $start_date Start date (optional)
 * @param string $end_date End date (optional)
 * @return array Array of price history
 */
function getPriceHistory($fuel_type_id = null, $start_date = null, $end_date = null) {
    global $conn;
    
    $params = [];
    $types = ''; // Initialize types string
    
    $sql = "SELECT fp.*, ft.fuel_name, u.full_name as set_by_name
            FROM fuel_prices fp
            JOIN fuel_types ft ON fp.fuel_type_id = ft.fuel_type_id
            LEFT JOIN users u ON fp.set_by = u.user_id
            WHERE 1=1";
    
    if ($fuel_type_id) {
        $sql .= " AND fp.fuel_type_id = ?";
        $params[] = $fuel_type_id;
        $types .= 'i'; // Assuming fuel_type_id is integer
    }
    
    if ($start_date) {
        $sql .= " AND fp.effective_date >= ?";
        $params[] = $start_date;
        $types .= 's'; // Assuming date is string
    }
    
    if ($end_date) {
        $sql .= " AND fp.effective_date <= ?";
        $params[] = $end_date;
        $types .= 's'; // Assuming date is string
    }
    
    $sql .= " ORDER BY fp.effective_date DESC, ft.fuel_name";
    
    $prices = [];
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Error preparing statement for getPriceHistory: " . $conn->error);
        return $prices; // Return empty array on prepare error
    }
    
    if (!empty($params)) {
        // Ensure bind_param is called only if there are params
        if (!$stmt->bind_param($types, ...$params)) {
             error_log("Error binding parameters for getPriceHistory: " . $stmt->error);
             $stmt->close();
             return $prices;
        }
    }
    
    if (!$stmt->execute()) {
         error_log("Error executing statement for getPriceHistory: " . $stmt->error);
         $stmt->close();
         return $prices;
    }

    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $prices[] = $row;
        }
    }
    
    $stmt->close();
    
    return $prices;
}

/**
 * Get single price record
 * * @param int $price_id Price ID
 * @return array|null Price data or null if not found
 */
function getPriceById($price_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT fp.*, ft.fuel_name 
                            FROM fuel_prices fp
                            JOIN fuel_types ft ON fp.fuel_type_id = ft.fuel_type_id
                            WHERE fp.price_id = ?");

    if (!$stmt) {
        error_log("Error preparing statement for getPriceById: " . $conn->error);
        return null;
    }

    if (!$stmt->bind_param("i", $price_id)) {
        error_log("Error binding parameters for getPriceById: " . $stmt->error);
        $stmt->close();
        return null;
    }

    if (!$stmt->execute()) {
        error_log("Error executing statement for getPriceById: " . $stmt->error);
        $stmt->close();
        return null;
    }
    
    $result = $stmt->get_result();
    $price = null; // Initialize price
    if ($result->num_rows > 0) {
        $price = $result->fetch_assoc();
    }
    
    $stmt->close();
    return $price; // Return price or null
}

/**
 * Add new price for a fuel type
 * * @param int $fuel_type_id Fuel type ID
 * @param string $effective_date Effective date
 * @param float $purchase_price Purchase price
 * @param float $selling_price Selling price
 * @param int $set_by User ID who set the price
 * @param string $notes Notes (optional)
 * @return int|false New price ID or false if error
 */
function addFuelPrice($fuel_type_id, $effective_date, $purchase_price, $selling_price, $set_by, $notes = null) {
    global $conn;
    
    // Begin transaction to ensure data consistency
    $conn->begin_transaction();
    
    try {
        // First, update any active prices to be 'expired' for this fuel type
        $update_sql = "UPDATE fuel_prices 
                       SET status = 'expired', updated_at = NOW() 
                       WHERE fuel_type_id = ? 
                       AND status = 'active'";
        
        $update_stmt = $conn->prepare($update_sql);
        if (!$update_stmt) throw new Exception("Prepare failed for update: (" . $conn->errno . ") " . $conn->error);
        if (!$update_stmt->bind_param("i", $fuel_type_id)) throw new Exception("Binding parameters failed for update: (" . $update_stmt->errno . ") " . $update_stmt->error);
        if (!$update_stmt->execute()) throw new Exception("Execute failed for update: (" . $update_stmt->errno . ") " . $update_stmt->error);
        $update_stmt->close();
        
        // Determine status: if effective date is today or in the past, mark as active
        // otherwise, mark as planned
        $today = date('Y-m-d');
        $status = ($effective_date <= $today) ? 'active' : 'planned';
        
        // Insert new price
        $sql = "INSERT INTO fuel_prices (fuel_type_id, effective_date, purchase_price, selling_price, status, set_by, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Prepare failed for insert: (" . $conn->errno . ") " . $conn->error);
        // Ensure notes is treated as string even if null
        $notes_val = $notes ?? ''; 
        if (!$stmt->bind_param("isddsis", $fuel_type_id, $effective_date, $purchase_price, $selling_price, $status, $set_by, $notes_val)) throw new Exception("Binding parameters failed for insert: (" . $stmt->errno . ") " . $stmt->error);
        
        if (!$stmt->execute()) throw new Exception("Execute failed for insert: (" . $stmt->errno . ") " . $stmt->error);
        $new_id = $conn->insert_id;
        $stmt->close();
        
        // Calculate price impact if this is an active price change
        if ($status === 'active') {
            // Wrap in check - if calculatePriceChangeImpact returns false, throw exception to trigger rollback
            if (!calculatePriceChangeImpact($new_id, $fuel_type_id, $selling_price, $set_by)) {
                 throw new Exception("Failed to calculate price change impact.");
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        return $new_id;

    } catch (Exception $e) {
        // An error occurred, rollback the transaction
        $conn->rollback();

        // --- CUSTOM LOGGING START ---
        // Define the path to your specific log file
        // IMPORTANT: Adjust this path to the correct ABSOLUTE path to your root directory's errors.log
        // Example assumes 'errors.log' is two levels above the current directory (__DIR__)
        $log_file_path = __DIR__ . '/../../errors.log'; // Adjust as needed!

        // Format the error message with a timestamp
        $error_message = "[" . date("Y-m-d H:i:s") . "] Error adding fuel price: " . $e->getMessage() . "\n";
        
        // Append the error message to the file
        // FILE_APPEND prevents overwriting the log file each time
        // LOCK_EX prevents other processes from writing to the file simultaneously
        @file_put_contents($log_file_path, $error_message, FILE_APPEND | LOCK_EX); // Added '@' to suppress warnings if logging fails
        // --- CUSTOM LOGGING END ---

        // Optionally, log to default PHP error log as well
        error_log("Error adding fuel price (also logged to custom file): " . $e->getMessage()); 

        return false; // Indicate failure
    }
}


/**
 * Update existing price
 * * @param int $price_id Price ID
 * @param string $effective_date Effective date
 * @param float $purchase_price Purchase price
 * @param float $selling_price Selling price
 * @param string $notes Notes (optional)
 * @return bool True if successful, false otherwise
 */
function updateFuelPrice($price_id, $effective_date, $purchase_price, $selling_price, $notes = null) {
    global $conn;
    
    // Get current price data
    $current_price = getPriceById($price_id);
    
    if (!$current_price) {
        error_log("Update failed: Price ID $price_id not found.");
        return false;
    }
    
    // Only allow updating 'planned' prices
    if ($current_price['status'] !== 'planned') {
        error_log("Update failed: Price ID $price_id is not in 'planned' status.");
        return false;
    }
    
    $sql = "UPDATE fuel_prices 
            SET effective_date = ?, purchase_price = ?, selling_price = ?, notes = ? 
            WHERE price_id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Error preparing statement for updateFuelPrice: " . $conn->error);
        return false;
    }
    
    // Ensure notes is treated as string even if null
    $notes_val = $notes ?? '';
    if (!$stmt->bind_param("sddsi", $effective_date, $purchase_price, $selling_price, $notes_val, $price_id)) {
        error_log("Error binding parameters for updateFuelPrice: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $result = $stmt->execute();
    if (!$result) {
         error_log("Error executing statement for updateFuelPrice: " . $stmt->error);
    }
    $stmt->close();
    
    return $result;
}

/**
 * Delete a planned price
 * * @param int $price_id Price ID
 * @return bool True if successful, false otherwise
 */
function deletePlannedPrice($price_id) {
    global $conn;
    
    // Get current price data to double-check status before deleting
    $current_price = getPriceById($price_id);
    
    if (!$current_price) {
        error_log("Delete failed: Price ID $price_id not found.");
        return false;
    }
    
    // Only allow deleting 'planned' prices (redundant check, but safe)
    if ($current_price['status'] !== 'planned') {
         error_log("Delete failed: Price ID $price_id is not in 'planned' status.");
        return false;
    }
    
    $sql = "DELETE FROM fuel_prices WHERE price_id = ? AND status = 'planned'";
    
    $stmt = $conn->prepare($sql);
     if (!$stmt) {
        error_log("Error preparing statement for deletePlannedPrice: " . $conn->error);
        return false;
    }

    if (!$stmt->bind_param("i", $price_id)) {
         error_log("Error binding parameters for deletePlannedPrice: " . $stmt->error);
         $stmt->close();
         return false;
    }
    
    $result = $stmt->execute();
     if (!$result) {
         error_log("Error executing statement for deletePlannedPrice: " . $stmt->error);
    }
    $stmt->close();
    
    return $result;
}

/**
 * Calculate and record price change impact on existing stock
 * * @param int $price_id New price ID
 * @param int $fuel_type_id Fuel type ID
 * @param float $new_price New selling price
 * @param int $calculated_by User ID who calculated the impact
 * @return bool True if successful, false otherwise (Note: returning false here will trigger rollback in addFuelPrice)
 */
function calculatePriceChangeImpact($price_id, $fuel_type_id, $new_price, $calculated_by) {
    global $conn;
    
    // Note: This function is called within the transaction of addFuelPrice.
    // Any exception thrown here or returning false will cause addFuelPrice to rollback.
    
    try {
        // Get tanks that contain this fuel type
        $sql = "SELECT tank_id, current_volume FROM tanks WHERE fuel_type_id = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Prepare failed for getting tanks: (" . $conn->errno . ") " . $conn->error);
        if (!$stmt->bind_param("i", $fuel_type_id)) throw new Exception("Binding parameters failed for getting tanks: (" . $stmt->errno . ") " . $stmt->error);
        if (!$stmt->execute()) throw new Exception("Execute failed for getting tanks: (" . $stmt->errno . ") " . $stmt->error);
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows == 0) {
            return true; // No tanks with this fuel type, impact calculation is technically successful (nothing to do)
        }
        
        // Get previous price for this fuel type (should be the one just marked as 'expired')
        $prev_price_sql = "SELECT selling_price 
                           FROM fuel_prices 
                           WHERE fuel_type_id = ? 
                             AND price_id != ? 
                             AND status = 'expired' 
                           ORDER BY effective_date DESC, updated_at DESC, price_id DESC 
                           LIMIT 1"; // Added updated_at for better ordering if dates are same
        
        $prev_stmt = $conn->prepare($prev_price_sql);
         if (!$prev_stmt) throw new Exception("Prepare failed for getting previous price: (" . $conn->errno . ") " . $conn->error);
        if (!$prev_stmt->bind_param("ii", $fuel_type_id, $price_id)) throw new Exception("Binding parameters failed for getting previous price: (" . $prev_stmt->errno . ") " . $prev_stmt->error);
        if (!$prev_stmt->execute()) throw new Exception("Execute failed for getting previous price: (" . $prev_stmt->errno . ") " . $prev_stmt->error);
        $prev_result = $prev_stmt->get_result();
        $prev_stmt->close();
        
        $old_price = 0.00; // Default to 0 if no previous price found
        if ($prev_result->num_rows > 0) {
            $prev_row = $prev_result->fetch_assoc();
            $old_price = $prev_row['selling_price'] ?? 0.00; // Ensure it's a float
        } else {
             // Optional: Log a warning if no previous 'expired' price was found for impact calculation
             error_log("Warning: No previous 'expired' price found for fuel_type_id $fuel_type_id when calculating impact for price_id $price_id. Old price assumed 0.");
        }
        
        // Record impact for each tank
        $now_datetime = date('Y-m-d H:i:s');
        
        while ($tank = $result->fetch_assoc()) {
            $tank_id = $tank['tank_id'];
            // Ensure stock_volume is treated as float
            $stock_volume = isset($tank['current_volume']) ? (float)$tank['current_volume'] : 0.00; 
            
            // Skip calculation if stock volume is zero or negative
            if ($stock_volume <= 0) {
                continue;
            }

            // Check if an impact record already exists for this price and tank (shouldn't happen with transaction logic, but safe check)
            $check_sql = "SELECT impact_id FROM price_change_impact WHERE price_id = ? AND tank_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            if (!$check_stmt) throw new Exception("Prepare failed for checking impact existence: (" . $conn->errno . ") " . $conn->error);
            if (!$check_stmt->bind_param("ii", $price_id, $tank_id)) throw new Exception("Binding parameters failed for checking impact existence: (" . $check_stmt->errno . ") " . $check_stmt->error);
            if (!$check_stmt->execute()) throw new Exception("Execute failed for checking impact existence: (" . $check_stmt->errno . ") " . $check_stmt->error);
            $check_result = $check_stmt->get_result();
            $check_stmt->close();
            
            if ($check_result->num_rows > 0) {
                 error_log("Warning: Price impact record already exists for price_id $price_id and tank_id $tank_id. Skipping insertion.");
                continue; // Skip if record somehow already exists
            }
            
            $impact_sql = "INSERT INTO price_change_impact 
                          (price_id, tank_id, old_price, new_price, stock_volume, calculated_by, calculation_date) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $impact_stmt = $conn->prepare($impact_sql);
            if (!$impact_stmt) throw new Exception("Prepare failed for inserting impact: (" . $conn->errno . ") " . $conn->error);
            // Ensure prices and volume are floats (d = double)
             if (!$impact_stmt->bind_param("iidddis", $price_id, $tank_id, $old_price, $new_price, $stock_volume, $calculated_by, $now_datetime)) throw new Exception("Binding parameters failed for inserting impact: (" . $impact_stmt->errno . ") " . $impact_stmt->error);
            
            if (!$impact_stmt->execute()) {
                 // If insert fails, throw an exception to trigger rollback in addFuelPrice
                 throw new Exception("Execute failed for inserting impact: (" . $impact_stmt->errno . ") " . $impact_stmt->error);
            }
            $impact_stmt->close();
        }
        
        return true; // Indicate success

    } catch (Exception $e) {
        // Log the specific error within calculatePriceChangeImpact
        error_log("Error calculating price impact: " . $e->getMessage());
        // Re-throw the exception OR return false to ensure addFuelPrice rolls back
        // Re-throwing gives more context in the main catch block log
         throw $e; 
        // return false; // Alternative: less specific error message in main log
    }
}


/**
 * Get price change impact data
 * * @param int $price_id Price ID (optional)
 * @return array Price impact data
 */
function getPriceChangeImpact($price_id = null) {
    global $conn;
    
    // Added pci.value_change explicitly as it's generated
    $sql = "SELECT pci.impact_id, pci.price_id, pci.tank_id, pci.old_price, pci.new_price, pci.stock_volume, pci.value_change, pci.calculated_by, pci.calculation_date, 
                   t.tank_name, ft.fuel_name, u.full_name as calculated_by_name
            FROM price_change_impact pci
            JOIN tanks t ON pci.tank_id = t.tank_id
            JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id
            JOIN users u ON pci.calculated_by = u.user_id";
    
    $impacts = [];
    if ($price_id) {
        $sql .= " WHERE pci.price_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
             error_log("Error preparing statement for getPriceChangeImpact (with ID): " . $conn->error);
             return $impacts;
        }
        if (!$stmt->bind_param("i", $price_id)) {
             error_log("Error binding parameters for getPriceChangeImpact (with ID): " . $stmt->error);
             $stmt->close();
             return $impacts;
        }
    } else {
        $sql .= " ORDER BY pci.calculation_date DESC LIMIT 20";
        $stmt = $conn->prepare($sql);
         if (!$stmt) {
             error_log("Error preparing statement for getPriceChangeImpact (no ID): " . $conn->error);
             return $impacts;
        }
    }
    
    if (!$stmt->execute()) {
         error_log("Error executing statement for getPriceChangeImpact: " . $stmt->error);
         $stmt->close();
         return $impacts;
    }

    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $impacts[] = $row;
        }
    }
    
    $stmt->close();
    
    return $impacts;
}

/**
 * Get all fuel types
 * * @return array Array of fuel types
 */
function getAllFuelTypes() {
    global $conn;
    
    $sql = "SELECT * FROM fuel_types ORDER BY fuel_name";
    $result = $conn->query($sql);
    
    $types = [];
     if ($result && $result->num_rows > 0) { // Added check for query success
        while ($row = $result->fetch_assoc()) {
            $types[] = $row;
        }
    } elseif (!$result) { // Log error if query failed
         error_log("Error getting all fuel types: " . $conn->error);
    }
    
    return $types;
}

/**
 * Format price with currency symbol
 * * @param float $price Price to format
 * @return string Formatted price
 */
function formatPrice($price) {
    // Assuming CURRENCY_SYMBOL is defined globally, e.g., in header.php or a config file
    $symbol = defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : 'Rs.'; // Default if not defined
    return $symbol . ' ' . number_format((float)$price, 2); // Ensure price is float
}

/**
 * Format percentage
 * * @param float $percentage Percentage to format
 * @return string Formatted percentage
 */
function formatPercentage($percentage) {
    // Added check for non-numeric input
    if (!is_numeric($percentage)) {
        return 'N/A'; // Or handle as appropriate
    }
    return number_format((float)$percentage, 2) . '%'; // Ensure percentage is float
}
?>