<?php
/**
 * Salary page
 * Handles salary calculation, viewing, and management
 */

// Check login
require_login();

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Generate salary
    if ($action === 'generate_salary') {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $month = (int)($_POST['month'] ?? 0);
        $year = (int)($_POST['year'] ?? 0);
        $bonus = (float)($_POST['bonus'] ?? 0);
        $deductions = (float)($_POST['deductions'] ?? 0);
        $note = sanitize_input($_POST['note'] ?? '');
        
        $errors = [];
        
        // Basic validation
        if (!$employee_id) {
            $errors['employee_id'] = 'Employee is required';
        }
        
        if (!$month || $month < 1 || $month > 12) {
            $errors['month'] = 'Valid month is required';
        }
        
        if (!$year || $year < 2000 || $year > 2100) {
            $errors['year'] = 'Valid year is required';
        }
        
        // If no errors, generate salary
        if (empty($errors)) {
            // Check if salary already exists for this month/year/employee
            $existing_query = "SELECT id FROM salary WHERE employee_id = ? AND month = ? AND year = ?";
            $existing = db_fetch_row($existing_query, "iii", [$employee_id, $month, $year]);
            
            if ($existing) {
                set_flash_message('error', 'Salary already exists for this employee for the selected month. Please edit the existing salary instead.');
            } else {
                // Get employee details
                $employee_query = "SELECT name, hourly_rate, monthly_rate, work_hours_per_day FROM employees WHERE id = ?";
                $employee = db_fetch_row($employee_query, "i", [$employee_id]);
                
                if (!$employee) {
                    set_flash_message('error', 'Employee not found');
                } else {
                    // Get start and end date of month
                    $start_date = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
                    $end_date = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
                    
                    // Get attendance records for this month
                    $attendance_query = "SELECT 
                                            status, 
                                            work_hours, 
                                            overtime_hours 
                                        FROM attendance 
                                        WHERE employee_id = ? AND date BETWEEN ? AND ?";
                    $attendance_records = db_fetch_all($attendance_query, "iss", [$employee_id, $start_date, $end_date]);
                    
                    // Calculate attendance counts
                    $present_days = 0;
                    $absent_days = 0;
                    $half_days = 0;
                    $leave_days = 0;
                    $late_days = 0;
                    $total_work_hours = 0;
                    $total_overtime_hours = 0;
                    
                    foreach ($attendance_records as $record) {
                        switch ($record['status']) {
                            case 'present':
                                $present_days++;
                                break;
                            case 'absent':
                                $absent_days++;
                                break;
                            case 'half-day':
                                $half_days++;
                                break;
                            case 'leave':
                                $leave_days++;
                                break;
                            case 'late':
                                $late_days++;
                                break;
                        }
                        
                        $total_work_hours += (float)($record['work_hours'] ?? 0);
                        $total_overtime_hours += (float)($record['overtime_hours'] ?? 0);
                    }
                    
                    // Calculate basic salary
                    $basic_salary = 0;
                    
                    if (!empty($employee['monthly_rate'])) {
                        // If monthly rate is set, use it
                        $basic_salary = (float)$employee['monthly_rate'];
                    } elseif (!empty($employee['hourly_rate'])) {
                        // If hourly rate is set, calculate based on work hours
                        $hourly_rate = (float)$employee['hourly_rate'];
                        $work_hours_per_day = (float)$employee['work_hours_per_day'];
                        
                        // Get working days in month (excluding weekends)
                        $working_days = 0;
                        $days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
                        
                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $date = mktime(0, 0, 0, $month, $day, $year);
                            $weekday = date('N', $date);
                            
                            // Skip weekends (6=Saturday, 7=Sunday)
                            if ($weekday < 6) {
                                $working_days++;
                            }
                        }
                        
                        // Calculate based on attendance
                        $attended_days = $present_days + ($half_days * 0.5) + $leave_days;
                        $attended_hours = $attended_days * $work_hours_per_day;
                        
                        $basic_salary = $hourly_rate * $attended_hours;
                    }
                    
                    // Calculate overtime pay
                    $overtime_pay = 0;
                    
                    if ($total_overtime_hours > 0 && !empty($employee['hourly_rate'])) {
                        $hourly_rate = (float)$employee['hourly_rate'];
                        $overtime_pay = $total_overtime_hours * $hourly_rate * OVERTIME_RATE_MULTIPLIER;
                    }
                    
                    // Calculate total salary
                    $total_salary = $basic_salary + $overtime_pay + $bonus - $deductions;
                    
                    // Insert salary record
                    $query = "INSERT INTO salary (
                                employee_id, month, year, basic_salary, overtime_pay, 
                                bonus, deductions, total_salary, payment_status, note,
                                present_days, absent_days, leave_days, half_days, late_days
                            ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?,
                                ?, ?, ?, ?, ?
                            )";
                    
                    $params = [
                        $employee_id, $month, $year, $basic_salary, $overtime_pay,
                        $bonus, $deductions, $total_salary, $note,
                        $present_days, $absent_days, $leave_days, $half_days, $late_days
                    ];
                    
                    $types = "iiidddddsiiiiii";
                    
                    $salary_id = db_insert($query, $types, $params, ['salary']);
                    
                    if ($salary_id) {
                        // Log activity
                        log_activity('create', "Generated salary for {$employee['name']} for " . date('F Y', mktime(0, 0, 0, $month, 1, $year)), $_SESSION['user_id'], $employee_id);
                        
                        set_flash_message('success', "Salary generated successfully for {$employee['name']} for " . date('F Y', mktime(0, 0, 0, $month, 1, $year)));
                        header('Location: index.php?page=salary');
                        exit;
                    } else {
                        set_flash_message('error', 'Failed to generate salary. Please try again.');
                    }
                }
            }
        }
    }
    
    // Update salary status
    elseif ($action === 'update_salary_status') {
        $salary_id = (int)($_POST['salary_id'] ?? 0);
        $status = sanitize_input($_POST['status'] ?? '');
        $payment_date = empty($_POST['payment_date']) ? null : sanitize_input($_POST['payment_date']);
        $payment_method = sanitize_input($_POST['payment_method'] ?? '');
        $note = sanitize_input($_POST['note'] ?? '');
        
        $errors = [];
        
        // Basic validation
        if (!$salary_id) {
            $errors['salary_id'] = 'Salary record is required';
        }
        
        if (!in_array($status, ['pending', 'paid', 'cancelled'])) {
            $errors['status'] = 'Invalid status';
        }
        
        if ($status === 'paid' && empty($payment_date)) {
            $errors['payment_date'] = 'Payment date is required for paid status';
        }
        
        // If no errors, update salary status
        if (empty($errors)) {
            $query = "UPDATE salary SET 
                        payment_status = ?, 
                        payment_date = ?, 
                        payment_method = ?,
                        note = CASE WHEN ? != '' THEN ? ELSE note END
                      WHERE id = ?";
            
            $result = db_update(
                $query, 
                "ssssi", 
                [$status, $payment_date, $payment_method, $note, $note, $salary_id],
                ['salary']
            );
            
            if ($result) {
                // Get salary details for activity log
                $salary_query = "SELECT s.*, e.name as employee_name 
                                FROM salary s
                                JOIN employees e ON s.employee_id = e.id
                                WHERE s.id = ?";
                $salary = db_fetch_row($salary_query, "i", [$salary_id]);
                
                // Log activity
                log_activity(
                    'update', 
                    "Updated salary status to '$status' for {$salary['employee_name']} for " . date('F Y', mktime(0, 0, 0, $salary['month'], 1, $salary['year'])), 
                    $_SESSION['user_id'], 
                    $salary['employee_id']
                );
                
                set_flash_message('success', "Salary status updated successfully");
                header('Location: index.php?page=salary');
                exit;
            } else {
                set_flash_message('error', 'Failed to update salary status. Please try again.');
            }
        }
    }
    
    // Delete salary
    elseif ($action === 'delete_salary') {
        $salary_id = (int)($_POST['salary_id'] ?? 0);
        
        // Get salary details for activity log
        $salary_query = "SELECT s.*, e.name as employee_name 
                        FROM salary s
                        JOIN employees e ON s.employee_id = e.id
                        WHERE s.id = ?";
        $salary = db_fetch_row($salary_query, "i", [$salary_id]);
        
        if ($salary) {
            $query = "DELETE FROM salary WHERE id = ?";
            $result = db_delete($query, "i", [$salary_id], ['salary']);
            
            if ($result) {
                // Log activity
                log_activity(
                    'delete', 
                    "Deleted salary record for {$salary['employee_name']} for " . date('F Y', mktime(0, 0, 0, $salary['month'], 1, $salary['year'])), 
                    $_SESSION['user_id'], 
                    $salary['employee_id']
                );
                
                set_flash_message('success', "Salary record deleted successfully");
                header('Location: index.php?page=salary');
                exit;
            } else {
                set_flash_message('error', 'Failed to delete salary record. Please try again.');
            }
        } else {
            set_flash_message('error', 'Salary record not found');
        }
    }
}

