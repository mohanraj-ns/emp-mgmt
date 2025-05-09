<?php
/**
 * Employees page
 * Handles employee listing, adding, editing, and deleting
 */

// Check login
require_login();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Add new employee
    if ($action === 'add_employee') {
        // Validate input data
        $name = sanitize_input($_POST['name'] ?? '');
        $position = sanitize_input($_POST['position'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        $address = sanitize_input($_POST['address'] ?? '');
        $hire_date = sanitize_input($_POST['hire_date'] ?? '');
        $hourly_rate = sanitize_input($_POST['hourly_rate'] ?? '');
        $monthly_rate = sanitize_input($_POST['monthly_rate'] ?? '');
        $work_hours = sanitize_input($_POST['work_hours'] ?? DEFAULT_WORKING_HOURS);
        $status = sanitize_input($_POST['status'] ?? 'active');
        
        $errors = [];
        
        // Basic validation
        if (empty($name)) {
            $errors['name'] = 'Name is required';
        }
        
        if (empty($position)) {
            $errors['position'] = 'Position is required';
        }
        
        if (empty($email)) {
            $errors['email'] = 'Email is required';
        } elseif (!validate_email($email)) {
            $errors['email'] = 'Invalid email format';
        }
        
        if (empty($hire_date)) {
            $errors['hire_date'] = 'Hire date is required';
        }
        
        if (empty($hourly_rate) && empty($monthly_rate)) {
            $errors['hourly_rate'] = 'Either hourly rate or monthly rate is required';
            $errors['monthly_rate'] = 'Either hourly rate or monthly rate is required';
        }
        
        // If no errors, add employee
        if (empty($errors)) {
            $query = "INSERT INTO employees (
                        name, position, email, phone, address, hire_date, 
                        hourly_rate, monthly_rate, work_hours_per_day, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $employee_id = db_insert(
                $query, 
                "ssssssddds", 
                [
                    $name, $position, $email, $phone, $address, $hire_date,
                    $hourly_rate ?: null, $monthly_rate ?: null, $work_hours, $status
                ],
                ['employees']
            );
            
            if ($employee_id) {
                // Log activity
                log_activity('create', "Added new employee: $name", $_SESSION['user_id'], $employee_id);
                
                set_flash_message('success', "Employee \"$name\" has been added successfully");
                header('Location: index.php?page=employees');
                exit;
            } else {
                set_flash_message('error', 'Failed to add employee. Please try again.');
            }
        }
    }
    
    // Edit employee
    elseif ($action === 'edit_employee') {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        
        // Validate input data
        $name = sanitize_input($_POST['name'] ?? '');
        $position = sanitize_input($_POST['position'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        $address = sanitize_input($_POST['address'] ?? '');
        $hire_date = sanitize_input($_POST['hire_date'] ?? '');
        $hourly_rate = sanitize_input($_POST['hourly_rate'] ?? '');
        $monthly_rate = sanitize_input($_POST['monthly_rate'] ?? '');
        $work_hours = sanitize_input($_POST['work_hours'] ?? DEFAULT_WORKING_HOURS);
        $status = sanitize_input($_POST['status'] ?? 'active');
        
        $errors = [];
        
        // Basic validation
        if (empty($name)) {
            $errors['name'] = 'Name is required';
        }
        
        if (empty($position)) {
            $errors['position'] = 'Position is required';
        }
        
        if (empty($email)) {
            $errors['email'] = 'Email is required';
        } elseif (!validate_email($email)) {
            $errors['email'] = 'Invalid email format';
        }
        
        if (empty($hire_date)) {
            $errors['hire_date'] = 'Hire date is required';
        }
        
        if (empty($hourly_rate) && empty($monthly_rate)) {
            $errors['hourly_rate'] = 'Either hourly rate or monthly rate is required';
            $errors['monthly_rate'] = 'Either hourly rate or monthly rate is required';
        }
        
        // If no errors, update employee
        if (empty($errors)) {
            $query = "UPDATE employees SET 
                        name = ?, position = ?, email = ?, phone = ?, 
                        address = ?, hire_date = ?, hourly_rate = ?, 
                        monthly_rate = ?, work_hours_per_day = ?, status = ?
                    WHERE id = ?";
            
            $result = db_update(
                $query, 
                "ssssssddssi", 
                [
                    $name, $position, $email, $phone, $address, $hire_date,
                    $hourly_rate ?: null, $monthly_rate ?: null, $work_hours, $status, $employee_id
                ],
                ['employees', 'employee_' . $employee_id]
            );
            
            if ($result) {
                // Log activity
                log_activity('update', "Updated employee: $name", $_SESSION['user_id'], $employee_id);
                
                set_flash_message('success', "Employee \"$name\" has been updated successfully");
                header('Location: index.php?page=employees');
                exit;
            } else {
                set_flash_message('error', 'Failed to update employee. Please try again.');
            }
        }
    }
    
    // Delete employee
    elseif ($action === 'delete_employee') {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        
        // Get employee name for activity log
        $query = "SELECT name FROM employees WHERE id = ?";
        $employee = db_fetch_row($query, "i", [$employee_id]);
        
        if ($employee) {
            $query = "DELETE FROM employees WHERE id = ?";
            $result = db_delete($query, "i", [$employee_id], ['employees', 'employee_' . $employee_id]);
            
            if ($result) {
                // Log activity
                log_activity('delete', "Deleted employee: {$employee['name']}", $_SESSION['user_id']);
                
                set_flash_message('success', "Employee \"{$employee['name']}\" has been deleted successfully");
                header('Location: index.php?page=employees');
                exit;
            } else {
                set_flash_message('error', 'Failed to delete employee. Please try again.');
            }
        } else {
            set_flash_message('error', 'Employee not found');
        }
    }
}

// Get employee list with pagination
$search = sanitize_input($_GET['search'] ?? '');
$status_filter = sanitize_input($_GET['status'] ?? 'all');
$current_page = max(1, intval($_GET['page'] ?? 1));
$items_per_page = ITEMS_PER_PAGE;
$offset = ($current_page - 1) * $items_per_page;

// Build query based on filters
$query_params = [];
$query_types = "";

$base_query = "FROM employees WHERE 1=1";

if (!empty($search)) {
    $base_query .= " AND (name LIKE ? OR position LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $query_params = array_merge($query_params, [$search_param, $search_param, $search_param, $search_param]);
    $query_types .= "ssss";
}

if ($status_filter !== 'all') {
    $base_query .= " AND status = ?";
    $query_params[] = $status_filter;
    $query_types .= "s";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total " . $base_query;
$count_result = db_fetch_row($count_query, $query_types, $query_params);
$total_items = $count_result['total'] ?? 0;
$total_pages = ceil($total_items / $items_per_page);

// Get paginated employees
$employees_query = "SELECT * " . $base_query . " ORDER BY name ASC LIMIT ? OFFSET ?";
$query_params[] = $items_per_page;
$query_params[] = $offset;
$query_types .= "ii";

$employees = db_fetch_all($employees_query, $query_types, $query_params);

// Get status counts for filters
$status_counts_query = "SELECT status, COUNT(*) as count FROM employees GROUP BY status";
$status_counts = db_fetch_all($status_counts_query, "", []);

// Convert to associative array
$status_count_map = [
    'active' => 0,
    'inactive' => 0,
    'on_leave' => 0,
    'terminated' => 0
];

foreach ($status_counts as $count) {
    $status_count_map[$count['status']] = $count['count'];
}

$total_count = array_sum($status_count_map);
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Employees</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
            <i class="fas fa-plus me-2"></i> Add Employee
        </button>
    </div>
    
    <!-- Search and filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="index.php" class="row g-3">
                <input type="hidden" name="page" value="employees">
                
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search employees..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status (<?php echo $total_count; ?>)</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active (<?php echo $status_count_map['active']; ?>)</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive (<?php echo $status_count_map['inactive']; ?>)</option>
                        <option value="on_leave" <?php echo $status_filter === 'on_leave' ? 'selected' : ''; ?>>On Leave (<?php echo $status_count_map['on_leave']; ?>)</option>
                        <option value="terminated" <?php echo $status_filter === 'terminated' ? 'selected' : ''; ?>>Terminated (<?php echo $status_count_map['terminated']; ?>)</option>
                    </select>
                </div>
                
                <?php if (!empty($search) || $status_filter !== 'all'): ?>
                <div class="col-md-3">
                    <a href="index.php?page=employees" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-times me-2"></i> Clear Filters
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Employees list -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($employees)): ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-4x text-muted mb-3"></i>
                <h4>No employees found</h4>
                <p class="text-muted">
                    <?php if (!empty($search) || $status_filter !== 'all'): ?>
                    Try adjusting your search or filters
                    <?php else: ?>
                    Add your first employee to get started
                    <?php endif; ?>
                </p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                    <i class="fas fa-plus me-2"></i> Add Employee
                </button>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Position</th>
                            <th>Contact</th>
                            <th>Hire Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle me-3">
                                        <span class="initials"><?php echo get_initials($employee['name']); ?></span>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($employee['name']); ?></div>
                                        <div class="small text-muted">
                                            ID: <?php echo $employee['id']; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($employee['position']); ?></td>
                            <td>
                                <div class="mb-1">
                                    <i class="fas fa-envelope me-1 text-muted"></i> 
                                    <a href="mailto:<?php echo htmlspecialchars($employee['email']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($employee['email']); ?>
                                    </a>
                                </div>
                                <?php if (!empty($employee['phone'])): ?>
                                <div>
                                    <i class="fas fa-phone me-1 text-muted"></i>
                                    <a href="tel:<?php echo htmlspecialchars($employee['phone']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($employee['phone']); ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo format_date($employee['hire_date']); ?></td>
                            <td>
                                <?php
                                $status_class = 'secondary';
                                switch ($employee['status']) {
                                    case 'active':
                                        $status_class = 'success';
                                        break;
                                    case 'inactive':
                                        $status_class = 'warning';
                                        break;
                                    case 'on_leave':
                                        $status_class = 'info';
                                        break;
                                    case 'terminated':
                                        $status_class = 'danger';
                                        break;
                                }
                                ?>
                                <span class="badge badge-<?php echo $status_class; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $employee['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" data-bs-target="#viewEmployeeModal<?php echo $employee['id']; ?>"
                                            data-tippy-content="View details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                                            data-bs-toggle="modal" data-bs-target="#editEmployeeModal<?php echo $employee['id']; ?>"
                                            data-tippy-content="Edit employee">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" data-bs-target="#deleteEmployeeModal<?php echo $employee['id']; ?>"
                                            data-tippy-content="Delete employee">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                                
                                <!-- View Employee Modal -->
                                <div class="modal fade" id="viewEmployeeModal<?php echo $employee['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Employee Details</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-4 text-center mb-4">
                                                        <div class="avatar-circle mx-auto" style="width: 100px; height: 100px; font-size: 40px;">
                                                            <span class="initials"><?php echo get_initials($employee['name']); ?></span>
                                                        </div>
                                                        <h4 class="mt-3"><?php echo htmlspecialchars($employee['name']); ?></h4>
                                                        <p class="text-muted"><?php echo htmlspecialchars($employee['position']); ?></p>
                                                        <span class="badge badge-<?php echo $status_class; ?> px-3 py-2">
                                                            <?php echo ucfirst(str_replace('_', ' ', $employee['status'])); ?>
                                                        </span>
                                                    </div>
                                                    <div class="col-md-8">
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-bold">Email</label>
                                                                <p><?php echo htmlspecialchars($employee['email']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-bold">Phone</label>
                                                                <p><?php echo htmlspecialchars($employee['phone'] ?: 'Not provided'); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-bold">Hire Date</label>
                                                                <p><?php echo format_date($employee['hire_date']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-bold">Work Hours Per Day</label>
                                                                <p><?php echo htmlspecialchars($employee['work_hours_per_day']); ?> hours</p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-bold">Hourly Rate</label>
                                                                <p><?php echo !empty($employee['hourly_rate']) ? format_currency($employee['hourly_rate']) : 'N/A'; ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-bold">Monthly Rate</label>
                                                                <p><?php echo !empty($employee['monthly_rate']) ? format_currency($employee['monthly_rate']) : 'N/A'; ?></p>
                                                            </div>
                                                            <div class="col-md-12">
                                                                <label class="form-label fw-bold">Address</label>
                                                                <p><?php echo htmlspecialchars($employee['address'] ?: 'Not provided'); ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <hr>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <p class="mb-1"><small class="text-muted">Created: <?php echo format_date($employee['created_at'], DATETIME_FORMAT); ?></small></p>
                                                    </div>
                                                    <div class="col-md-6 text-end">
                                                        <p class="mb-1"><small class="text-muted">Last Updated: <?php echo format_date($employee['updated_at'], DATETIME_FORMAT); ?></small></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#editEmployeeModal<?php echo $employee['id']; ?>">
                                                    <i class="fas fa-edit me-2"></i> Edit
                                                </button>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Edit Employee Modal -->
                                <div class="modal fade" id="editEmployeeModal<?php echo $employee['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <form method="post" action="index.php?page=employees" id="editEmployeeForm<?php echo $employee['id']; ?>">
                                                <input type="hidden" name="action" value="edit_employee">
                                                <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Employee</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row g-3">
                                                        <!-- Personal Information -->
                                                        <div class="col-md-6">
                                                            <label for="edit_name<?php echo $employee['id']; ?>" class="form-label">Full Name <span class="text-danger">*</span></label>
                                                            <input type="text" class="form-control" id="edit_name<?php echo $employee['id']; ?>" name="name" value="<?php echo htmlspecialchars($employee['name']); ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="edit_position<?php echo $employee['id']; ?>" class="form-label">Position <span class="text-danger">*</span></label>
                                                            <input type="text" class="form-control" id="edit_position<?php echo $employee['id']; ?>" name="position" value="<?php echo htmlspecialchars($employee['position']); ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="edit_email<?php echo $employee['id']; ?>" class="form-label">Email <span class="text-danger">*</span></label>
                                                            <input type="email" class="form-control" id="edit_email<?php echo $employee['id']; ?>" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="edit_phone<?php echo $employee['id']; ?>" class="form-label">Phone</label>
                                                            <input type="tel" class="form-control" id="edit_phone<?php echo $employee['id']; ?>" name="phone" value="<?php echo htmlspecialchars($employee['phone'] ?: ''); ?>">
                                                        </div>
                                                        <div class="col-md-12">
                                                            <label for="edit_address<?php echo $employee['id']; ?>" class="form-label">Address</label>
                                                            <textarea class="form-control" id="edit_address<?php echo $employee['id']; ?>" name="address" rows="2"><?php echo htmlspecialchars($employee['address'] ?: ''); ?></textarea>
                                                        </div>
                                                        
                                                        <!-- Employment Information -->
                                                        <div class="col-md-6">
                                                            <label for="edit_hire_date<?php echo $employee['id']; ?>" class="form-label">Hire Date <span class="text-danger">*</span></label>
                                                            <input type="date" class="form-control" id="edit_hire_date<?php echo $employee['id']; ?>" name="hire_date" value="<?php echo htmlspecialchars($employee['hire_date']); ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="edit_status<?php echo $employee['id']; ?>" class="form-label">Status <span class="text-danger">*</span></label>
                                                            <select class="form-select" id="edit_status<?php echo $employee['id']; ?>" name="status" required>
                                                                <option value="active" <?php echo $employee['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                                <option value="inactive" <?php echo $employee['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                                <option value="on_leave" <?php echo $employee['status'] === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                                                                <option value="terminated" <?php echo $employee['status'] === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label for="edit_work_hours<?php echo $employee['id']; ?>" class="form-label">Work Hours Per Day</label>
                                                            <input type="number" class="form-control" id="edit_work_hours<?php echo $employee['id']; ?>" name="work_hours" min="0" step="0.5" value="<?php echo htmlspecialchars($employee['work_hours_per_day']); ?>">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label for="edit_hourly_rate<?php echo $employee['id']; ?>" class="form-label">Hourly Rate</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                                                                <input type="number" class="form-control" id="edit_hourly_rate<?php echo $employee['id']; ?>" name="hourly_rate" min="0" step="0.01" value="<?php echo htmlspecialchars($employee['hourly_rate'] ?: ''); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label for="edit_monthly_rate<?php echo $employee['id']; ?>" class="form-label">Monthly Rate</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                                                                <input type="number" class="form-control" id="edit_monthly_rate<?php echo $employee['id']; ?>" name="monthly_rate" min="0" step="0.01" value="<?php echo htmlspecialchars($employee['monthly_rate'] ?: ''); ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-save me-2"></i> Save Changes
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Delete Employee Modal -->
                                <div class="modal fade" id="deleteEmployeeModal<?php echo $employee['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="post" action="index.php?page=employees">
                                                <input type="hidden" name="action" value="delete_employee">
                                                <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Confirm Delete</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="text-center mb-4">
                                                        <i class="fas fa-exclamation-triangle text-warning fa-4x mb-3"></i>
                                                        <h4>Are you sure you want to delete this employee?</h4>
                                                        <p class="text-muted">
                                                            You are about to delete <strong><?php echo htmlspecialchars($employee['name']); ?></strong>. 
                                                            This will also delete all attendance records, salary information, and other data associated with this employee.
                                                            This action cannot be undone.
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="fas fa-trash-alt me-2"></i> Delete Employee
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <?php
                $pagination_url = 'index.php?page=employees&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&page={page}';
                echo generate_pagination($current_page, $total_pages, $pagination_url);
                ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post" action="index.php?page=employees" id="addEmployeeForm">
                    <input type="hidden" name="action" value="add_employee">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Employee</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <!-- Personal Information -->
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                                <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['name']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="position" class="form-label">Position <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['position']) ? 'is-invalid' : ''; ?>" id="position" name="position" value="<?php echo htmlspecialchars($position ?? ''); ?>" required>
                                <?php if (isset($errors['position'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['position']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                                <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                                <?php if (isset($errors['phone'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-12">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>" id="address" name="address" rows="2"><?php echo htmlspecialchars($address ?? ''); ?></textarea>
                                <?php if (isset($errors['address'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['address']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Employment Information -->
                            <div class="col-md-6">
                                <label for="hire_date" class="form-label">Hire Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control <?php echo isset($errors['hire_date']) ? 'is-invalid' : ''; ?>" id="hire_date" name="hire_date" value="<?php echo htmlspecialchars($hire_date ?? date('Y-m-d')); ?>" required>
                                <?php if (isset($errors['hire_date'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['hire_date']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select <?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>" id="status" name="status" required>
                                    <option value="active" <?php echo isset($status) && $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo isset($status) && $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="on_leave" <?php echo isset($status) && $status === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                                    <option value="terminated" <?php echo isset($status) && $status === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                                </select>
                                <?php if (isset($errors['status'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['status']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label for="work_hours" class="form-label">Work Hours Per Day</label>
                                <input type="number" class="form-control <?php echo isset($errors['work_hours']) ? 'is-invalid' : ''; ?>" id="work_hours" name="work_hours" min="0" step="0.5" value="<?php echo htmlspecialchars($work_hours ?? DEFAULT_WORKING_HOURS); ?>">
                                <?php if (isset($errors['work_hours'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['work_hours']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label for="hourly_rate" class="form-label">Hourly Rate</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                                    <input type="number" class="form-control <?php echo isset($errors['hourly_rate']) ? 'is-invalid' : ''; ?>" id="hourly_rate" name="hourly_rate" min="0" step="0.01" value="<?php echo htmlspecialchars($hourly_rate ?? ''); ?>">
                                    <?php if (isset($errors['hourly_rate'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['hourly_rate']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="monthly_rate" class="form-label">Monthly Rate</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                                    <input type="number" class="form-control <?php echo isset($errors['monthly_rate']) ? 'is-invalid' : ''; ?>" id="monthly_rate" name="monthly_rate" min="0" step="0.01" value="<?php echo htmlspecialchars($monthly_rate ?? ''); ?>">
                                    <?php if (isset($errors['monthly_rate'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['monthly_rate']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Either <strong>Hourly Rate</strong> or <strong>Monthly Rate</strong> is required. If both are provided, 
                                    Monthly Rate will be used for salary calculations.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Add Employee
                        </button>
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
$(document).ready(function() {
    // Form validation rules
    const employeeRules = {
        name: {
            required: 'Full name is required'
        },
        position: {
            required: 'Position is required'
        },
        email: {
            required: 'Email is required',
            pattern: /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/,
            message: 'Please enter a valid email address'
        },
        hire_date: {
            required: 'Hire date is required'
        }
    };
    
    // Initialize validation for add employee form
    $('#addEmployeeForm').on('submit', function(e) {
        if (!validateForm('addEmployeeForm', employeeRules)) {
            e.preventDefault();
        }
    });
    
    // Initialize validation for edit employee forms
    $('.edit-employee-form').each(function() {
        const formId = $(this).attr('id');
        $(this).on('submit', function(e) {
            if (!validateForm(formId, employeeRules)) {
                e.preventDefault();
            }
        });
    });
});
</script>
SCRIPT;