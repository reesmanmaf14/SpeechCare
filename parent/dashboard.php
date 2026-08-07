<?php
include "../includes/auth_parent.php";
include "../includes/functions.php";
include "../includes/notification_functions.php";

$parent_id = $_SESSION["parent_id"];
$parent_name = $_SESSION["parent_name"] ?? "Parent";

/*
|--------------------------------------------------------------------------
| Child details
|--------------------------------------------------------------------------
*/
$child_sql = "SELECT child_id, child_name, age, gender, speech_concern
              FROM children
              WHERE parent_id = ?
              LIMIT 1";

$child_stmt = mysqli_prepare($conn, $child_sql);
mysqli_stmt_bind_param($child_stmt, "i", $parent_id);
mysqli_stmt_execute($child_stmt);

$child_result = mysqli_stmt_get_result($child_stmt);
$child = mysqli_fetch_assoc($child_result);

$child_id = $child["child_id"] ?? 0;
$child_name = $child["child_name"] ?? "Child";
$child_age = $child["age"] ?? "-";

/*
|--------------------------------------------------------------------------
| Dashboard counts
|--------------------------------------------------------------------------
*/
$pending_sql = "SELECT COUNT(*) AS total
                FROM appointments
                WHERE parent_id = ?
                AND status = 'pending'";

$pending_stmt = mysqli_prepare($conn, $pending_sql);
mysqli_stmt_bind_param($pending_stmt, "i", $parent_id);
mysqli_stmt_execute($pending_stmt);
$pending_result = mysqli_stmt_get_result($pending_stmt);
$pending_count = mysqli_fetch_assoc($pending_result)["total"];

$upcoming_sql = "SELECT COUNT(*) AS total
                 FROM appointments
                 WHERE parent_id = ?
                 AND status = 'approved'
                 AND appointment_date >= CURDATE()";

$upcoming_stmt = mysqli_prepare($conn, $upcoming_sql);
mysqli_stmt_bind_param($upcoming_stmt, "i", $parent_id);
mysqli_stmt_execute($upcoming_stmt);
$upcoming_result = mysqli_stmt_get_result($upcoming_stmt);
$upcoming_count = mysqli_fetch_assoc($upcoming_result)["total"];

$completed_sql = "SELECT COUNT(*) AS total
                  FROM appointments
                  WHERE parent_id = ?
                  AND status = 'completed'";

$completed_stmt = mysqli_prepare($conn, $completed_sql);
mysqli_stmt_bind_param($completed_stmt, "i", $parent_id);
mysqli_stmt_execute($completed_stmt);
$completed_result = mysqli_stmt_get_result($completed_stmt);
$completed_count = mysqli_fetch_assoc($completed_result)["total"];

$reschedule_sql = "SELECT COUNT(*) AS total
                   FROM appointments
                   WHERE parent_id = ?
                   AND status = 'needs_rescheduling'";

$reschedule_stmt = mysqli_prepare($conn, $reschedule_sql);
mysqli_stmt_bind_param($reschedule_stmt, "i", $parent_id);
mysqli_stmt_execute($reschedule_stmt);
$reschedule_result = mysqli_stmt_get_result($reschedule_stmt);
$reschedule_count = mysqli_fetch_assoc($reschedule_result)["total"];

$unread_notifications = getUnreadNotificationCount($conn, "parent", $parent_id);

/*
|--------------------------------------------------------------------------
| Recent appointments
|--------------------------------------------------------------------------
*/
$recent_sql = "SELECT 
                    a.appointment_id,
                    a.appointment_date,
                    a.start_time,
                    a.end_time,
                    a.status,
                    t.full_name AS therapist_name,
                    t.specialization
               FROM appointments a
               INNER JOIN therapists t ON a.therapist_id = t.therapist_id
               WHERE a.parent_id = ?
               ORDER BY a.created_at DESC
               LIMIT 5";

$recent_stmt = mysqli_prepare($conn, $recent_sql);
mysqli_stmt_bind_param($recent_stmt, "i", $parent_id);
mysqli_stmt_execute($recent_stmt);

$recent_result = mysqli_stmt_get_result($recent_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Parent Dashboard - SpeechCare</title>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Same dashboard design -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">
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
        <a href="dashboard.php" class="active"><i class="bi bi-house-door"></i> Dashboard</a>
        <a href="book-appointment.php"><i class="bi bi-calendar-plus"></i> Book Appointment</a>
        <a href="my-appointments.php"><i class="bi bi-calendar-check"></i> My Appointments</a>
         <a href="progress.php" ><i class="bi bi-graph-up-arrow"></i> Progress</a>
        <a href="notifications.php"><i class="bi bi-bell"></i> Notifications</a>
        <a href="profile.php"><i class="bi bi-person-circle"></i> Profile</a>
        <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>

<div class="dashboard-wrapper">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-logo">SpeechCare</div>
        <div class="sidebar-subtitle">Parent Panel</div>

        <nav class="sidebar-menu">
            <a href="dashboard.php" class="active"><i class="bi bi-house-door"></i> Dashboard</a>
            <a href="book-appointment.php"><i class="bi bi-calendar-plus"></i> Book Appointment</a>
            <a href="my-appointments.php"><i class="bi bi-calendar-check"></i> My Appointments</a>
             <a href="progress.php" ><i class="bi bi-graph-up-arrow"></i> Progress</a>
            <a href="notifications.php"><i class="bi bi-bell"></i> Notifications</a>
            <a href="profile.php"><i class="bi bi-person-circle"></i> Profile</a>
            <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="page-title">Welcome back, <?php echo escapeOutput($parent_name); ?>!</h1>
                <p class="page-subtitle">Here is your child's therapy appointment overview.</p>
            </div>

            <div class="profile-circle">
                <?php echo strtoupper(substr($parent_name, 0, 1)); ?>
            </div>
        </div>

      

        <?php if ($reschedule_count > 0) { ?>
            <div class="alert alert-warning border-0 rounded-4 mb-4">
                <i class="bi bi-arrow-repeat me-2"></i>
                You have <strong><?php echo $reschedule_count; ?></strong> appointment(s) that need rescheduling.
                <a href="my-appointments.php?status=needs_rescheduling" class="fw-bold ms-2">Reschedule now</a>
            </div>
        <?php } ?>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                    <div class="stat-label">Pending Requests</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                    <div class="stat-number"><?php echo $upcoming_count; ?></div>
                    <div class="stat-label">Upcoming Sessions</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-patch-check"></i></div>
                    <div class="stat-number"><?php echo $completed_count; ?></div>
                    <div class="stat-label">Completed Sessions</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-bell"></i></div>
                    <div class="stat-number"><?php echo $unread_notifications; ?></div>
                    <div class="stat-label">Unread Notifications</div>
                </div>
            </div>

        </div>


        <!-- Recent Appointments -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="fw-bold mb-1">Recent Activity</h4>
                    <p class="text-muted small mb-0">Latest appointment records for your child.</p>
                </div>

                <a href="my-appointments.php" class="fw-bold text-decoration-none" style="color:#3998E8;">
                    View All
                </a>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
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

                                    <td>
                                        <strong><?php echo escapeOutput($row["therapist_name"]); ?></strong>
                                        <div class="small text-muted">
                                            <?php echo escapeOutput($row["specialization"]); ?>
                                        </div>
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
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="bi bi-calendar-x"></i>
                                        <h5 class="fw-bold">No appointments yet</h5>
                                        <p class="mb-0">Book your first therapy appointment to see activity here.</p>
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

</body>
</html>