<?php
/**
 * Login page
 */

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($username)) {
        $errors['username'] = 'Username is required';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    }
    
    // If no errors, attempt login
    if (empty($errors)) {
        if (login_user($username, $password)) {
            // Set remember me cookie if checked
            if ($remember) {
                setcookie('remember_user', base64_encode($username), time() + (86400 * 30), '/');
            }
            
            // Redirect to dashboard
            header('Location: index.php?page=dashboard');
            exit;
        } else {
            $login_error = 'Invalid username or password';
        }
    }
}

// Check for remember me cookie
$remembered_username = '';
if (isset($_COOKIE['remember_user'])) {
    $remembered_username = sanitize_input(base64_decode($_COOKIE['remember_user']));
}
?>

<div class="auth-container">
    <div class="card auth-card">
        <div class="row g-0">
            <!-- Left sidebar with graphics -->
            <div class="col-lg-5 auth-sidebar d-none d-lg-block">
                <h2 class="mb-4">Welcome to <?php echo APP_NAME; ?></h2>
                <p class="lead">The complete solution for employee attendance tracking and salary management.</p>
                <div class="mt-5">
                    <div class="mb-4">
                        <i class="fas fa-check-circle me-2"></i> One-click attendance marking
                    </div>
                    <div class="mb-4">
                        <i class="fas fa-check-circle me-2"></i> Automated salary calculations
                    </div>
                    <div class="mb-4">
                        <i class="fas fa-check-circle me-2"></i> Detailed reporting features
                    </div>
                    <div class="mb-4">
                        <i class="fas fa-check-circle me-2"></i> Secure user authentication
                    </div>
                </div>
                <div class="mt-5 pt-5 text-center">
                    <i class="fas fa-clipboard-check fa-4x opacity-75"></i>
                </div>
            </div>
            
            <!-- Login form -->
            <div class="col-lg-7 auth-content">
                <div class="text-center mb-4">
                    <h3 class="fw-bold">Sign In</h3>
                    <p class="text-muted">Enter your credentials to access your account</p>
                </div>
                
                <?php if (isset($login_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $login_error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <form method="post" action="index.php?page=login" id="loginForm">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                               id="username" name="username" placeholder="Username" 
                               value="<?php echo $remembered_username ?: ($username ?? ''); ?>" 
                               data-tippy-content="Enter your username">
                        <label for="username">Username</label>
                        <?php if (isset($errors['username'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['username']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                               id="password" name="password" placeholder="Password"
                               data-tippy-content="Enter your password">
                        <label for="password">Password</label>
                        <?php if (isset($errors['password'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember"
                               data-tippy-content="Check this to stay signed in on this device">
                        <label class="form-check-label" for="remember">
                            Remember me
                        </label>
                    </div>
                    
                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-primary btn-lg" 
                                data-tippy-content="Click to sign in">
                            <i class="fas fa-sign-in-alt me-2"></i> Sign In
                        </button>
                    </div>
                    
                    <div class="text-center mb-3">
                        <a href="#" class="text-decoration-none" 
                           data-tippy-content="Click if you forgot your password">
                            Forgot password?
                        </a>
                    </div>
                    
                    <div class="text-center">
                        <p class="mb-0">
                            Don't have an account? 
                            <a href="index.php?page=register" class="text-decoration-none fw-bold"
                               data-tippy-content="Click to create a new account">
                                Register
                            </a>
                        </p>
                    </div>
                </form>
                
                <div class="text-center mt-5">
                    <p class="small text-muted">
                        Example: Use <strong>admin</strong> / <strong>password</strong> for demo
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Add page-specific JavaScript
$page_script = <<<SCRIPT
<script>
// Initialize form validation on login form
$(document).ready(function() {
    // Form validation rules
    const loginRules = {
        username: {
            required: 'Username is required'
        },
        password: {
            required: 'Password is required'
        }
    };
    
    // Form submission handling
    $('#loginForm').on('submit', function(e) {
        if (!validateForm('loginForm', loginRules)) {
            e.preventDefault();
        }
    });
});
</script>
SCRIPT;