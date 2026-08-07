<?php
include "../includes/auth_parent.php";
include "../includes/functions.php";
include "../includes/notification_functions.php";
include "../includes/email-functions.php";

$success = "";
$error = "";

$parent_id = $_SESSION["parent_id"];
$parent_name = $_SESSION["parent_name"] ?? "Parent";

$allowed_statuses = [
    "all",
    "pending",
    "approved",
    "rejected",
    "completed",
    "cancelled",
    "needs_rescheduling",
    "rescheduled"
];

/*
|--------------------------------------------------------------------------
| Notify all admins helper
|--------------------------------------------------------------------------
*/
function notifyAllAdminsAboutParentCancellation($conn, $parent_name, $child_name) {
    $admin_result = mysqli_query($conn, "SELECT admin_id FROM admins");

    while ($admin = mysqli_fetch_assoc($admin_result)) {
        createNotification(
            $conn,
            "admin",
            $admin["admin_id"],
            "Appointment Cancelled by Parent",
            $parent_name . " cancelled an appointment for " . $child_name . "."
        );
    }
}

/*
|--------------------------------------------------------------------------
| CANCEL APPOINTMENT BY PARENT
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cancel_appointment"])) {
    $appointment_id = intval($_POST["appointment_id"]);
    $cancel_reason = cleanInput($_POST["cancel_reason"] ?? "");

    if ($appointment_id <= 0) {
        $error = "Invalid appointment selected.";
    } elseif (empty($cancel_reason)) {
        $error = "Please enter a cancellation reason.";
    } else {
        mysqli_begin_transaction($conn);

        try {
            $appointment_sql = "SELECT 
                                    a.appointment_id,
                                    a.parent_id,
                                    a.child_id,
                                    a.therapist_id,
                                    a.slot_id,
                                    a.status,
                                    a.appointment_date,
                                    a.start_time,
                                    a.end_time,
                                    c.child_name,
                                    t.full_name AS therapist_name,
                                    t.email AS therapist_email,
                                    p.full_name AS parent_name_db,
                                    p.email AS parent_email
                                FROM appointments a
                                INNER JOIN children c ON a.child_id = c.child_id
                                INNER JOIN therapists t ON a.therapist_id = t.therapist_id
                                INNER JOIN parents p ON a.parent_id = p.parent_id
                                WHERE a.appointment_id = ?
                                AND a.parent_id = ?
                                LIMIT 1
                                FOR UPDATE";

            $appointment_stmt = mysqli_prepare($conn, $appointment_sql);
            mysqli_stmt_bind_param($appointment_stmt, "ii", $appointment_id, $parent_id);
            mysqli_stmt_execute($appointment_stmt);

            $appointment_result = mysqli_stmt_get_result($appointment_stmt);

            if (mysqli_num_rows($appointment_result) !== 1) {
                throw new Exception("Appointment not found.");
            }

            $appointment = mysqli_fetch_assoc($appointment_result);

            if (!in_array($appointment["status"], ["pending", "approved"])) {
                throw new Exception("Only pending or approved appointments can be cancelled.");
            }

            $note = "Cancelled by parent: " . $cancel_reason;

            $update_appointment_sql = "UPDATE appointments
                                       SET 
                                            status = 'cancelled',
                                            admin_note = ?,
                                            updated_at = NOW()
                                       WHERE appointment_id = ?";

            $update_appointment_stmt = mysqli_prepare($conn, $update_appointment_sql);
            mysqli_stmt_bind_param($update_appointment_stmt, "si", $note, $appointment_id);

            if (!mysqli_stmt_execute($update_appointment_stmt)) {
                throw new Exception("Failed to cancel appointment.");
            }

            $update_slot_sql = "UPDATE time_slots
                                SET status = 'available', updated_at = NOW()
                                WHERE slot_id = ?";

            $update_slot_stmt = mysqli_prepare($conn, $update_slot_sql);
            mysqli_stmt_bind_param($update_slot_stmt, "i", $appointment["slot_id"]);

            if (!mysqli_stmt_execute($update_slot_stmt)) {
                throw new Exception("Failed to release time slot.");
            }

            notifyAllAdminsAboutParentCancellation($conn, $parent_name, $appointment["child_name"]);

            createNotification(
                $conn,
                "therapist",
                $appointment["therapist_id"],
                "Appointment Cancelled",
                $parent_name . " cancelled an appointment for " . $appointment["child_name"] . "."
            );

            createNotification(
                $conn,
                "parent",
                $parent_id,
                "Appointment Cancelled",
                "Your appointment has been cancelled successfully."
            );

            /* ====================== SEND EMAILS ====================== */
            $apptDate = $appointment["appointment_date"];
            $startTime = $appointment["start_time"];
            $endTime = $appointment["end_time"];
            $pName = $appointment["parent_name_db"] ?? $parent_name;
            $pEmail = $appointment["parent_email"];
            $tName = $appointment["therapist_name"];
            $tEmail = $appointment["therapist_email"];
            $cName = $appointment["child_name"];

            sendParentAppointmentStatusEmail(
                $pEmail,
                $pName,
                $cName,
                "cancelled",
                $apptDate,
                $startTime,
                $endTime,
                $cancel_reason
            );

            sendTherapistAppointmentCancelledEmail(
                $tEmail,
                $tName,
                $cName,
                $apptDate,
                $startTime,
                $endTime,
                $cancel_reason,
                $pName . " (Parent)"
            );


            /* ======================================================= */

            mysqli_commit($conn);

            header("Location: my-appointments.php?success=cancelled");
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
    if ($_GET["success"] === "cancelled") {
        $success = "Appointment cancelled successfully. The slot is now available again.";
    }
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/
$status_filter = isset($_GET["status"]) ? cleanInput($_GET["status"]) : "all";
$date_filter = isset($_GET["appointment_date"]) ? cleanInput($_GET["appointment_date"]) : "";

