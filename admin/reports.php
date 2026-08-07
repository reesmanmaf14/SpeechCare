<?php
include "../includes/auth_admin.php";
include "../includes/functions.php";

$success = "";
$error = "";

/*
|--------------------------------------------------------------------------
| Date Filters
|--------------------------------------------------------------------------
*/
$start_date = isset($_GET["start_date"]) ? cleanInput($_GET["start_date"]) : date("Y-m-01");
$end_date = isset($_GET["end_date"]) ? cleanInput($_GET["end_date"]) : date("Y-m-d");

if (empty($start_date) || empty($end_date)) {
    $start_date = date("Y-m-01");
    $end_date = date("Y-m-d");
}

if (strtotime($start_date) > strtotime($end_date)) {
    $error = "Start date cannot be after end date.";
    $start_date = date("Y-m-01");
    $end_date = date("Y-m-d");
}

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/
function getAppointmentCount($conn, $status, $start_date, $end_date) {
    if ($status === "all") {
        $sql = "SELECT COUNT(*) AS total
                FROM appointments
                WHERE appointment_date BETWEEN ? AND ?";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    } else {
        $sql = "SELECT COUNT(*) AS total
                FROM appointments
                WHERE status = ?
                AND appointment_date BETWEEN ? AND ?";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sss", $status, $start_date, $end_date);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    return $row["total"] ?? 0;
}

function getSlotCount($conn, $status, $start_date, $end_date) {
    $sql = "SELECT COUNT(*) AS total
            FROM time_slots
            WHERE status = ?
            AND slot_date BETWEEN ? AND ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sss", $status, $start_date, $end_date);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    return $row["total"] ?? 0;
}

function calculatePercent($value, $total) {
    if ($total <= 0) {
        return 0;
    }

    return round(($value / $total) * 100);
}

/*
|--------------------------------------------------------------------------
| Main Report Counts
|--------------------------------------------------------------------------
*/
$total_appointments = getAppointmentCount($conn, "all", $start_date, $end_date);
$pending_appointments = getAppointmentCount($conn, "pending", $start_date, $end_date);
$approved_appointments = getAppointmentCount($conn, "approved", $start_date, $end_date);
$rejected_appointments = getAppointmentCount($conn, "rejected", $start_date, $end_date);
$completed_appointments = getAppointmentCount($conn, "completed", $start_date, $end_date);
$cancelled_appointments = getAppointmentCount($conn, "cancelled", $start_date, $end_date);
$needs_rescheduling = getAppointmentCount($conn, "needs_rescheduling", $start_date, $end_date);
$rescheduled_appointments = getAppointmentCount($conn, "rescheduled", $start_date, $end_date);

$available_slots = getSlotCount($conn, "available", $start_date, $end_date);
$pending_slots = getSlotCount($conn, "pending", $start_date, $end_date);
$booked_slots = getSlotCount($conn, "booked", $start_date, $end_date);
$blocked_slots = getSlotCount($conn, "blocked_for_leave", $start_date, $end_date);
$completed_slots = getSlotCount($conn, "completed", $start_date, $end_date);

$total_parents = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM parents"))["total"];
$total_therapists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM therapists"))["total"];
$total_children = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM children"))["total"];

/*
|--------------------------------------------------------------------------
| Therapist-wise Report
|--------------------------------------------------------------------------
*/
$therapist_report_sql = "SELECT 
                            t.therapist_id,
                            t.full_name,
                            t.specialization,

                            COUNT(a.appointment_id) AS total_appointments,

                            SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                            SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
                            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
                            SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
                            SUM(CASE WHEN a.status = 'needs_rescheduling' THEN 1 ELSE 0 END) AS needs_rescheduling_count
                         FROM therapists t
                         LEFT JOIN appointments a 
                            ON t.therapist_id = a.therapist_id
                            AND a.appointment_date BETWEEN ? AND ?
                         GROUP BY t.therapist_id
                         ORDER BY total_appointments DESC, t.full_name ASC";

