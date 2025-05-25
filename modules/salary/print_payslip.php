<?php
/**
 * Print Payslip
 * 
 * This file generates a PDF version of an employee's payslip
 */

// Include database connection
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once 'functions.php';

// Check permissions
if (!is_logged_in() || !has_permission('view_salary_reports')) {
    header("HTTP/1.1 403 Forbidden");
    echo "Access denied";
    exit;
}

// Get parameters
$salary_id = isset($_GET['salary_id']) ? intval($_GET['salary_id']) : null;
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : null;
$year = isset($_GET['year']) ? intval($_GET['year']) : null;
$month = isset($_GET['month']) ? intval($_GET['month']) : null;

// If we have staff_id, year, and month but no salary_id, try to find it
if (!$salary_id && $staff_id && $year && $month) {
    $pay_period = sprintf('%04d-%02d', $year, $month);
    $query = "SELECT salary_id FROM salary_records WHERE staff_id = ? AND pay_period = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $staff_id, $pay_period);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $salary_id = $row['salary_id'];
    }
    $stmt->close();
}

// Verify we have a valid salary_id
if (!$salary_id) {
    header("HTTP/1.1 400 Bad Request");
    echo "Missing or invalid salary ID";
    exit;
}

// Get salary record details
$query = "SELECT sr.*, s.first_name, s.last_name, s.staff_code, s.department, s.position,
                 u.full_name as approved_by_name
          FROM salary_records sr
          JOIN staff s ON sr.staff_id = s.staff_id
          LEFT JOIN users u ON sr.approved_by = u.user_id
          WHERE sr.salary_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $salary_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("HTTP/1.1 404 Not Found");
    echo "Salary record not found";
    exit;
}

$salary = $result->fetch_assoc();
$stmt->close();

// Get company information
$company_name = get_setting('company_name', 'Company Name');
$company_address = get_setting('company_address', 'Company Address');
$company_phone = get_setting('company_phone', 'Phone Number');
$company_email = get_setting('company_email', 'Email');

// Format pay period for display
$pay_period_parts = explode('-', $salary['pay_period']);
$pay_period_display = date('F Y', mktime(0, 0, 0, $pay_period_parts[1], 1, $pay_period_parts[0]));

// Create file name for the PDF
$file_name = 'Payslip_' . $salary['staff_code'] . '_' . $salary['pay_period'] . '.pdf';

// Initialize TCPDF library
// Note: You need to have TCPDF installed or use another PDF library
require_once('../../vendor/tcpdf/tcpdf.php');

// Create new PDF document
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Payroll System');
$pdf->SetAuthor($company_name);
$pdf->SetTitle('Payslip - ' . $salary['first_name'] . ' ' . $salary['last_name'] . ' - ' . $pay_period_display);
$pdf->SetSubject('Employee Payslip');
$pdf->SetKeywords('Payslip, Salary, Employee');

// Remove header and footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set default monospaced font
$pdf->SetDefaultMonospacedFont('courier');

// Set margins
$pdf->SetMargins(15, 15, 15);

// Set auto page breaks
$pdf->SetAutoPageBreak(true, 15);

// Set image scale factor
$pdf->setImageScale(1.25);

// Set font
$pdf->SetFont('helvetica', '', 10);

// Add a page
$pdf->AddPage();

// Company information
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, strtoupper($company_name), 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, $company_address, 0, 1, 'C');
$pdf->Cell(0, 6, 'Phone: ' . $company_phone . ' | Email: ' . $company_email, 0, 1, 'C');

// Payslip title
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Ln(5);
$pdf->Cell(0, 10, 'PAYSLIP FOR ' . strtoupper($pay_period_display), 0, 1, 'C');
$pdf->Ln(5);

// Employee details - left column
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(90, 7, 'Employee Details', 0, 0);

// Payment details - right column
$pdf->Cell(90, 7, 'Payment Details', 0, 1);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(2);

// Employee info table - left side
$pdf->SetFont('helvetica', '', 10);
$employee_info = array(
    array('Employee Name:', $salary['first_name'] . ' ' . $salary['last_name']),
    array('Employee ID:', $salary['staff_code']),
    array('Department:', $salary['department'] ?? 'N/A'),
    array('Position:', $salary['position']),
    array('Payment Date:', $salary['payment_date'] ? date('d M, Y', strtotime($salary['payment_date'])) : 'Pending')
);

// Payment info table - right side
$payment_info = array(
    array('Pay Period:', $pay_period_display),
    array('Payment Method:', $salary['payment_method'] ? ucfirst($salary['payment_method']) : 'Pending'),
    array('Reference No:', $salary['reference_no'] ?? 'N/A'),
    array('Days Worked:', $salary['days_worked']),
    array('Leave Days:', $salary['leave_days'])
);

