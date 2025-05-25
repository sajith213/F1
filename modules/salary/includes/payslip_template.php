<?php
/**
 * Payslip Template
 * 
 * This file defines the template and styling for employee payslips.
 * It is used by both the print_payslip.php and payslip.php files.
 */

// Get company details from settings
$company_name = get_setting('company_name', 'Company Name');
$company_address = get_setting('company_address', 'Company Address');
$company_phone = get_setting('company_phone', 'Company Phone');
$company_email = get_setting('company_email', 'Company Email');
$currency_symbol = get_setting('currency_symbol', 'Rs.');

// Helper function for formatting currency values
function formatCurrency($value) {
    global $currency_symbol;
    return $currency_symbol . ' ' . number_format((float)$value, 2, '.', ',');
}

/**
 * Generate HTML for a payslip
 * 
 * @param array $payslip_data The salary record data with employee details
 * @param array $ytd_data Optional YTD totals for the employee
 * @param bool $print_mode Whether the payslip is being rendered for printing
 * @return string HTML content for the payslip
 */
function generate_payslip_html($payslip_data, $ytd_data = null, $print_mode = false) {
    global $company_name, $company_address, $company_phone, $company_email, $currency_symbol;
    
    // Format pay period for display (e.g. "April 2025")
    $pay_period_display = date('F Y', strtotime($payslip_data['pay_period'] . '-01'));
    
    // Get employee's full name
    $employee_name = $payslip_data['first_name'] . ' ' . $payslip_data['last_name'];
    
    // Calculate payment status badge color
    $status_badge_class = 'bg-green-100 text-green-800';
    if ($payslip_data['payment_status'] === 'pending') {
        $status_badge_class = 'bg-yellow-100 text-yellow-800';
    } elseif ($payslip_data['payment_status'] === 'cancelled') {
        $status_badge_class = 'bg-red-100 text-red-800';
    }
    
    // Calculate attendance summary
    $total_working_days = cal_days_in_month(CAL_GREGORIAN, substr($payslip_data['pay_period'], 5, 2), substr($payslip_data['pay_period'], 0, 4));
    $days_worked = $payslip_data['days_worked'] ?? 0;
    $leave_days = $payslip_data['leave_days'] ?? 0;
    $absent_days = $payslip_data['absent_days'] ?? 0;
    
    // Convert pay_period (YYYY-MM) to date object for watermark
    $pay_period_date = date_create_from_format('Y-m', $payslip_data['pay_period']);
    $pay_period_formatted = date_format($pay_period_date, 'F Y');
    
    // Generate salary in words
    $amount_in_words = getWords($payslip_data['net_salary']);
    
    // Begin HTML output
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payslip - <?= htmlspecialchars($employee_name) ?> - <?= htmlspecialchars($pay_period_display) ?></title>
        
        <?php if (!$print_mode): ?>
        <!-- Regular view styling - only included when not in print mode -->
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <?php endif; ?>
        
        <style>
            /* Common styles for both print and screen */
            .payslip-container {
                font-family: Arial, sans-serif;
                max-width: 800px;
                margin: 0 auto;
                <?php if ($print_mode): ?>
                padding: 20px;
                <?php else: ?>
                padding: 0;
                <?php endif; ?>
            }
            
            .payslip {
                border: 1px solid #ccc;
                background-color: #fff;
                position: relative;
                overflow: hidden;
            }
            
            .payslip-header {
                border-bottom: 2px solid #333;
                padding: 15px 20px;
                position: relative;
            }
            
            .company-logo {
                width: 80px;
                height: auto;
            }
            
            .payslip-watermark {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-45deg);
                font-size: 80px;
                opacity: 0.05;
                color: #000;
                white-space: nowrap;
                z-index: 0;
            }
            
            .payslip-body {
                position: relative;
                z-index: 1;
            }
            
            .employee-info {
                border-bottom: 1px solid #eee;
                padding: 15px 20px;
                display: flex;
                flex-wrap: wrap;
            }
            
            .employee-detail {
                margin-bottom: 5px;
            }
            
            .section-title {
                font-weight: bold;
                margin-bottom: 8px;
                font-size: 14px;
                color: #333;
                border-bottom: 1px solid #eee;
                padding-bottom: 5px;
            }
            
            .earnings-section,
            .deductions-section {
                padding: 15px 20px;
            }
            
            .details-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 5px;
                font-size: 13px;
            }
            
            .detail-label {
                color: #555;
            }
            
            .detail-value {
                font-weight: bold;
                text-align: right;
            }
            
            .totals-section {
                background-color: #f9f9f9;
                padding: 15px 20px;
                border-top: 1px solid #eee;
                border-bottom: 1px solid #eee;
            }
            
            .net-pay-row {
                font-size: 16px;
                font-weight: bold;
                margin-top: 10px;
            }
            
            .amount-in-words {
                padding: 15px 20px;
                font-style: italic;
                font-size: 12px;
                color: #666;
                border-bottom: 1px solid #eee;
            }
            
            .footer-text {
                text-align: center;
                padding: 15px 20px;
                font-size: 11px;
                color: #777;
            }
            
            .ytd-section {
                padding: 15px 20px;
                border-top: 1px solid #eee;
            }
            
            .company-info {
                text-align: right;
            }
            
            .company-name {
                font-weight: bold;
                font-size: 18px;
            }
            
            /* Status badge styling */
            .status-badge {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: bold;
            }
            
            .attendance-summary {
                padding: 15px 20px;
                border-bottom: 1px solid #eee;
            }
            
            .attendance-grid {
                display: flex;
                gap: 15px;
                flex-wrap: wrap;
            }
            
            .attendance-item {
                flex: 1;
                min-width: 100px;
                text-align: center;
                background-color: #f5f5f5;
                padding: 8px;
                border-radius: 4px;
            }
            
            .item-value {
                font-weight: bold;
                font-size: 16px;
                margin-bottom: 4px;
            }
            
            .item-label {
                font-size: 12px;
                color: #666;
            }
            
            /* Print-specific styles */
            @media print {
                body {
                    padding: 0;
                    margin: 0;
                    background: white;
                }
                
                .payslip-container {
                    width: 100%;
                    max-width: none;
                    padding: 0;
                    margin: 0;
                }
                
                .payslip {
                    border: 1px solid #ccc;
                    box-shadow: none;
                    margin: 0;
                }
                
                .print-buttons {
                    display: none !important;
                }
                
                /* Force background printing for watermark */
                * {
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
            }
        </style>
    </head>
    <body class="<?= $print_mode ? 'bg-white' : 'bg-gray-100' ?> <?= $print_mode ? '' : 'p-4' ?>">
        <div class="payslip-container">
            <?php if (!$print_mode): ?>
            <div class="print-buttons mb-4 flex justify-end space-x-2">
                <button onclick="window.print();" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm">
                    <i class="fas fa-print mr-1"></i> Print Payslip
                </button>
                <button onclick="window.close();" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm">
                    <i class="fas fa-times mr-1"></i> Close
                </button>
            </div>
            <?php endif; ?>
            
            <div class="payslip">
                <!-- Watermark -->
                <div class="payslip-watermark">
                    <?= strtoupper($payslip_data['payment_status']) ?> PAYSLIP
                </div>
                
                <!-- Payslip Header -->
                <div class="payslip-header flex justify-between items-center">
                    <div class="flex items-center">
                        <div class="mr-4">
                            <!-- Company logo would go here -->
                            <div class="w-16 h-16 bg-gray-200 flex items-center justify-center text-gray-400 rounded">
                                <span>LOGO</span>
                            </div>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold"><?= htmlspecialchars($company_name) ?></h1>
                            <p class="text-sm"><?= htmlspecialchars($company_address) ?></p>
                            <p class="text-sm"><?= htmlspecialchars($company_phone) ?> | <?= htmlspecialchars($company_email) ?></p>
                        </div>
                    </div>
                    
                    <div class="text-right">
                        <h2 class="text-xl font-bold mb-1">PAYSLIP</h2>
                        <p class="text-sm font-semibold"><?= htmlspecialchars($pay_period_display) ?></p>
                        <p class="mt-2">
                            <span class="status-badge <?= $status_badge_class ?>">
                                <?= ucfirst($payslip_data['payment_status']) ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <!-- Payslip Body -->
                <div class="payslip-body">
                    <!-- Employee Information -->
                    <div class="employee-info">
                        <div class="w-full md:w-1/2">
                            <div class="employee-detail">
                                <span class="font-semibold">Employee Name:</span> 
                                <?= htmlspecialchars($employee_name) ?>
                            </div>
                            <div class="employee-detail">
                                <span class="font-semibold">Employee ID:</span> 
                                <?= htmlspecialchars($payslip_data['staff_code']) ?>
                            </div>
                            <div class="employee-detail">
                                <span class="font-semibold">Position:</span> 
                                <?= htmlspecialchars($payslip_data['position']) ?>
                            </div>
                            <div class="employee-detail">
                                <span class="font-semibold">Department:</span> 
                                <?= htmlspecialchars($payslip_data['department'] ?? 'N/A') ?>
                            </div>
                        </div>
                        
                        <div class="w-full md:w-1/2 mt-2 md:mt-0">
                            <div class="employee-detail">
                                <span class="font-semibold">Pay Period:</span> 
                                <?= htmlspecialchars($pay_period_display) ?>
                            </div>
                            <?php if ($payslip_data['payment_date']): ?>
                            <div class="employee-detail">
                                <span class="font-semibold">Payment Date:</span> 
                                <?= date('d M, Y', strtotime($payslip_data['payment_date'])) ?>
                            </div>
                            <?php endif; ?>
                            <div class="employee-detail">
                                <span class="font-semibold">Payment Method:</span> 
                                <?= ucfirst($payslip_data['payment_method'] ?? 'Not specified') ?>
                            </div>
                            <?php if (!empty($payslip_data['reference_no'])): ?>
                            <div class="employee-detail">
                                <span class="font-semibold">Reference No:</span> 
                                <?= htmlspecialchars($payslip_data['reference_no']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Attendance Summary -->
                    <div class="attendance-summary">
                        <div class="section-title">Attendance Summary</div>
                        <div class="attendance-grid">
                            <div class="attendance-item">
                                <div class="item-value"><?= $total_working_days ?></div>
                                <div class="item-label">Total Days</div>
                            </div>
                            <div class="attendance-item">
                                <div class="item-value"><?= $days_worked ?></div>
                                <div class="item-label">Days Worked</div>
                            </div>
                            <div class="attendance-item">
                                <div class="item-value"><?= $leave_days ?></div>
                                <div class="item-label">Leave Days</div>
                            </div>
                            <div class="attendance-item">
                                <div class="item-value"><?= $absent_days ?></div>
                                <div class="item-label">Absent Days</div>
                            </div>
                            <?php if (isset($payslip_data['overtime_hours']) && $payslip_data['overtime_hours'] > 0): ?>
                            <div class="attendance-item">
                                <div class="item-value"><?= $payslip_data['overtime_hours'] ?></div>
                                <div class="item-label">Overtime Hours</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Earnings Section -->
                    <div class="earnings-section">
                        <div class="section-title">Earnings</div>
                        
                        <div class="details-row">
                            <div class="detail-label">Basic Salary</div>
                            <div class="detail-value"><?= formatCurrency($payslip_data['basic_salary']) ?></div>
                        </div>
                        
                        <?php if ($payslip_data['transport_allowance'] > 0): ?>
                        <div class="details-row">
                            <div class="detail-label">Transport Allowance</div>
                            <div class="detail-value"><?= formatCurrency($payslip_data['transport_allowance']) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($payslip_data['meal_allowance'] > 0): ?>
                        <div class="details-row">
                            <div class="detail-label">Meal Allowance</div>
                            <div class="detail-value"><?= formatCurrency($payslip_data['meal_allowance']) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($payslip_data['housing_allowance'] > 0): ?>
                        <div class="details-row">
                            <div class="detail-label">Housing Allowance</div>
                            <div class="detail-value"><?= formatCurrency($payslip_data['housing_allowance']) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($payslip_data['other_allowance'] > 0): ?>
                        <div class="details-row">
                            <div class="detail-label">Other Allowances</div>
                            <div class="detail-value"><?= formatCurrency($payslip_data['other_allowance']) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($payslip_data['overtime_amount'] > 0): ?>
                        <div class="details-row">
                            <div class="detail-label">Overtime (<?= $payslip_data['overtime_hours'] ?> hours)</div>
                            <div class="detail-value"><?= formatCurrency($payslip_data['overtime_amount']) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="details-row mt-2 pt-2 border-t border-gray-200">
                            <div class="detail-label font-semibold">Total Earnings</div>
                            <div class="detail-value"><?= formatCurrency($payslip_data['gross_salary']) ?></div>
                        </div>
                    </div>
                    
                    <!-- Deductions Section -->
                    <div class="deductions-section">
                        <div class="section-title">Deductions</div>
                        
                        <div class="details-row">
                            <div class="detail-label">EPF Employee Contribution (<?= $payslip_data['epf_employee_percent'] ?? 8 ?>%)</div>
                            <div class="detail-value"><?= formatCurrency($payslip_data['epf_employee']) ?></div>
                        </div>
                        
                        <?php if ($payslip_data['paye_tax'] > 0): ?>
                        <div class="details-row">
                            <div class="detail-label">PAYE Tax</div>
                            <div class="detail-value"><?= formatCurrency($payslip_data['paye_tax']) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($payslip_data['loan_deductions'] > 0): ?>
                        <div class="details-row">
                            <div class="detail-label">Loan Repayment</div>
                            <div class="detail-value"><?= formatCurrency($payslip_data['loan_deductions']) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($payslip_data['other_deductions'] > 0): ?>
                        <div class="details-row">
                            <div class="detail-label">Other Deductions</div>
                            <div class="detail-value"><?= formatCurrency($payslip_data['other_deductions']) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="details-row mt-2 pt-2 border-t border-gray-200">
                            <div class="detail-label font-semibold">Total Deductions</div>
                            <div class="detail-value"><?= formatCurrency($payslip_data['total_deductions']) ?></div>
                        </div>
                    </div>
                    
                    <!-- Net Pay Section -->
                    <div class="totals-section">
                        <div class="details-row">
                            <div class="detail-label">Gross Salary</div>
                            <div class="detail-value"><?= formatCurrency($payslip_data['gross_salary']) ?></div>
                        </div>
                        
                        <div class="details-row">
                            <div class="detail-label">Total Deductions</div>
                            <div class="detail-value"><?= formatCurrency($payslip_data['total_deductions']) ?></div>
                        </div>
                        
                        <div class="details-row net-pay-row border-t border-gray-300 pt-2">
                            <div class="detail-label">NET SALARY</div>
                            <div class="detail-value"><?= formatCurrency($payslip_data['net_salary']) ?></div>
                        </div>
                    </div>
                    
                    <!-- Amount in Words -->
                    <div class="amount-in-words">
                        <strong>Amount in words:</strong> <?= $amount_in_words ?>
                    </div>
                    
                    <!-- Year-to-Date Section (if available) -->
                    <?php if ($ytd_data): ?>
                    <div class="ytd-section">
                        <div class="section-title">Year-to-Date Totals (<?= substr($payslip_data['pay_period'], 0, 4) ?>)</div>
                        
                        <div class="md:flex md:space-x-4">
                            <div class="md:w-1/2">
                                <div class="details-row">
                                    <div class="detail-label">YTD Gross Earnings</div>
                                    <div class="detail-value"><?= formatCurrency($ytd_data['ytd_gross']) ?></div>
                                </div>
                                <div class="details-row">
                                    <div class="detail-label">YTD EPF (Employee)</div>
                                    <div class="detail-value"><?= formatCurrency($ytd_data['ytd_epf_employee']) ?></div>
                                </div>
                                <div class="details-row">
                                    <div class="detail-label">YTD PAYE Tax</div>
                                    <div class="detail-value"><?= formatCurrency($ytd_data['ytd_tax']) ?></div>
                                </div>
                            </div>
                            
                            <div class="md:w-1/2 mt-2 md:mt-0">
                                <div class="details-row">
                                    <div class="detail-label">YTD Total Deductions</div>
                                    <div class="detail-value"><?= formatCurrency($ytd_data['ytd_epf_employee'] + $ytd_data['ytd_tax'] + $ytd_data['ytd_loans'] + $ytd_data['ytd_other_deductions']) ?></div>
                                </div>
                                <div class="details-row">
                                    <div class="detail-label">YTD Net Salary</div>
                                    <div class="detail-value"><?= formatCurrency($ytd_data['ytd_net']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Company Contributions -->
                    <div class="ytd-section">
                        <div class="section-title">Employer Contributions (not deducted from salary)</div>
                        
                        <div class="details-row">
                            <div class="detail-label">EPF Employer Contribution (<?= $payslip_data['epf_employer_percent'] ?? 12 ?>%)</div>
                            <div class="detail-value"><?= formatCurrency($payslip_data['epf_employer']) ?></div>
                        </div>
                        
                        <div class="details-row">
                            <div class="detail-label">ETF Contribution (<?= $payslip_data['etf_percent'] ?? 3 ?>%)</div>
                            <div class="detail-value"><?= formatCurrency($payslip_data['etf']) ?></div>
                        </div>
                        
                        <div class="details-row mt-2 pt-2 border-t border-gray-200">
                            <div class="detail-label font-semibold">Total Employer Contributions</div>
                            <div class="detail-value"><?= formatCurrency($payslip_data['epf_employer'] + $payslip_data['etf']) ?></div>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="footer-text">
                        <p>This is a computer-generated payslip and does not require a signature.</p>
                        <p>For any queries regarding your payslip, please contact the HR department.</p>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    return $html;
}
?>