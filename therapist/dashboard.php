<?php
include "../includes/auth_therapist.php";
include "../includes/functions.php";
include "../includes/notification_functions.php";

$therapist_id = $_SESSION["therapist_id"];
$therapist_name = $_SESSION["therapist_name"] ?? "Therapist";

/*
|--------------------------------------------------------------------------
| Dashboard Counts
|--------------------------------------------------------------------------
*/

// Today's approved appointments
$today_sql = "SELECT COUNT(*) AS total
              FROM appointments
              WHERE therapist_id = ?
              AND status = 'approved'
              AND appointment_date = CURDATE()";

$today_stmt = mysqli_prepare($conn, $today_sql);
mysqli_stmt_bind_param($today_stmt, "i", $therapist_id);
mysqli_stmt_execute($today_stmt);
$today_result = mysqli_stmt_get_result($today_stmt);
$today_appointments = mysqli_fetch_assoc($today_result)["total"];

// Upcoming approved appointments
$upcoming_sql = "SELECT COUNT(*) AS total
                 FROM appointments
                 WHERE therapist_id = ?
                 AND status = 'approved'
                 AND appointment_date >= CURDATE()";

$upcoming_stmt = mysqli_prepare($conn, $upcoming_sql);
mysqli_stmt_bind_param($upcoming_stmt, "i", $therapist_id);
mysqli_stmt_execute($upcoming_stmt);
$upcoming_result = mysqli_stmt_get_result($upcoming_stmt);
$upcoming_appointments = mysqli_fetch_assoc($upcoming_result)["total"];

// Completed sessions
$completed_sql = "SELECT COUNT(*) AS total
                  FROM appointments
                  WHERE therapist_id = ?
                  AND status = 'completed'";

$completed_stmt = mysqli_prepare($conn, $completed_sql);
mysqli_stmt_bind_param($completed_stmt, "i", $therapist_id);
mysqli_stmt_execute($completed_stmt);
$completed_result = mysqli_stmt_get_result($completed_stmt);
$completed_sessions = mysqli_fetch_assoc($completed_result)["total"];





// Pending leave requests
$pending_leave_sql = "SELECT COUNT(*) AS total
                      FROM therapist_leave_requests
                      WHERE therapist_id = ?
                      AND status = 'pending'";

$pending_leave_stmt = mysqli_prepare($conn, $pending_leave_sql);
mysqli_stmt_bind_param($pending_leave_stmt, "i", $therapist_id);
mysqli_stmt_execute($pending_leave_stmt);
$pending_leave_result = mysqli_stmt_get_result($pending_leave_stmt);
$pending_leave_requests = mysqli_fetch_assoc($pending_leave_result)["total"];


$status_sql = "SELECT
    SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed,
    SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled,
    SUM(CASE WHEN status='needs_rescheduling' THEN 1 ELSE 0 END) AS rescheduled
FROM appointments
WHERE therapist_id = ?";

$status_stmt = mysqli_prepare($conn, $status_sql);
mysqli_stmt_bind_param($status_stmt, "i", $therapist_id);
mysqli_stmt_execute($status_stmt);
$status_result = mysqli_stmt_get_result($status_stmt);
$status_data = mysqli_fetch_assoc($status_result);

$approved_count = $status_data['approved'] ?? 0;
$completed_count = $status_data['completed'] ?? 0;
$cancelled_count = $status_data['cancelled'] ?? 0;
$rescheduled_count = $status_data['rescheduled'] ?? 0;

$months = [];
$monthly_counts = [];

$trend_sql = "SELECT
                MONTH(appointment_date) AS month_num,
                DATE_FORMAT(appointment_date,'%b') AS month_name,
                COUNT(*) AS total
              FROM appointments
              WHERE therapist_id = ?
              GROUP BY MONTH(appointment_date)
              ORDER BY MONTH(appointment_date)";

$trend_stmt = mysqli_prepare($conn, $trend_sql);
mysqli_stmt_bind_param($trend_stmt, "i", $therapist_id);
mysqli_stmt_execute($trend_stmt);
$trend_result = mysqli_stmt_get_result($trend_stmt);

while($row = mysqli_fetch_assoc($trend_result)){
    $months[] = $row['month_name'];
    $monthly_counts[] = $row['total'];
}

