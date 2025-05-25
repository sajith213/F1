/**
 * Fuel Ordering Module - Pricing Methods
 * 
 * This file contains JavaScript functions for handling pricing methods
 * (per liter vs invoice value) in fuel ordering forms.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize pricing methods functionality
    initializePricingMethods();
    
    // Handle form submission
    initializeFormSubmitHandler();
    
    // Modify add item button to initialize pricing methods for new items
    modifyAddItemButton();
});

/**
 * Initializes pricing methods functionality for all rows
 */
function initializePricingMethods() {
    // Setup pricing method toggle buttons
    setupPricingMethodButtons();
    
    // Setup invoice value input handlers
    setupInvoiceValueInputs();
    
    // Setup quantity input handlers for invoice pricing
    setupQuantityInputsForInvoicePricing();
}

/**
 * Sets up the pricing method toggle buttons
 */
function setupPricingMethodButtons() {
    // Setup for standard pricing method buttons
    document.querySelectorAll('.pricing-method-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const method = this.dataset.method;
            const buttonContainer = this.parentElement;
            const unitPriceInput = row.querySelector('.unit-price-input');
            const invoiceValueContainer = row.querySelector('.invoice-value-container');
            const invoiceValueInput = row.querySelector('.invoice-value-input');
            const pricingMethodInput = row.querySelector('.pricing-method');
            const quantityInput = row.querySelector('.quantity-input');
            
            // Toggle active state for buttons
            buttonContainer.querySelectorAll('.pricing-method-btn').forEach(button => {
                if (button === this) {
                    button.classList.add('bg-blue-500', 'text-white');
                    button.classList.remove('bg-gray-200');
                } else {
                    button.classList.remove('bg-blue-500', 'text-white');
                    button.classList.add('bg-gray-200');
                }
            });
            
            // Update pricing method value
            pricingMethodInput.value = method;
            
            if (method === 'invoice') {
                // Switch to invoice value pricing
                invoiceValueContainer.style.display = 'block';
                unitPriceInput.readOnly = true;
                unitPriceInput.placeholder = 'Auto-calculated';
                
                // Initialize invoice value from unit price * quantity if empty
                if (!invoiceValueInput.value && unitPriceInput.value && quantityInput.value) {
                    const quantity = parseFloat(quantityInput.value);
                    const unitPrice = parseFloat(unitPriceInput.value);
                    if (quantity > 0 && unitPrice > 0) {
                        invoiceValueInput.value = (quantity * unitPrice).toFixed(2);
                    }
                }
                
                // If we already have values, calculate unit price
                if (invoiceValueInput.value && quantityInput.value) {
                    const quantity = parseFloat(quantityInput.value);
                    const invoiceValue = parseFloat(invoiceValueInput.value);
                    if (quantity > 0 && invoiceValue > 0) {
                        const unitPrice = invoiceValue / quantity;
                        unitPriceInput.value = unitPrice.toFixed(2);
                        if (typeof calculateTotals === 'function') {
                            calculateTotals();
                        }
                    }
                }
            } else {
                // Switch to per liter pricing
                invoiceValueContainer.style.display = 'none';
                unitPriceInput.readOnly = false;
                unitPriceInput.placeholder = 'Price per liter';
            }
        });
    });
    
    // Setup for new item pricing method buttons
    document.querySelectorAll('.new-pricing-method-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const method = this.dataset.method;
            const buttonContainer = this.parentElement;
            const unitPriceInput = row.querySelector('.new-unit-price-input');
            const invoiceValueContainer = row.querySelector('.new-invoice-value-container');
            const invoiceValueInput = row.querySelector('.new-invoice-value-input');
            const pricingMethodInput = row.querySelector('.new-pricing-method');
            const quantityInput = row.querySelector('.new-quantity-input');
            
            // Toggle active state for buttons
            buttonContainer.querySelectorAll('.new-pricing-method-btn').forEach(button => {
                if (button === this) {
                    button.classList.add('bg-blue-500', 'text-white');
                    button.classList.remove('bg-gray-200');
                } else {
                    button.classList.remove('bg-blue-500', 'text-white');
                    button.classList.add('bg-gray-200');
                }
            });
            
            // Update pricing method value
            pricingMethodInput.value = method;
            
            if (method === 'invoice') {
                // Switch to invoice value pricing
                invoiceValueContainer.style.display = 'block';
                unitPriceInput.readOnly = true;
                unitPriceInput.placeholder = 'Auto-calculated';
                
                // If we already have values, calculate unit price
                if (invoiceValueInput.value && quantityInput.value) {
                    const quantity = parseFloat(quantityInput.value);
                    const invoiceValue = parseFloat(invoiceValueInput.value);
                    if (quantity > 0 && invoiceValue > 0) {
                        const unitPrice = invoiceValue / quantity;
                        unitPriceInput.value = unitPrice.toFixed(2);
                        if (typeof calculateTotals === 'function') {
                            calculateTotals();
                        }
                    }
                }
            } else {
                // Switch to per liter pricing
                invoiceValueContainer.style.display = 'none';
                unitPriceInput.readOnly = false;
                unitPriceInput.placeholder = 'Price per liter';
            }
        });
    });
}