// Get filter parameters
$month_filter = (int)($_GET['month'] ?? date('n'));
$year_filter = (int)($_GET['year'] ?? date('Y'));
$status_filter = sanitize_input($_GET['status'] ?? 'all');
$search = sanitize_input($_GET['search'] ?? '');
$current_page = max(1, intval($_GET['page'] ?? 1));
$items_per_page = ITEMS_PER_PAGE;
$offset = ($current_page - 1) * $items_per_page;

// Build query based on filters
$query_params = [];
$query_types = "";

$base_query = "FROM salary s
               JOIN employees e ON s.employee_id = e.id
               WHERE 1=1";

if ($month_filter > 0) {
    $base_query .= " AND s.month = ?";
    $query_params[] = $month_filter;
    $query_types .= "i";
}

if ($year_filter > 0) {
    $base_query .= " AND s.year = ?";
    $query_params[] = $year_filter;
    $query_types .= "i";
}

if ($status_filter !== 'all') {
    $base_query .= " AND s.payment_status = ?";
    $query_params[] = $status_filter;
    $query_types .= "s";
}

if (!empty($search)) {
    $base_query .= " AND (e.name LIKE ? OR e.position LIKE ?)";
    $search_param = "%$search%";
    $query_params = array_merge($query_params, [$search_param, $search_param]);
    $query_types .= "ss";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total " . $base_query;
$count_result = db_fetch_row($count_query, $query_types, $query_params);
$total_items = $count_result['total'] ?? 0;
$total_pages = ceil($total_items / $items_per_page);

// Get paginated salary records
$salary_query = "SELECT s.*, e.name as employee_name, e.position " . $base_query . " ORDER BY s.year DESC, s.month DESC, e.name ASC LIMIT ? OFFSET ?";
$query_params[] = $items_per_page;
$query_params[] = $offset;
$query_types .= "ii";

$salary_records = db_fetch_all($salary_query, $query_types, $query_params);

// Get status counts for filters
$status_counts_query = "SELECT s.payment_status, COUNT(*) as count 
                        FROM salary s 
                        WHERE s.month = ? AND s.year = ?
                        GROUP BY s.payment_status";
$status_counts = db_fetch_all($status_counts_query, "ii", [$month_filter, $year_filter]);

// Convert to associative array
$status_count_map = [
    'pending' => 0,
    'paid' => 0,
    'cancelled' => 0
];

foreach ($status_counts as $count) {
    $status_count_map[$count['payment_status']] = $count['count'];
}

$total_count = array_sum($status_count_map);

// Get list of active employees who don't have salary for the selected month/year
$employees_query = "SELECT e.id, e.name, e.position 
                   FROM employees e 
                   WHERE e.status = 'active' 
                   AND e.id NOT IN (
                       SELECT employee_id FROM salary WHERE month = ? AND year = ?
                   )
                   ORDER BY e.name";
$employees = db_fetch_all($employees_query, "ii", [$month_filter, $year_filter]);

// Get salary stats for the month/year
$stats_query = "SELECT 
                   COUNT(*) as total_records,
                   SUM(basic_salary) as total_basic,
                   SUM(overtime_pay) as total_overtime,
                   SUM(bonus) as total_bonus,
                   SUM(deductions) as total_deductions,
                   SUM(total_salary) as total_amount
                FROM salary
                WHERE month = ? AND year = ?";
$salary_stats = db_fetch_row($stats_query, "ii", [$month_filter, $year_filter]);
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Salary Management</h2>
        <?php if (!empty($employees)): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateSalaryModal">
            <i class="fas fa-plus me-2"></i> Generate Salary
        </button>
        <?php endif; ?>
    </div>
    
    <!-- Search and filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="index.php" class="row g-3">
                <input type="hidden" name="page" value="salary">
                
                <div class="col-md-3">
                    <label for="month" class="form-label">Month</label>
                    <select class="form-select" id="month" name="month" onchange="this.form.submit()">
                        <?php foreach (get_months() as $num => $name): ?>
                        <option value="<?php echo $num; ?>" <?php echo $month_filter === $num ? 'selected' : ''; ?>>
                            <?php echo $name; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="year" class="form-label">Year</label>
                    <select class="form-select" id="year" name="year" onchange="this.form.submit()">
                        <?php foreach (get_years() as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo $year_filter === $year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status (<?php echo $total_count; ?>)</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending (<?php echo $status_count_map['pending']; ?>)</option>
                        <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid (<?php echo $status_count_map['paid']; ?>)</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled (<?php echo $status_count_map['cancelled']; ?>)</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="search" class="form-label">Search Employee</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="search" name="search" placeholder="Search by name or position..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                
                <?php if (!empty($search) || $status_filter !== 'all'): ?>
                <div class="col-md-2">
                    <label class="form-label d-none d-md-block">&nbsp;</label>
                    <a href="index.php?page=salary&month=<?php echo $month_filter; ?>&year=<?php echo $year_filter; ?>" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-times me-2"></i> Clear Filters
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Month Summary Card -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Summary for <?php echo get_month_name($month_filter) . ' ' . $year_filter; ?></h5>
                    
                    <div class="row mt-3">
                        <div class="col-md-4 col-xl-2 mb-3">
                            <div class="card h-100 border-0 shadow-sm text-center">
                                <div class="card-body">
                                    <p class="text-muted mb-1 small">Total Employees</p>
                                    <h4 class="mb-0"><?php echo $salary_stats['total_records'] ?? 0; ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-2 mb-3">
                            <div class="card h-100 border-0 shadow-sm text-center">
                                <div class="card-body">
                                    <p class="text-muted mb-1 small">Basic Salary</p>
                                    <h4 class="mb-0"><?php echo format_currency($salary_stats['total_basic'] ?? 0); ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-2 mb-3">
                            <div class="card h-100 border-0 shadow-sm text-center">
                                <div class="card-body">
                                    <p class="text-muted mb-1 small">Overtime Pay</p>
                                    <h4 class="mb-0"><?php echo format_currency($salary_stats['total_overtime'] ?? 0); ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-2 mb-3">
                            <div class="card h-100 border-0 shadow-sm text-center">
                                <div class="card-body">
                                    <p class="text-muted mb-1 small">Bonuses</p>
                                    <h4 class="mb-0"><?php echo format_currency($salary_stats['total_bonus'] ?? 0); ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-2 mb-3">
                            <div class="card h-100 border-0 shadow-sm text-center">
                                <div class="card-body">
                                    <p class="text-muted mb-1 small">Deductions</p>
                                    <h4 class="mb-0"><?php echo format_currency($salary_stats['total_deductions'] ?? 0); ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-2 mb-3">
                            <div class="card h-100 border-0 shadow-sm text-center bg-light">
                                <div class="card-body">
                                    <p class="text-primary mb-1 small fw-bold">Total Amount</p>
                                    <h3 class="mb-0"><?php echo format_currency($salary_stats['total_amount'] ?? 0); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="progress mt-2" style="height: 8px;">
                        <?php
                        $total = $salary_stats['total_amount'] ?? 0;
                        
                        $basic_percent = $total > 0 ? round((($salary_stats['total_basic'] ?? 0) / $total) * 100) : 0;
                        $overtime_percent = $total > 0 ? round((($salary_stats['total_overtime'] ?? 0) / $total) * 100) : 0;
                        $bonus_percent = $total > 0 ? round((($salary_stats['total_bonus'] ?? 0) / $total) * 100) : 0;
                        $deduction_percent = $total > 0 ? round((($salary_stats['total_deductions'] ?? 0) / $total) * 100) : 0;
                        ?>
                        <div class="progress-bar bg-primary" style="width: <?php echo $basic_percent; ?>%" title="Basic: <?php echo $basic_percent; ?>%"></div>
                        <div class="progress-bar bg-success" style="width: <?php echo $overtime_percent; ?>%" title="Overtime: <?php echo $overtime_percent; ?>%"></div>
                        <div class="progress-bar bg-info" style="width: <?php echo $bonus_percent; ?>%" title="Bonus: <?php echo $bonus_percent; ?>%"></div>
                        <div class="progress-bar bg-danger" style="width: <?php echo $deduction_percent; ?>%" title="Deductions: <?php echo $deduction_percent; ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-center mt-2 small">
                        <div class="me-3"><i class="fas fa-square text-primary me-1"></i> Basic Salary</div>
                        <div class="me-3"><i class="fas fa-square text-success me-1"></i> Overtime</div>
                        <div class="me-3"><i class="fas fa-square text-info me-1"></i> Bonus</div>
                        <div><i class="fas fa-square text-danger me-1"></i> Deductions</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Salary Records -->
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Salary Records - <?php echo get_month_name($month_filter) . ' ' . $year_filter; ?></h5>
            <div>
                <a href="#" class="btn btn-sm btn-outline-primary me-2" onclick="printElement('salaryTableContainer'); return false;">
                    <i class="fas fa-print me-1"></i> Print
                </a>
                <a href="#" class="btn btn-sm btn-outline-success" onclick="exportToExcel('salaryTable', 'Salary_<?php echo get_month_name($month_filter) . '_' . $year_filter; ?>'); return false;">
                    <i class="fas fa-file-excel me-1"></i> Export
                </a>
            </div>
        </div>
        <div class="card-body" id="salaryTableContainer">
            <?php if (empty($salary_records)): ?>
            <div class="text-center py-5">
                <i class="fas fa-money-bill-wave fa-4x text-muted mb-3"></i>
                <h4>No salary records found</h4>
                <p class="text-muted">
                    <?php if (!empty($search) || $status_filter !== 'all'): ?>
                    Try adjusting your search or filters
                    <?php else: ?>
                    No salaries have been generated for <?php echo get_month_name($month_filter) . ' ' . $year_filter; ?>
                    <?php endif; ?>
                </p>
                <?php if (!empty($employees)): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateSalaryModal">
                    <i class="fas fa-plus me-2"></i> Generate Salary
                </button>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="salaryTable">
                    <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Basic Salary</th>
                            <th>Overtime</th>
                            <th>Bonus</th>
                            <th>Deductions</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Attendance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($salary_records as $record): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle avatar-sm me-2">
                                        <span class="initials"><?php echo get_initials($record['employee_name']); ?></span>
                                    </div>
                                    <div>
                                        <div class="fw-medium"><?php echo htmlspecialchars($record['employee_name']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($record['position']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo format_currency($record['basic_salary']); ?></td>
                            <td>
                                <?php if ($record['overtime_pay'] > 0): ?>
                                <span class="text-success"><?php echo format_currency($record['overtime_pay']); ?></span>
                                <?php else: ?>
                                —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($record['bonus'] > 0): ?>
                                <span class="text-info"><?php echo format_currency($record['bonus']); ?></span>
                                <?php else: ?>
                                —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($record['deductions'] > 0): ?>
                                <span class="text-danger"><?php echo format_currency($record['deductions']); ?></span>
                                <?php else: ?>
                                —
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold"><?php echo format_currency($record['total_salary']); ?></td>
                            <td><?php echo get_payment_badge($record['payment_status']); ?></td>
                            <td>
                                <div class="d-flex">
                                    <div class="me-2" data-tippy-content="Present Days">
                                        <i class="fas fa-user-check text-success"></i> <?php echo $record['present_days']; ?>
                                    </div>
                                    <div class="me-2" data-tippy-content="Absent Days">
                                        <i class="fas fa-user-times text-danger"></i> <?php echo $record['absent_days']; ?>
                                    </div>
                                    <div data-tippy-content="Late Days">
                                        <i class="fas fa-clock text-warning"></i> <?php echo $record['late_days']; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" data-bs-target="#viewSalaryModal<?php echo $record['id']; ?>"
                                            data-tippy-content="View details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success" 
                                            data-bs-toggle="modal" data-bs-target="#updateStatusModal<?php echo $record['id']; ?>"
                                            data-tippy-content="Update payment status">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" data-bs-target="#deleteSalaryModal<?php echo $record['id']; ?>"
                                            data-tippy-content="Delete salary record">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                                
                                <!-- View Salary Modal -->
                                <div class="modal fade" id="viewSalaryModal<?php echo $record['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Salary Details</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <h5><?php echo htmlspecialchars($record['employee_name']); ?></h5>
                                                        <p class="text-muted"><?php echo htmlspecialchars($record['position']); ?></p>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Period</label>
                                                            <p><?php echo get_month_name($record['month']) . ' ' . $record['year']; ?></p>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Status</label>
                                                            <p><?php echo get_payment_badge($record['payment_status']); ?></p>
                                                        </div>
                                                        
                                                        <?php if ($record['payment_status'] === 'paid'): ?>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Payment Date</label>
                                                            <p><?php echo format_date($record['payment_date']); ?></p>
                                                        </div>
                                                        
                                                        <?php if (!empty($record['payment_method'])): ?>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Payment Method</label>
                                                            <p><?php echo htmlspecialchars($record['payment_method']); ?></p>
                                                        </div>
                                                        <?php endif; ?>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!empty($record['note'])): ?>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Note</label>
                                                            <p><?php echo nl2br(htmlspecialchars($record['note'])); ?></p>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="col-md-6">
                                                        <div class="card">
                                                            <div class="card-header">
                                                                <h5 class="card-title mb-0">Salary Breakdown</h5>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="d-flex justify-content-between mb-3">
                                                                    <span>Basic Salary:</span>
                                                                    <span class="fw-bold"><?php echo format_currency($record['basic_salary']); ?></span>
                                                                </div>
                                                                <div class="d-flex justify-content-between mb-3">
                                                                    <span>Overtime Pay:</span>
                                                                    <span class="text-success"><?php echo format_currency($record['overtime_pay']); ?></span>
                                                                </div>
                                                                <div class="d-flex justify-content-between mb-3">
                                                                    <span>Bonus:</span>
                                                                    <span class="text-info"><?php echo format_currency($record['bonus']); ?></span>
                                                                </div>
                                                                <div class="d-flex justify-content-between mb-3">
                                                                    <span>Deductions:</span>
                                                                    <span class="text-danger">-<?php echo format_currency($record['deductions']); ?></span>
                                                                </div>
                                                                <hr>
                                                                <div class="d-flex justify-content-between fw-bold">
                                                                    <span>Total Salary:</span>
                                                                    <span class="fs-5"><?php echo format_currency($record['total_salary']); ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="card mt-3">
                                                            <div class="card-header">
                                                                <h5 class="card-title mb-0">Attendance Summary</h5>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="row">
                                                                    <div class="col-6">
                                                                        <p class="mb-1">Present Days: <span class="fw-bold"><?php echo $record['present_days']; ?></span></p>
                                                                        <p class="mb-1">Absent Days: <span class="fw-bold"><?php echo $record['absent_days']; ?></span></p>
                                                                        <p class="mb-1">Half Days: <span class="fw-bold"><?php echo $record['half_days']; ?></span></p>
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <p class="mb-1">Leave Days: <span class="fw-bold"><?php echo $record['leave_days']; ?></span></p>
                                                                        <p class="mb-1">Late Days: <span class="fw-bold"><?php echo $record['late_days']; ?></span></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-primary me-2" onclick="window.print();">
                                                    <i class="fas fa-print me-1"></i> Print
                                                </button>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Update Status Modal -->
                                <div class="modal fade" id="updateStatusModal<?php echo $record['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="post" action="index.php?page=salary">
                                                <input type="hidden" name="action" value="update_salary_status">
                                                <input type="hidden" name="salary_id" value="<?php echo $record['id']; ?>">
                                                
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Update Payment Status</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label for="status<?php echo $record['id']; ?>" class="form-label">Status <span class="text-danger">*</span></label>
                                                        <select class="form-select" id="status<?php echo $record['id']; ?>" name="status" required>
                                                            <option value="pending" <?php echo $record['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="paid" <?php echo $record['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                            <option value="cancelled" <?php echo $record['payment_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3 payment-fields<?php echo $record['payment_status'] === 'paid' ? '' : ' d-none'; ?>">
                                                        <label for="payment_date<?php echo $record['id']; ?>" class="form-label">Payment Date <span class="text-danger">*</span></label>
                                                        <input type="date" class="form-control" id="payment_date<?php echo $record['id']; ?>" name="payment_date" value="<?php echo $record['payment_date'] ? date('Y-m-d', strtotime($record['payment_date'])) : date('Y-m-d'); ?>">
                                                    </div>
                                                    
                                                    <div class="mb-3 payment-fields<?php echo $record['payment_status'] === 'paid' ? '' : ' d-none'; ?>">
                                                        <label for="payment_method<?php echo $record['id']; ?>" class="form-label">Payment Method</label>
                                                        <select class="form-select" id="payment_method<?php echo $record['id']; ?>" name="payment_method">
                                                            <option value="">Select Payment Method</option>
                                                            <option value="cash" <?php echo $record['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                                            <option value="bank_transfer" <?php echo $record['payment_method'] === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                                            <option value="cheque" <?php echo $record['payment_method'] === 'cheque' ? 'selected' : ''; ?>>Cheque</option>
                                                            <option value="online" <?php echo $record['payment_method'] === 'online' ? 'selected' : ''; ?>>Online Payment</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="note<?php echo $record['id']; ?>" class="form-label">Note</label>
                                                        <textarea class="form-control" id="note<?php echo $record['id']; ?>" name="note" rows="2"><?php echo htmlspecialchars($record['note'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="fas fa-save me-2"></i> Update Status
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Delete Salary Modal -->
                                <div class="modal fade" id="deleteSalaryModal<?php echo $record['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="post" action="index.php?page=salary">
                                                <input type="hidden" name="action" value="delete_salary">
                                                <input type="hidden" name="salary_id" value="<?php echo $record['id']; ?>">
                                                
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Confirm Delete</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="text-center mb-4">
                                                        <i class="fas fa-exclamation-triangle text-warning fa-4x mb-3"></i>
                                                        <h4>Are you sure you want to delete this salary record?</h4>
                                                        <p class="text-muted">
                                                            You are about to delete the salary record for <strong><?php echo htmlspecialchars($record['employee_name']); ?></strong> 
                                                            for <strong><?php echo get_month_name($record['month']) . ' ' . $record['year']; ?></strong>.
                                                            This action cannot be undone.
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="fas fa-trash-alt me-2"></i> Delete Record
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
                $pagination_url = 'index.php?page=salary&month=' . $month_filter . '&year=' . $year_filter . '&status=' . urlencode($status_filter) . '&search=' . urlencode($search) . '&page={page}';
                echo generate_pagination($current_page, $total_pages, $pagination_url);
                ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Generate Salary Modal -->
    <?php if (!empty($employees)): ?>
    <div class="modal fade" id="generateSalaryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="index.php?page=salary" id="generateSalaryForm">
                    <input type="hidden" name="action" value="generate_salary">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Generate Salary</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Generating salary will calculate based on attendance records and employee pay rates for the selected month.
                        </div>
                        
                        <div class="mb-3">
                            <label for="employee_id" class="form-label">Employee <span class="text-danger">*</span></label>
                            <select class="form-select <?php echo isset($errors['employee_id']) ? 'is-invalid' : ''; ?>" id="employee_id" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['name']); ?> (<?php echo htmlspecialchars($employee['position']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['employee_id'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['employee_id']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="month" class="form-label">Month <span class="text-danger">*</span></label>
                                <select class="form-select <?php echo isset($errors['month']) ? 'is-invalid' : ''; ?>" id="month" name="month" required>
                                    <?php foreach (get_months() as $num => $name): ?>
                                    <option value="<?php echo $num; ?>" <?php echo $month_filter === $num ? 'selected' : ''; ?>>
                                        <?php echo $name; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['month'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['month']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="year" class="form-label">Year <span class="text-danger">*</span></label>
                                <select class="form-select <?php echo isset($errors['year']) ? 'is-invalid' : ''; ?>" id="year" name="year" required>
                                    <?php foreach (get_years() as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo $year_filter === $year ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['year'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['year']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="bonus" class="form-label">Bonus</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                                    <input type="number" class="form-control <?php echo isset($errors['bonus']) ? 'is-invalid' : ''; ?>" id="bonus" name="bonus" min="0" step="0.01" value="0">
                                </div>
                                <?php if (isset($errors['bonus'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['bonus']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="deductions" class="form-label">Deductions</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                                    <input type="number" class="form-control <?php echo isset($errors['deductions']) ? 'is-invalid' : ''; ?>" id="deductions" name="deductions" min="0" step="0.01" value="0">
                                </div>
                                <?php if (isset($errors['deductions'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['deductions']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="note" class="form-label">Note</label>
                            <textarea class="form-control <?php echo isset($errors['note']) ? 'is-invalid' : ''; ?>" id="note" name="note" rows="2"></textarea>
                            <?php if (isset($errors['note'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['note']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calculator me-2"></i> Generate Salary
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Add page-specific JavaScript
$page_script = <<<SCRIPT
<script>
// Excel export function
function exportToExcel(tableId, fileName) {
    const table = document.getElementById(tableId);
    const workbook = XLSX.utils.table_to_book(table, {sheet: "Salary"});
    XLSX.writeFile(workbook, fileName + '.xlsx');
}

// Initialize form validation for generating salary
$(document).ready(function() {
    // Form validation rules
    const salaryRules = {
        employee_id: {
            required: 'Please select an employee'
        },
        month: {
            required: 'Month is required'
        },
        year: {
            required: 'Year is required'
        }
    };
    
    // Initialize validation for generate salary form
    $('#generateSalaryForm').on('submit', function(e) {
        if (!validateForm('generateSalaryForm', salaryRules)) {
            e.preventDefault();
        }
    });
    
    // Toggle payment fields based on status selection
    $('select[name="status"]').on('change', function() {
        const status = $(this).val();
        const paymentFields = $(this).closest('.modal-body').find('.payment-fields');
        
        if (status === 'paid') {
            paymentFields.removeClass('d-none');
        } else {
            paymentFields.addClass('d-none');
        }
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
SCRIPT;