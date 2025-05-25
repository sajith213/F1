/**
 * Salary Calculator JS
 * 
 * Client-side calculations for the salary management module.
 * This script handles real-time salary calculations, validations,
 * and dynamic updates of the salary calculator form.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize calculator if we're on the salary calculator page
    const calculatorForm = document.getElementById('salary-calculator-form');
    if (calculatorForm) {
        initializeSalaryCalculator();
    }
    
    // Initialize individual salary form if present
    const individualSalaryForm = document.getElementById('individual-salary-form');
    if (individualSalaryForm) {
        initializeIndividualSalaryForm();
    }
});

/**
 * Initialize the main salary calculator form
 */
function initializeSalaryCalculator() {
    // Get form elements
    const basicSalaryInput = document.getElementById('basic_salary');
    const transportAllowanceInput = document.getElementById('transport_allowance');
    const mealAllowanceInput = document.getElementById('meal_allowance');
    const housingAllowanceInput = document.getElementById('housing_allowance');
    const otherAllowanceInput = document.getElementById('other_allowance');
    const overtimeHoursInput = document.getElementById('overtime_hours');
    const overtimeRateInput = document.getElementById('overtime_rate');
    const epfEmployeePercentInput = document.getElementById('epf_employee_percent');
    const epfEmployerPercentInput = document.getElementById('epf_employer_percent');
    const etfPercentInput = document.getElementById('etf_percent');
    const payeTaxPercentInput = document.getElementById('paye_tax_percent');
    const loanDeductionInput = document.getElementById('loan_deduction');
    const otherDeductionInput = document.getElementById('other_deduction');
    
    // Get display elements
    const grossSalaryDisplay = document.getElementById('gross_salary');
    const epfEmployeeDisplay = document.getElementById('epf_employee');
    const epfEmployerDisplay = document.getElementById('epf_employer');
    const etfDisplay = document.getElementById('etf');
    const payeTaxDisplay = document.getElementById('paye_tax');
    const totalDeductionsDisplay = document.getElementById('total_deductions');
    const netSalaryDisplay = document.getElementById('net_salary');
    const overtimeAmountDisplay = document.getElementById('overtime_amount');
    
    // Attach event listeners to all input elements
    const inputs = [
        basicSalaryInput, transportAllowanceInput, mealAllowanceInput, 
        housingAllowanceInput, otherAllowanceInput, overtimeHoursInput,
        overtimeRateInput, epfEmployeePercentInput, epfEmployerPercentInput,
        etfPercentInput, payeTaxPercentInput, loanDeductionInput, otherDeductionInput
    ];
    
    inputs.forEach(input => {
        if (input) {
            input.addEventListener('input', calculateSalary);
        }
    });
    
    // Initial calculation
    calculateSalary();
    
    /**
     * Calculates the salary based on current input values
     */
    function calculateSalary() {
        // Get input values, defaulting to 0 if not present
        const basicSalary = parseFloat(basicSalaryInput?.value || 0);
        const transportAllowance = parseFloat(transportAllowanceInput?.value || 0);
        const mealAllowance = parseFloat(mealAllowanceInput?.value || 0);
        const housingAllowance = parseFloat(housingAllowanceInput?.value || 0);
        const otherAllowance = parseFloat(otherAllowanceInput?.value || 0);
        const overtimeHours = parseFloat(overtimeHoursInput?.value || 0);
        const overtimeRate = parseFloat(overtimeRateInput?.value || 0);
        const epfEmployeePercent = parseFloat(epfEmployeePercentInput?.value || 0);
        const epfEmployerPercent = parseFloat(epfEmployerPercentInput?.value || 0);
        const etfPercent = parseFloat(etfPercentInput?.value || 0);
        const payeTaxPercent = parseFloat(payeTaxPercentInput?.value || 0);
        const loanDeduction = parseFloat(loanDeductionInput?.value || 0);
        const otherDeduction = parseFloat(otherDeductionInput?.value || 0);
        
        // Calculate overtime amount (if applicable)
        // Assuming overtime is calculated on basic salary
        // Hourly rate = Basic salary / (8 hours * 22 working days)
        const hourlyRate = basicSalary / (8 * 22);
        const overtimeAmount = overtimeHours * hourlyRate * overtimeRate;
        
        // Calculate gross salary
        const grossSalary = basicSalary + transportAllowance + mealAllowance + 
                           housingAllowance + otherAllowance + overtimeAmount;
        
        // Calculate deductions
        const epfEmployee = (basicSalary * epfEmployeePercent) / 100;
        const epfEmployer = (basicSalary * epfEmployerPercent) / 100;
        const etf = (basicSalary * etfPercent) / 100;
        const payeTax = (grossSalary * payeTaxPercent) / 100;
        
        // Calculate total deductions
        const totalDeductions = epfEmployee + payeTax + loanDeduction + otherDeduction;
        
        // Calculate net salary
        const netSalary = grossSalary - totalDeductions;
        
        // Update displays if elements exist
        if (grossSalaryDisplay) grossSalaryDisplay.textContent = formatCurrency(grossSalary);
        if (epfEmployeeDisplay) epfEmployeeDisplay.textContent = formatCurrency(epfEmployee);
        if (epfEmployerDisplay) epfEmployerDisplay.textContent = formatCurrency(epfEmployer);
        if (etfDisplay) etfDisplay.textContent = formatCurrency(etf);
        if (payeTaxDisplay) payeTaxDisplay.textContent = formatCurrency(payeTax);
        if (totalDeductionsDisplay) totalDeductionsDisplay.textContent = formatCurrency(totalDeductions);
        if (netSalaryDisplay) netSalaryDisplay.textContent = formatCurrency(netSalary);
        if (overtimeAmountDisplay) overtimeAmountDisplay.textContent = formatCurrency(overtimeAmount);
        
        // Update hidden fields if they exist
        updateHiddenField('hidden_gross_salary', grossSalary);
        updateHiddenField('hidden_epf_employee', epfEmployee);
        updateHiddenField('hidden_epf_employer', epfEmployer);
        updateHiddenField('hidden_etf', etf);
        updateHiddenField('hidden_paye_tax', payeTax);
        updateHiddenField('hidden_total_deductions', totalDeductions);
        updateHiddenField('hidden_net_salary', netSalary);
        updateHiddenField('hidden_overtime_amount', overtimeAmount);
    }
}