if (!in_array($status_filter, $allowed_statuses)) {
    $status_filter = "all";
}

$where_parts = ["a.parent_id = ?"];
$params = [$parent_id];
$types = "i";

if ($status_filter !== "all") {
    $where_parts[] = "a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($date_filter)) {
    $where_parts[] = "a.appointment_date = ?";
    $params[] = $date_filter;
    $types .= "s";
}

$where_sql = "WHERE " . implode(" AND ", $where_parts);

/*
|--------------------------------------------------------------------------
| FETCH APPOINTMENTS
|--------------------------------------------------------------------------
*/
$appointments_sql = "SELECT 
                        a.appointment_id,
                        a.parent_id,
                        a.child_id,
                        a.therapist_id,
                        a.slot_id,
                        a.appointment_date,
                        a.start_time,
                        a.end_time,
                        a.status,
                        a.parent_message,
                        a.admin_note,
                        a.rejection_reason,
                        a.reschedule_reason,
                        a.created_at,

                        c.child_name,
                        c.age,
                        c.gender,
                        c.speech_concern,
                        c.previous_therapy_history,
                        c.additional_notes,

                        t.full_name AS therapist_name,
                        t.qualification,
                        t.specialization,
                        t.experience,

                        sn.note_id,
                        sn.session_activities,
                        sn.child_performance,
                        sn.progress_notes,
                        sn.home_practice,
                        sn.next_session_recommendation,
                        sn.updated_at AS note_updated_at
                    FROM appointments a
                    INNER JOIN children c ON a.child_id = c.child_id
                    INNER JOIN therapists t ON a.therapist_id = t.therapist_id
                    LEFT JOIN session_notes sn ON a.appointment_id = sn.appointment_id
                    $where_sql
                    ORDER BY a.created_at DESC";

$appointments_stmt = mysqli_prepare($conn, $appointments_sql);
mysqli_stmt_bind_param($appointments_stmt, $types, ...$params);
mysqli_stmt_execute($appointments_stmt);

$appointments_result = mysqli_stmt_get_result($appointments_stmt);

/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/
$pending_sql = "SELECT COUNT(*) AS total FROM appointments WHERE parent_id = ? AND status = 'pending'";
$pending_stmt = mysqli_prepare($conn, $pending_sql);
mysqli_stmt_bind_param($pending_stmt, "i", $parent_id);
mysqli_stmt_execute($pending_stmt);
$pending_result = mysqli_stmt_get_result($pending_stmt);
$pending_count = mysqli_fetch_assoc($pending_result)["total"];

