<?php
include "../includes/auth_admin.php";
include "../includes/functions.php";
include "../includes/notification_functions.php";
include "../includes/email-functions.php";

$success = "";
$error = "";

$admin_id = $_SESSION["admin_id"] ?? 0;

$allowed_statuses = ["all", "pending", "approved", "rejected", "cancelled"];

/*
|--------------------------------------------------------------------------
| Get Leave Request For Update
|--------------------------------------------------------------------------
*/
function getLeaveRequestForUpdate($conn, $leave_id) {
    $sql = "SELECT 
                lr.leave_id,
                lr.therapist_id,
                lr.leave_date,
                lr.leave_type,
                lr.start_time,
                lr.end_time,
                lr.reason,
                lr.status,
                t.full_name AS therapist_name,
                t.email AS therapist_email
            FROM therapist_leave_requests lr
            INNER JOIN therapists t ON lr.therapist_id = t.therapist_id
            WHERE lr.leave_id = ?
            LIMIT 1
            FOR UPDATE";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $leave_id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) !== 1) {
        return null;
    }

    return mysqli_fetch_assoc($result);
}

/*
|--------------------------------------------------------------------------
| Get affected appointments before update
|--------------------------------------------------------------------------
*/
function getAffectedAppointmentsForLeave($conn, $leave) {
    $appointments = [];

    if ($leave["leave_type"] === "full_day") {
        $sql = "SELECT 
                    a.appointment_id,
                    a.parent_id,
                    a.therapist_id,
                    a.status,
                    a.appointment_date,
                    a.start_time,
                    a.end_time,
                    p.full_name AS parent_name,
                    p.email AS parent_email,
                    c.child_name
                FROM appointments a
                INNER JOIN parents p ON a.parent_id = p.parent_id
                INNER JOIN children c ON a.child_id = c.child_id
                WHERE a.therapist_id = ?
                AND a.appointment_date = ?
                AND a.status IN ('pending', 'approved')
                FOR UPDATE";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "is", $leave["therapist_id"], $leave["leave_date"]);
    } else {
        $sql = "SELECT 
                    a.appointment_id,
                    a.parent_id,
                    a.therapist_id,
                    a.status,
                    a.appointment_date,
                    a.start_time,
                    a.end_time,
                    p.full_name AS parent_name,
                    p.email AS parent_email,
                    c.child_name
                FROM appointments a
                INNER JOIN parents p ON a.parent_id = p.parent_id
                INNER JOIN children c ON a.child_id = c.child_id
                WHERE a.therapist_id = ?
                AND a.appointment_date = ?
                AND a.status IN ('pending', 'approved')
                AND (? < a.end_time AND ? > a.start_time)
                FOR UPDATE";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "isss",
            $leave["therapist_id"],
            $leave["leave_date"],
            $leave["start_time"],
            $leave["end_time"]
        );
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $appointments[] = $row;
    }

    return $appointments;
}

/*
|--------------------------------------------------------------------------
| Mark appointments as needs_rescheduling
|--------------------------------------------------------------------------
*/
function markAppointmentsNeedsRescheduling($conn, $leave, $reason) {
    if ($leave["leave_type"] === "full_day") {
        $sql = "UPDATE appointments
                SET 
                    status = 'needs_rescheduling',
                    reschedule_reason = ?,
                    admin_note = ?,
                    updated_at = NOW()
                WHERE therapist_id = ?
                AND appointment_date = ?
                AND status IN ('pending', 'approved')";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "ssis",
            $reason,
            $reason,
            $leave["therapist_id"],
            $leave["leave_date"]
        );
    } else {
        $sql = "UPDATE appointments
                SET 
                    status = 'needs_rescheduling',
                    reschedule_reason = ?,
                    admin_note = ?,
                    updated_at = NOW()
                WHERE therapist_id = ?
                AND appointment_date = ?
                AND status IN ('pending', 'approved')
                AND (? < end_time AND ? > start_time)";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "ssisss",
            $reason,
            $reason,
            $leave["therapist_id"],
            $leave["leave_date"],
            $leave["start_time"],
            $leave["end_time"]
        );
    }

    if (!mysqli_stmt_execute($stmt)) {
        return false;
    }

    return true;
}