$therapist_report_stmt = mysqli_prepare($conn, $therapist_report_sql);
mysqli_stmt_bind_param($therapist_report_stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($therapist_report_stmt);
$therapist_report_result = mysqli_stmt_get_result($therapist_report_stmt);

/*
|--------------------------------------------------------------------------
| Recent Reschedule History
|--------------------------------------------------------------------------
*/
$reschedule_sql = "SELECT 
                    rh.reschedule_id,
                    rh.old_date,
                    rh.old_start_time,
                    rh.old_end_time,
                    rh.new_date,
                    rh.new_start_time,
                    rh.new_end_time,
                    rh.reason,
                    rh.created_at,

                    p.full_name AS parent_name,
                    t.full_name AS therapist_name,
                    c.child_name
                  FROM appointment_reschedule_history rh
                  INNER JOIN appointments a ON rh.appointment_id = a.appointment_id
                  INNER JOIN parents p ON rh.parent_id = p.parent_id
                  INNER JOIN therapists t ON rh.therapist_id = t.therapist_id
                  INNER JOIN children c ON a.child_id = c.child_id
                  WHERE DATE(rh.created_at) BETWEEN ? AND ?
                  ORDER BY rh.created_at DESC
                  LIMIT 10";

$reschedule_stmt = mysqli_prepare($conn, $reschedule_sql);
mysqli_stmt_bind_param($reschedule_stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($reschedule_stmt);
$reschedule_result = mysqli_stmt_get_result($reschedule_stmt);

/*
|--------------------------------------------------------------------------
| Appointment status report array
|--------------------------------------------------------------------------
*/
$status_rows = [
    [
        "label" => "Pending",
        "count" => $pending_appointments,
        "status" => "pending"
    ],
    [
        "label" => "Approved",
        "count" => $approved_appointments,
        "status" => "approved"
    ],
    [
        "label" => "Rejected",
        "count" => $rejected_appointments,
        "status" => "rejected"
    ],
    [
        "label" => "Completed",
        "count" => $completed_appointments,
        "status" => "completed"
    ],
    [
        "label" => "Cancelled",
        "count" => $cancelled_appointments,
        "status" => "cancelled"
    ],
    [
        "label" => "Needs Rescheduling",
        "count" => $needs_rescheduling,
        "status" => "needs_rescheduling"
    ],
    [
        "label" => "Rescheduled",
        "count" => $rescheduled_appointments,
        "status" => "rescheduled"
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reports - SpeechCare</title>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Same dashboard design -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <style>
        .filter-box {
            background: #ffffff;
            border: 1px solid var(--light-border);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 12px 35px rgba(57, 152, 232, 0.08);
            margin-bottom: 24px;
        }

        .form-control {
            border: 1px solid var(--light-border);
            border-radius: 14px;
            min-height: 50px;
            padding: 11px 14px;
        }

        .report-box {
            background: #ffffff;
            border: 1px solid var(--light-border);
            border-radius: 22px;
            padding: 24px;
            box-shadow: 0 12px 35px rgba(57, 152, 232, 0.08);
            margin-bottom: 24px;
        }

        .report-title {
            font-weight: 900;
            color: var(--dark-text);
            margin-bottom: 6px;
        }

        .report-subtitle {
            color: var(--gray-text);
            font-size: 14px;
            margin-bottom: 20px;
        }

        .progress {
            height: 10px;
            border-radius: 999px;
            background: #EEF0F4;
        }

        .progress-bar-custom {
            background: var(--primary-blue);
            border-radius: 999px;
        }

        .status-report-row {
            padding: 14px 0;
            border-bottom: 1px solid var(--light-border);
        }

        .status-report-row:last-child {
            border-bottom: none;
        }

        .status-count {
            font-size: 22px;
            font-weight: 900;
            color: var(--dark-text);
        }

        .slot-pill {
            border-radius: 999px;
            padding: 8px 14px;
            font-weight: 800;
            font-size: 13px;
            display: inline-block;
            background: var(--primary-blue-light);
            color: var(--primary-blue);
        }

        @media print {
            .sidebar,
            .mobile-navbar,
            .offcanvas,
            .filter-box,
            .print-hide,
            .action-btn-primary,
            .btn {
                display: none !important;
            }

            .main-content {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }

            .dashboard-wrapper {
                display: block !important;
            }

            .report-box,
            .content-card,
            .stat-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
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
        <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="manage-therapists.php"><i class="bi bi-person-badge"></i> Manage Therapists</a>
        <a href="manage-parents.php"><i class="bi bi-people"></i> Manage Parents</a>
        <a href="manage-appointments.php"><i class="bi bi-calendar-check"></i> Manage Appointments</a>
        <a href="manage-leave-requests.php"><i class="bi bi-calendar-x"></i> Leave Requests</a>
        <a href="reports.php" class="active"><i class="bi bi-bar-chart"></i> Reports</a>
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
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="manage-therapists.php"><i class="bi bi-person-badge"></i> Manage Therapists</a>
            <a href="manage-parents.php"><i class="bi bi-people"></i> Manage Parents</a>
            <a href="manage-appointments.php"><i class="bi bi-calendar-check"></i> Manage Appointments</a>
            <a href="manage-leave-requests.php"><i class="bi bi-calendar-x"></i> Leave Requests</a>
            <a href="reports.php" class="active"><i class="bi bi-bar-chart"></i> Reports</a>
            <a href="notifications.php"><i class="bi bi-bell"></i> Notifications</a>
            <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="page-title">Reports</h1>
                <p class="page-subtitle">
                    Clinic performance report from 
                    <strong><?php echo formatDateNice($start_date); ?></strong>
                    to
                    <strong><?php echo formatDateNice($end_date); ?></strong>.
                </p>
            </div>

            <button type="button" onclick="window.print()" class="action-btn-primary print-hide">
                <i class="bi bi-printer me-1"></i>
                Print Report
            </button>
        </div>

        <?php if (!empty($error)) { ?>
            <div class="alert alert-danger border-0 rounded-4 mb-4">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo escapeOutput($error); ?>
            </div>
        <?php } ?>

        <!-- Date Filter -->
        <div class="filter-box print-hide">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-bold">Start Date</label>
                    <input 
                        type="date" 
                        name="start_date" 
                        class="form-control"
                        value="<?php echo escapeOutput($start_date); ?>"
                        required
                    >
                </div>

                <div class="col-md-5">
                    <label class="form-label fw-bold">End Date</label>
                    <input 
                        type="date" 
                        name="end_date" 
                        class="form-control"
                        value="<?php echo escapeOutput($end_date); ?>"
                        required
                    >
                </div>

                <div class="col-md-2">
                    <button type="submit" class="action-btn-primary w-100">
                        Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                    <div class="stat-number"><?php echo $total_appointments; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-patch-check"></i></div>
                    <div class="stat-number"><?php echo $completed_appointments; ?></div>
                    <div class="stat-label">Completed Sessions</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-number"><?php echo $pending_appointments; ?></div>
                    <div class="stat-label">Pending Requests</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-arrow-repeat"></i></div>
                    <div class="stat-number"><?php echo $needs_rescheduling; ?></div>
                    <div class="stat-label">Needs Rescheduling</div>
                </div>
            </div>

        </div>

        <!-- General Clinic Counts -->
        <div class="row g-4 mb-4">

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-people"></i></div>
                    <div class="stat-number"><?php echo $total_parents; ?></div>
                    <div class="stat-label">Registered Parents</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-person-hearts"></i></div>
                    <div class="stat-number"><?php echo $total_children; ?></div>
                    <div class="stat-label">Registered Children</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-person-badge"></i></div>
                    <div class="stat-number"><?php echo $total_therapists; ?></div>
                    <div class="stat-label">Therapists</div>
                </div>
            </div>

        </div>

        <!-- Appointment Status Report -->
        <div class="report-box">
            <h4 class="report-title">Appointment Status Summary</h4>
            <p class="report-subtitle">Shows how appointments are distributed by status in the selected date range.</p>

            <?php foreach ($status_rows as $row) { ?>
                <?php $percent = calculatePercent($row["count"], $total_appointments); ?>

                <div class="status-report-row">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-2">
                        <div>
                            <span class="badge <?php echo getAppointmentStatusBadgeClass($row["status"]); ?>">
                                <?php echo escapeOutput($row["label"]); ?>
                            </span>
                        </div>

                        <div class="status-count">
                            <?php echo $row["count"]; ?>
                            <span class="text-muted fs-6 fw-normal">
                                (<?php echo $percent; ?>%)
                            </span>
                        </div>
                    </div>

                    <div class="progress">
                        <div 
                            class="progress-bar progress-bar-custom" 
                            style="width: <?php echo $percent; ?>%;"
                        ></div>
                    </div>
                </div>
            <?php } ?>
        </div>

        <!-- Slot Summary -->
        <div class="report-box">
            <h4 class="report-title">Time Slot Summary</h4>
            <p class="report-subtitle">Shows generated slot usage and blocked leave slots in the selected date range.</p>

            <div class="row g-3">
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <span class="slot-pill">Available: <?php echo $available_slots; ?></span>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6">
                    <span class="slot-pill">Pending: <?php echo $pending_slots; ?></span>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6">
                    <span class="slot-pill">Booked: <?php echo $booked_slots; ?></span>
                </div>

                <div class="col-xl-3 col-md-4 col-sm-6">
                    <span class="slot-pill">Blocked For Leave: <?php echo $blocked_slots; ?></span>
                </div>

                <div class="col-xl-3 col-md-4 col-sm-6">
                    <span class="slot-pill">Completed: <?php echo $completed_slots; ?></span>
                </div>
            </div>
        </div>

        <!-- Therapist Wise Report -->
        <div class="content-card mb-4">
            <div class="mb-4">
                <h4 class="fw-bold mb-1">Therapist-wise Appointment Report</h4>
                <p class="text-muted small mb-0">
                    Compare workload and completed sessions by therapist.
                </p>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Therapist</th>
                            <th>Specialization</th>
                            <th>Total</th>
                            <th>Pending</th>
                            <th>Approved</th>
                            <th>Completed</th>
                            <th>Cancelled</th>
                            <th>Needs Rescheduling</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (mysqli_num_rows($therapist_report_result) > 0) { ?>
                            <?php while ($therapist = mysqli_fetch_assoc($therapist_report_result)) { ?>
                                <tr>
                                    <td>
                                        <strong><?php echo escapeOutput($therapist["full_name"]); ?></strong>
                                    </td>

                                    <td><?php echo escapeOutput($therapist["specialization"]); ?></td>
                                    <td><?php echo escapeOutput($therapist["total_appointments"]); ?></td>
                                    <td><?php echo escapeOutput($therapist["pending_count"] ?? 0); ?></td>
                                    <td><?php echo escapeOutput($therapist["approved_count"] ?? 0); ?></td>
                                    <td><?php echo escapeOutput($therapist["completed_count"] ?? 0); ?></td>
                                    <td><?php echo escapeOutput($therapist["cancelled_count"] ?? 0); ?></td>
                                    <td><?php echo escapeOutput($therapist["needs_rescheduling_count"] ?? 0); ?></td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="bi bi-person-badge"></i>
                                        <h5 class="fw-bold">No therapist records found</h5>
                                        <p class="mb-0">Therapist reports will appear here.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>

                </table>
            </div>
        </div>

        <!-- Reschedule History -->
        <div class="content-card">
            <div class="mb-4">
                <h4 class="fw-bold mb-1">Recent Reschedule History</h4>
                <p class="text-muted small mb-0">
                    Latest appointment reschedule records in the selected date range.
                </p>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Parent</th>
                            <th>Child</th>
                            <th>Therapist</th>
                            <th>Old Slot</th>
                            <th>New Slot</th>
                            <th>Reason</th>
                            <th>Rescheduled At</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (mysqli_num_rows($reschedule_result) > 0) { ?>
                            <?php while ($row = mysqli_fetch_assoc($reschedule_result)) { ?>
                                <tr>
                                    <td>#<?php echo escapeOutput($row["reschedule_id"]); ?></td>

                                    <td><?php echo escapeOutput($row["parent_name"]); ?></td>

                                    <td><?php echo escapeOutput($row["child_name"]); ?></td>

                                    <td><?php echo escapeOutput($row["therapist_name"]); ?></td>

                                    <td>
                                        <?php echo formatDateNice($row["old_date"]); ?><br>
                                        <span class="small text-muted">
                                            <?php echo formatTimeNice($row["old_start_time"]); ?>
                                            -
                                            <?php echo formatTimeNice($row["old_end_time"]); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php echo formatDateNice($row["new_date"]); ?><br>
                                        <span class="small text-muted">
                                            <?php echo formatTimeNice($row["new_start_time"]); ?>
                                            -
                                            <?php echo formatTimeNice($row["new_end_time"]); ?>
                                        </span>
                                    </td>

                                    <td style="min-width:180px;">
                                        <?php echo !empty($row["reason"]) ? escapeOutput($row["reason"]) : "No reason added."; ?>
                                    </td>

                                    <td>
                                        <?php echo date("M d, Y h:i A", strtotime($row["created_at"])); ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="bi bi-arrow-repeat"></i>
                                        <h5 class="fw-bold">No reschedule records found</h5>
                                        <p class="mb-0">Reschedule history will appear here after parents reschedule appointments.</p>
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