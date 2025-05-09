<?php if (is_logged_in() && !in_array($page, ['login', 'register'])): ?>
        </div> <!-- End main-content -->
    </div> <!-- End row -->
</div> <!-- End container-fluid -->
<?php else: ?>
</div> <!-- End container for login/register -->
<?php endif; ?>

<footer class="footer mt-auto py-3 bg-light">
    <div class="container text-center">
        <span class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</span>
    </div>
</footer>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Toastr notifications -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<!-- Tippy.js for tooltips -->
<script src="https://unpkg.com/popper.js@1/dist/umd/popper.min.js"></script>
<script src="https://unpkg.com/tippy.js@6/dist/tippy-bundle.umd.js"></script>

<!-- Chart.js for charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Common JavaScript code -->
<script>
    // Initialize tooltips
    document.addEventListener("DOMContentLoaded", function() {
        // Initialize all tooltips
        tippy('[data-tippy-content]', {
            arrow: true,
            animation: 'fade'
        });
        
        // Configure Toastr options
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };
    });
    
    // AJAX request helper function
    function ajaxRequest(url, method, data, successCallback, errorCallback) {
        $.ajax({
            url: url,
            type: method,
            data: data,
            dataType: 'json',
            success: function(response) {
                if (typeof successCallback === 'function') {
                    successCallback(response);
                }
            },
            error: function(xhr, status, error) {
                if (typeof errorCallback === 'function') {
                    errorCallback(xhr, status, error);
                } else {
                    toastr.error('An error occurred: ' + error);
                }
            }
        });
    }
    
    // Print function
    function printElement(elementId) {
        var printContents = document.getElementById(elementId).innerHTML;
        var originalContents = document.body.innerHTML;
        
        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;
        
        // Reinitialize tooltips after reprinting
        tippy('[data-tippy-content]', {
            arrow: true,
            animation: 'fade'
        });
    }
    
    // Form validation helper
    function validateForm(formId, rules) {
        let isValid = true;
        
        for (const field in rules) {
            const value = $(`#${formId} [name="${field}"]`).val();
            const fieldRules = rules[field];
            
            // Reset previous error
            $(`#${formId} [name="${field}"]`).removeClass('is-invalid');
            $(`#${field}-error`).html('');
            
            // Check required
            if (fieldRules.required && (!value || value.trim() === '')) {
                $(`#${formId} [name="${field}"]`).addClass('is-invalid');
                $(`#${field}-error`).html(fieldRules.required);
                isValid = false;
                continue;
            }
            
            // Check pattern if value exists
            if (value && fieldRules.pattern && !fieldRules.pattern.test(value)) {
                $(`#${formId} [name="${field}"]`).addClass('is-invalid');
                $(`#${field}-error`).html(fieldRules.message);
                isValid = false;
            }
            
            // Check min length if value exists
            if (value && fieldRules.minLength && value.length < fieldRules.minLength) {
                $(`#${formId} [name="${field}"]`).addClass('is-invalid');
                $(`#${field}-error`).html(`Must be at least ${fieldRules.minLength} characters`);
                isValid = false;
            }
            
            // Check custom validation if provided
            if (value && fieldRules.validate && typeof fieldRules.validate === 'function') {
                const customValid = fieldRules.validate(value);
                if (!customValid) {
                    $(`#${formId} [name="${field}"]`).addClass('is-invalid');
                    $(`#${field}-error`).html(fieldRules.message);
                    isValid = false;
                }
            }
        }
        
        return isValid;
    }
</script>

<!-- Page-specific JavaScript -->
<?php if (isset($page_script)) echo $page_script; ?>

</body>
</html>