/*
|--------------------------------------------------------------------------
| Recent assigned appointments
|--------------------------------------------------------------------------
*/
$recent_sql = "SELECT 
                    a.appointment_id,
                    a.appointment_date,
                    a.start_time,
                    a.end_time,
                    a.status,
                    p.full_name AS parent_name,
                    c.child_name,
                    c.age,
                    c.speech_concern
               FROM appointments a
               INNER JOIN parents p ON a.parent_id = p.parent_id
               INNER JOIN children c ON a.child_id = c.child_id
               WHERE a.therapist_id = ?
               AND a.status IN ('approved', 'completed', 'cancelled', 'needs_rescheduling')
               ORDER BY a.appointment_date DESC, a.start_time DESC
               LIMIT 5";

$recent_stmt = mysqli_prepare($conn, $recent_sql);
mysqli_stmt_bind_param($recent_stmt, "i", $therapist_id);
mysqli_stmt_execute($recent_stmt);
$recent_result = mysqli_stmt_get_result($recent_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Therapist Dashboard - SpeechCare</title>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Same dashboard design -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <style>
        .therapist-sidebar {
            width: 280px;
    min-height: 100vh;
    background: #ffffff;
    border-right: 1px solid #e2e8f0;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.04);
    padding: 24px 18px;
    position: fixed;
    left: 0;
    top: 0;
        }

        .therapist-sidebar .sidebar-logo {
           debar-logo 
    font-family: 'Segoe UI', sans-serif; 
    font-size: 32px;
    font-weight: 600;
    letter-spacing: -1.4px;
    color: #3998E8;
    margin-bottom: 6px;
    line-height: 1;
        }

        .therapist-sidebar .sidebar-subtitle {
           font-size: 13px;
    font-weight: 500;
    color: #666B7A;
    letter-spacing: 0.5px;
    margin-bottom: 34px;
    opacity: 0.85;
    text-transform: uppercase;
        }

        .therapist-sidebar .sidebar-menu a {
             display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 12px;
    color: #666B7A;
    text-decoration: none;
    margin-bottom: 6px;
    font-weight: 600;
    transition: 0.2s ease;
        }

        .therapist-sidebar .sidebar-menu a:hover,
        .therapist-sidebar .sidebar-menu a.active {
            background: #eff6ff;
    color: #3998E8;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.12);
        }

       .content-card {
        height:100%;
    background: #fff;
    border-radius: 24px;
    padding: 24px;
    border: 1px solid #edf2f7;
    box-shadow:
        0 10px 25px rgba(15,23,42,.05),
        0 2px 10px rgba(15,23,42,.03);
}

.chart-container {
    position: relative;
    height: 350px;
    width: 100%;
}
    </style>
</head>
<body>

<!-- Mobile Navbar -->
<div class="mobile-navbar">
    <div class="sidebar-logo mb-0">SpeechCare</div>
    <button class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu">
        <i class="bi bi-list"></i>
    </button>
</div>

<!-- Mobile Menu -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenu">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title sidebar-logo">SpeechCare</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body sidebar-menu">
        <a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="manage-availability.php"><i class="bi bi-calendar-week"></i> Manage Availability</a>
        <a href="apply-leave.php"><i class="bi bi-calendar-x"></i> Apply Leave</a>
        <a href="appointments.php"><i class="bi bi-calendar-check"></i> My Appointments</a>
        <a href="notifications.php"><i class="bi bi-bell"></i> Notifications</a>
        <a href="profile.php"><i class="bi bi-person-circle"></i> Profile</a>
        <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>

<div class="dashboard-wrapper">

    <!-- Sidebar -->
    <aside class="sidebar therapist-sidebar">
        <div class="sidebar-logo">SpeechCare</div>
        <div class="sidebar-subtitle">Therapist Panel</div>

        <nav class="sidebar-menu">
            <a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="manage-availability.php"><i class="bi bi-calendar-week"></i> Manage Availability</a>
            <a href="apply-leave.php"><i class="bi bi-calendar-x"></i> Apply Leave</a>
            <a href="appointments.php"><i class="bi bi-calendar-check"></i> My Appointments</a>
            <a href="notifications.php"><i class="bi bi-bell"></i> Notifications</a>
            <a href="profile.php"><i class="bi bi-person-circle"></i> Profile</a>
            <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="page-title">Welcome, <?php echo escapeOutput($therapist_name); ?>!</h1>
                <p class="page-subtitle">Here is your therapy schedule overview.</p>
            </div>

            <div class="profile-circle">
                <?php echo strtoupper(substr($therapist_name, 0, 1)); ?>
            </div>
        </div>

        <?php if ($today_appointments > 0) { ?>
            <div class="alert alert-info border-0 rounded-4 mb-4">
                <i class="bi bi-calendar-day-fill me-2"></i>
                You have <strong><?php echo $today_appointments; ?></strong> approved appointment(s) today.
                <a href="appointments.php?appointment_date=<?php echo date('Y-m-d'); ?>" class="fw-bold ms-2">View today</a>
            </div>
        <?php } ?>

        <?php if ($pending_leave_requests > 0) { ?>
            <div class="alert alert-warning border-0 rounded-4 mb-4">
                <i class="bi bi-hourglass-split me-2"></i>
                You have <strong><?php echo $pending_leave_requests; ?></strong> pending leave request(s).
                <a href="apply-leave.php" class="fw-bold ms-2">View leave history</a>
            </div>
        <?php } ?>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">

            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-calendar-day"></i></div>
                    <div class="stat-number"><?php echo $today_appointments; ?></div>
                    <div class="stat-label">Today’s Sessions</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                    <div class="stat-number"><?php echo $upcoming_appointments; ?></div>
                    <div class="stat-label">Upcoming Sessions</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-patch-check"></i></div>
                    <div class="stat-number"><?php echo $completed_sessions; ?></div>
                    <div class="stat-label">Completed Sessions</div>
                </div>
            </div>


        </div>

     <div class="row g-4 mb-4">

    <div class="col-lg-6">
        <div class="content-card">
            <h4 class="fw-bold mb-3">Session Status Overview</h4>
            <div class="chart-container">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="content-card">
            <h4 class="fw-bold mb-3">Monthly Session Trend</h4>
            <div class="chart-container">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>

</div>

        <!-- Recent Appointments -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="fw-bold mb-1">Recent Assigned Appointments</h4>
                    <p class="text-muted small mb-0">Latest appointment records assigned to you.</p>
                </div>

                <a href="appointments.php" class="fw-bold text-decoration-none" style="color:#3998E8;">
                    View All
                </a>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Parent</th>
                            <th>Child</th>
                            <th>Concern</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (mysqli_num_rows($recent_result) > 0) { ?>
                            <?php while ($row = mysqli_fetch_assoc($recent_result)) { ?>
                                <tr>
                                    <td>#<?php echo escapeOutput($row["appointment_id"]); ?></td>

                                    <td><?php echo escapeOutput($row["parent_name"]); ?></td>

                                    <td>
                                        <strong><?php echo escapeOutput($row["child_name"]); ?></strong>
                                        <div class="small text-muted">
                                            Age <?php echo escapeOutput($row["age"]); ?>
                                        </div>
                                    </td>

                                    <td style="min-width:180px;">
                                        <?php echo escapeOutput($row["speech_concern"]); ?>
                                    </td>

                                    <td><?php echo formatDateNice($row["appointment_date"]); ?></td>

                                    <td>
                                        <?php echo formatTimeNice($row["start_time"]); ?>
                                        -
                                        <?php echo formatTimeNice($row["end_time"]); ?>
                                    </td>

                                    <td>
                                        <span class="badge <?php echo getAppointmentStatusBadgeClass($row["status"]); ?>">
                                            <?php echo displayStatusText($row["status"]); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="bi bi-calendar-x"></i>
                                        <h5 class="fw-bold">No assigned appointments yet</h5>
                                        <p class="mb-0">Approved appointments assigned to you will appear here.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Approved', 'Completed', 'Cancelled', 'Rescheduled'],
        datasets: [{
            data: [
                <?php echo $approved_count; ?>,
                <?php echo $completed_count; ?>,
                <?php echo $cancelled_count; ?>,
                <?php echo $rescheduled_count; ?>
            ],
            backgroundColor: [
                '#3998E8',
                '#22C55E',
                '#EF4444',
                '#F59E0B'
            ],
            borderWidth: 0,
            hoverOffset: 12
        }]
    },
    options: {
        cutout: '75%',
    
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'circle',
                    padding: 20
                }
            }
        }
    }
});

</script>

<script>

const ctx = document.getElementById('trendChart').getContext('2d');

const gradient = ctx.createLinearGradient(0, 0, 0, 300);
gradient.addColorStop(0, 'rgba(57,152,232,0.35)');
gradient.addColorStop(1, 'rgba(57,152,232,0.02)');

new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($months); ?>,
        datasets: [{
            data: <?php echo json_encode($monthly_counts); ?>,
            borderColor: '#3998E8',
            backgroundColor: gradient,
            fill: true,
            tension: 0.45,
            borderWidth: 4,
            pointRadius: 5,
            pointHoverRadius: 8,
            pointBackgroundColor: '#3998E8',
            pointBorderColor: '#fff',
            pointBorderWidth: 3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            x: {
                grid: {
                    display: false
                }
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0,0,0,0.05)'
                },
                ticks: {
                    precision: 0
                }
            }
        }
    }
});
</script>


</body>
</html>