/*
|--------------------------------------------------------------------------
| Block affected slots
|--------------------------------------------------------------------------
*/
function blockSlotsForLeave($conn, $leave) {
    if ($leave["leave_type"] === "full_day") {
        $sql = "UPDATE time_slots
                SET 
                    status = 'blocked_for_leave',
                    updated_at = NOW()
                WHERE therapist_id = ?
                AND slot_date = ?
                AND status IN ('available', 'pending', 'booked')";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "is", $leave["therapist_id"], $leave["leave_date"]);
    } else {
        $sql = "UPDATE time_slots
                SET 
                    status = 'blocked_for_leave',
                    updated_at = NOW()
                WHERE therapist_id = ?
                AND slot_date = ?
                AND status IN ('available', 'pending', 'booked')
                AND (? < end_time AND ? > start_time)";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "isss",
            $leave["therapist_id"],
            $leave["leave_date"],
            $leave["start_time"],
            $leave["end_time"]
        );
    }

    if (!mysqli_stmt_execute($stmt)) {
        return false;
    }

    return mysqli_stmt_affected_rows($stmt);
}

/*
|--------------------------------------------------------------------------
| APPROVE LEAVE REQUEST
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["approve_leave"])) {
    $leave_id = intval($_POST["leave_id"]);
    $admin_note = cleanInput($_POST["admin_note"] ?? "");

    if ($leave_id <= 0) {
        $error = "Invalid leave request selected.";
    } else {
        mysqli_begin_transaction($conn);

        try {
            $leave = getLeaveRequestForUpdate($conn, $leave_id);

            if (!$leave) {
                throw new Exception("Leave request not found.");
            }

            if ($leave["status"] !== "pending") {
                throw new Exception("Only pending leave requests can be approved.");
            }

            $affected_appointments = getAffectedAppointmentsForLeave($conn, $leave);

            $update_leave_sql = "UPDATE therapist_leave_requests
                                 SET 
                                    status = 'approved',
                                    admin_note = ?,
                                    approved_by = ?,
                                    updated_at = NOW()
                                 WHERE leave_id = ?";

            $update_leave_stmt = mysqli_prepare($conn, $update_leave_sql);
            mysqli_stmt_bind_param($update_leave_stmt, "sii", $admin_note, $admin_id, $leave_id);

            if (!mysqli_stmt_execute($update_leave_stmt)) {
                throw new Exception("Failed to approve leave request.");
            }

            $reschedule_reason = "Therapist is unavailable due to approved leave. Please reschedule this appointment.";

            if (!markAppointmentsNeedsRescheduling($conn, $leave, $reschedule_reason)) {
                throw new Exception("Failed to update affected appointments.");
            }

            $blocked_slot_count = blockSlotsForLeave($conn, $leave);

            if ($blocked_slot_count === false) {
                throw new Exception("Failed to block affected time slots.");
            }

          

             
            createNotification(
                $conn,
                "therapist",
                $leave["therapist_id"],
                "Leave Request Approved",
                "Your leave request for " . formatDateNice($leave["leave_date"]) . " has been approved."
            );
            
            // Notify affected parents
            foreach ($affected_appointments as $appt) {
                createNotification(
                    $conn,
                    "parent",
                    $appt["parent_id"],
                    "Appointment Needs Rescheduling",
                    "Your appointment on " . formatDateNice($appt["appointment_date"]) . " needs to be rescheduled as the therapist will be on leave."
                );

                sendParentNeedsReschedulingEmail(
                    $appt["parent_email"],
                    $appt["parent_name"],
                    $appt["child_name"],
                    $appt["appointment_date"],
                    $appt["start_time"],
                    $appt["end_time"],
                    $reschedule_reason
                );
            }

            mysqli_commit($conn);

        
          sendLeaveApprovedEmail(
                $leave['therapist_email'],
                $leave['therapist_name'],
                $leave['leave_date'],
                $leave['leave_type']
            );


            header(
                "Location: manage-leave-requests.php?success=approved&slots=" .
                intval($blocked_slot_count) .
                "&appointments=" .
                count($affected_appointments)
            );
            exit();

        } catch (Exception $exception) {
            mysqli_rollback($conn);
            $error = $exception->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| REJECT LEAVE REQUEST
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["reject_leave"])) {
    $leave_id = intval($_POST["leave_id"]);
    $admin_note = cleanInput($_POST["admin_note"] ?? "");

    if ($leave_id <= 0) {
        $error = "Invalid leave request selected.";
    } elseif (empty($admin_note)) {
        $error = "Please enter a rejection reason.";
    } else {
        mysqli_begin_transaction($conn);

        try {
            $leave = getLeaveRequestForUpdate($conn, $leave_id);

            if (!$leave) {
                throw new Exception("Leave request not found.");
            }

            if ($leave["status"] !== "pending") {
                throw new Exception("Only pending leave requests can be rejected.");
            }

            $update_sql = "UPDATE therapist_leave_requests
                           SET 
                                status = 'rejected',
                                admin_note = ?,
                                approved_by = ?,
                                updated_at = NOW()
                           WHERE leave_id = ?";

            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "sii", $admin_note, $admin_id, $leave_id);

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to reject leave request.");
            }
             
      

            createNotification(
                $conn,
                "therapist",
                $leave["therapist_id"],
                "Leave Request Rejected",
                "Your leave request for " . formatDateNice($leave["leave_date"]) . " was rejected. Reason: " . $admin_note
            );

            mysqli_commit($conn);
    
  sendLeaveRejectedEmail(
                $leave['therapist_email'],
                $leave['therapist_name'],
                $leave['leave_date'],
                $leave['leave_type'],
                $admin_note
            );

            header("Location: manage-leave-requests.php?success=rejected");
            exit();

        } catch (Exception $exception) {
            mysqli_rollback($conn);
            $error = $exception->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/
if (isset($_GET["success"])) {
    if ($_GET["success"] === "approved") {
        $slots = intval($_GET["slots"] ?? 0);
        $appointments = intval($_GET["appointments"] ?? 0);

        $success = "Leave request approved successfully. Blocked slots: {$slots}. Appointments needing rescheduling: {$appointments}.";
    } elseif ($_GET["success"] === "rejected") {
        $success = "Leave request rejected successfully.";
    }
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/
$status_filter = isset($_GET["status"]) ? cleanInput($_GET["status"]) : "all";

if (!in_array($status_filter, $allowed_statuses)) {
    $status_filter = "all";
}

$where_parts = [];
$params = [];
$types = "";

if ($status_filter !== "all") {
    $where_parts[] = "lr.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_sql = "";

if (count($where_parts) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_parts);
}

/*
|--------------------------------------------------------------------------
| FETCH LEAVE REQUESTS
|--------------------------------------------------------------------------
*/
$leave_sql = "SELECT 
                lr.leave_id,
                lr.therapist_id,
                lr.leave_date,
                lr.leave_type,
                lr.start_time,
                lr.end_time,
                lr.reason,
                lr.status,
                lr.admin_note,
                lr.created_at,
                lr.updated_at,
                t.full_name AS therapist_name,
                t.email AS therapist_email,
                t.specialization
              FROM therapist_leave_requests lr
              INNER JOIN therapists t ON lr.therapist_id = t.therapist_id
              $where_sql
              ORDER BY lr.created_at DESC";