// Print employee and payment info in two columns
for ($i = 0; $i < count($employee_info); $i++) {
    $pdf->Cell(25, 7, $employee_info[$i][0], 0, 0);
    $pdf->Cell(65, 7, $employee_info[$i][1], 0, 0);
    
    if (isset($payment_info[$i])) {
        $pdf->Cell(25, 7, $payment_info[$i][0], 0, 0);
        $pdf->Cell(65, 7, $payment_info[$i][1], 0, 1);
    } else {
        $pdf->Ln();
    }
}

$pdf->Ln(5);

// Attendance Summary
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 7, 'Attendance Summary', 0, 1);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(2);

// Create attendance summary table
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(45, 7, 'Days Worked: ' . $salary['days_worked'], 1, 0, 'C');
$pdf->Cell(45, 7, 'Leave Days: ' . $salary['leave_days'], 1, 0, 'C');
$pdf->Cell(45, 7, 'Absent Days: ' . $salary['absent_days'], 1, 0, 'C');
$pdf->Cell(45, 7, 'Overtime Hours: ' . number_format($salary['overtime_hours'], 1), 1, 1, 'C');

$pdf->Ln(5);

// Earnings and Deductions in two columns
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(90, 7, 'Earnings', 0, 0);
$pdf->Cell(90, 7, 'Deductions', 0, 1);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(2);

// Earnings table
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(65, 7, 'Description', 1, 0, 'L');
$pdf->Cell(25, 7, 'Amount (Rs.)', 1, 0, 'R');

// Start of deductions table (next column)
$pdf->Cell(5, 7, '', 0, 0); // spacer
$pdf->Cell(65, 7, 'Description', 1, 0, 'L');
$pdf->Cell(25, 7, 'Amount (Rs.)', 1, 1, 'R');

// Basic Salary
$pdf->Cell(65, 7, 'Basic Salary', 1, 0, 'L');
$pdf->Cell(25, 7, number_format($salary['basic_salary'], 2), 1, 0, 'R');

// EPF Employee
$pdf->Cell(5, 7, '', 0, 0); // spacer
$pdf->Cell(65, 7, 'EPF (Employee 8%)', 1, 0, 'L');
$pdf->Cell(25, 7, number_format($salary['epf_employee'], 2), 1, 1, 'R');

// Transport Allowance if present
if ($salary['transport_allowance'] > 0) {
    $pdf->Cell(65, 7, 'Transport Allowance', 1, 0, 'L');
    $pdf->Cell(25, 7, number_format($salary['transport_allowance'], 2), 1, 0, 'R');
    
    // PAYE Tax if present
    if ($salary['paye_tax'] > 0) {
        $pdf->Cell(5, 7, '', 0, 0); // spacer
        $pdf->Cell(65, 7, 'PAYE Tax', 1, 0, 'L');
        $pdf->Cell(25, 7, number_format($salary['paye_tax'], 2), 1, 1, 'R');
    } else {
        $pdf->Ln(); // Just end this line
    }
}

// Meal Allowance if present
if ($salary['meal_allowance'] > 0) {
    $pdf->Cell(65, 7, 'Meal Allowance', 1, 0, 'L');
    $pdf->Cell(25, 7, number_format($salary['meal_allowance'], 2), 1, 0, 'R');
    
    // Loan Deductions if present
    if ($salary['loan_deductions'] > 0) {
        $pdf->Cell(5, 7, '', 0, 0); // spacer
        $pdf->Cell(65, 7, 'Loan Repayments', 1, 0, 'L');
        $pdf->Cell(25, 7, number_format($salary['loan_deductions'], 2), 1, 1, 'R');
    } else {
        $pdf->Ln(); // Just end this line
    }
}

// Housing Allowance if present
if ($salary['housing_allowance'] > 0) {
    $pdf->Cell(65, 7, 'Housing Allowance', 1, 0, 'L');
    $pdf->Cell(25, 7, number_format($salary['housing_allowance'], 2), 1, 0, 'R');
    
    // Other Deductions if present
    if ($salary['other_deductions'] > 0) {
        $pdf->Cell(5, 7, '', 0, 0); // spacer
        $pdf->Cell(65, 7, 'Other Deductions', 1, 0, 'L');
        $pdf->Cell(25, 7, number_format($salary['other_deductions'], 2), 1, 1, 'R');
    } else {
        $pdf->Ln(); // Just end this line
    }
}

// Other Allowance if present
if ($salary['other_allowance'] > 0) {
    $pdf->Cell(65, 7, 'Other Allowances', 1, 0, 'L');
    $pdf->Cell(25, 7, number_format($salary['other_allowance'], 2), 1, 0, 'R');
    $pdf->Ln(); // Just end this line
}

// Overtime if present
if ($salary['overtime_amount'] > 0) {
    $pdf->Cell(65, 7, 'Overtime (' . $salary['overtime_hours'] . ' hours)', 1, 0, 'L');
    $pdf->Cell(25, 7, number_format($salary['overtime_amount'], 2), 1, 0, 'R');
    $pdf->Ln(); // Just end this line
}

// Total Earnings
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(65, 7, 'Total Earnings', 1, 0, 'L');
$pdf->Cell(25, 7, number_format($salary['gross_salary'], 2), 1, 0, 'R');

