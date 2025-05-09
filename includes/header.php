<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Toastr notifications CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    
    <!-- Tippy.js for tooltips -->
    <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
    
    <!-- Optional page-specific CSS -->
    <?php if (isset($page_css)) echo $page_css; ?>
</head>
<body>

<?php 
// Don't show navbar on login/register pages
if (is_logged_in() && !in_array($page, ['login', 'register'])): 
?>
<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-clipboard-check me-2"></i>
            <?php echo APP_NAME; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" 
                aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo $page === 'dashboard' ? 'active' : ''; ?>" 
                       href="index.php?page=dashboard">
                        <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page === 'employees' ? 'active' : ''; ?>" 
                       href="index.php?page=employees">
                        <i class="fas fa-users me-1"></i> Employees
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page === 'attendance' ? 'active' : ''; ?>" 
                       href="index.php?page=attendance">
                        <i class="fas fa-clipboard-check me-1"></i> Attendance
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page === 'salary' ? 'active' : ''; ?>" 
                       href="index.php?page=salary">
                        <i class="fas fa-money-bill-wave me-1"></i> Salary
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page === 'reports' ? 'active' : ''; ?>" 
                       href="index.php?page=reports">
                        <i class="fas fa-chart-bar me-1"></i> Reports
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" 
                       data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo $_SESSION['name']; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="index.php?page=logout">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Sidebar for logged-in users -->
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-2 d-none d-lg-block sidebar">
            <div class="user-profile text-center py-4">
                <div class="user-avatar mb-2">
                    <?php 
                    $initials = get_initials($_SESSION['name']);
                    echo '<div class="avatar-circle"><span class="initials">' . $initials . '</span></div>';
                    ?>
                </div>
                <h5 class="mb-0"><?php echo $_SESSION['name']; ?></h5>
                <small class="text-muted"><?php echo ucfirst($_SESSION['role']); ?></small>
            </div>
            <hr>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo $page === 'dashboard' ? 'active' : ''; ?>" 
                       href="index.php?page=dashboard">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page === 'employees' ? 'active' : ''; ?>" 
                       href="index.php?page=employees">
                        <i class="fas fa-users me-2"></i> Employees
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page === 'attendance' ? 'active' : ''; ?>" 
                       href="index.php?page=attendance">
                        <i class="fas fa-clipboard-check me-2"></i> Attendance
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page === 'salary' ? 'active' : ''; ?>" 
                       href="index.php?page=salary">
                        <i class="fas fa-money-bill-wave me-2"></i> Salary
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page === 'reports' ? 'active' : ''; ?>" 
                       href="index.php?page=reports">
                        <i class="fas fa-chart-bar me-2"></i> Reports
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link text-danger" href="index.php?page=logout">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Main content area -->
        <div class="col-lg-10 offset-lg-2 main-content py-4">
            <?php
            // Display flash messages if any
            $flash = get_flash_message();
            if ($flash) {
                echo '<div class="alert alert-' . $flash['type'] . ' alert-dismissible fade show" role="alert">';
                echo $flash['message'];
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                echo '</div>';
            }
            ?>
<?php else: ?>
<!-- Simple container for login/register pages -->
<div class="container">
<?php endif; ?>