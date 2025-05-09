<?php
/**
 * Main application entry point
 * Routes requests to appropriate pages based on the 'page' parameter
 */

// Include configuration
require_once 'config/config.php';

// Get requested page or default to dashboard
$page = isset($_GET['page']) ? sanitize_input($_GET['page']) : 'dashboard';

// For non-logged in users, only allow access to login and register pages
if (!is_logged_in() && !in_array($page, ['login', 'register'])) {
    $page = 'login';
}

// Define valid pages and their required roles
$valid_pages = [
    'dashboard' => 'user',
    'employees' => 'user',
    'attendance' => 'user',
    'salary' => 'user',
    'reports' => 'user',
    'profile' => 'user',
    'settings' => 'user',
    'login' => 'guest',
    'register' => 'guest',
    'logout' => 'user',
];

// Check if page is valid
if (!array_key_exists($page, $valid_pages)) {
    $page = 'dashboard';
}

// Restrict pages based on user role
if ($valid_pages[$page] !== 'guest' && !user_has_role($valid_pages[$page])) {
    set_flash_message('error', 'You do not have permission to access that page');
    $page = 'dashboard';
}

// Start output buffering
ob_start();

// Include the page file
$page_file = 'pages/' . $page . '.php';
if (file_exists($page_file)) {
    include $page_file;
} else {
    // Page file not found
    echo '<div class="alert alert-danger">Page not found: ' . htmlspecialchars($page) . '</div>';
}

// Get content from buffer
$content = ob_get_clean();

// Special case for logout page
if ($page === 'logout') {
    logout_user();
    header('Location: index.php?page=login');
    exit;
}

// Include header
include 'includes/header.php';

// Output page content
echo $content;

// Include footer
include 'includes/footer.php';

// Close database connection
db_close();