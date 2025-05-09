<?php
/**
 * Reports page
 * Provides various reports for attendance, salary, and employee data
 */
error_reporting(1);
ini_set('display_errors', 1);
// Check login
require_login();

// Get filter parameters
$report_type = sanitize_input($_GET['type'] ?? 'attendance');
$employee_id = (int)($_GET['employee_id'] ?? 0);
$date_range = sanitize_input($_GET['date_range'] ?? 'this_month');
$start_date = sanitize_input($_GET['start_date'] ?? '');
$end_date = sanitize_input($_GET['end_date'] ?? '');
$status_filter = sanitize_input($_GET['status'] ?? 'all');

// Set default date range if not specified
if (empty($start_date) || empty($end_date)) {
    switch ($date_range) {
        case 'today':
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d');
            break;
        case 'yesterday':
            $start_date = date('Y-m-d', strtotime('-1 day'));
            $end_date = date('Y-m-d', strtotime('-1 day'));
            break;
        case 'this_week':
            // Start of week (Monday)
            $start_date = date('Y-m-d', strtotime('monday this week'));
            $end_date = date('Y-m-d');
            break;
        case 'last_week':
            $start_date = date('Y-m-d', strtotime('monday last week'));
            $end_date = date('Y-m-d', strtotime('sunday last week'));
            break;
        case 'this_month':
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-d');
            break;
        case 'last_month':
            $start_date = date('Y-m-01', strtotime('first day of last month'));
            $end_date = date('Y-m-t', strtotime('last day of last month'));
            break;
        case 'this_year':
            $start_date = date('Y-01-01');
            $end_date = date('Y-m-d');
            break;
        case 'last_year':
            $start_date = date('Y-01-01', strtotime('-1 year'));
            $end_date = date('Y-12-31', strtotime('-1 year'));
            break;
    }
}

// Get list of employees for filter
$employees_query = "SELECT id, name, position FROM employees ORDER BY name";
$employees = db_fetch_all($employees_query, "", []);

// Generate report based on type
$report_data = [];
$report_title = '';
$report_subtitle = format_date($start_date) . ' - ' . format_date($end_date);
$report_columns = [];

// Attendance Report
if ($report_type === 'attendance') {
    $report_title = 'Attendance Report';
    $report_columns = ['Employee', 'Date', 'Status', 'Check In', 'Check Out', 'Work Hours', 'Overtime', 'Note'];
    
    // Build attendance query
    $query_params = [$start_date, $end_date];
    $query_types = "ss";
    
    $query = "SELECT a.*, e.name as employee_name, e.position,
                     CASE 
                         WHEN a.check_in_time IS NOT NULL AND a.check_out_time IS NOT NULL 
                         THEN TIME_TO_SEC(TIMEDIFF(a.check_out_time, a.check_in_time))/3600 
                         ELSE NULL 
                     END as hours_worked
              FROM attendance a
              JOIN employees e ON a.employee_id = e.id
              WHERE a.date BETWEEN ? AND ?";
    
    if ($employee_id > 0) {
        $query .= " AND a.employee_id = ?";
        $query_params[] = $employee_id;
        $query_types .= "i";
    }
    
    if ($status_filter !== 'all') {
        $query .= " AND a.status = ?";
        $query_params[] = $status_filter;
        $query_types .= "s";
    }
    
    $query .= " ORDER BY a.date DESC, e.name ASC";
    
    $report_data = db_fetch_all($query, $query_types, $query_params);
    
    // Calculate summary
    $total_records = count($report_data);
    $present_count = 0;
    $absent_count = 0;
    $half_day_count = 0;
    $late_count = 0;
    $leave_count = 0;
    $total_work_hours = 0;
    $total_overtime = 0;
    
    foreach ($report_data as $record) {
        switch ($record['status']) {
            case 'present':
                $present_count++;
                break;
            case 'absent':
                $absent_count++;
                break;
            case 'half-day':
                $half_day_count++;
                break;
            case 'late':
                $late_count++;
                break;
            case 'leave':
                $leave_count++;
                break;
        }
        
        $total_work_hours += (float)($record['work_hours'] ?? 0);
        $total_overtime += (float)($record['overtime_hours'] ?? 0);
    }
    
    // For a specific employee, adjust the subtitle
    if ($employee_id > 0) {
        $employee_query = "SELECT name, position FROM employees WHERE id = ?";
        $employee = db_fetch_row($employee_query, "i", [$employee_id]);
        
        if ($employee) {
            $report_subtitle = "{$employee['name']} ({$employee['position']}) - " . $report_subtitle;
        }
    }
}

