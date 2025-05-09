<?php
/**
 * Authentication functions
 * Contains user authentication and authorization functions
 */

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Verify login credentials and start session
 * 
 * @param string $username Username
 * @param string $password Plain text password
 * @return bool True if login successful, false otherwise
 */
function login_user($username, $password) {
    // Get user by username
    $user = get_user_by_username($username);
    
    if (!$user) {
        // User not found
        return false;
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        // Password incorrect
        log_failed_login_attempt($username);
        return false;
    }
    
    // Start session and store user info
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    
    // Update last login time
    update_last_login($user['id']);
    
    // Log successful login
    log_activity('login', 'User logged in successfully', $user['id']);
    
    return true;
}

/**
 * Log out current user
 * 
 * @return bool True if logout successful, false otherwise
 */
function logout_user() {
    // Log the logout activity if user is logged in
    if (is_logged_in()) {
        log_activity('logout', 'User logged out', $_SESSION['user_id']);
    }
    
    // Unset all session variables
    $_SESSION = [];
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    return true;
}

/**
 * Register a new user
 * 
 * @param string $username Username
 * @param string $password Plain text password
 * @param string $name Full name
 * @param string $email Email address
 * @param string $role Role (admin, manager, user)
 * @return int|false User ID if registration successful, false otherwise
 */
function register_user($username, $password, $name, $email, $role = 'user') {
    // Check if username or email already exists
    if (get_user_by_username($username) || get_user_by_email($email)) {
        return false;
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user into database
    $query = "INSERT INTO users (username, password, name, email, role) VALUES (?, ?, ?, ?, ?)";
    $user_id = db_insert($query, "sssss", [$username, $hashed_password, $name, $email, $role], ['users']);
    
    if (!$user_id) {
        return false;
    }
    
    // Log user registration
    log_activity('register', 'New user registered', $user_id);
    
    return $user_id;
}

/**
 * Change user password
 * 
 * @param int $user_id User ID
 * @param string $current_password Current password
 * @param string $new_password New password
 * @return bool True if password change successful, false otherwise
 */
function change_password($user_id, $current_password, $new_password) {
    // Get user
    $user = get_user($user_id);
    
    if (!$user) {
        return false;
    }
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        return false;
    }
    
    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password in database
    $query = "UPDATE users SET password = ? WHERE id = ?";
    $result = db_update($query, "si", [$hashed_password, $user_id], ['user_' . $user_id]);
    
    if (!$result) {
        return false;
    }
    
    // Log password change
    log_activity('change_password', 'User changed password', $user_id);
    
    return true;
}

/**
 * Reset user password and generate a new random one
 * 
 * @param string $email User email
 * @return string|false New password if reset successful, false otherwise
 */
function reset_password($email) {
    // Get user by email
    $user = get_user_by_email($email);
    
    if (!$user) {
        return false;
    }
    
    // Generate new random password
    $new_password = generate_random_password();
    
    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password in database
    $query = "UPDATE users SET password = ? WHERE id = ?";
    $result = db_update($query, "si", [$hashed_password, $user['id']], ['user_' . $user['id']]);
    
    if (!$result) {
        return false;
    }
    
    // Log password reset
    log_activity('reset_password', 'User password was reset', $user['id']);
    
    return $new_password;
}

/**
 * Check if user has a specific role
 * 
 * @param string $role Role to check (admin, manager, user)
 * @return bool True if user has role, false otherwise
 */
function user_has_role($role) {
    if (!is_logged_in()) {
        return false;
    }
    
    // Admin has all roles
    if ($_SESSION['role'] === 'admin') {
        return true;
    }
    
    // Manager has manager and user roles
    if ($_SESSION['role'] === 'manager' && ($role === 'manager' || $role === 'user')) {
        return true;
    }
    
    // User only has user role
    if ($_SESSION['role'] === 'user' && $role === 'user') {
        return true;
    }
    
    return false;
}

/**
 * Redirect if user is not logged in
 * 
 * @param string $redirect_url URL to redirect to if not logged in
 */
function require_login($redirect_url = 'index.php?page=login') {
    if (!is_logged_in()) {
        header('Location: ' . $redirect_url);
        exit;
    }
}

/**
 * Redirect if user doesn't have required role
 * 
 * @param string $role Required role
 * @param string $redirect_url URL to redirect to if not authorized
 */
function require_role($role, $redirect_url = 'index.php?page=dashboard') {
    require_login();
    
    if (!user_has_role($role)) {
        set_flash_message('error', 'You do not have permission to access that page');
        header('Location: ' . $redirect_url);
        exit;
    }
}

/**
 * Get user by ID
 * 
 * @param int $user_id User ID
 * @return array|null User data or null if not found
 */
function get_user($user_id) {
    $query = "SELECT * FROM users WHERE id = ?";
    $cache_key = 'user_' . $user_id;
    
    return db_fetch_row($query, "i", [$user_id], $cache_key);
}

/**
 * Get user by username
 * 
 * @param string $username Username
 * @return array|null User data or null if not found
 */
function get_user_by_username($username) {
    $query = "SELECT * FROM users WHERE username = ?";
    
    return db_fetch_row($query, "s", [$username]);
}

/**
 * Get user by email
 * 
 * @param string $email Email address
 * @return array|null User data or null if not found
 */
function get_user_by_email($email) {
    $query = "SELECT * FROM users WHERE email = ?";
    
    return db_fetch_row($query, "s", [$email]);
}

/**
 * Update user's last login time
 * 
 * @param int $user_id User ID
 * @return bool True if update successful, false otherwise
 */
function update_last_login($user_id) {
    $query = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
    
    return db_update($query, "i", [$user_id], ['user_' . $user_id]);
}

/**
 * Log failed login attempt
 * 
 * @param string $username Username used in attempt
 */
function log_failed_login_attempt($username) {
    log_activity('login_failed', 'Failed login attempt for username: ' . $username);
}

/**
 * Log user activity
 * 
 * @param string $action Action performed
 * @param string $description Activity description
 * @param int|null $user_id User ID (if known)
 * @param int|null $employee_id Related employee ID (if applicable)
 * @return int|false Activity ID if logging successful, false otherwise
 */
function log_activity($action, $description, $user_id = null, $employee_id = null) {
    $query = "INSERT INTO activities (user_id, employee_id, action, description) VALUES (?, ?, ?, ?)";
    
    return db_insert($query, "iiss", [$user_id, $employee_id, $action, $description], ['activities']);
}