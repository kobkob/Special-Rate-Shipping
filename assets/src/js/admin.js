/**
 * Admin JavaScript for Special Rate Shipping plugin
 * 
 * @package KobKob\SpecialRateShipping
 * @since 2.0.0
 */

(function($) {
    'use strict';

    /**
     * Admin functionality
     */
    const SpecialRateShippingAdmin = {
        
        /**
         * Initialize admin features
         */
        init() {
            this.bindEvents();
            this.initColorPickers();
            this.initTooltips();
            this.validateForms();
        },

        /**
         * Bind event handlers
         */
        bindEvents() {
            // Test shipping calculation
            $(document).on('click', '.test-shipping-calculation', this.testShippingCalculation.bind(this));
            
            // Settings form submission
            $(document).on('submit', '.special-rate-shipping-settings form', this.handleSettingsSubmit.bind(this));
            
            // Dynamic package configuration
            $(document).on('change', '.package-enabled-checkbox', this.togglePackageSettings.bind(this));
            
            // Real-time validation
            $(document).on('input', 'input[type="number"]', this.validateNumberInput.bind(this));
            $(document).on('input', 'input[data-type="price"]', this.validatePriceInput.bind(this));
        },

        /**
         * Initialize color pickers
         */
        initColorPickers() {
            if ($.fn.wpColorPicker) {
                $('.color-picker').wpColorPicker();
            }
        },

        /**
         * Initialize tooltips
         */
        initTooltips() {
            $('.help-tip').tooltip({
                content: function() {
                    return $(this).data('tip');
                },
                tooltipClass: 'special-rate-shipping-tooltip',
                position: {
                    my: 'center bottom-20',
                    at: 'center top'
                },
                hide: {
                    duration: 200
                },
                show: {
                    duration: 200
                }
            });
        },

        /**
         * Validate forms
         */
        validateForms() {
            $('.special-rate-shipping-settings form').on('submit', function(e) {
                const requiredFields = $(this).find('input[required], select[required]');
                let isValid = true;

                requiredFields.each(function() {
                    const $field = $(this);
                    const value = $field.val().trim();
                    
                    if (!value) {
                        isValid = false;
                        $field.addClass('error').focus();
                        SpecialRateShippingAdmin.showNotice(
                            'Please fill in all required fields.',
                            'error'
                        );
                        return false;
                    } else {
                        $field.removeClass('error');
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    return false;
                }
            });
        },

        /**
         * Test shipping calculation
         */
        testShippingCalculation(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const originalText = $button.text();
            
            // Show loading state
            $button.prop('disabled', true)
                  .text('Testing...')
                  .append('<span class="special-rate-shipping-loading"></span>');

            // Make AJAX request
            $.ajax({
                url: specialRateShipping.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'special_rate_shipping_test',
                    nonce: specialRateShipping.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message, 'success');
                        console.log('Shipping test result:', response.data);
                    } else {
                        this.showNotice(response.data.message, 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showNotice('Test failed: ' + error, 'error');
                },
                complete: () => {
                    // Restore button state
                    $button.prop('disabled', false)
                          .text(originalText)
                          .find('.special-rate-shipping-loading').remove();
                }
            });
        },

        /**
         * Handle settings form submission
         */
        handleSettingsSubmit(e) {
            const $form = $(e.target);
            const $submitButton = $form.find('input[type="submit"]');
            
            // Show loading state
            $submitButton.prop('disabled', true).val('Saving...');
            
            // Let the form submit naturally, but provide feedback
            setTimeout(() => {
                this.showNotice('Settings are being saved...', 'info');
            }, 100);
        },

        /**
         * Toggle package settings visibility
         */
        togglePackageSettings(e) {
            const $checkbox = $(e.target);
            const $settings = $checkbox.closest('tr').next('.package-settings-row');
            
            if ($checkbox.is(':checked')) {
                $settings.show().removeClass('hidden');
            } else {
                $settings.hide().addClass('hidden');
            }
        },

        /**
         * Validate number input
         */
        validateNumberInput(e) {
            const $input = $(e.target);
            const value = parseFloat($input.val());
            const min = parseFloat($input.attr('min')) || 0;
            const max = parseFloat($input.attr('max')) || Infinity;

            $input.removeClass('error');

            if (isNaN(value) || value < min || value > max) {
                $input.addClass('error');
            }
        },

        /**
         * Validate price input
         */
        validatePriceInput(e) {
            const $input = $(e.target);
            let value = $input.val();

            // Remove non-numeric characters except decimal point
            value = value.replace(/[^0-9.]/g, '');
            
            // Ensure only one decimal point
            const parts = value.split('.');
            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }
            
            // Limit to 2 decimal places
            if (parts[1] && parts[1].length > 2) {
                value = parseFloat(value).toFixed(2);
            }

            $input.val(value);
        },

        /**
         * Show admin notice
         */
        showNotice(message, type = 'info', timeout = 5000) {
            const $notice = $(`
                <div class="notice notice-${type} special-rate-shipping-notice is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);

            // Insert notice
            if ($('.wrap h1').length) {
                $('.wrap h1').after($notice);
            } else {
                $('.wrap').prepend($notice);
            }

            // Make notice dismissible
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });

            // Auto-dismiss after timeout
            if (timeout > 0) {
                setTimeout(() => {
                    $notice.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, timeout);
            }
        },

        /**
         * Show loading overlay
         */
        showLoading(target = 'body') {
            const $overlay = $('<div class="special-rate-shipping-overlay"><div class="special-rate-shipping-loading large"></div></div>');
            $(target).append($overlay).addClass('loading');
        },

        /**
         * Hide loading overlay
         */
        hideLoading(target = 'body') {
            $(target).find('.special-rate-shipping-overlay').remove();
            $(target).removeClass('loading');
        },

        /**
         * Format currency
         */
        formatCurrency(amount, currencySymbol = '$') {
            const formatted = parseFloat(amount).toFixed(2);
            return currencySymbol + formatted;
        },

        /**
         * Debug log (only if WP_DEBUG is enabled)
         */
        debug(...args) {
            if (window.console && specialRateShipping.debug) {
                console.log('[Special Rate Shipping]', ...args);
            }
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(() => {
        SpecialRateShippingAdmin.init();
        SpecialRateShippingAdmin.debug('Admin scripts loaded');
    });

    // Expose to global scope for external access
    window.SpecialRateShippingAdmin = SpecialRateShippingAdmin;

})(jQuery);