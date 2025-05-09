<?php
/**
 * General helper functions
 * Contains utility functions used throughout the application
 */

/**
 * Sanitize input data to prevent XSS
 * 
 * @param string $data Input data
 * @return string Sanitized data
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Format date according to specified format
 * 
 * @param string $date Date string
 * @param string $format Output format (default: Y-m-d)
 * @return string Formatted date
 */
function format_date($date, $format = DATE_FORMAT) {
    $datetime = new DateTime($date);
    return $datetime->format($format);
}

/**
 * Format time according to specified format
 * 
 * @param string $time Time string
 * @param string $format Output format (default: H:i)
 * @return string Formatted time
 */
function format_time($time, $format = TIME_FORMAT) {
    // Handle special case for NULL or empty time
    if (empty($time)) {
        return "—";
    }
    
    $datetime = new DateTime($time);
    return $datetime->format($format);
}

/**
 * Format currency amount
 * 
 * @param float $amount Amount to format
 * @param int $decimals Number of decimal places
 * @return string Formatted currency
 */
function format_currency($amount, $decimals = 2) {
    return CURRENCY_SYMBOL . number_format($amount, $decimals);
}

/**
 * Calculate the difference between two times in hours
 * 
 * @param string $time1 Start time (format: HH:MM:SS)
 * @param string $time2 End time (format: HH:MM:SS)
 * @return float|null Hours difference or null if invalid
 */
function calculate_hours_difference($time1, $time2) {
    // Handle invalid inputs
    if (empty($time1) || empty($time2)) {
        return null;
    }
    
    try {
        $datetime1 = new DateTime($time1);
        $datetime2 = new DateTime($time2);
        
        $interval = $datetime1->diff($datetime2);
        
        // Calculate total hours
        $hours = $interval->h + ($interval->i / 60) + ($interval->s / 3600);
        
        // If it's a new day, add 24 hours
        if ($interval->d > 0) {
            $hours += (24 * $interval->d);
        }
        
        return round($hours, 2);
    } catch (Exception $e) {
        error_log("Error calculating time difference: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate overtime hours
 * 
 * @param float $worked_hours Total worked hours
 * @param float $standard_hours Standard working hours
 * @return float Overtime hours (0 if no overtime)
 */
function calculate_overtime($worked_hours, $standard_hours = DEFAULT_WORKING_HOURS) {
    if ($worked_hours <= $standard_hours) {
        return 0;
    }
    
    return round($worked_hours - $standard_hours, 2);
}

/**
 * Generate a random password
 * 
 * @param int $length Password length
 * @return string Random password
 */
function generate_random_password($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
    $password = '';
    
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    
    return $password;
}

/**
 * Check if string contains only allowed characters
 * 
 * @param string $string String to check
 * @param string $pattern Regex pattern to match
 * @return bool True if string matches pattern
 */
function validate_string($string, $pattern = '/^[a-zA-Z0-9\s\-_.@]+$/') {
    return preg_match($pattern, $string) === 1;
}

/**
 * Validate email address
 * 
 * @param string $email Email to validate
 * @return bool True if email is valid
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Get list of months
 * 
 * @return array Associative array of month numbers and names
 */
function get_months() {
    return [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December'
    ];
}

/**
 * Get month name from number
 * 
 * @param int $month_number Month number (1-12)
 * @return string Month name
 */
function get_month_name($month_number) {
    $months = get_months();
    return isset($months[$month_number]) ? $months[$month_number] : '';
}

/**
 * Get list of years for dropdown
 * 
 * @param int $start_offset Years before current year
 * @param int $end_offset Years after current year
 * @return array Array of years
 */
function get_years($start_offset = 5, $end_offset = 5) {
    $current_year = (int)date('Y');
    $years = [];
    
    for ($i = $current_year - $start_offset; $i <= $current_year + $end_offset; $i++) {
        $years[] = $i;
    }
    
    return $years;
}

/**
 * Set flash message to be displayed on next page load
 * 
 * @param string $type Message type (success, error, warning, info)
 * @param string $message Message text
 */
function set_flash_message($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get flash message and clear it
 * 
 * @return array|null Flash message or null if none exists
 */
function get_flash_message() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    
    return null;
}

/**
 * Get attendance status badge HTML
 * 
 * @param string $status Attendance status
 * @return string HTML for status badge
 */
function get_status_badge($status) {
    $class = '';
    $text = ucfirst($status);
    
    switch ($status) {
        case 'present':
            $class = 'badge-success';
            break;
        case 'absent':
            $class = 'badge-danger';
            break;
        case 'half-day':
            $class = 'badge-warning';
            break;
        case 'late':
            $class = 'badge-orange';
            break;
        case 'leave':
            $class = 'badge-info';
            break;
        default:
            $class = 'badge-secondary';
    }
    
    return '<span class="badge ' . $class . '">' . $text . '</span>';
}

/**
 * Get payment status badge HTML
 * 
 * @param string $status Payment status
 * @return string HTML for status badge
 */
function get_payment_badge($status) {
    $class = '';
    $text = ucfirst($status);
    
    switch ($status) {
        case 'paid':
            $class = 'badge-success';
            break;
        case 'pending':
            $class = 'badge-warning';
            break;
        case 'cancelled':
            $class = 'badge-danger';
            break;
        default:
            $class = 'badge-secondary';
    }
    
    return '<span class="badge ' . $class . '">' . $text . '</span>';
}

/**
 * Get initials from name
 * 
 * @param string $name Full name
 * @param int $limit Maximum number of initials
 * @return string Initials
 */
function get_initials($name, $limit = 2) {
    $words = explode(' ', $name);
    $initials = '';
    
    $count = 0;
    foreach ($words as $word) {
        if ($word && $count < $limit) {
            $initials .= strtoupper(substr($word, 0, 1));
            $count++;
        }
    }
    
    return $initials;
}

/**
 * Generate pagination HTML
 * 
 * @param int $current_page Current page number
 * @param int $total_pages Total number of pages
 * @param string $url_pattern URL pattern with {page} placeholder
 * @return string Pagination HTML
 */
function generate_pagination($current_page, $total_pages, $url_pattern) {
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination">';
    
    // Previous button
    if ($current_page > 1) {
        $prev_url = str_replace('{page}', $current_page - 1, $url_pattern);
        $html .= '<li class="page-item"><a class="page-link" href="' . $prev_url . '">Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>';
    }
    
    // Page numbers
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . str_replace('{page}', 1, $url_pattern) . '">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $current_page) {
            $html .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $page_url = str_replace('{page}', $i, $url_pattern);
            $html .= '<li class="page-item"><a class="page-link" href="' . $page_url . '">' . $i . '</a></li>';
        }
    }
    
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . str_replace('{page}', $total_pages, $url_pattern) . '">' . $total_pages . '</a></li>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $next_url = str_replace('{page}', $current_page + 1, $url_pattern);
        $html .= '<li class="page-item"><a class="page-link" href="' . $next_url . '">Next</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">Next</a></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}