/**
 * Sets up the invoice value input handlers
 */
function setupInvoiceValueInputs() {
    // Setup for standard invoice value inputs
    document.querySelectorAll('.invoice-value-input').forEach(input => {
        input.addEventListener('input', function() {
            const row = this.closest('tr');
            const unitPriceInput = row.querySelector('.unit-price-input');
            const quantityInput = row.querySelector('.quantity-input');
            const pricingMethodInput = row.querySelector('.pricing-method');
            
            if (pricingMethodInput.value === 'invoice') {
                const quantity = parseFloat(quantityInput.value);
                const invoiceValue = parseFloat(this.value);
                
                if (quantity > 0 && invoiceValue > 0) {
                    const unitPrice = invoiceValue / quantity;
                    unitPriceInput.value = unitPrice.toFixed(2);
                    if (typeof calculateTotals === 'function') {
                        calculateTotals();
                    }
                }
            }
        });
    });
    
    // Setup for new item invoice value inputs
    document.querySelectorAll('.new-invoice-value-input').forEach(input => {
        input.addEventListener('input', function() {
            const row = this.closest('tr');
            const unitPriceInput = row.querySelector('.new-unit-price-input');
            const quantityInput = row.querySelector('.new-quantity-input');
            const pricingMethodInput = row.querySelector('.new-pricing-method');
            
            if (pricingMethodInput.value === 'invoice') {
                const quantity = parseFloat(quantityInput.value);
                const invoiceValue = parseFloat(this.value);
                
                if (quantity > 0 && invoiceValue > 0) {
                    const unitPrice = invoiceValue / quantity;
                    unitPriceInput.value = unitPrice.toFixed(2);
                    if (typeof calculateTotals === 'function') {
                        calculateTotals();
                    }
                }
            }
        });
    });
}

/**
 * Sets up quantity input handlers for invoice pricing
 */
function setupQuantityInputsForInvoicePricing() {
    // Setup for standard quantity inputs
    document.querySelectorAll('.quantity-input').forEach(input => {
        input.addEventListener('input', function() {
            const row = this.closest('tr');
            const unitPriceInput = row.querySelector('.unit-price-input');
            const invoiceValueInput = row.querySelector('.invoice-value-input');
            const pricingMethodInput = row.querySelector('.pricing-method');
            
            if (pricingMethodInput && pricingMethodInput.value === 'invoice' && invoiceValueInput && invoiceValueInput.value) {
                const quantity = parseFloat(this.value);
                const invoiceValue = parseFloat(invoiceValueInput.value);
                
                if (quantity > 0 && invoiceValue > 0) {
                    const unitPrice = invoiceValue / quantity;
                    unitPriceInput.value = unitPrice.toFixed(2);
                    if (typeof calculateTotals === 'function') {
                        calculateTotals();
                    }
                }
            }
        });
    });
    
    // Setup for new item quantity inputs
    document.querySelectorAll('.new-quantity-input').forEach(input => {
        input.addEventListener('input', function() {
            const row = this.closest('tr');
            const unitPriceInput = row.querySelector('.new-unit-price-input');
            const invoiceValueInput = row.querySelector('.new-invoice-value-input');
            const pricingMethodInput = row.querySelector('.new-pricing-method');
            
            if (pricingMethodInput && pricingMethodInput.value === 'invoice' && invoiceValueInput && invoiceValueInput.value) {
                const quantity = parseFloat(this.value);
                const invoiceValue = parseFloat(invoiceValueInput.value);
                
                if (quantity > 0 && invoiceValue > 0) {
                    const unitPrice = invoiceValue / quantity;
                    unitPriceInput.value = unitPrice.toFixed(2);
                    if (typeof calculateTotals === 'function') {
                        calculateTotals();
                    }
                }
            }
        });
    });
}