$leave_stmt = mysqli_prepare($conn, $leave_sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($leave_stmt, $types, ...$params);
}

mysqli_stmt_execute($leave_stmt);
$leave_result = mysqli_stmt_get_result($leave_stmt);

/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/
$total_requests = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM therapist_leave_requests"))["total"];
$pending_requests = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM therapist_leave_requests WHERE status = 'pending'"))["total"];
$approved_requests = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM therapist_leave_requests WHERE status = 'approved'"))["total"];
$rejected_requests = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM therapist_leave_requests WHERE status = 'rejected'"))["total"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Leave Requests - SpeechCare</title>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Same dashboard design -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <style>
        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-tabs a {
            border: 1px solid var(--light-border);
            background: #ffffff;
            color: var(--gray-text);
            border-radius: 999px;
            padding: 9px 16px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            transition: 0.2s ease;
        }

        .filter-tabs a:hover,
        .filter-tabs a.active {
            background: var(--primary-blue-light);
            color: var(--primary-blue);
            border-color: var(--primary-blue);
        }

        .small-action-btn {
            border: none;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 800;
            transition: 0.2s ease;
        }

        .btn-approve {
            background: #E6F8F0;
            color: var(--success);
        }

        .btn-approve:hover {
            background: var(--success);
            color: #ffffff;
        }

        .btn-reject {
            background: #FFECEC;
            color: var(--danger);
        }

        .btn-reject:hover {
            background: var(--danger);
            color: #ffffff;
        }

        .info-card {
            background: #F7FBFF;
            border: 1px solid var(--light-border);
            border-radius: 18px;
            padding: 18px;
            color: var(--gray-text);
            line-height: 1.7;
        }

        .form-control,
        .form-select {
            border: 1px solid var(--light-border);
            border-radius: 14px;
            min-height: 50px;
            padding: 11px 14px;
        }

        textarea.form-control {
            min-height: 110px;
            resize: vertical;
        }

        @media (max-width: 576px) {
            .small-action-btn {
                width: 100%;
                margin-bottom: 6px;
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
        <a href="manage-leave-requests.php" class="active"><i class="bi bi-calendar-x"></i> Leave Requests</a>
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
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="manage-therapists.php"><i class="bi bi-person-badge"></i> Manage Therapists</a>
            <a href="manage-parents.php"><i class="bi bi-people"></i> Manage Parents</a>
            <a href="manage-appointments.php"><i class="bi bi-calendar-check"></i> Manage Appointments</a>
            <a href="manage-leave-requests.php" class="active"><i class="bi bi-calendar-x"></i> Leave Requests</a>
            <a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
            <a href="notifications.php"><i class="bi bi-bell"></i> Notifications</a>
            <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="page-title">Manage Leave Requests</h1>
                <p class="page-subtitle">Approve or reject therapist leave requests.</p>
            </div>
        </div>

        <?php if (!empty($success)) { ?>
            <div class="alert alert-success border-0 rounded-4 mb-4">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo escapeOutput($success); ?>
            </div>
        <?php } ?>

        <?php if (!empty($error)) { ?>
            <div class="alert alert-danger border-0 rounded-4 mb-4">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo escapeOutput($error); ?>
            </div>
        <?php } ?>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-calendar-x"></i></div>
                    <div class="stat-number"><?php echo $total_requests; ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-number"><?php echo $pending_requests; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                    <div class="stat-number"><?php echo $approved_requests; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
                    <div class="stat-number"><?php echo $rejected_requests; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

        </div>

        <div class="info-card mb-4">
            <strong>Important:</strong><br>
            When you approve leave, the system blocks affected slots. If parents already have pending or approved appointments during that leave time, those appointments become <strong>Needs Rescheduling</strong>.
        </div>

        <!-- Leave Requests Table -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="fw-bold mb-1">Therapist Leave Requests</h4>
                    <p class="text-muted small mb-0">Review all submitted therapist leave requests.</p>
                </div>

                <div class="filter-tabs">
                    <a href="manage-leave-requests.php?status=all" class="<?php echo $status_filter === 'all' ? 'active' : ''; ?>">All</a>
                    <a href="manage-leave-requests.php?status=pending" class="<?php echo $status_filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                    <a href="manage-leave-requests.php?status=approved" class="<?php echo $status_filter === 'approved' ? 'active' : ''; ?>">Approved</a>
                    <a href="manage-leave-requests.php?status=rejected" class="<?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
                    <a href="manage-leave-requests.php?status=cancelled" class="<?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Therapist</th>
                            <th>Leave Date</th>
                            <th>Type</th>
                            <th>Time</th>
                            <th>Reason</th>
                            <th>Admin Note</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (mysqli_num_rows($leave_result) > 0) { ?>
                            <?php while ($leave = mysqli_fetch_assoc($leave_result)) { ?>
                                <tr>
                                    <td>#<?php echo escapeOutput($leave["leave_id"]); ?></td>

                                    <td>
                                        <strong><?php echo escapeOutput($leave["therapist_name"]); ?></strong>
                                        <div class="small text-muted">
                                            <?php echo escapeOutput($leave["therapist_email"]); ?>
                                        </div>
                                        <div class="small text-muted">
                                            <?php echo escapeOutput($leave["specialization"]); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <strong><?php echo formatDateNice($leave["leave_date"]); ?></strong>
                                    </td>

                                    <td><?php echo displayStatusText($leave["leave_type"]); ?></td>

                                    <td>
                                        <?php if ($leave["leave_type"] === "full_day") { ?>
                                            Full Day
                                        <?php } else { ?>
                                            <?php echo formatTimeNice($leave["start_time"]); ?>
                                            -
                                            <?php echo formatTimeNice($leave["end_time"]); ?>
                                        <?php } ?>
                                    </td>

                                    <td style="min-width:180px;">
                                        <?php echo !empty($leave["reason"]) ? escapeOutput($leave["reason"]) : "No reason added."; ?>
                                    </td>

                                    <td style="min-width:180px;">
                                        <?php echo !empty($leave["admin_note"]) ? escapeOutput($leave["admin_note"]) : "No admin note."; ?>
                                    </td>

                                    <td>
                                        <span class="badge <?php echo getAppointmentStatusBadgeClass($leave["status"]); ?>">
                                            <?php echo displayStatusText($leave["status"]); ?>
                                        </span>
                                    </td>

                                    <td class="text-end">
                                        <?php if ($leave["status"] === "pending") { ?>
                                            <div class="d-flex justify-content-end gap-2 flex-wrap">
                                                <button 
                                                    type="button"
                                                    class="small-action-btn btn-approve"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#approveLeaveModal"
                                                    data-leave-id="<?php echo $leave["leave_id"]; ?>"
                                                    data-therapist-name="<?php echo escapeOutput($leave["therapist_name"]); ?>"
                                                    data-leave-date="<?php echo formatDateNice($leave["leave_date"]); ?>"
                                                    data-leave-type="<?php echo displayStatusText($leave["leave_type"]); ?>"
                                                >
                                                    Approve
                                                </button>

                                                <button 
                                                    type="button"
                                                    class="small-action-btn btn-reject"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#rejectLeaveModal"
                                                    data-leave-id="<?php echo $leave["leave_id"]; ?>"
                                                    data-therapist-name="<?php echo escapeOutput($leave["therapist_name"]); ?>"
                                                    data-leave-date="<?php echo formatDateNice($leave["leave_date"]); ?>"
                                                    data-leave-type="<?php echo displayStatusText($leave["leave_type"]); ?>"
                                                >
                                                    Reject
                                                </button>
                                            </div>
                                        <?php } else { ?>
                                            <span class="text-muted small fw-bold">No Action</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i class="bi bi-calendar-x"></i>
                                        <h5 class="fw-bold">No leave requests found</h5>
                                        <p class="mb-0">Leave requests submitted by therapists will appear here.</p>
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

<!-- Approve Leave Modal -->
<div class="modal fade" id="approveLeaveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="">
                <input type="hidden" name="leave_id" id="approveLeaveId">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Approve Leave Request</h5>
                        <p class="text-muted small mb-0">
                            This will block affected slots and mark affected appointments for rescheduling.
                        </p>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="alert alert-warning border-0 rounded-4">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Approving leave may affect existing parent appointments.
                    </div>

                    <div class="mb-3">
                        <span class="text-muted small">Therapist</span>
                        <div class="fw-bold" id="approveTherapistName">-</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <span class="text-muted small">Leave Date</span>
                            <div class="fw-bold" id="approveLeaveDate">-</div>
                        </div>

                        <div class="col-md-6">
                            <span class="text-muted small">Leave Type</span>
                            <div class="fw-bold" id="approveLeaveType">-</div>
                        </div>
                    </div>

                    <label class="form-label fw-bold">Admin Note</label>
                    <textarea 
                        name="admin_note" 
                        class="form-control"
                        placeholder="Optional note for therapist."
                    ></textarea>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" name="approve_leave" class="small-action-btn btn-approve">
                        Confirm Approve
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- Reject Leave Modal -->
<div class="modal fade" id="rejectLeaveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="">
                <input type="hidden" name="leave_id" id="rejectLeaveId">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Reject Leave Request</h5>
                        <p class="text-muted small mb-0">
                            Please enter a reason. Therapist will see this message.
                        </p>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="mb-3">
                        <span class="text-muted small">Therapist</span>
                        <div class="fw-bold" id="rejectTherapistName">-</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <span class="text-muted small">Leave Date</span>
                            <div class="fw-bold" id="rejectLeaveDate">-</div>
                        </div>

                        <div class="col-md-6">
                            <span class="text-muted small">Leave Type</span>
                            <div class="fw-bold" id="rejectLeaveType">-</div>
                        </div>
                    </div>

                    <label class="form-label fw-bold">Rejection Reason</label>
                    <textarea 
                        name="admin_note" 
                        class="form-control"
                        placeholder="Example: Leave cannot be approved because appointments are already scheduled."
                        required
                    ></textarea>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" name="reject_leave" class="small-action-btn btn-reject">
                        Confirm Reject
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
const approveLeaveModal = document.getElementById("approveLeaveModal");

if (approveLeaveModal) {
    approveLeaveModal.addEventListener("show.bs.modal", function (event) {
        const button = event.relatedTarget;

        document.getElementById("approveLeaveId").value = button.getAttribute("data-leave-id");
        document.getElementById("approveTherapistName").textContent = button.getAttribute("data-therapist-name");
        document.getElementById("approveLeaveDate").textContent = button.getAttribute("data-leave-date");
        document.getElementById("approveLeaveType").textContent = button.getAttribute("data-leave-type");
    });
}

const rejectLeaveModal = document.getElementById("rejectLeaveModal");

if (rejectLeaveModal) {
    rejectLeaveModal.addEventListener("show.bs.modal", function (event) {
        const button = event.relatedTarget;

        document.getElementById("rejectLeaveId").value = button.getAttribute("data-leave-id");
        document.getElementById("rejectTherapistName").textContent = button.getAttribute("data-therapist-name");
        document.getElementById("rejectLeaveDate").textContent = button.getAttribute("data-leave-date");
        document.getElementById("rejectLeaveType").textContent = button.getAttribute("data-leave-type");
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>