/**
 * Initialize the individual employee salary form
 */
function initializeIndividualSalaryForm() {
    // Get form elements
    const basicSalaryInput = document.getElementById('basic_salary');
    const salaryEffectiveDateInput = document.getElementById('effective_date');
    const transportAllowanceInput = document.getElementById('transport_allowance');
    const mealAllowanceInput = document.getElementById('meal_allowance');
    const housingAllowanceInput = document.getElementById('housing_allowance');
    const otherAllowanceInput = document.getElementById('other_allowance');
    const epfEmployeePercentInput = document.getElementById('epf_employee_percent');
    const epfEmployerPercentInput = document.getElementById('epf_employer_percent');
    const etfPercentInput = document.getElementById('etf_percent');
    const payeTaxPercentInput = document.getElementById('paye_tax_percent');
    const overtimeRateRegularInput = document.getElementById('overtime_rate_regular');
    const overtimeRateHolidayInput = document.getElementById('overtime_rate_holiday');
    
    // Get display elements
    const totalMonthlyCostDisplay = document.getElementById('total_monthly_cost');
    const employerContributionsDisplay = document.getElementById('employer_contributions');
    const netSalaryDisplay = document.getElementById('estimated_net_salary');
    
    // Attach event listeners
    const inputs = [
        basicSalaryInput, transportAllowanceInput, mealAllowanceInput, 
        housingAllowanceInput, otherAllowanceInput, epfEmployeePercentInput,
        epfEmployerPercentInput, etfPercentInput, payeTaxPercentInput
    ];
    
    inputs.forEach(input => {
        if (input) {
            input.addEventListener('input', calculateEmployeeSalary);
        }
    });
    
    // Validate date input
    if (salaryEffectiveDateInput) {
        salaryEffectiveDateInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            
            // Reset if date is invalid
            if (isNaN(selectedDate.getTime())) {
                this.value = formatDateForInput(today);
            }
        });
        
        // Set default date if empty
        if (!salaryEffectiveDateInput.value) {
            salaryEffectiveDateInput.value = formatDateForInput(new Date());
        }
    }
    
    // Initial calculation
    calculateEmployeeSalary();
    
    /**
     * Calculates the employee salary details
     */
    function calculateEmployeeSalary() {
        // Get input values
        const basicSalary = parseFloat(basicSalaryInput?.value || 0);
        const transportAllowance = parseFloat(transportAllowanceInput?.value || 0);
        const mealAllowance = parseFloat(mealAllowanceInput?.value || 0);
        const housingAllowance = parseFloat(housingAllowanceInput?.value || 0);
        const otherAllowance = parseFloat(otherAllowanceInput?.value || 0);
        const epfEmployeePercent = parseFloat(epfEmployeePercentInput?.value || 0);
        const epfEmployerPercent = parseFloat(epfEmployerPercentInput?.value || 0);
        const etfPercent = parseFloat(etfPercentInput?.value || 0);
        const payeTaxPercent = parseFloat(payeTaxPercentInput?.value || 0);
        
        // Calculate gross salary
        const grossSalary = basicSalary + transportAllowance + mealAllowance + 
                          housingAllowance + otherAllowance;
        
        // Calculate deductions
        const epfEmployee = (basicSalary * epfEmployeePercent) / 100;
        const epfEmployer = (basicSalary * epfEmployerPercent) / 100;
        const etf = (basicSalary * etfPercent) / 100;
        const payeTax = (grossSalary * payeTaxPercent) / 100;
        
        // Calculate employer contributions
        const employerContributions = epfEmployer + etf;
        
        // Calculate total monthly cost to company
        const totalMonthlyCost = grossSalary + employerContributions;
        
        // Calculate estimated net salary
        const estimatedNetSalary = grossSalary - epfEmployee - payeTax;
        
        // Update displays
        if (totalMonthlyCostDisplay) totalMonthlyCostDisplay.textContent = formatCurrency(totalMonthlyCost);
        if (employerContributionsDisplay) employerContributionsDisplay.textContent = formatCurrency(employerContributions);
        if (netSalaryDisplay) netSalaryDisplay.textContent = formatCurrency(estimatedNetSalary);
    }
}

