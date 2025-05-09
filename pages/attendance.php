<?php
/**
 * Attendance page
 * Handles attendance tracking, marking, and management
 */

// Check login
require_login();

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Mark attendance for today
    if ($action === 'mark_attendance') {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $status = sanitize_input($_POST['status'] ?? '');
        $check_in_time = sanitize_input($_POST['check_in_time'] ?? '');
        $check_out_time = sanitize_input($_POST['check_out_time'] ?? '');
        $note = sanitize_input($_POST['note'] ?? '');
        $date = sanitize_input($_POST['date'] ?? date('Y-m-d'));
        
        $errors = [];
        
        // Basic validation
        if (!$employee_id) {
            $errors['employee_id'] = 'Employee is required';
        }
        
        if (!in_array($status, ['present', 'absent', 'half-day', 'late', 'leave'])) {
            $errors['status'] = 'Invalid status';
        }
        
        // Check if attendance already exists for this employee on this date
        $existing_query = "SELECT id FROM attendance WHERE employee_id = ? AND date = ?";
        $existing = db_fetch_row($existing_query, "is", [$employee_id, $date]);
        
        if ($existing) {
            // Update existing attendance
            $query = "UPDATE attendance SET 
                        status = ?, 
                        check_in_time = ?, 
                        check_out_time = ?, 
                        note = ?";
            
            $params = [$status, $check_in_time ?: null, $check_out_time ?: null, $note ?: null];
            $types = "ssss";
            
            // Calculate work hours if both check-in and check-out times are provided
            if ($check_in_time && $check_out_time) {
                $work_hours = calculate_hours_difference($check_in_time, $check_out_time);
                
                // Get employee's standard work hours
                $employee_query = "SELECT work_hours_per_day FROM employees WHERE id = ?";
                $employee = db_fetch_row($employee_query, "i", [$employee_id]);
                $standard_hours = $employee ? $employee['work_hours_per_day'] : DEFAULT_WORKING_HOURS;
                
                // Check if overtime
                $is_overtime = $work_hours > $standard_hours;
                $overtime_hours = $is_overtime ? calculate_overtime($work_hours, $standard_hours) : 0;
                
                $query .= ", work_hours = ?, is_overtime = ?, overtime_hours = ?";
                $params[] = $work_hours;
                $params[] = $is_overtime ? 1 : 0;
                $params[] = $overtime_hours;
                $types .= "ddd";
            }
            
            $query .= " WHERE id = ?";
            $params[] = $existing['id'];
            $types .= "i";
            
            $result = db_update($query, $types, $params, ['attendance', 'attendance_' . $date]);
            
            if ($result) {
                // Get employee name for activity log
                $name_query = "SELECT name FROM employees WHERE id = ?";
                $employee = db_fetch_row($name_query, "i", [$employee_id]);
                $employee_name = $employee ? $employee['name'] : "Employee #$employee_id";
                
                // Log activity
                log_activity('update', "Updated attendance for $employee_name on $date", $_SESSION['user_id'], $employee_id);
                
                set_flash_message('success', "Attendance updated for $employee_name");
                header('Location: index.php?page=attendance');
                exit;
            } else {
                set_flash_message('error', 'Failed to update attendance. Please try again.');
            }
        } else {
            // Create new attendance record
            if (empty($errors)) {
                $query = "INSERT INTO attendance (
                            employee_id, date, status, check_in_time, check_out_time, note
                        ) VALUES (?, ?, ?, ?, ?, ?)";
                
                $params = [$employee_id, $date, $status, $check_in_time ?: null, $check_out_time ?: null, $note ?: null];
                $types = "isssss";
                
                // Calculate work hours if both check-in and check-out times are provided
                if ($check_in_time && $check_out_time) {
                    $work_hours = calculate_hours_difference($check_in_time, $check_out_time);
                    
                    // Get employee's standard work hours
                    $employee_query = "SELECT work_hours_per_day FROM employees WHERE id = ?";
                    $employee = db_fetch_row($employee_query, "i", [$employee_id]);
                    $standard_hours = $employee ? $employee['work_hours_per_day'] : DEFAULT_WORKING_HOURS;
                    
                    // Check if overtime
                    $is_overtime = $work_hours > $standard_hours;
                    $overtime_hours = $is_overtime ? calculate_overtime($work_hours, $standard_hours) : 0;
                    
                    $query = "INSERT INTO attendance (
                                employee_id, date, status, check_in_time, check_out_time, 
                                work_hours, is_overtime, overtime_hours, note
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $params = [
                        $employee_id, $date, $status, $check_in_time, $check_out_time,
                        $work_hours, $is_overtime ? 1 : 0, $overtime_hours, $note ?: null
                    ];
                    $types = "issssddds";
                }
                
                $attendance_id = db_insert($query, $types, $params, ['attendance', 'attendance_' . $date]);
                
                if ($attendance_id) {
                    // Get employee name for activity log
                    $name_query = "SELECT name FROM employees WHERE id = ?";
                    $employee = db_fetch_row($name_query, "i", [$employee_id]);
                    $employee_name = $employee ? $employee['name'] : "Employee #$employee_id";
                    
                    // Log activity
                    log_activity('create', "Marked attendance for $employee_name on $date", $_SESSION['user_id'], $employee_id);
                    
                    set_flash_message('success', "Attendance marked for $employee_name");
                    header('Location: index.php?page=attendance');
                    exit;
                } else {
                    set_flash_message('error', 'Failed to mark attendance. Please try again.');
                }
            }
        }
    }
    
    // Bulk attendance marking
    elseif ($action === 'bulk_attendance') {
        $date = sanitize_input($_POST['date'] ?? date('Y-m-d'));
        $status = sanitize_input($_POST['status'] ?? '');
        $employee_ids = isset($_POST['employee_ids']) ? (array)$_POST['employee_ids'] : [];
        $note = sanitize_input($_POST['note'] ?? '');
        
        $errors = [];
        
        // Basic validation
        if (empty($employee_ids)) {
            $errors['employee_ids'] = 'No employees selected';
        }
        
        if (!in_array($status, ['present', 'absent', 'half-day', 'late', 'leave'])) {
            $errors['status'] = 'Invalid status';
        }
        
        // If no errors, mark attendance for all selected employees
        if (empty($errors)) {
            $success_count = 0;
            $error_count = 0;
            
            // Begin transaction
            db_begin_transaction();
            
            foreach ($employee_ids as $employee_id) {
                $employee_id = (int)$employee_id;
                
                // Check if attendance already exists for this employee on this date
                $existing_query = "SELECT id FROM attendance WHERE employee_id = ? AND date = ?";
                $existing = db_fetch_row($existing_query, "is", [$employee_id, $date]);
                
                if ($existing) {
                    // Update existing attendance
                    $query = "UPDATE attendance SET status = ?, note = ? WHERE id = ?";
                    $result = db_update($query, "ssi", [$status, $note, $existing['id']], ['attendance', 'attendance_' . $date]);
                } else {
                    // Create new attendance record
                    $query = "INSERT INTO attendance (employee_id, date, status, note) VALUES (?, ?, ?, ?)";
                    $attendance_id = db_insert($query, "isss", [$employee_id, $date, $status, $note], ['attendance', 'attendance_' . $date]);
                    $result = $attendance_id ? true : false;
                }
                
                if ($result) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
            
            // Commit or rollback transaction
            if ($error_count === 0) {
                db_commit();
                
                // Log activity
                log_activity('bulk_create', "Marked bulk attendance for $success_count employees on $date", $_SESSION['user_id']);
                
                set_flash_message('success', "Attendance marked for $success_count employees");
                header('Location: index.php?page=attendance');
                exit;
            } else {
                db_rollback();
                set_flash_message('error', "Failed to mark attendance for $error_count employees. Please try again.");
            }
        }
    }
    
    // Delete attendance
    elseif ($action === 'delete_attendance') {
        $attendance_id = (int)($_POST['attendance_id'] ?? 0);
        
        // Get attendance details for activity log
        $query = "SELECT a.*, e.name as employee_name 
                  FROM attendance a
                  JOIN employees e ON a.employee_id = e.id
                  WHERE a.id = ?";
        $attendance = db_fetch_row($query, "i", [$attendance_id]);
        
        if ($attendance) {
            $query = "DELETE FROM attendance WHERE id = ?";
            $result = db_delete($query, "i", [$attendance_id], ['attendance', 'attendance_' . $attendance['date']]);
            
            if ($result) {
                // Log activity
                log_activity(
                    'delete', 
                    "Deleted attendance for {$attendance['employee_name']} on {$attendance['date']}", 
                    $_SESSION['user_id'], 
                    $attendance['employee_id']
                );
                
                set_flash_message('success', "Attendance deleted for {$attendance['employee_name']} on {$attendance['date']}");
                header('Location: index.php?page=attendance');
                exit;
            } else {
                set_flash_message('error', 'Failed to delete attendance. Please try again.');
            }
        } else {
            set_flash_message('error', 'Attendance record not found');
        }
    }
}

