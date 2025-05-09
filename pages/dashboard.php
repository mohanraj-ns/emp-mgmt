<?php
/**
 * Dashboard page
 * Shows overview of attendance, employees, and recent activities
 */

// Check login
require_login();

// Dates
$today = new DateTime();
$todayFormatted = $today->format('Y-m-d');
$firstOfMonth = (new DateTime('first day of this month'))->format('Y-m-d');

// Attendance stats for today
$attendanceStats = db_fetch_row(
    "SELECT 
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late_count,
        SUM(CASE WHEN status = 'half-day' THEN 1 ELSE 0 END) AS halfday_count,
        SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) AS leave_count,
        COUNT(*) AS total_count
     FROM attendance
     WHERE date = ?",
    "s",
    [$todayFormatted],
    "attendance_stats_$todayFormatted"
);

// Active employee count
$employeeCount = db_fetch_row(
    "SELECT COUNT(*) AS total FROM employees WHERE status = 'active'",
    "",
    [],
    "active_employee_count"
);

// Recent attendance records
$recentAttendance = db_fetch_all(
    "SELECT a.*, e.name, e.position 
     FROM attendance a
     JOIN employees e ON a.employee_id = e.id
     ORDER BY a.date DESC, a.id DESC
     LIMIT 10",
    "",
    [],
    "recent_attendance"
);

// Recent activities
$recentActivities = db_fetch_all(
    "SELECT a.*, u.name AS user_name, e.name AS employee_name
     FROM activities a
     LEFT JOIN users u ON a.user_id = u.id
     LEFT JOIN employees e ON a.employee_id = e.id
     ORDER BY a.created_at DESC
     LIMIT 10",
    "",
    [],
    "recent_activities"
);

// Monthly attendance summary
$monthlyStats = db_fetch_row(
    "SELECT 
        COUNT(*) AS total_attendance,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late_count,
        SUM(CASE WHEN status = 'half-day' THEN 1 ELSE 0 END) AS halfday_count,
        SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) AS leave_count
     FROM attendance 
     WHERE date BETWEEN ? AND ?",
    "ss",
    [$firstOfMonth, $todayFormatted],
    "monthly_stats_{$firstOfMonth}_{$todayFormatted}"
);

// HTML Output Begins Here
?>

<!-- Replace this with your existing HTML header -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<h1>Welcome to the Attendance Dashboard</h1>

<!-- Example Summary Cards -->
<div>
    <h2>Today: <?= $todayFormatted ?></h2>
    <ul>
        <li>Present: <?= $attendanceStats['present_count'] ?? 0 ?></li>
        <li>Late: <?= $attendanceStats['late_count'] ?? 0 ?></li>
        <li>Absent: <?= $attendanceStats['absent_count'] ?? 0 ?></li>
        <li>Half Day: <?= $attendanceStats['halfday_count'] ?? 0 ?></li>
        <li>Leave: <?= $attendanceStats['leave_count'] ?? 0 ?></li>
        <li>Total Records: <?= $attendanceStats['total_count'] ?? 0 ?></li>
    </ul>
</div>

<!-- Attendance Chart -->
<div style="max-width: 400px;">
    <canvas id="attendanceChart"></canvas>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ["Present", "Late", "Absent", "Half-Day", "Leave"],
            datasets: [{
                data: [
                    <?= $monthlyStats['present_count'] ?? 0 ?>,
                    <?= $monthlyStats['late_count'] ?? 0 ?>,
                    <?= $monthlyStats['absent_count'] ?? 0 ?>,
                    <?= $monthlyStats['halfday_count'] ?? 0 ?>,
                    <?= $monthlyStats['leave_count'] ?? 0 ?>
                ],
                backgroundColor: [
                    '#1cc88a', '#f6c23e', '#e74a3b', '#fd7e14', '#4e73df'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                }
            },
            cutout: '70%'
        }
    });
});
</script>

</body>
</html>