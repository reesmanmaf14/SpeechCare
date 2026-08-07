<?php
include "../includes/auth_admin.php";
include "../includes/functions.php";

$admin_name = $_SESSION["admin_name"] ?? "Admin";

/*
|--------------------------------------------------------------------------
| Dashboard Counts
|--------------------------------------------------------------------------
*/
$total_parents = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM parents"))["total"];
$active_parents = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM parents WHERE status = 'active'"))["total"];
$blocked_parents = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM parents WHERE status = 'blocked'"))["total"];

$total_therapists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM therapists"))["total"];
$active_therapists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM therapists WHERE status = 'active'"))["total"];

$total_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments"))["total"];
$pending_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE status = 'pending'"))["total"];
$approved_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE status = 'approved'"))["total"];
$completed_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE status = 'completed'"))["total"];
$reschedule_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE status = 'needs_rescheduling'"))["total"];

$available_slots = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM time_slots WHERE status = 'available'"))["total"];
$booked_slots = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM time_slots WHERE status = 'booked'"))["total"];

$pending_leave_requests = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM therapist_leave_requests WHERE status = 'pending'"))["total"];

/*
|--------------------------------------------------------------------------
| Recent Pending Appointments
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
                    t.full_name AS therapist_name
               FROM appointments a
               INNER JOIN parents p ON a.parent_id = p.parent_id
               INNER JOIN children c ON a.child_id = c.child_id
               INNER JOIN therapists t ON a.therapist_id = t.therapist_id
               ORDER BY a.created_at DESC
               LIMIT 5";

$recent_result = mysqli_query($conn, $recent_sql);
?>

<?php
$months = [];
$monthly_counts = [];

$trend_sql = "
SELECT
    DATE_FORMAT(appointment_date,'%b') AS month_name,
    COUNT(*) AS total
FROM appointments
WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
GROUP BY YEAR(appointment_date), MONTH(appointment_date)
ORDER BY YEAR(appointment_date), MONTH(appointment_date)
";

$trend_result = mysqli_query($conn, $trend_sql);

while($row = mysqli_fetch_assoc($trend_result)){
    $months[] = $row['month_name'];
    $monthly_counts[] = $row['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Dashboard - SpeechCare</title>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    
    <!-- Same dashboard design -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <a href="manage-therapists.php"><i class="bi bi-person-badge"></i> Manage Therapists</a>
        <a href="manage-parents.php"><i class="bi bi-people"></i> Manage Parents</a>
        <a href="manage-appointments.php"><i class="bi bi-calendar-check"></i> Manage Appointments</a>
        <a href="manage-leave-requests.php"><i class="bi bi-calendar-x"></i> Leave Requests</a>
        <a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
        <a href="notifications.php"><i class="bi bi-bell"></i> Notifications</a>
        <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>

<div class="dashboard-wrapper">

    <!-- Sidebar -->
    <aside class="sidebar admin-sidebar">
        <div class="sidebar-logo">SpeechCare</div>
        <div class="sidebar-subtitle">Admin Panel</div>

        <nav class="sidebar-menu">
            <a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="manage-therapists.php"><i class="bi bi-person-badge"></i> Manage Therapists</a>
            <a href="manage-parents.php"><i class="bi bi-people"></i> Manage Parents</a>
            <a href="manage-appointments.php"><i class="bi bi-calendar-check"></i> Manage Appointments</a>
            <a href="manage-leave-requests.php"><i class="bi bi-calendar-x"></i> Leave Requests</a>
            <a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
            <a href="notifications.php"><i class="bi bi-bell"></i> Notifications</a>
            <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="page-title">Admin Dashboard</h1>
                <p class="page-subtitle">Welcome back, <?php echo escapeOutput($admin_name); ?>. Here is your clinic overview.</p>
            </div>

            <div class="profile-circle">
                <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
            </div>
        </div>

        <?php if ($pending_appointments > 0) { ?>
            <div class="alert alert-warning border-0 rounded-4 mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                You have <strong><?php echo $pending_appointments; ?></strong> pending appointment request(s).
                <a href="manage-appointments.php?status=pending" class="fw-bold ms-2">Review now</a>
            </div>
        <?php } ?>

        <?php if ($pending_leave_requests > 0) { ?>
            <div class="alert alert-info border-0 rounded-4 mb-4">
                <i class="bi bi-calendar-x-fill me-2"></i>
                You have <strong><?php echo $pending_leave_requests; ?></strong> therapist leave request(s).
                <a href="manage-leave-requests.php?status=pending" class="fw-bold ms-2">Check leave requests</a>
            </div>
        <?php } ?>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">

            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-people"></i></div>
                    <div class="stat-number"><?php echo $total_parents; ?></div>
                    <div class="stat-label">Total Parents</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-person-badge"></i></div>
                    <div class="stat-number"><?php echo $total_therapists; ?></div>
                    <div class="stat-label">Total Therapists</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                    <div class="stat-number"><?php echo $total_appointments; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
            </div>

        

            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-patch-check"></i></div>
                    <div class="stat-number"><?php echo $completed_appointments; ?></div>
                    <div class="stat-label">Completed Sessions</div>
                </div>
            </div>


        </div>
        
<div class="row g-4 mb-4">

    <div class="col-lg-6">
        <div class="content-card">
            <h4 class="fw-bold mb-3">Appointment Status Overview</h4>
            <div class="chart-container">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="content-card">
            <h4 class="fw-bold mb-3">Parent vs Therapist Distribution</h4>
            <div class="chart-container">
                <canvas id="distributionChart"></canvas>
            </div>
        </div>
    </div>

</div>

<div class="content-card mb-4">
    <h4 class="fw-bold mb-3">Monthly Appointment Trend</h4>
    <div class="chart-container">
        <canvas id="trendChart"></canvas>
    </div>
</div>
       
        <!-- Recent Appointments -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="fw-bold mb-1">Recent Appointments</h4>
                    <p class="text-muted small mb-0">Latest appointment activity in the clinic.</p>
                </div>

                <a href="manage-appointments.php" class="fw-bold text-decoration-none" style="color:#3998E8;">
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
                            <th>Therapist</th>
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
                                    <td><?php echo escapeOutput($row["child_name"]); ?></td>
                                    <td><?php echo escapeOutput($row["therapist_name"]); ?></td>
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
                                        <h5 class="fw-bold">No appointments yet</h5>
                                        <p class="mb-0">Appointment records will appear here after parents book sessions.</p>
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

<script>

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Pending', 'Approved', 'Completed', 'Rescheduled'],
        datasets: [{
            data: [
                <?php echo $pending_appointments; ?>,
                <?php echo $approved_appointments; ?>,
                <?php echo $completed_appointments; ?>,
                <?php echo $reschedule_appointments; ?>
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

new Chart(document.getElementById('distributionChart'), {
    type: 'doughnut',
    data: {
        labels: ['Parents', 'Therapists'],
        datasets: [{
            data: [
                <?php echo $total_parents; ?>,
                <?php echo $total_therapists; ?>
            ],
            backgroundColor: [
                '#3998E8',
                '#22C55E'
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