/**
 * Initializes form submit handler to process pricing methods before submission
 */
function initializeFormSubmitHandler() {
    // Handle create order form
    const createOrderForm = document.getElementById("create-order-form");
    if (createOrderForm) {
        createOrderForm.addEventListener("submit", function() {
            // Process any remaining invoice calculations
            processInvoiceCalculations();
        });
    }
    
    // Handle update order form
    const updateOrderForm = document.getElementById("update-order-form");
    if (updateOrderForm) {
        updateOrderForm.addEventListener("submit", function() {
            // Process any remaining invoice calculations
            processInvoiceCalculations();
            
            // Process any remaining invoice calculations for new items
            processNewItemInvoiceCalculations();
        });
    }
}

/**
 * Process invoice calculations for existing items
 */
function processInvoiceCalculations() {
    document.querySelectorAll('.pricing-method').forEach(input => {
        if (input.value === 'invoice') {
            const row = input.closest('tr');
            const unitPriceInput = row.querySelector('.unit-price-input');
            const invoiceValueInput = row.querySelector('.invoice-value-input');
            const quantityInput = row.querySelector('.quantity-input');
            
            if (unitPriceInput && invoiceValueInput && quantityInput) {
                const quantity = parseFloat(quantityInput.value);
                const invoiceValue = parseFloat(invoiceValueInput.value);
                
                if (quantity > 0 && invoiceValue > 0) {
                    const unitPrice = invoiceValue / quantity;
                    unitPriceInput.value = unitPrice.toFixed(2);
                }
            }
        }
    });
}

/**
 * Process invoice calculations for new items
 */
function processNewItemInvoiceCalculations() {
    document.querySelectorAll('.new-pricing-method').forEach(input => {
        if (input.value === 'invoice') {
            const row = input.closest('tr');
            const unitPriceInput = row.querySelector('.new-unit-price-input');
            const invoiceValueInput = row.querySelector('.new-invoice-value-input');
            const quantityInput = row.querySelector('.new-quantity-input');
            
            if (unitPriceInput && invoiceValueInput && quantityInput) {
                const quantity = parseFloat(quantityInput.value);
                const invoiceValue = parseFloat(invoiceValueInput.value);
                
                if (quantity > 0 && invoiceValue > 0) {
                    const unitPrice = invoiceValue / quantity;
                    unitPriceInput.value = unitPrice.toFixed(2);
                }
            }
        }
    });
}

/**
 * Modifies the add item button to initialize pricing methods for new items
 */
function modifyAddItemButton() {
    // Modify add item button for create order page
    const addItemBtn = document.getElementById("add-item-btn");
    if (addItemBtn) {
        const originalClickHandler = addItemBtn.onclick;
        addItemBtn.onclick = function() {
            if (originalClickHandler) {
                originalClickHandler.call(this);
            }
            
            // Initialize pricing methods for the new item
            setTimeout(function() {
                initializePricingMethods();
            }, 50);
        };
    }
    
    // Modify add new item button for update order page
    const addNewItemBtn = document.getElementById("add-new-item-btn");
    if (addNewItemBtn) {
        const originalClickHandler = addNewItemBtn.onclick;
        addNewItemBtn.onclick = function() {
            if (originalClickHandler) {
                originalClickHandler.call(this);
            }
            
            // Initialize pricing methods for the new item
            setTimeout(function() {
                initializePricingMethods();
            }, 50);
        };
    }
}