/**
 * Batch calculation functions
 */
function calculateAllSalaries() {
    const checkboxes = document.querySelectorAll('input[name="employee_ids[]"]:checked');
    if (checkboxes.length === 0) {
        alert('Please select at least one employee');
        return false;
    }
    return true;
}

/**
 * Calculate loan installments and completion date
 */
function calculateLoanDetails() {
    const loanAmount = parseFloat(document.getElementById('loan_amount')?.value || 0);
    const monthlyDeduction = parseFloat(document.getElementById('monthly_deduction')?.value || 0);
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const installmentsDisplay = document.getElementById('installments_display');
    
    if (loanAmount > 0 && monthlyDeduction > 0 && startDateInput) {
        // Calculate number of installments
        const installments = Math.ceil(loanAmount / monthlyDeduction);
        
        // Calculate end date
        const startDate = new Date(startDateInput.value);
        if (!isNaN(startDate.getTime())) {
            // Calculate completion date (add installments - 1 months to start date)
            const completionDate = new Date(startDate);
            completionDate.setMonth(completionDate.getMonth() + installments - 1);
            
            // Update end date if it exists
            if (endDateInput && !endDateInput.value) {
                endDateInput.value = formatDateForInput(completionDate);
            }
            
            // Update installments display
            if (installmentsDisplay) {
                installmentsDisplay.textContent = installments;
            }
        }
    }
}

/**
 * Helper function to update hidden form fields
 */
function updateHiddenField(fieldId, value) {
    const field = document.getElementById(fieldId);
    if (field) {
        field.value = value.toFixed(2);
    }
}

/**
 * Format a number as currency (with commas for thousands)
 */
function formatCurrency(amount) {
    return amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Format a date for input fields (YYYY-MM-DD)
 */
function formatDateForInput(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Generate payslip preview in modal
 */
function previewPayslip(staffId, year, month) {
    const modal = document.getElementById('payslip-preview-modal');
    const contentDiv = document.getElementById('payslip-preview-content');
    
    if (!modal || !contentDiv) return;
    
    // Show loading state
    contentDiv.innerHTML = '<div class="text-center p-8"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading payslip...</p></div>';
    modal.classList.remove('hidden');
    
    // Fetch payslip data
    fetch(`print_payslip.php?staff_id=${staffId}&year=${year}&month=${month}&preview=1`)
        .then(response => response.text())
        .then(html => {
            contentDiv.innerHTML = html;
        })
        .catch(error => {
            contentDiv.innerHTML = `<div class="text-red-500 p-4">Error loading payslip: ${error.message}</div>`;
        });
}

/**
 * Close payslip preview modal
 */
function closePayslipPreview() {
    const modal = document.getElementById('payslip-preview-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

/**
 * Generate salary certificate
 */
function generateSalaryCertificate(staffId) {
    // Redirect to certificate generation page
    window.location.href = `salary_certificate.php?staff_id=${staffId}`;
}

/**
 * Calculate PAYE tax based on Sri Lanka tax brackets
 * Note: Tax brackets need to be updated when tax laws change
 */
function calculatePAYETax(taxableIncome, year = 2023) {
    // 2023-2024 Tax Brackets for Sri Lanka
    // These need to be updated when tax laws change
    const taxBrackets = {
        2023: [
            { limit: 100000, rate: 0 },      // First 100,000 is tax-free
            { limit: 500000, rate: 6 },      // Next 500,000 at 6%
            { limit: 500000, rate: 12 },     // Next 500,000 at 12%
            { limit: 500000, rate: 18 },     // Next 500,000 at 18%
            { limit: 500000, rate: 24 },     // Next 500,000 at 24%
            { limit: 1000000, rate: 30 },    // Next 1,000,000 at 30%
            { limit: Infinity, rate: 36 }    // Remainder at 36%
        ]
    };
    
    // Use default if year not found
    const brackets = taxBrackets[year] || taxBrackets[2023];
    
    let remainingIncome = taxableIncome;
    let totalTax = 0;
    
    // Calculate tax for each bracket
    for (const bracket of brackets) {
        if (remainingIncome <= 0) break;
        
        const taxableAmount = Math.min(remainingIncome, bracket.limit);
        const taxForBracket = (taxableAmount * bracket.rate) / 100;
        
        totalTax += taxForBracket;
        remainingIncome -= taxableAmount;
    }
    
    return totalTax;
}

/**
 * Calculate tax on annual bonus
 */
function calculateBonusTax(annualSalary, bonusAmount) {
    // Calculate tax on annual salary with and without bonus
    const taxWithoutBonus = calculatePAYETax(annualSalary);
    const taxWithBonus = calculatePAYETax(annualSalary + bonusAmount);
    
    // Tax on bonus is the difference
    return taxWithBonus - taxWithoutBonus;
}