// Get filter parameters
$date_filter = sanitize_input($_GET['date'] ?? date('Y-m-d'));
$status_filter = sanitize_input($_GET['status'] ?? 'all');
$search = sanitize_input($_GET['search'] ?? '');
$current_page = max(1, intval($_GET['page'] ?? 1));
$items_per_page = ITEMS_PER_PAGE;
$offset = ($current_page - 1) * $items_per_page;

// Build query based on filters
$query_params = [];
$query_types = "";

$base_query = "FROM attendance a
               JOIN employees e ON a.employee_id = e.id
               WHERE 1=1";

if (!empty($date_filter)) {
    $base_query .= " AND a.date = ?";
    $query_params[] = $date_filter;
    $query_types .= "s";
}

if ($status_filter !== 'all') {
    $base_query .= " AND a.status = ?";
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

// Get paginated attendance records
$attendance_query = "SELECT a.*, e.name, e.position, e.work_hours_per_day " . $base_query . " ORDER BY e.name ASC LIMIT ? OFFSET ?";
$query_params[] = $items_per_page;
$query_params[] = $offset;
$query_types .= "ii";

$attendance_records = db_fetch_all($attendance_query, $query_types, $query_params);

// Get status counts for filters
$status_counts_query = "SELECT a.status, COUNT(*) as count 
                        FROM attendance a 
                        WHERE a.date = ? 
                        GROUP BY a.status";
$status_counts = db_fetch_all($status_counts_query, "s", [$date_filter]);

// Convert to associative array
$status_count_map = [
    'present' => 0,
    'absent' => 0,
    'half-day' => 0,
    'late' => 0,
    'leave' => 0
];

foreach ($status_counts as $count) {
    $status_count_map[$count['status']] = $count['count'];
}

$total_count = array_sum($status_count_map);

// Get active employees for marking attendance
$employees_query = "SELECT id, name, position, work_hours_per_day FROM employees WHERE status = 'active' ORDER BY name";
$employees = db_fetch_all($employees_query, "", []);

// Get unmarked employees for the selected date
$unmarked_query = "SELECT e.id, e.name, e.position 
                   FROM employees e 
                   WHERE e.status = 'active' 
                   AND e.id NOT IN (
                       SELECT employee_id FROM attendance WHERE date = ?
                   )
                   ORDER BY e.name";
$unmarked_employees = db_fetch_all($unmarked_query, "s", [$date_filter]);
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Attendance</h2>
        <div>
            <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#markAttendanceModal">
                <i class="fas fa-plus me-2"></i> Mark Attendance
            </button>
            <?php if (!empty($unmarked_employees)): ?>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkAttendanceModal">
                <i class="fas fa-clipboard-check me-2"></i> Bulk Attendance
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Search and filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="index.php" class="row g-3">
                <input type="hidden" name="page" value="attendance">
                
                <div class="col-md-3">
                    <label for="date" class="form-label">Date</label>
                    <input type="date" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" onchange="this.form.submit()">
                </div>
                
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status (<?php echo $total_count; ?>)</option>
                        <option value="present" <?php echo $status_filter === 'present' ? 'selected' : ''; ?>>Present (<?php echo $status_count_map['present']; ?>)</option>
                        <option value="absent" <?php echo $status_filter === 'absent' ? 'selected' : ''; ?>>Absent (<?php echo $status_count_map['absent']; ?>)</option>
                        <option value="half-day" <?php echo $status_filter === 'half-day' ? 'selected' : ''; ?>>Half Day (<?php echo $status_count_map['half-day']; ?>)</option>
                        <option value="late" <?php echo $status_filter === 'late' ? 'selected' : ''; ?>>Late (<?php echo $status_count_map['late']; ?>)</option>
                        <option value="leave" <?php echo $status_filter === 'leave' ? 'selected' : ''; ?>>Leave (<?php echo $status_count_map['leave']; ?>)</option>
                    </select>
                </div>
                
                <div class="col-md-4">
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
                    <a href="index.php?page=attendance&date=<?php echo urlencode($date_filter); ?>" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-times me-2"></i> Clear Filters
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Unmarked employees alert -->
    <?php if (!empty($unmarked_employees)): ?>
    <div class="alert alert-warning">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong><?php echo count($unmarked_employees); ?> employee(s)</strong> still need attendance marked for <?php echo format_date($date_filter); ?>
            </div>
            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#bulkAttendanceModal">
                Mark Now
            </button>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Attendance Records -->
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Attendance Records - <?php echo format_date($date_filter); ?></h5>
            <div>
                <a href="#" class="btn btn-sm btn-outline-primary me-2" onclick="printElement('attendanceTableContainer'); return false;">
                    <i class="fas fa-print me-1"></i> Print
                </a>
                <a href="#" class="btn btn-sm btn-outline-success" onclick="exportToExcel('attendanceTable', 'Attendance_<?php echo $date_filter; ?>'); return false;">
                    <i class="fas fa-file-excel me-1"></i> Export
                </a>
            </div>
        </div>
        <div class="card-body" id="attendanceTableContainer">
            <?php if (empty($attendance_records)): ?>
            <div class="text-center py-5">
                <i class="fas fa-clipboard-check fa-4x text-muted mb-3"></i>
                <h4>No attendance records found</h4>
                <p class="text-muted">
                    <?php if (!empty($search) || $status_filter !== 'all'): ?>
                    Try adjusting your search or filters
                    <?php else: ?>
                    No attendance has been marked for <?php echo format_date($date_filter); ?>
                    <?php endif; ?>
                </p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#markAttendanceModal">
                    <i class="fas fa-plus me-2"></i> Mark Attendance
                </button>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="attendanceTable">
                    <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Work Hours</th>
                            <th>Overtime</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle avatar-sm me-2">
                                        <span class="initials"><?php echo get_initials($record['name']); ?></span>
                                    </div>
                                    <div>
                                        <div class="fw-medium"><?php echo htmlspecialchars($record['name']); ?></div>
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
                                <?php if ($record['is_overtime'] && $record['overtime_hours'] > 0): ?>
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
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" data-bs-target="#editAttendanceModal<?php echo $record['id']; ?>"
                                            data-tippy-content="Edit attendance">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" data-bs-target="#deleteAttendanceModal<?php echo $record['id']; ?>"
                                            data-tippy-content="Delete attendance">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                                
                                <!-- Edit Attendance Modal -->
                                <div class="modal fade" id="editAttendanceModal<?php echo $record['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="post" action="index.php?page=attendance">
                                                <input type="hidden" name="action" value="mark_attendance">
                                                <input type="hidden" name="employee_id" value="<?php echo $record['employee_id']; ?>">
                                                <input type="hidden" name="date" value="<?php echo $record['date']; ?>">
                                                
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Attendance</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Employee</label>
                                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($record['name']); ?>" disabled>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Date</label>
                                                        <input type="text" class="form-control" value="<?php echo format_date($record['date']); ?>" disabled>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="edit_status<?php echo $record['id']; ?>" class="form-label">Status <span class="text-danger">*</span></label>
                                                        <select class="form-select" id="edit_status<?php echo $record['id']; ?>" name="status" required>
                                                            <option value="present" <?php echo $record['status'] === 'present' ? 'selected' : ''; ?>>Present</option>
                                                            <option value="absent" <?php echo $record['status'] === 'absent' ? 'selected' : ''; ?>>Absent</option>
                                                            <option value="half-day" <?php echo $record['status'] === 'half-day' ? 'selected' : ''; ?>>Half Day</option>
                                                            <option value="late" <?php echo $record['status'] === 'late' ? 'selected' : ''; ?>>Late</option>
                                                            <option value="leave" <?php echo $record['status'] === 'leave' ? 'selected' : ''; ?>>Leave</option>
                                                        </select>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label for="edit_check_in_time<?php echo $record['id']; ?>" class="form-label">Check In Time</label>
                                                            <input type="time" class="form-control" id="edit_check_in_time<?php echo $record['id']; ?>" name="check_in_time" value="<?php echo $record['check_in_time'] ? date('H:i', strtotime($record['check_in_time'])) : ''; ?>">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label for="edit_check_out_time<?php echo $record['id']; ?>" class="form-label">Check Out Time</label>
                                                            <input type="time" class="form-control" id="edit_check_out_time<?php echo $record['id']; ?>" name="check_out_time" value="<?php echo $record['check_out_time'] ? date('H:i', strtotime($record['check_out_time'])) : ''; ?>">
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="edit_note<?php echo $record['id']; ?>" class="form-label">Note</label>
                                                        <textarea class="form-control" id="edit_note<?php echo $record['id']; ?>" name="note" rows="2"><?php echo htmlspecialchars($record['note'] ?: ''); ?></textarea>
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
                                
                                <!-- Delete Attendance Modal -->
                                <div class="modal fade" id="deleteAttendanceModal<?php echo $record['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="post" action="index.php?page=attendance">
                                                <input type="hidden" name="action" value="delete_attendance">
                                                <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
                                                
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Confirm Delete</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="text-center mb-4">
                                                        <i class="fas fa-exclamation-triangle text-warning fa-4x mb-3"></i>
                                                        <h4>Are you sure you want to delete this attendance record?</h4>
                                                        <p class="text-muted">
                                                            You are about to delete the attendance record for <strong><?php echo htmlspecialchars($record['name']); ?></strong> 
                                                            on <strong><?php echo format_date($record['date']); ?></strong>.
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
                $pagination_url = 'index.php?page=attendance&date=' . urlencode($date_filter) . '&status=' . urlencode($status_filter) . '&search=' . urlencode($search) . '&page={page}';
                echo generate_pagination($current_page, $total_pages, $pagination_url);
                ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Mark Attendance Modal -->
    <div class="modal fade" id="markAttendanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="index.php?page=attendance" id="markAttendanceForm">
                    <input type="hidden" name="action" value="mark_attendance">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Mark Attendance</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
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
                        <div class="mb-3">
                            <label for="date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control <?php echo isset($errors['date']) ? 'is-invalid' : ''; ?>" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" required>
                            <?php if (isset($errors['date'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['date']; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select <?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>" id="status" name="status" required>
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="half-day">Half Day</option>
                                <option value="late">Late</option>
                                <option value="leave">Leave</option>
                            </select>
                            <?php if (isset($errors['status'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['status']; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="check_in_time" class="form-label">Check In Time</label>
                                <input type="time" class="form-control <?php echo isset($errors['check_in_time']) ? 'is-invalid' : ''; ?>" id="check_in_time" name="check_in_time">
                                <?php if (isset($errors['check_in_time'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['check_in_time']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="check_out_time" class="form-label">Check Out Time</label>
                                <input type="time" class="form-control <?php echo isset($errors['check_out_time']) ? 'is-invalid' : ''; ?>" id="check_out_time" name="check_out_time">
                                <?php if (isset($errors['check_out_time'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['check_out_time']; ?></div>
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
                            <i class="fas fa-check me-2"></i> Mark Attendance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bulk Attendance Modal -->
    <?php if (!empty($unmarked_employees)): ?>
    <div class="modal fade" id="bulkAttendanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post" action="index.php?page=attendance" id="bulkAttendanceForm">
                    <input type="hidden" name="action" value="bulk_attendance">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Bulk Attendance</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="bulk_date" class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="bulk_date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="bulk_status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="bulk_status" name="status" required>
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="half-day">Half Day</option>
                                    <option value="late">Late</option>
                                    <option value="leave">Leave</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="bulk_note" class="form-label">Note (applies to all selected employees)</label>
                            <textarea class="form-control" id="bulk_note" name="note" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select Employees <span class="text-danger">*</span></label>
                            <div class="d-flex mb-2">
                                <div class="form-check me-3">
                                    <input class="form-check-input" type="checkbox" id="selectAllEmployees">
                                    <label class="form-check-label fw-bold" for="selectAllEmployees">
                                        Select All
                                    </label>
                                </div>
                                <div class="ms-auto">
                                    <span class="badge bg-primary" id="selectedCount">0</span> of <?php echo count($unmarked_employees); ?> selected
                                </div>
                            </div>
                            <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                <div class="row">
                                    <?php foreach ($unmarked_employees as $employee): ?>
                                    <div class="col-md-6">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input employee-checkbox" type="checkbox" name="employee_ids[]" value="<?php echo $employee['id']; ?>" id="employee_<?php echo $employee['id']; ?>">
                                            <label class="form-check-label" for="employee_<?php echo $employee['id']; ?>">
                                                <?php echo htmlspecialchars($employee['name']); ?>
                                                <small class="text-muted">(<?php echo htmlspecialchars($employee['position']); ?>)</small>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php if (isset($errors['employee_ids'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['employee_ids']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="bulkSubmitButton" disabled>
                            <i class="fas fa-check me-2"></i> Mark Attendance
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
    const workbook = XLSX.utils.table_to_book(table, {sheet: "Attendance"});
    XLSX.writeFile(workbook, fileName + '.xlsx');
}

// Initialize form validation for marking attendance
$(document).ready(function() {
    // Form validation rules
    const attendanceRules = {
        employee_id: {
            required: 'Please select an employee'
        },
        date: {
            required: 'Date is required'
        },
        status: {
            required: 'Status is required'
        }
    };
    
    // Initialize validation for mark attendance form
    $('#markAttendanceForm').on('submit', function(e) {
        if (!validateForm('markAttendanceForm', attendanceRules)) {
            e.preventDefault();
        }
    });
    
    // Initialize validation for bulk attendance form
    $('#bulkAttendanceForm').on('submit', function(e) {
        if ($('.employee-checkbox:checked').length === 0) {
            e.preventDefault();
            alert('Please select at least one employee.');
        }
    });
    
    // Handle select all checkbox
    $('#selectAllEmployees').on('change', function() {
        $('.employee-checkbox').prop('checked', $(this).is(':checked'));
        updateSelectedCount();
    });
    
    // Handle individual checkboxes
    $('.employee-checkbox').on('change', function() {
        updateSelectedCount();
    });
    
    // Update selected count
    function updateSelectedCount() {
        const count = $('.employee-checkbox:checked').length;
        $('#selectedCount').text(count);
        $('#bulkSubmitButton').prop('disabled', count === 0);
        
        // If all individual checkboxes are checked, check the "Select All" checkbox
        if (count === $('.employee-checkbox').length) {
            $('#selectAllEmployees').prop('checked', true);
        } else {
            $('#selectAllEmployees').prop('checked', false);
        }
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
SCRIPT;