$approved_sql = "SELECT COUNT(*) AS total FROM appointments WHERE parent_id = ? AND status = 'approved'";
$approved_stmt = mysqli_prepare($conn, $approved_sql);
mysqli_stmt_bind_param($approved_stmt, "i", $parent_id);
mysqli_stmt_execute($approved_stmt);
$approved_result = mysqli_stmt_get_result($approved_stmt);
$approved_count = mysqli_fetch_assoc($approved_result)["total"];

$completed_sql = "SELECT COUNT(*) AS total FROM appointments WHERE parent_id = ? AND status = 'completed'";
$completed_stmt = mysqli_prepare($conn, $completed_sql);
mysqli_stmt_bind_param($completed_stmt, "i", $parent_id);
mysqli_stmt_execute($completed_stmt);
$completed_result = mysqli_stmt_get_result($completed_stmt);
$completed_count = mysqli_fetch_assoc($completed_result)["total"];

$reschedule_sql = "SELECT COUNT(*) AS total FROM appointments WHERE parent_id = ? AND status = 'needs_rescheduling'";
$reschedule_stmt = mysqli_prepare($conn, $reschedule_sql);
mysqli_stmt_bind_param($reschedule_stmt, "i", $parent_id);
mysqli_stmt_execute($reschedule_stmt);
$reschedule_result = mysqli_stmt_get_result($reschedule_stmt);
$reschedule_count = mysqli_fetch_assoc($reschedule_result)["total"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Appointments - SpeechCare</title>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Same dashboard design -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <style>
        .small-action-btn {
            border: none;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 800;
            transition: 0.2s ease;
        }

        .btn-view {
            background: var(--primary-blue-light);
            color: var(--primary-blue);
        }

        .btn-view:hover {
            background: var(--primary-blue);
            color: #ffffff;
        }

        .btn-cancel {
            background: #FFECEC;
            color: var(--danger);
        }

        .btn-cancel:hover {
            background: var(--danger);
            color: #ffffff;
        }

        .btn-reschedule {
            background: #FFF4DE;
            color: #B7791F;
        }

        .btn-reschedule:hover {
            background: #F6B23D;
            color: #ffffff;
        }

        .filter-box {
            background: #ffffff;
            border: 1px solid var(--light-border);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 12px 35px rgba(57, 152, 232, 0.08);
            margin-bottom: 24px;
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

        .detail-box {
            background: #F7FBFF;
            border: 1px solid var(--light-border);
            border-radius: 16px;
            padding: 16px;
            height: 100%;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            border-bottom: 1px solid var(--light-border);
            padding: 10px 0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--gray-text);
            font-weight: 600;
        }

        .detail-value {
            color: var(--dark-text);
            font-weight: 800;
            text-align: right;
        }

        .note-box {
            background: #ffffff;
            border: 1px solid var(--light-border);
            border-radius: 14px;
            padding: 14px;
            color: var(--gray-text);
            line-height: 1.7;
            min-height: 80px;
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
        <a href="dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
        <a href="book-appointment.php"><i class="bi bi-calendar-plus"></i> Book Appointment</a>
        <a href="my-appointments.php" class="active"><i class="bi bi-calendar-check"></i> My Appointments</a>
          <a href="progress.php"><i class="bi bi-graph-up-arrow"></i> Progress</a>
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
            <a href="dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
            <a href="book-appointment.php"><i class="bi bi-calendar-plus"></i> Book Appointment</a>
            <a href="my-appointments.php" class="active"><i class="bi bi-calendar-check"></i> My Appointments</a>
              <a href="progress.php"><i class="bi bi-graph-up-arrow"></i> Progress</a>
            <a href="notifications.php"><i class="bi bi-bell"></i> Notifications</a>
            <a href="profile.php"><i class="bi bi-person-circle"></i> Profile</a>
            <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="page-title">My Appointments</h1>
                <p class="page-subtitle">Track your booking requests, session status, and therapy notes.</p>
            </div>

            <div class="profile-circle">
                <?php echo strtoupper(substr($parent_name, 0, 1)); ?>
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

        <?php if ($reschedule_count > 0) { ?>
            <div class="alert alert-warning border-0 rounded-4 mb-4">
                <i class="bi bi-arrow-repeat me-2"></i>
                You have <strong><?php echo $reschedule_count; ?></strong> appointment(s) that need rescheduling.
            </div>
        <?php } ?>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                    <div class="stat-number"><?php echo $approved_count; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-patch-check"></i></div>
                    <div class="stat-number"><?php echo $completed_count; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-arrow-repeat"></i></div>
                    <div class="stat-number"><?php echo $reschedule_count; ?></div>
                    <div class="stat-label">Needs Rescheduling</div>
                </div>
            </div>

        </div>

        <!-- Filters -->
        <div class="filter-box">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach ($allowed_statuses as $status_option) { ?>
                            <option value="<?php echo $status_option; ?>" <?php echo $status_filter === $status_option ? "selected" : ""; ?>>
                                <?php echo $status_option === "all" ? "All" : displayStatusText($status_option); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Appointment Date</label>
                    <input 
                        type="date" 
                        name="appointment_date" 
                        class="form-control"
                        value="<?php echo escapeOutput($date_filter); ?>"
                    >
                </div>

                <div class="col-md-4">
                    <button type="submit" class="action-btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i>
                        Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="fw-bold mb-1">Booking History</h4>
                    <p class="text-muted small mb-0">All your appointment records are listed here.</p>
                </div>

                <a href="my-appointments.php" class="btn btn-light rounded-3 fw-bold">
                    Clear Filters
                </a>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Child</th>
                            <th>Therapist</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Note</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (mysqli_num_rows($appointments_result) > 0) { ?>
                            <?php while ($appointment = mysqli_fetch_assoc($appointments_result)) { ?>
                                <?php
                                    $view_modal_id = "viewAppointmentModal" . $appointment["appointment_id"];
                                ?>

                                <tr>
                                    <td>#<?php echo escapeOutput($appointment["appointment_id"]); ?></td>

                                    <td>
                                        <strong><?php echo escapeOutput($appointment["child_name"]); ?></strong>
                                        <div class="small text-muted">
                                            Age <?php echo escapeOutput($appointment["age"]); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <strong><?php echo escapeOutput($appointment["therapist_name"]); ?></strong>
                                        <div class="small text-muted">
                                            <?php echo escapeOutput($appointment["specialization"]); ?>
                                        </div>
                                    </td>

                                    <td><?php echo formatDateNice($appointment["appointment_date"]); ?></td>

                                    <td>
                                        <?php echo formatTimeNice($appointment["start_time"]); ?>
                                        -
                                        <?php echo formatTimeNice($appointment["end_time"]); ?>
                                    </td>

                                    <td>
                                        <span class="badge <?php echo getAppointmentStatusBadgeClass($appointment["status"]); ?>">
                                            <?php echo displayStatusText($appointment["status"]); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if (!empty($appointment["note_id"])) { ?>
                                            <span class="badge rounded-pill text-bg-primary">Available</span>
                                        <?php } else { ?>
                                            <span class="badge rounded-pill text-bg-secondary">Not Available</span>
                                        <?php } ?>
                                    </td>

                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2 flex-wrap">

                                            <button 
                                                type="button"
                                                class="small-action-btn btn-view"
                                                data-bs-toggle="modal"
                                                data-bs-target="#<?php echo $view_modal_id; ?>"
                                            >
                                                View
                                            </button>

                                            <?php if (in_array($appointment["status"], ["pending", "approved"])) { ?>
                                                <button 
                                                    type="button"
                                                    class="small-action-btn btn-cancel"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#cancelAppointmentModal"
                                                    data-appointment-id="<?php echo $appointment["appointment_id"]; ?>"
                                                    data-child-name="<?php echo escapeOutput($appointment["child_name"]); ?>"
                                                    data-therapist-name="<?php echo escapeOutput($appointment["therapist_name"]); ?>"
                                                    data-date="<?php echo formatDateNice($appointment["appointment_date"]); ?>"
                                                    data-time="<?php echo formatTimeNice($appointment["start_time"]) . ' - ' . formatTimeNice($appointment["end_time"]); ?>"
                                                >
                                                    Cancel
                                                </button>
                                            <?php } ?>

                                            <?php if ($appointment["status"] === "needs_rescheduling") { ?>
                                                <a 
                                                    href="reschedule-appointment.php?appointment_id=<?php echo $appointment["appointment_id"]; ?>" 
                                                    class="small-action-btn btn-reschedule text-decoration-none"
                                                >
                                                    Reschedule
                                                </a>
                                            <?php } ?>

                                        </div>
                                    </td>
                                </tr>

                                <!-- View Modal -->
                                <div class="modal fade" id="<?php echo $view_modal_id; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered modal-xl">
                                        <div class="modal-content">

                                            <div class="modal-header">
                                                <div>
                                                    <h5 class="modal-title">Appointment Details</h5>
                                                    <p class="text-muted small mb-0">
                                                        View appointment status, therapist details, notes, and session feedback.
                                                    </p>
                                                </div>

                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>

                                            <div class="modal-body">

                                                <div class="row g-4 mb-4">

                                                    <div class="col-md-4">
                                                        <div class="detail-box">
                                                            <h5 class="fw-bold mb-3">Child Details</h5>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Name</span>
                                                                <span class="detail-value"><?php echo escapeOutput($appointment["child_name"]); ?></span>
                                                            </div>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Age</span>
                                                                <span class="detail-value"><?php echo escapeOutput($appointment["age"]); ?></span>
                                                            </div>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Gender</span>
                                                                <span class="detail-value"><?php echo displayStatusText($appointment["gender"]); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <div class="detail-box">
                                                            <h5 class="fw-bold mb-3">Therapist Details</h5>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Name</span>
                                                                <span class="detail-value"><?php echo escapeOutput($appointment["therapist_name"]); ?></span>
                                                            </div>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Specialization</span>
                                                                <span class="detail-value"><?php echo escapeOutput($appointment["specialization"]); ?></span>
                                                            </div>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Experience</span>
                                                                <span class="detail-value"><?php echo escapeOutput($appointment["experience"] ?: "Not added"); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <div class="detail-box">
                                                            <h5 class="fw-bold mb-3">Appointment Details</h5>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Date</span>
                                                                <span class="detail-value"><?php echo formatDateNice($appointment["appointment_date"]); ?></span>
                                                            </div>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Time</span>
                                                                <span class="detail-value">
                                                                    <?php echo formatTimeNice($appointment["start_time"]); ?>
                                                                    -
                                                                    <?php echo formatTimeNice($appointment["end_time"]); ?>
                                                                </span>
                                                            </div>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Status</span>
                                                                <span class="detail-value"><?php echo displayStatusText($appointment["status"]); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>

                                                <div class="row g-4 mb-4">
                                                    <div class="col-md-6">
                                                        <h5 class="fw-bold mb-3">Speech Concern</h5>
                                                        <div class="note-box">
                                                            <?php echo escapeOutput($appointment["speech_concern"]); ?>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <h5 class="fw-bold mb-3">Your Message</h5>
                                                        <div class="note-box">
                                                            <?php echo !empty($appointment["parent_message"]) ? escapeOutput($appointment["parent_message"]) : "No message added."; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row g-4 mb-4">
                                                    <div class="col-md-6">
                                                        <h5 class="fw-bold mb-3">Admin Note / Reason</h5>
                                                        <div class="note-box">
                                                            <?php
                                                                if (!empty($appointment["rejection_reason"])) {
                                                                    echo escapeOutput($appointment["rejection_reason"]);
                                                                } elseif (!empty($appointment["admin_note"])) {
                                                                    echo escapeOutput($appointment["admin_note"]);
                                                                } elseif (!empty($appointment["reschedule_reason"])) {
                                                                    echo escapeOutput($appointment["reschedule_reason"]);
                                                                } else {
                                                                    echo "No admin note.";
                                                                }
                                                            ?>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <h5 class="fw-bold mb-3">Previous Therapy History</h5>
                                                        <div class="note-box">
                                                            <?php echo !empty($appointment["previous_therapy_history"]) ? escapeOutput($appointment["previous_therapy_history"]) : "No previous therapy history."; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <h5 class="fw-bold mb-3">Session Notes</h5>

                                                <?php if ($appointment["status"] === "completed" && !empty($appointment["note_id"])) { ?>
                                                    <div class="row g-4">
                                                        <div class="col-md-6">
                                                            <div class="note-box">
                                                                <strong>Session Activities:</strong><br>
                                                                <?php echo escapeOutput($appointment["session_activities"]); ?>
                                                            </div>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <div class="note-box">
                                                                <strong>Child Performance:</strong><br>
                                                                <?php echo escapeOutput($appointment["child_performance"]); ?>
                                                            </div>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <div class="note-box">
                                                                <strong>Progress Notes:</strong><br>
                                                                <?php echo escapeOutput($appointment["progress_notes"]); ?>
                                                            </div>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <div class="note-box">
                                                                <strong>Home Practice:</strong><br>
                                                                <?php echo escapeOutput($appointment["home_practice"]); ?>
                                                            </div>
                                                        </div>

                                                        <div class="col-12">
                                                            <div class="note-box">
                                                                <strong>Next Session Recommendation:</strong><br>
                                                                <?php echo escapeOutput($appointment["next_session_recommendation"]); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } elseif ($appointment["status"] === "completed") { ?>
                                                    <div class="note-box">
                                                        Session completed, but notes are not added yet.
                                                    </div>
                                                <?php } else { ?>
                                                    <div class="note-box">
                                                        Session notes will be available after the therapist completes the session.
                                                    </div>
                                                <?php } ?>

                                            </div>

                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">
                                                    Close
                                                </button>
                                            </div>

                                        </div>
                                    </div>
                                </div>

                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="bi bi-calendar-x"></i>
                                        <h5 class="fw-bold">No appointments found</h5>
                                        <p class="mb-0">Your booking history will appear here after you book an appointment.</p>
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

<!-- Cancel Appointment Modal -->
<div class="modal fade" id="cancelAppointmentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="">
                <input type="hidden" name="appointment_id" id="cancelAppointmentId">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Cancel Appointment</h5>
                        <p class="text-muted small mb-0">This will cancel your appointment and release the slot.</p>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="alert alert-warning border-0 rounded-4">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        You can cancel only pending or approved appointments.
                    </div>

                    <div class="mb-3">
                        <span class="text-muted small">Child</span>
                        <div class="fw-bold" id="cancelChildName">-</div>
                    </div>

                    <div class="mb-3">
                        <span class="text-muted small">Therapist</span>
                        <div class="fw-bold" id="cancelTherapistName">-</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <span class="text-muted small">Date</span>
                            <div class="fw-bold" id="cancelDate">-</div>
                        </div>

                        <div class="col-md-6">
                            <span class="text-muted small">Time</span>
                            <div class="fw-bold" id="cancelTime">-</div>
                        </div>
                    </div>

                    <label class="form-label fw-bold">Cancellation Reason</label>
                    <textarea 
                        name="cancel_reason" 
                        class="form-control"
                        placeholder="Example: We are unable to attend this session."
                        required
                    ></textarea>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">
                        Close
                    </button>

                    <button type="submit" name="cancel_appointment" class="small-action-btn btn-cancel">
                        Confirm Cancel
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
const cancelModal = document.getElementById("cancelAppointmentModal");

if (cancelModal) {
    cancelModal.addEventListener("show.bs.modal", function (event) {
        const button = event.relatedTarget;

        document.getElementById("cancelAppointmentId").value = button.getAttribute("data-appointment-id");
        document.getElementById("cancelChildName").textContent = button.getAttribute("data-child-name");
        document.getElementById("cancelTherapistName").textContent = button.getAttribute("data-therapist-name");
        document.getElementById("cancelDate").textContent = button.getAttribute("data-date");
        document.getElementById("cancelTime").textContent = button.getAttribute("data-time");
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>