// Salary Report
elseif ($report_type === 'salary') {
    $report_title = 'Salary Report';
    $report_columns = ['Employee', 'Period', 'Basic Salary', 'Overtime Pay', 'Bonus', 'Deductions', 'Total Salary', 'Status', 'Payment Date'];
    
    // For salary report, convert date range to month range
    $start_month = date('n', strtotime($start_date));
    $start_year = date('Y', strtotime($start_date));
    $end_month = date('n', strtotime($end_date));
    $end_year = date('Y', strtotime($end_date));
    
    // Build salary query
    $query = "SELECT s.*, e.name as employee_name, e.position
              FROM salary s
              JOIN employees e ON s.employee_id = e.id
              WHERE (s.year > ? OR (s.year = ? AND s.month >= ?))
                AND (s.year < ? OR (s.year = ? AND s.month <= ?))";
                
    $query_params = [$start_year, $start_year, $start_month, $end_year, $end_year, $end_month];
    $query_types = "iiiiii";
    
    if ($employee_id > 0) {
        $query .= " AND s.employee_id = ?";
        $query_params[] = $employee_id;
        $query_types .= "i";
    }
    
    if ($status_filter !== 'all') {
        $query .= " AND s.payment_status = ?";
        $query_params[] = $status_filter;
        $query_types .= "s";
    }
    
    $query .= " ORDER BY s.year DESC, s.month DESC, e.name ASC";
    
    $report_data = db_fetch_all($query, $query_types, $query_params);
    
    // Calculate summary
    $total_records = count($report_data);
    $total_basic = 0;
    $total_overtime = 0;
    $total_bonus = 0;
    $total_deductions = 0;
    $total_salary = 0;
    $paid_count = 0;
    $pending_count = 0;
    $cancelled_count = 0;
    
    foreach ($report_data as $record) {
        $total_basic += (float)$record['basic_salary'];
        $total_overtime += (float)$record['overtime_pay'];
        $total_bonus += (float)$record['bonus'];
        $total_deductions += (float)$record['deductions'];
        $total_salary += (float)$record['total_salary'];
        
        switch ($record['payment_status']) {
            case 'paid':
                $paid_count++;
                break;
            case 'pending':
                $pending_count++;
                break;
            case 'cancelled':
                $cancelled_count++;
                break;
        }
    }
    
    // For a specific employee, adjust the subtitle
    if ($employee_id > 0) {
        $employee_query = "SELECT name, position FROM employees WHERE id = ?";
        $employee = db_fetch_row($employee_query, "i", [$employee_id]);
        
        if ($employee) {
            $report_subtitle = "{$employee['name']} ({$employee['position']}) - " . $report_subtitle;
        }
    }
}

// Employee Report
elseif ($report_type === 'employee') {
    $report_title = 'Employee Report';
    $report_columns = ['Name', 'Position', 'Email', 'Phone', 'Hire Date', 'Status', 'Salary Rate'];
    
    // Build employee query
    $query = "SELECT * FROM employees WHERE 1=1";
    $query_params = [];
    $query_types = "";
    
    if ($employee_id > 0) {
        $query .= " AND id = ?";
        $query_params[] = $employee_id;
        $query_types .= "i";
    }
    
    if ($status_filter !== 'all') {
        $query .= " AND status = ?";
        $query_params[] = $status_filter;
        $query_types .= "s";
    }
    
    $query .= " ORDER BY name ASC";
    
    $report_data = db_fetch_all($query, $query_types, $query_params);
    
    // Calculate summary
    $total_records = count($report_data);
    $active_count = 0;
    $inactive_count = 0;
    $on_leave_count = 0;
    $terminated_count = 0;
    
    foreach ($report_data as $record) {
        switch ($record['status']) {
            case 'active':
                $active_count++;
                break;
            case 'inactive':
                $inactive_count++;
                break;
            case 'on_leave':
                $on_leave_count++;
                break;
            case 'terminated':
                $terminated_count++;
                break;
        }
    }
    
    $report_subtitle = "Total Employees: $total_records";
}

