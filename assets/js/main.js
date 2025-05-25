/**
 * Main JavaScript File
 * 
 * Contains common functions for the Petrol Pump Management System
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all dropdown menus
    initializeDropdowns();
    
    // Initialize sidebar toggle
    initializeSidebar();
    
    // Initialize notifications
    initializeNotifications();
    
    // Set up any form validations
    initializeFormValidations();
    
    // Enable all tooltips
    initializeTooltips();
});

/**
 * Initializes dropdown menus throughout the application
 */
function initializeDropdowns() {
    const dropdownButtons = document.querySelectorAll('.dropdown-toggle');
    
    dropdownButtons.forEach(button => {
        const target = document.getElementById(button.dataset.target);
        
        if (button && target) {
            // Toggle dropdown when button is clicked
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                target.classList.toggle('hidden');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!button.contains(e.target) && !target.contains(e.target)) {
                    target.classList.add('hidden');
                }
            });
        }
    });
    
    // User dropdown (special case)
    const userButton = document.querySelector('[x-data="{ open: false }"] button');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userButton && userDropdown) {
        userButton.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('hidden');
        });
        
        document.addEventListener('click', function(e) {
            if (!userButton.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.add('hidden');
            }
        });
    }
}

/**
 * Initializes sidebar toggle functionality for mobile
 */
function initializeSidebar() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (sidebarToggle && sidebar) {
        // Toggle sidebar
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('-translate-x-full');
            
            // Create overlay if it doesn't exist
            if (!overlay && !sidebar.classList.contains('-translate-x-full')) {
                const newOverlay = document.createElement('div');
                newOverlay.id = 'sidebar-overlay';
                newOverlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-20 lg:hidden';
                document.body.appendChild(newOverlay);
                
                // Close sidebar when overlay is clicked
                newOverlay.addEventListener('click', function() {
                    sidebar.classList.add('-translate-x-full');
                    newOverlay.remove();
                });
            } else if (overlay) {
                // Remove overlay if sidebar is closed
                if (sidebar.classList.contains('-translate-x-full')) {
                    overlay.remove();
                }
            }
        });
    }
}

/**
 * Initializes notification functionality
 */
function initializeNotifications() {
    const notificationButton = document.querySelector('.notifications-button');
    const notificationPanel = document.querySelector('.notifications-panel');
    
    if (notificationButton && notificationPanel) {
        notificationButton.addEventListener('click', function() {
            notificationPanel.classList.toggle('hidden');
        });
        
        document.addEventListener('click', function(e) {
            if (!notificationButton.contains(e.target) && !notificationPanel.contains(e.target)) {
                notificationPanel.classList.add('hidden');
            }
        });
    }
}

/**
 * Initializes form validations
 */
function initializeFormValidations() {
    const forms = document.querySelectorAll('form[data-validate="true"]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                // Remove any existing error messages
                const existingError = field.parentNode.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }
                
                // Reset field styling
                field.classList.remove('border-red-500');
                
                // Check if field is empty
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('border-red-500');
                    
                    // Add error message
                    const errorMessage = document.createElement('p');
                    errorMessage.className = 'text-red-500 text-xs mt-1 error-message';
                    errorMessage.textContent = 'This field is required';
                    field.parentNode.appendChild(errorMessage);
                }
            });
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Initializes tooltips throughout the application
 */
function initializeTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    
    tooltips.forEach(tooltip => {
        tooltip.addEventListener('mouseenter', function() {
            const message = this.dataset.tooltip;
            
            // Create tooltip element
            const tooltipElement = document.createElement('div');
            tooltipElement.className = 'absolute z-50 bg-gray-800 text-white text-xs rounded py-1 px-2 -mt-8 -ml-2';
            tooltipElement.textContent = message;
            tooltipElement.style.top = '-25px';
            
            // Position it
            this.style.position = 'relative';
            this.appendChild(tooltipElement);
        });
        
        tooltip.addEventListener('mouseleave', function() {
            const tooltipElement = this.querySelector('div');
            if (tooltipElement) {
                tooltipElement.remove();
            }
        });
    });
}

/**
 * Shows a notification message
 * @param {string} message - The message to display
 * @param {string} type - The type of notification (success, error, warning, info)
 * @param {number} duration - How long to show the notification in ms
 */
function showNotification(message, type = 'info', duration = 3000) {
    // Create notification container if it doesn't exist
    let container = document.getElementById('notification-container');
    
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.className = 'fixed top-4 right-4 z-50 flex flex-col items-end space-y-2';
        document.body.appendChild(container);
    }
    
    // Create notification element
    const notification = document.createElement('div');
    
    // Set class based on type
    let bgColor = 'bg-blue-500';
    let icon = 'fas fa-info-circle';
    
    if (type === 'success') {
        bgColor = 'bg-green-500';
        icon = 'fas fa-check-circle';
    } else if (type === 'error') {
        bgColor = 'bg-red-500';
        icon = 'fas fa-exclamation-circle';
    } else if (type === 'warning') {
        bgColor = 'bg-yellow-500';
        icon = 'fas fa-exclamation-triangle';
    }
    
    notification.className = `${bgColor} text-white rounded-lg shadow-lg p-4 mb-2 flex items-center transition-all transform translate-x-0 duration-300 ease-in-out`;
    notification.innerHTML = `
        <i class="${icon} mr-2"></i>
        <span>${message}</span>
        <button class="ml-4 text-white focus:outline-none">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add to container
    container.appendChild(notification);
    
    // Set up auto-remove
    setTimeout(() => {
        notification.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, duration);
    
    // Set up manual remove
    const closeButton = notification.querySelector('button');
    closeButton.addEventListener('click', () => {
        notification.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => {
            notification.remove();
        }, 300);
    });
}

/**
 * Format number as currency
 * @param {number} amount - The amount to format
 * @param {string} currency - The currency symbol
 * @returns {string} Formatted currency string
 */
function formatCurrency(amount, currency = '$') {
    return currency + ' ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Format date from database (YYYY-MM-DD) to localized format
 * @param {string} dateString - The date string to format
 * @param {string} format - The output format
 * @returns {string} Formatted date string
 */
function formatDate(dateString, format = 'long') {
    const date = new Date(dateString);
    
    if (isNaN(date)) {
        return dateString;
    }
    
    if (format === 'short') {
        return date.toLocaleDateString();
    } else if (format === 'long') {
        return date.toLocaleDateString(undefined, { 
            weekday: 'long',
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    } else if (format === 'time') {
        return date.toLocaleTimeString(undefined, {
            hour: '2-digit',
            minute: '2-digit'
        });
    } else if (format === 'datetime') {
        return date.toLocaleDateString(undefined, { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        }) + ' ' + date.toLocaleTimeString(undefined, {
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    return dateString;
}

/**
 * Confirm action before proceeding
 * @param {string} message - The confirmation message
 * @param {function} callback - Function to execute if confirmed
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Fetch data from API
 * @param {string} url - The API endpoint
 * @param {Object} options - Fetch options
 * @returns {Promise} Promise with JSON response
 */
async function fetchData(url, options = {}) {
    try {
        const response = await fetch(url, options);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error('Fetch error:', error);
        showNotification('Error fetching data: ' + error.message, 'error');
        throw error;
    }
}