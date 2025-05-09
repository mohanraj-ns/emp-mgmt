<?php
/**
 * Registration page
 */

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    
    // Validate inputs
    $errors = [];
    
    if (empty($username)) {
        $errors['username'] = 'Username is required';
    } elseif (!validate_string($username)) {
        $errors['username'] = 'Username can only contain alphanumeric characters, hyphens, dots, and underscores';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters long';
    }
    
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    if (empty($name)) {
        $errors['name'] = 'Full name is required';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!validate_email($email)) {
        $errors['email'] = 'Invalid email format';
    }
    
    // If no errors, attempt registration
    if (empty($errors)) {
        $user_id = register_user($username, $password, $name, $email);
        
        if ($user_id) {
            // Set success message and redirect to login
            set_flash_message('success', 'Registration successful! You can now log in.');
            header('Location: index.php?page=login');
            exit;
        } else {
            $register_error = 'Registration failed. Username or email may already exist.';
        }
    }
}
?>

<div class="auth-container">
    <div class="card auth-card">
        <div class="row g-0">
            <!-- Left sidebar with graphics -->
            <div class="col-lg-5 auth-sidebar d-none d-lg-block">
                <h2 class="mb-4">Join <?php echo APP_NAME; ?></h2>
                <p class="lead">Create an account to start managing employee attendance and salary efficiently.</p>
                <div class="mt-5">
                    <div class="mb-4">
                        <i class="fas fa-check-circle me-2"></i> Fast and intuitive interface
                    </div>
                    <div class="mb-4">
                        <i class="fas fa-check-circle me-2"></i> Detailed attendance tracking
                    </div>
                    <div class="mb-4">
                        <i class="fas fa-check-circle me-2"></i> Automated salary calculations
                    </div>
                    <div class="mb-4">
                        <i class="fas fa-check-circle me-2"></i> Comprehensive reporting
                    </div>
                </div>
                <div class="mt-5 pt-5 text-center">
                    <i class="fas fa-user-plus fa-4x opacity-75"></i>
                </div>
            </div>
            
            <!-- Registration form -->
            <div class="col-lg-7 auth-content">
                <div class="text-center mb-4">
                    <h3 class="fw-bold">Create Account</h3>
                    <p class="text-muted">Fill in the details below to register</p>
                </div>
                
                <?php if (isset($register_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $register_error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <form method="post" action="index.php?page=register" id="registerForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                       id="username" name="username" placeholder="Username" 
                                       value="<?php echo $username ?? ''; ?>" 
                                       data-tippy-content="Choose a unique username">
                                <label for="username">Username</label>
                                <?php if (isset($errors['username'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['username']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                       id="name" name="name" placeholder="Full Name" 
                                       value="<?php echo $name ?? ''; ?>" 
                                       data-tippy-content="Enter your full name">
                                <label for="name">Full Name</label>
                                <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['name']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                               id="email" name="email" placeholder="Email Address" 
                               value="<?php echo $email ?? ''; ?>" 
                               data-tippy-content="Enter a valid email address">
                        <label for="email">Email Address</label>
                        <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                       id="password" name="password" placeholder="Password"
                                       data-tippy-content="Minimum 6 characters">
                                <label for="password">Password</label>
                                <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                       id="confirm_password" name="confirm_password" placeholder="Confirm Password"
                                       data-tippy-content="Repeat your password">
                                <label for="confirm_password">Confirm Password</label>
                                <?php if (isset($errors['confirm_password'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-primary btn-lg" 
                                data-tippy-content="Click to create your account">
                            <i class="fas fa-user-plus me-2"></i> Register
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <p class="mb-0">
                            Already have an account? 
                            <a href="index.php?page=login" class="text-decoration-none fw-bold"
                               data-tippy-content="Click to go to the login page">
                                Sign In
                            </a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Add page-specific JavaScript
$page_script = <<<SCRIPT
<script>
// Initialize form validation on registration form
$(document).ready(function() {
    // Form validation rules
    const registerRules = {
        username: {
            required: 'Username is required',
            pattern: /^[a-zA-Z0-9\-_.]+$/,
            message: 'Username can only contain alphanumeric characters, hyphens, dots, and underscores'
        },
        name: {
            required: 'Full name is required'
        },
        email: {
            required: 'Email is required',
            pattern: /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/,
            message: 'Please enter a valid email address'
        },
        password: {
            required: 'Password is required',
            minLength: 6,
            message: 'Password must be at least 6 characters long'
        },
        confirm_password: {
            required: 'Please confirm your password',
            validate: function(value) {
                return value === $('#password').val();
            },
            message: 'Passwords do not match'
        }
    };
    
    // Form submission handling
    $('#registerForm').on('submit', function(e) {
        if (!validateForm('registerForm', registerRules)) {
            e.preventDefault();
        }
    });
});
</script>
SCRIPT;