// Summary Report
elseif ($report_type === 'summary') {
    $report_title = 'Summary Report';
    
    // Calculate attendance statistics for the period
    $attendance_query = "SELECT 
                           COUNT(*) as total_records,
                           SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                           SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                           SUM(CASE WHEN status = 'half-day' THEN 1 ELSE 0 END) as half_day_count,
                           SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
                           SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_count,
                           SUM(work_hours) as total_work_hours,
                           SUM(overtime_hours) as total_overtime
                         FROM attendance
                         WHERE date BETWEEN ? AND ?";
    
    $attendance_params = [$start_date, $end_date];
    $attendance_types = "ss";
    
    if ($employee_id > 0) {
        $attendance_query .= " AND employee_id = ?";
        $attendance_params[] = $employee_id;
        $attendance_types .= "i";
    }
    
    $attendance_stats = db_fetch_row($attendance_query, $attendance_types, $attendance_params);
    
    // Calculate salary statistics for the period
    // For salary report, convert date range to month range
    $start_month = date('n', strtotime($start_date));
    $start_year = date('Y', strtotime($start_date));
    $end_month = date('n', strtotime($end_date));
    $end_year = date('Y', strtotime($end_date));
    
    $salary_query = "SELECT 
                       COUNT(*) as total_records,
                       SUM(basic_salary) as total_basic,
                       SUM(overtime_pay) as total_overtime,
                       SUM(bonus) as total_bonus,
                       SUM(deductions) as total_deductions,
                       SUM(total_salary) as total_salary,
                       SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                       SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                       SUM(CASE WHEN payment_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
                     FROM salary
                     WHERE (year > ? OR (year = ? AND month >= ?))
                       AND (year < ? OR (year = ? AND month <= ?))";
                       
    $salary_params = [$start_year, $start_year, $start_month, $end_year, $end_year, $end_month];
    $salary_types = "iiiiii";
    
    if ($employee_id > 0) {
        $salary_query .= " AND employee_id = ?";
        $salary_params[] = $employee_id;
        $salary_types .= "i";
    }
    
    $salary_stats = db_fetch_row($salary_query, $salary_types, $salary_params);
    
    // Get employee count
    $employee_query = "SELECT COUNT(*) as total FROM employees";
    $employee_params = [];
    $employee_types = "";
    
    if ($status_filter !== 'all') {
        $employee_query .= " WHERE status = ?";
        $employee_params[] = $status_filter;
        $employee_types .= "s";
    }
    
    $employee_stats = db_fetch_row($employee_query, $employee_types, $employee_params);
    
    // For a specific employee, adjust the subtitle
    if ($employee_id > 0) {
        $employee_query = "SELECT name, position FROM employees WHERE id = ?";
        $employee = db_fetch_row($employee_query, "i", [$employee_id]);
        
        if ($employee) {
            $report_subtitle = "{$employee['name']} ({$employee['position']}) - " . $report_subtitle;
        }
    }
}
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Reports</h2>
        <div>
            <button type="button" class="btn btn-outline-primary me-2" onclick="printElement('reportContainer'); return false;">
                <i class="fas fa-print me-1"></i> Print
            </button>
            <button type="button" class="btn btn-outline-success" onclick="exportToExcel('reportTable', '<?php echo $report_title . '_' . date('Y-m-d'); ?>'); return false;">
                <i class="fas fa-file-excel me-1"></i> Export to Excel
            </button>
        </div>
    </div>
    
    <!-- Report Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="index.php" id="reportForm" class="row g-3">
                <input type="hidden" name="page" value="reports">
                
                <div class="col-md-2">
                    <label for="type" class="form-label">Report Type</label>
                    <select class="form-select" id="type" name="type" onchange="updateReportForm()">
                        <option value="attendance" <?php echo $report_type === 'attendance' ? 'selected' : ''; ?>>Attendance Report</option>
                        <option value="salary" <?php echo $report_type === 'salary' ? 'selected' : ''; ?>>Salary Report</option>
                        <option value="employee" <?php echo $report_type === 'employee' ? 'selected' : ''; ?>>Employee Report</option>
                        <option value="summary" <?php echo $report_type === 'summary' ? 'selected' : ''; ?>>Summary Report</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="employee_id" class="form-label">Employee</label>
                    <select class="form-select" id="employee_id" name="employee_id">
                        <option value="0">All Employees</option>
                        <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo $employee['id']; ?>" <?php echo $employee_id === $employee['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($employee['name']); ?> (<?php echo htmlspecialchars($employee['position']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="date_range" class="form-label">Date Range</label>
                    <select class="form-select" id="date_range" name="date_range" onchange="updateDateRange()">
                        <option value="today" <?php echo $date_range === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="yesterday" <?php echo $date_range === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="this_week" <?php echo $date_range === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="last_week" <?php echo $date_range === 'last_week' ? 'selected' : ''; ?>>Last Week</option>
                        <option value="this_month" <?php echo $date_range === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="last_month" <?php echo $date_range === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                        <option value="this_year" <?php echo $date_range === 'this_year' ? 'selected' : ''; ?>>This Year</option>
                        <option value="last_year" <?php echo $date_range === 'last_year' ? 'selected' : ''; ?>>Last Year</option>
                        <option value="custom" <?php echo $date_range === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                
                <div class="col-md-1">
                    <label class="form-label d-none d-md-block">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Generate
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="reportContainer">
        <!-- Report Header -->
        <div class="row mb-4">
            <div class="col-12 text-center">
                <h3 class="mb-1"><?php echo $report_title; ?></h3>
                <p class="text-muted"><?php echo $report_subtitle; ?></p>
            </div>
        </div>
        
        <?php if ($report_type === 'attendance'): ?>
        <!-- Attendance Report -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Attendance Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <canvas id="attendanceChart" height="250"></canvas>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex flex-column justify-content-center h-100">
                                    <div class="text-center mb-4">
                                        <h3><?php echo $total_records; ?></h3>
                                        <p class="text-muted small mb-0">Total attendance records</p>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="small">Present (<?php echo $present_count; ?>)</span>
                                            <span class="small fw-bold"><?php echo $total_records > 0 ? round(($present_count / $total_records) * 100) : 0; ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $total_records > 0 ? round(($present_count / $total_records) * 100) : 0; ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="small">Late (<?php echo $late_count; ?>)</span>
                                            <span class="small fw-bold"><?php echo $total_records > 0 ? round(($late_count / $total_records) * 100) : 0; ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-warning" style="width: <?php echo $total_records > 0 ? round(($late_count / $total_records) * 100) : 0; ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="small">Absent (<?php echo $absent_count; ?>)</span>
                                            <span class="small fw-bold"><?php echo $total_records > 0 ? round(($absent_count / $total_records) * 100) : 0; ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-danger" style="width: <?php echo $total_records > 0 ? round(($absent_count / $total_records) * 100) : 0; ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="small">Half-Day (<?php echo $half_day_count; ?>)</span>
                                            <span class="small fw-bold"><?php echo $total_records > 0 ? round(($half_day_count / $total_records) * 100) : 0; ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-info" style="width: <?php echo $total_records > 0 ? round(($half_day_count / $total_records) * 100) : 0; ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="small">Leave (<?php echo $leave_count; ?>)</span>
                                            <span class="small fw-bold"><?php echo $total_records > 0 ? round(($leave_count / $total_records) * 100) : 0; ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-secondary" style="width: <?php echo $total_records > 0 ? round(($leave_count / $total_records) * 100) : 0; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <p class="mb-0"><strong>Total Work Hours:</strong> <?php echo number_format($total_work_hours, 2); ?> hours</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-0"><strong>Total Overtime:</strong> <?php echo number_format($total_overtime, 2); ?> hours</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Records Table -->
        <div class="card mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Attendance Records</h5>
            </div>
            <div class="card-body">
                <?php if (empty($report_data)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-clipboard-check fa-4x text-muted mb-3"></i>
                    <h4>No attendance records found</h4>
                    <p class="text-muted">Try adjusting your search filters</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="reportTable">
                        <thead class="table-light">
                            <tr>
                                <?php foreach ($report_columns as $column): ?>
                                <th><?php echo $column; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $record): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle avatar-xs me-2">
                                            <span class="initials"><?php echo get_initials($record['employee_name']); ?></span>
                                        </div>
                                        <div>
                                            <div class="fw-medium"><?php echo htmlspecialchars($record['employee_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($record['position']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo format_date($record['date']); ?></td>
                                <td><?php echo get_status_badge($record['status']); ?></td>
                                <td><?php echo format_time($record['check_in_time']); ?></td>
                                <td><?php echo format_time($record['check_out_time']); ?></td>
                                <td>
                                    <?php if ($record['work_hours']): ?>
                                    <?php echo number_format($record['work_hours'], 2); ?> hrs
                                    <?php else: ?>
                                    —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['overtime_hours'] > 0): ?>
                                    <span class="text-success"><?php echo number_format($record['overtime_hours'], 2); ?> hrs</span>
                                    <?php else: ?>
                                    —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($record['note'])): ?>
                                    <span data-tippy-content="<?php echo htmlspecialchars($record['note']); ?>">
                                        <i class="fas fa-sticky-note"></i>
                                    </span>
                                    <?php else: ?>
                                    —
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($report_type === 'salary'): ?>
        <!-- Salary Report -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Salary Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <canvas id="salaryChart" height="250"></canvas>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">Total Summary</h5>
                                        <div class="mt-3">
                                            <div class="d-flex justify-content-between mb-3">
                                                <span>Basic Salary:</span>
                                                <span class="fw-bold"><?php echo format_currency($total_basic); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-3">
                                                <span>Overtime Pay:</span>
                                                <span class="text-success"><?php echo format_currency($total_overtime); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-3">
                                                <span>Bonuses:</span>
                                                <span class="text-info"><?php echo format_currency($total_bonus); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-3">
                                                <span>Deductions:</span>
                                                <span class="text-danger">-<?php echo format_currency($total_deductions); ?></span>
                                            </div>
                                            <hr>
                                            <div class="d-flex justify-content-between fw-bold">
                                                <span>Total:</span>
                                                <span class="fs-5"><?php echo format_currency($total_salary); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <div class="card text-center border-0 shadow-sm">
                                    <div class="card-body">
                                        <h6 class="text-success">Paid</h6>
                                        <h4><?php echo $paid_count; ?></h4>
                                        <p class="small text-muted mb-0"><?php echo $total_records > 0 ? round(($paid_count / $total_records) * 100) : 0; ?>% of total</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center border-0 shadow-sm">
                                    <div class="card-body">
                                        <h6 class="text-warning">Pending</h6>
                                        <h4><?php echo $pending_count; ?></h4>
                                        <p class="small text-muted mb-0"><?php echo $total_records > 0 ? round(($pending_count / $total_records) * 100) : 0; ?>% of total</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center border-0 shadow-sm">
                                    <div class="card-body">
                                        <h6 class="text-danger">Cancelled</h6>
                                        <h4><?php echo $cancelled_count; ?></h4>
                                        <p class="small text-muted mb-0"><?php echo $total_records > 0 ? round(($cancelled_count / $total_records) * 100) : 0; ?>% of total</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Salary Records Table -->
        <div class="card mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Salary Records</h5>
            </div>
            <div class="card-body">
                <?php if (empty($report_data)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-money-bill-wave fa-4x text-muted mb-3"></i>
                    <h4>No salary records found</h4>
                    <p class="text-muted">Try adjusting your search filters</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="reportTable">
                        <thead class="table-light">
                            <tr>
                                <?php foreach ($report_columns as $column): ?>
                                <th><?php echo $column; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $record): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle avatar-xs me-2">
                                            <span class="initials"><?php echo get_initials($record['employee_name']); ?></span>
                                        </div>
                                        <div>
                                            <div class="fw-medium"><?php echo htmlspecialchars($record['employee_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($record['position']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo get_month_name($record['month']) . ' ' . $record['year']; ?></td>
                                <td><?php echo format_currency($record['basic_salary']); ?></td>
                                <td><?php echo format_currency($record['overtime_pay']); ?></td>
                                <td><?php echo format_currency($record['bonus']); ?></td>
                                <td><?php echo format_currency($record['deductions']); ?></td>
                                <td><?php echo format_currency($record['total_salary']); ?></td>
                                <td><?php echo get_payment_badge($record['payment_status']); ?></td>
                                <td>
                                    <?php if ($record['payment_date']): ?>
                                    <?php echo format_date($record['payment_date']); ?>
                                    <?php else: ?>
                                    —
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($report_type === 'employee'): ?>
        <!-- Employee Report -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Employee Status Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <canvas id="employeeChart" height="250"></canvas>
                            </div>
                            <div class="col-md-6">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="card text-center border-0 shadow-sm">
                                            <div class="card-body">
                                                <h6 class="text-success">Active</h6>
                                                <h4><?php echo $active_count; ?></h4>
                                                <p class="small text-muted mb-0"><?php echo $total_records > 0 ? round(($active_count / $total_records) * 100) : 0; ?>% of total</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="card text-center border-0 shadow-sm">
                                            <div class="card-body">
                                                <h6 class="text-warning">Inactive</h6>
                                                <h4><?php echo $inactive_count; ?></h4>
                                                <p class="small text-muted mb-0"><?php echo $total_records > 0 ? round(($inactive_count / $total_records) * 100) : 0; ?>% of total</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="card text-center border-0 shadow-sm">
                                            <div class="card-body">
                                                <h6 class="text-info">On Leave</h6>
                                                <h4><?php echo $on_leave_count; ?></h4>
                                                <p class="small text-muted mb-0"><?php echo $total_records > 0 ? round(($on_leave_count / $total_records) * 100) : 0; ?>% of total</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="card text-center border-0 shadow-sm">
                                            <div class="card-body">
                                                <h6 class="text-danger">Terminated</h6>
                                                <h4><?php echo $terminated_count; ?></h4>
                                                <p class="small text-muted mb-0"><?php echo $total_records > 0 ? round(($terminated_count / $total_records) * 100) : 0; ?>% of total</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee Records Table -->
        <div class="card mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Employee List</h5>
            </div>
            <div class="card-body">
                <?php if (empty($report_data)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-4x text-muted mb-3"></i>
                    <h4>No employee records found</h4>
                    <p class="text-muted">Try adjusting your search filters</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="reportTable">
                        <thead class="table-light">
                            <tr>
                                <?php foreach ($report_columns as $column): ?>
                                <th><?php echo $column; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $record): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle avatar-xs me-2">
                                            <span class="initials"><?php echo get_initials($record['name']); ?></span>
                                        </div>
                                        <div class="fw-medium"><?php echo htmlspecialchars($record['name']); ?></div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($record['position']); ?></td>
                                <td><?php echo htmlspecialchars($record['email']); ?></td>
                                <td><?php echo htmlspecialchars($record['phone'] ?: '—'); ?></td>
                                <td><?php echo format_date($record['hire_date']); ?></td>
                                <td>
                                    <?php
                                    $status_class = 'secondary';
                                    switch ($record['status']) {
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
                                        <?php echo ucfirst(str_replace('_', ' ', $record['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($record['monthly_rate'])): ?>
                                    <?php echo format_currency($record['monthly_rate']); ?> / month
                                    <?php elseif (!empty($record['hourly_rate'])): ?>
                                    <?php echo format_currency($record['hourly_rate']); ?> / hour
                                    <?php else: ?>
                                    —
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($report_type === 'summary'): ?>
        <!-- Summary Report -->
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Attendance Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h1><?php echo $attendance_stats['total_records'] ?? 0; ?></h1>
                            <p class="text-muted">Total Attendance Records</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <canvas id="attendanceChart" height="200"></canvas>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Present:</span>
                                        <span class="fw-medium"><?php echo $attendance_stats['present_count'] ?? 0; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Absent:</span>
                                        <span class="fw-medium"><?php echo $attendance_stats['absent_count'] ?? 0; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Late:</span>
                                        <span class="fw-medium"><?php echo $attendance_stats['late_count'] ?? 0; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Half-Day:</span>
                                        <span class="fw-medium"><?php echo $attendance_stats['half_day_count'] ?? 0; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Leave:</span>
                                        <span class="fw-medium"><?php echo $attendance_stats['leave_count'] ?? 0; ?></span>
                                    </div>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <span>Total Work Hours:</span>
                                    <span class="fw-medium"><?php echo number_format($attendance_stats['total_work_hours'] ?? 0, 2); ?> hrs</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Total Overtime:</span>
                                    <span class="fw-medium"><?php echo number_format($attendance_stats['total_overtime'] ?? 0, 2); ?> hrs</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Salary Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h1><?php echo format_currency($salary_stats['total_salary'] ?? 0); ?></h1>
                            <p class="text-muted">Total Salary Amount</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <canvas id="salaryChart" height="200"></canvas>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Basic Salary:</span>
                                        <span class="fw-medium"><?php echo format_currency($salary_stats['total_basic'] ?? 0); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Overtime Pay:</span>
                                        <span class="fw-medium"><?php echo format_currency($salary_stats['total_overtime'] ?? 0); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Bonus:</span>
                                        <span class="fw-medium"><?php echo format_currency($salary_stats['total_bonus'] ?? 0); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Deductions:</span>
                                        <span class="fw-medium"><?php echo format_currency($salary_stats['total_deductions'] ?? 0); ?></span>
                                    </div>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <span>Paid Records:</span>
                                    <span class="fw-medium"><?php echo $salary_stats['paid_count'] ?? 0; ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Pending Records:</span>
                                    <span class="fw-medium"><?php echo $salary_stats['pending_count'] ?? 0; ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Cancelled Records:</span>
                                    <span class="fw-medium"><?php echo $salary_stats['cancelled_count'] ?? 0; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Employee Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h1><?php echo $employee_stats['total'] ?? 0; ?></h1>
                                    <p class="text-muted">Total Employees</p>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="row">
                                    <?php
                                    // Get employee status counts for chart
                                    $status_query = "SELECT status, COUNT(*) as count FROM employees GROUP BY status";
                                    $status_data = db_fetch_all($status_query, "", []);
                                    
                                    $active_count = 0;
                                    $inactive_count = 0;
                                    $on_leave_count = 0;
                                    $terminated_count = 0;
                                    
                                    foreach ($status_data as $status) {
                                        switch ($status['status']) {
                                            case 'active':
                                                $active_count = $status['count'];
                                                break;
                                            case 'inactive':
                                                $inactive_count = $status['count'];
                                                break;
                                            case 'on_leave':
                                                $on_leave_count = $status['count'];
                                                break;
                                            case 'terminated':
                                                $terminated_count = $status['count'];
                                                break;
                                        }
                                    }
                                    ?>
                                    <div class="col-md-6 col-6 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body py-2 text-center">
                                                <div class="text-success">Active</div>
                                                <h4 class="mb-0"><?php echo $active_count; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-6 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body py-2 text-center">
                                                <div class="text-warning">Inactive</div>
                                                <h4 class="mb-0"><?php echo $inactive_count; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-6 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body py-2 text-center">
                                                <div class="text-info">On Leave</div>
                                                <h4 class="mb-0"><?php echo $on_leave_count; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-6 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body py-2 text-center">
                                                <div class="text-danger">Terminated</div>
                                                <h4 class="mb-0"><?php echo $terminated_count; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Report Footer -->
        <div class="row mt-5 mb-4">
            <div class="col-12 text-center">
                <p class="text-muted small">Report generated on <?php echo date('F j, Y g:i A'); ?></p>
            </div>
        </div>
    </div>
</div>

<?php
// Add page-specific JavaScript
$page_script = <<<SCRIPT
<script>
// Excel export function
function exportToExcel(tableId, fileName) {
    const table = document.getElementById(tableId);
    if (!table) {
        alert('No table data to export');
        return;
    }
    
    const workbook = XLSX.utils.table_to_book(table, {sheet: "Report"});
    XLSX.writeFile(workbook, fileName + '.xlsx');
}

// Update date range based on selection
function updateDateRange() {
    const dateRange = document.getElementById('date_range').value;
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    const today = new Date();
    let startDate = new Date();
    let endDate = new Date();
    
    switch (dateRange) {
        case 'today':
            // Start and end are both today
            break;
        case 'yesterday':
            startDate.setDate(today.getDate() - 1);
            endDate.setDate(today.getDate() - 1);
            break;
        case 'this_week':
            // Start of week (Monday)
            startDate.setDate(today.getDate() - today.getDay() + (today.getDay() === 0 ? -6 : 1));
            break;
        case 'last_week':
            startDate.setDate(today.getDate() - today.getDay() - 6);
            endDate.setDate(today.getDate() - today.getDay());
            break;
        case 'this_month':
            startDate.setDate(1);
            break;
        case 'last_month':
            startDate.setMonth(today.getMonth() - 1);
            startDate.setDate(1);
            endDate.setDate(0);
            break;
        case 'this_year':
            startDate.setMonth(0);
            startDate.setDate(1);
            break;
        case 'last_year':
            startDate.setFullYear(today.getFullYear() - 1);
            startDate.setMonth(0);
            startDate.setDate(1);
            endDate.setFullYear(today.getFullYear() - 1);
            endDate.setMonth(11);
            endDate.setDate(31);
            break;
        case 'custom':
            // Don't change the dates for custom range
            return;
    }
    
    startDateInput.value = startDate.toISOString().split('T')[0];
    endDateInput.value = endDate.toISOString().split('T')[0];
}

// Update form fields based on report type
function updateReportForm() {
    const reportType = document.getElementById('type').value;
    const statusFilter = document.getElementById('status_filter');
    
    if (reportType === 'employee') {
        if (statusFilter) {
            statusFilter.innerHTML = `
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="on_leave">On Leave</option>
                <option value="terminated">Terminated</option>
            `;
        }
    } else if (reportType === 'attendance') {
        if (statusFilter) {
            statusFilter.innerHTML = `
                <option value="all">All Status</option>
                <option value="present">Present</option>
                <option value="absent">Absent</option>
                <option value="half-day">Half Day</option>
                <option value="late">Late</option>
                <option value="leave">Leave</option>
            `;
        }
    } else if (reportType === 'salary') {
        if (statusFilter) {
            statusFilter.innerHTML = `
                <option value="all">All Status</option>
                <option value="paid">Paid</option>
                <option value="pending">Pending</option>
                <option value="cancelled">Cancelled</option>
            `;
        }
    }
}

// Initialize document
document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts if they exist
    <?php if ($report_type === 'attendance'): ?>
    const attendanceCtx = document.getElementById('attendanceChart');
    if (attendanceCtx) {
        new Chart(attendanceCtx, {
            type: 'pie',
            data: {
                labels: ['Present', 'Late', 'Absent', 'Half-Day', 'Leave'],
                datasets: [{
                    data: [
                        <?php echo $present_count; ?>,
                        <?php echo $late_count; ?>,
                        <?php echo $absent_count; ?>,
                        <?php echo $half_day_count; ?>,
                        <?php echo $leave_count; ?>
                    ],
                    backgroundColor: [
                        '#1cc88a', // success
                        '#f6c23e', // warning
                        '#e74a3b', // danger
                        '#36b9cc', // info
                        '#6c757d'  // secondary
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });
    }
    <?php endif; ?>
    
    <?php if ($report_type === 'salary'): ?>
    const salaryCtx = document.getElementById('salaryChart');
    if (salaryCtx) {
        new Chart(salaryCtx, {
            type: 'doughnut',
            data: {
                labels: ['Basic Salary', 'Overtime Pay', 'Bonus', 'Deductions'],
                datasets: [{
                    data: [
                        <?php echo $total_basic; ?>,
                        <?php echo $total_overtime; ?>,
                        <?php echo $total_bonus; ?>,
                        <?php echo $total_deductions; ?>
                    ],
                    backgroundColor: [
                        '#4e73df', // primary
                        '#1cc88a', // success
                        '#36b9cc', // info
                        '#e74a3b'  // danger
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });
    }
    <?php endif; ?>
    
    <?php if ($report_type === 'employee'): ?>
    const employeeCtx = document.getElementById('employeeChart');
    if (employeeCtx) {
        new Chart(employeeCtx, {
            type: 'pie',
            data: {
                labels: ['Active', 'Inactive', 'On Leave', 'Terminated'],
                datasets: [{
                    data: [
                        <?php echo $active_count; ?>,
                        <?php echo $inactive_count; ?>,
                        <?php echo $on_leave_count; ?>,
                        <?php echo $terminated_count; ?>
                    ],
                    backgroundColor: [
                        '#1cc88a', // success
                        '#f6c23e', // warning
                        '#36b9cc', // info
                        '#e74a3b'  // danger
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });
    }
    <?php endif; ?>
    
    <?php if ($report_type === 'summary'): ?>
    const attendanceCtx = document.getElementById('attendanceChart');
    if (attendanceCtx) {
        new Chart(attendanceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Late', 'Half-Day', 'Leave'],
                datasets: [{
                    data: [
                        <?php echo {$attendance_stats['present_count']} ?? 0; ?>,
                        <?php echo {$attendance_stats['absent_count']} ?? 0; ?>,
                        <?php echo {$attendance_stats['late_count']} ?? 0; ?>,
                        <?php echo {$attendance_stats['half_day_count']} ?? 0; ?>,
                        <?php echo {$attendance_stats['leave_count']} ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#1cc88a', // success
                        '#e74a3b', // danger
                        '#f6c23e', // warning
                        '#36b9cc', // info
                        '#6c757d'  // secondary
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
    
    const salaryCtx = document.getElementById('salaryChart');
    if (salaryCtx) {
        new Chart(salaryCtx, {
            type: 'doughnut',
            data: {
                labels: ['Basic', 'Overtime', 'Bonus', 'Deductions'],
                datasets: [{
                    data: [
                        <?php echo {$salary_stats['total_basic']} ?? 0; ?>,
                        <?php echo {$salary_stats['total_overtime']} ?? 0; ?>,
                        <?php echo {$salary_stats['total_bonus']} ?? 0; ?>,
                        <?php echo {$salary_stats['total_deductions']} ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#4e73df', // primary
                        '#1cc88a', // success
                        '#36b9cc', // info
                        '#e74a3b'  // danger
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
    <?php endif; ?>
});
</script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
SCRIPT;