// Total Deductions (add a new line if needed)
if ($pdf->GetY() % 7 != 0) {
    $pdf->Ln(); // Go to next line
    $pdf->Cell(90, 7, '', 0, 0); // Empty space for alignment
}
$pdf->Cell(5, 7, '', 0, 0); // spacer
$pdf->Cell(65, 7, 'Total Deductions', 1, 0, 'L');
$pdf->Cell(25, 7, number_format($salary['total_deductions'], 2), 1, 1, 'R');

$pdf->Ln(5);

// Net Pay Section
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'NET PAY: Rs. ' . number_format($salary['net_salary'], 2), 1, 1, 'C', false, '', 0, false, 'T', 'M');

// In Words
$pdf->SetFont('helvetica', 'I', 10);
$pdf->Cell(0, 10, 'Amount in words: ' . getWords($salary['net_salary']) . ' Rupees Only', 0, 1, 'L');

$pdf->Ln(5);

// Employer Contributions
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 7, 'Employer Contributions', 0, 1);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(2);

// Create employer contributions table
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(90, 7, 'EPF Employer Contribution (12%)', 1, 0, 'L');
$pdf->Cell(90, 7, 'Rs. ' . number_format($salary['epf_employer'], 2), 1, 1, 'R');
$pdf->Cell(90, 7, 'ETF Contribution (3%)', 1, 0, 'L');
$pdf->Cell(90, 7, 'Rs. ' . number_format($salary['etf'], 2), 1, 1, 'R');
$pdf->Cell(90, 7, 'Total Employer Contributions', 1, 0, 'L');
$pdf->Cell(90, 7, 'Rs. ' . number_format($salary['epf_employer'] + $salary['etf'], 2), 1, 1, 'R');

$pdf->Ln(10);

// Signatures
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(90, 7, 'Authorized Signature', 0, 0, 'C');
$pdf->Cell(90, 7, 'Employee Signature', 0, 1, 'C');
$pdf->Ln(15); // Space for signatures

$pdf->Line(30, $pdf->GetY(), 85, $pdf->GetY()); // Line for authorized signature
$pdf->Line(105, $pdf->GetY(), 160, $pdf->GetY()); // Line for employee signature

// Footer text
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 7, 'This is a computer-generated document and does not require a signature.', 0, 1, 'C');
$pdf->Cell(0, 7, 'For any queries regarding this payslip, please contact the HR department.', 0, 1, 'C');

// Output the PDF
$pdf->Output($file_name, 'I'); // 'I' sends to browser, 'D' forces download

/**
 * Convert a number to words for Sri Lankan Rupees
 * 
 * @param float $number The number to convert
 * @return string The number in words
 */
function getWords($number) {
    $ones = array(
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'
    );
    
    $tens = array(
        0 => '', 1 => 'Ten', 2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
        6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'
    );
    
    $number = number_format($number, 2, '.', '');
    $number_parts = explode('.', $number);
    $whole_number = $number_parts[0];
    $decimal = $number_parts[1];
    
    // Get whole number in words
    if ($whole_number == 0) {
        $whole_words = 'Zero';
    } else {
        $whole_words = '';
        
        // Handle millions
        if ($whole_number >= 1000000 && $whole_number < 1000000000) {
            $millions = (int)($whole_number / 1000000);
            $whole_words .= getWords($millions) . ' Million ';
            $whole_number -= $millions * 1000000;
        }
        
        // Handle thousands
        if ($whole_number >= 1000 && $whole_number < 1000000) {
            $thousands = (int)($whole_number / 1000);
            $whole_words .= getWords($thousands) . ' Thousand ';
            $whole_number -= $thousands * 1000;
        }
        
        // Handle hundreds
        if ($whole_number >= 100 && $whole_number < 1000) {
            $hundreds = (int)($whole_number / 100);
            $whole_words .= $ones[$hundreds] . ' Hundred ';
            $whole_number -= $hundreds * 100;
        }
        
        // Handle tens and ones
        if ($whole_number > 0) {
            if ($whole_words != '') {
                $whole_words .= 'and ';
            }
            
            if ($whole_number < 20) {
                $whole_words .= $ones[$whole_number];
            } else {
                $whole_words .= $tens[(int)($whole_number / 10)];
                if ($whole_number % 10 > 0) {
                    $whole_words .= '-' . $ones[$whole_number % 10];
                }
            }
        }
    }
    
    // Get cents in words
    if ($decimal == '00') {
        return trim($whole_words);
    } else {
        $cents_words = '';
        $decimal_num = (int)$decimal;
        
        if ($decimal_num < 20) {
            $cents_words = $ones[$decimal_num];
        } else {
            $cents_words = $tens[(int)($decimal_num / 10)];
            if ($decimal_num % 10 > 0) {
                $cents_words .= '-' . $ones[$decimal_num % 10];
            }
        }
        
        return trim($whole_words) . ' and ' . $cents_words . ' Cents';
    }
}
?>