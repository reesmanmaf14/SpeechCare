<?php
include "../includes/auth_admin.php";
include "../includes/functions.php";
include "../includes/notification_functions.php";
include "../includes/email-functions.php";

$success = "";
$error = "";

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
| Helper: get appointment with slot lock
|--------------------------------------------------------------------------
*/
function getAppointmentForAdminUpdate($conn, $appointment_id) {
    $sql = "SELECT 
                a.appointment_id,
                a.parent_id,
                a.child_id,
                a.therapist_id,
                a.slot_id,
                a.status,
                a.appointment_date,
                a.start_time,
                a.end_time,
                p.full_name AS parent_name,
                c.child_name,
                t.full_name AS therapist_name,
                t.email AS therapist_email,
                p.email AS parent_email,
                ts.status AS slot_status
            FROM appointments a
            INNER JOIN parents p ON a.parent_id = p.parent_id
            INNER JOIN children c ON a.child_id = c.child_id
            INNER JOIN therapists t ON a.therapist_id = t.therapist_id
            INNER JOIN time_slots ts ON a.slot_id = ts.slot_id
            WHERE a.appointment_id = ?
            LIMIT 1
            FOR UPDATE";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $appointment_id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) !== 1) {
        return null;
    }

    return mysqli_fetch_assoc($result);
}

/*
|--------------------------------------------------------------------------
| APPROVE APPOINTMENT
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["approve_appointment"])) {
    $appointment_id = intval($_POST["appointment_id"]);

    if ($appointment_id <= 0) {
        $error = "Invalid appointment selected.";
    } else {
        mysqli_begin_transaction($conn);

        try {
            $appointment = getAppointmentForAdminUpdate($conn, $appointment_id);

            if (!$appointment) {
                throw new Exception("Appointment not found.");
            }

            if ($appointment["status"] !== "pending") {
                throw new Exception("Only pending appointments can be approved.");
            }

            $update_appointment_sql = "UPDATE appointments
                                       SET status = 'approved', updated_at = NOW()
                                       WHERE appointment_id = ?";

            $update_appointment_stmt = mysqli_prepare($conn, $update_appointment_sql);
            mysqli_stmt_bind_param($update_appointment_stmt, "i", $appointment_id);

            if (!mysqli_stmt_execute($update_appointment_stmt)) {
                throw new Exception("Failed to approve appointment.");
            }

            $update_slot_sql = "UPDATE time_slots
                                SET status = 'booked', updated_at = NOW()
                                WHERE slot_id = ?";

            $update_slot_stmt = mysqli_prepare($conn, $update_slot_sql);
            mysqli_stmt_bind_param($update_slot_stmt, "i", $appointment["slot_id"]);

            if (!mysqli_stmt_execute($update_slot_stmt)) {
                throw new Exception("Failed to update slot status.");
            }

            createNotification(
                $conn,
                "parent",
                $appointment["parent_id"],
                "Appointment Approved",
                "Your appointment with " . $appointment["therapist_name"] . " has been approved."
            );

            createNotification(
                $conn,
                "therapist",
                $appointment["therapist_id"],
                "New Approved Appointment",
                "You have a new approved appointment with " . $appointment["child_name"] . "."
            );

                       /* ====================== SEND EMAIL ====================== */
            $parent_full_name = $appointment["parent_name"] ?? 'Parent';

            sendParentAppointmentStatusEmail(
                $appointment["parent_email"],
                $parent_full_name,
                $appointment["child_name"],
                "approved",
                $appointment["appointment_date"],
                $appointment["start_time"],
                $appointment["end_time"]
            );
            /* ======================================================= */

            $appointmentQuery = mysqli_query($conn,"
SELECT
    a.appointment_date,
    a.start_time,
    a.end_time,
    c.child_name,
    t.full_name,
    t.email
FROM appointments a
INNER JOIN children c
    ON a.child_id = c.child_id
INNER JOIN therapists t
    ON a.therapist_id = t.therapist_id
WHERE a.appointment_id = $appointment_id
");

$data = mysqli_fetch_assoc($appointmentQuery);

sendTherapistAppointmentApprovedEmail(
    $data['email'],
    $data['full_name'],
    $data['child_name'],
    $data['appointment_date'],
    $data['start_time'],
    $data['end_time']
);
$admin_name = $_SESSION["admin_name"] ?? "Admin";

/* ================================================================ */

            mysqli_commit($conn);

            header("Location: manage-appointments.php?success=approved");
            exit();

        } catch (Exception $exception) {
            mysqli_rollback($conn);
            $error = $exception->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| REJECT APPOINTMENT
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["reject_appointment"])) {
    $appointment_id = intval($_POST["appointment_id"]);
    $rejection_reason = cleanInput($_POST["rejection_reason"] ?? "");

    if ($appointment_id <= 0) {
        $error = "Invalid appointment selected.";
    } elseif (empty($rejection_reason)) {
        $error = "Please enter a rejection reason.";
    } else {
        mysqli_begin_transaction($conn);

        try {
            $appointment = getAppointmentForAdminUpdate($conn, $appointment_id);

            if (!$appointment) {
                throw new Exception("Appointment not found.");
            }

            if ($appointment["status"] !== "pending") {
                throw new Exception("Only pending appointments can be rejected.");
            }

            $update_appointment_sql = "UPDATE appointments
                                       SET 
                                            status = 'rejected',
                                            rejection_reason = ?,
                                            admin_note = ?,
                                            updated_at = NOW()
                                       WHERE appointment_id = ?";

            $update_appointment_stmt = mysqli_prepare($conn, $update_appointment_sql);
            mysqli_stmt_bind_param(
                $update_appointment_stmt,
                "ssi",
                $rejection_reason,
                $rejection_reason,
                $appointment_id
            );

            if (!mysqli_stmt_execute($update_appointment_stmt)) {
                throw new Exception("Failed to reject appointment.");
            }

            $update_slot_sql = "UPDATE time_slots
                                SET status = 'available', updated_at = NOW()
                                WHERE slot_id = ?";

            $update_slot_stmt = mysqli_prepare($conn, $update_slot_sql);
            mysqli_stmt_bind_param($update_slot_stmt, "i", $appointment["slot_id"]);

            if (!mysqli_stmt_execute($update_slot_stmt)) {
                throw new Exception("Failed to release time slot.");
            }

            createNotification(
                $conn,
                "parent",
                $appointment["parent_id"],
                "Appointment Rejected",
                "Your appointment request was rejected. Reason: " . $rejection_reason
            );
            /* ====================== SEND EMAIL ====================== */
            $parent_full_name = $appointment["parent_name"] ?? 'Parent';

            sendParentAppointmentStatusEmail(
                $appointment["parent_email"],
                $parent_full_name,
                $appointment["child_name"],
                "rejected",
                $appointment["appointment_date"],
                $appointment["start_time"],
                $appointment["end_time"],
                $rejection_reason
            );
            /* ======================================================= */

            $appointmentQuery = mysqli_query($conn,"
SELECT
    a.appointment_date,
    a.start_time,
    a.end_time,
    a.rejection_reason,
    c.child_name,
    t.full_name,
    t.email
FROM appointments a
INNER JOIN children c
    ON a.child_id = c.child_id
INNER JOIN therapists t
    ON a.therapist_id = t.therapist_id
WHERE a.appointment_id = $appointment_id
");

$data = mysqli_fetch_assoc($appointmentQuery);

sendTherapistAppointmentRejectedEmail(
    $data['email'],
    $data['full_name'],
    $data['child_name'],
    $data['appointment_date'],
    $data['start_time'],
    $data['end_time'],
    $data['rejection_reason']
);
            mysqli_commit($conn);

            header("Location: manage-appointments.php?success=rejected");
            exit();

        } catch (Exception $exception) {
            mysqli_rollback($conn);
            $error = $exception->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| CANCEL APPOINTMENT
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
            $appointment = getAppointmentForAdminUpdate($conn, $appointment_id);

            if (!$appointment) {
                throw new Exception("Appointment not found.");
            }

            if (!in_array($appointment["status"], ["pending", "approved"])) {
                throw new Exception("Only pending or approved appointments can be cancelled.");
            }

            $update_appointment_sql = "UPDATE appointments
                                       SET 
                                            status = 'cancelled',
                                            admin_note = ?,
                                            updated_at = NOW()
                                       WHERE appointment_id = ?";

            $update_appointment_stmt = mysqli_prepare($conn, $update_appointment_sql);
            mysqli_stmt_bind_param($update_appointment_stmt, "si", $cancel_reason, $appointment_id);

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

            createNotification(
                $conn,
                "parent",
                $appointment["parent_id"],
                "Appointment Cancelled",
                "Your appointment was cancelled. Reason: " . $cancel_reason
            );

            createNotification(
                $conn,
                "therapist",
                $appointment["therapist_id"],
                "Appointment Cancelled",
                "An appointment with " . $appointment["child_name"] . " was cancelled."
            );

                      /* ====================== SEND EMAIL ====================== */
            $parent_full_name = $appointment["parent_name"] ?? 'Parent';

            sendParentAppointmentStatusEmail(
                $appointment["parent_email"],
                $parent_full_name,
                $appointment["child_name"],
                "cancelled",
                $appointment["appointment_date"],
                $appointment["start_time"],
                $appointment["end_time"],
                $cancel_reason
            );

            sendTherapistAppointmentCancelledEmail(
                $appointment["therapist_email"],
                $appointment["therapist_name"],
                $appointment["child_name"],
                $appointment["appointment_date"],
                $appointment["start_time"],
                $appointment["end_time"],
                $cancel_reason,
                "Administrator"
            );


            /* ======================================================= */

            mysqli_commit($conn);

            header("Location: manage-appointments.php?success=cancelled");
            exit();

        } catch (Exception $exception) {
            mysqli_rollback($conn);
            $error = $exception->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Success messages
|--------------------------------------------------------------------------
*/
if (isset($_GET["success"])) {
    if ($_GET["success"] === "approved") {
        $success = "Appointment approved successfully. Slot status changed to booked.";
    } elseif ($_GET["success"] === "rejected") {
        $success = "Appointment rejected successfully. Slot is available again.";
    } elseif ($_GET["success"] === "cancelled") {
        $success = "Appointment cancelled successfully. Slot is available again.";
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$status_filter = isset($_GET["status"]) ? cleanInput($_GET["status"]) : "all";
$search = isset($_GET["search"]) ? cleanInput($_GET["search"]) : "";
$date_filter = isset($_GET["appointment_date"]) ? cleanInput($_GET["appointment_date"]) : "";

if (!in_array($status_filter, $allowed_statuses)) {
    $status_filter = "all";
}

$where_parts = [];
$params = [];
$types = "";

if ($status_filter !== "all") {
    $where_parts[] = "a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search)) {
    $where_parts[] = "(
        p.full_name LIKE ?
        OR p.email LIKE ?
        OR c.child_name LIKE ?
        OR c.speech_concern LIKE ?
        OR t.full_name LIKE ?
        OR t.specialization LIKE ?
    )";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "ssssss";
}

if (!empty($date_filter)) {
    $where_parts[] = "a.appointment_date = ?";
    $params[] = $date_filter;
    $types .= "s";
}

$where_sql = "";

if (count($where_parts) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_parts);
}

/*
|--------------------------------------------------------------------------
| Fetch appointments
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

                        p.full_name AS parent_name,
                        p.email AS parent_email,
                        p.phone AS parent_phone,

                        c.child_name,
                        c.age,
                        c.gender,
                        c.speech_concern,
                        c.previous_therapy_history,
                        c.additional_notes,

                        t.full_name AS therapist_name,
                        t.specialization,

                        sn.note_id,
                        sn.session_activities,
                        sn.child_performance,
                        sn.progress_notes,
                        sn.home_practice,
                        sn.next_session_recommendation
                    FROM appointments a
                    INNER JOIN parents p ON a.parent_id = p.parent_id
                    INNER JOIN children c ON a.child_id = c.child_id
                    INNER JOIN therapists t ON a.therapist_id = t.therapist_id
                    LEFT JOIN session_notes sn ON a.appointment_id = sn.appointment_id
                    $where_sql
                    ORDER BY a.created_at DESC";

$appointments_stmt = mysqli_prepare($conn, $appointments_sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($appointments_stmt, $types, ...$params);
}

mysqli_stmt_execute($appointments_stmt);
$appointments_result = mysqli_stmt_get_result($appointments_stmt);

/*
|--------------------------------------------------------------------------
| Counts
|--------------------------------------------------------------------------
*/
$total_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments"))["total"];
$pending_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE status = 'pending'"))["total"];
$approved_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE status = 'approved'"))["total"];
$completed_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE status = 'completed'"))["total"];
$reschedule_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE status = 'needs_rescheduling'"))["total"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Appointments - SpeechCare</title>

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

        .btn-cancel {
            background: #EEF0F4;
            color: #6B7280;
        }

        .btn-cancel:hover {
            background: #6B7280;
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
        <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="manage-therapists.php"><i class="bi bi-person-badge"></i> Manage Therapists</a>
        <a href="manage-parents.php"><i class="bi bi-people"></i> Manage Parents</a>
        <a href="manage-appointments.php" class="active"><i class="bi bi-calendar-check"></i> Manage Appointments</a>
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
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="manage-therapists.php"><i class="bi bi-person-badge"></i> Manage Therapists</a>
            <a href="manage-parents.php"><i class="bi bi-people"></i> Manage Parents</a>
            <a href="manage-appointments.php" class="active"><i class="bi bi-calendar-check"></i> Manage Appointments</a>
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
                <h1 class="page-title">Manage Appointments</h1>
                <p class="page-subtitle">Approve, reject, cancel, and review therapy appointments.</p>
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
                    <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                    <div class="stat-number"><?php echo $total_appointments; ?></div>
                    <div class="stat-label">Total Appointments</div>
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
                    <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                    <div class="stat-number"><?php echo $approved_appointments; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-patch-check"></i></div>
                    <div class="stat-number"><?php echo $completed_appointments; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>

        </div>

        <?php if ($reschedule_appointments > 0) { ?>
            <div class="alert alert-warning border-0 rounded-4 mb-4">
                <i class="bi bi-arrow-repeat me-2"></i>
                There are <strong><?php echo $reschedule_appointments; ?></strong> appointment(s) that need parent rescheduling.
            </div>
        <?php } ?>

        <!-- Filters -->
        <div class="filter-box">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label fw-bold">Search</label>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control"
                        value="<?php echo escapeOutput($search); ?>"
                        placeholder="Parent, child, therapist..."
                    >
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach ($allowed_statuses as $status_option) { ?>
                            <option value="<?php echo $status_option; ?>" <?php echo $status_filter === $status_option ? "selected" : ""; ?>>
                                <?php echo $status_option === "all" ? "All" : displayStatusText($status_option); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">Date</label>
                    <input 
                        type="date" 
                        name="appointment_date" 
                        class="form-control"
                        value="<?php echo escapeOutput($date_filter); ?>"
                    >
                </div>

                <div class="col-lg-2 col-md-6">
                    <button type="submit" class="action-btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i>
                        Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Appointments Table -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="fw-bold mb-1">Appointment Requests</h4>
                    <p class="text-muted small mb-0">Pending appointments require admin approval.</p>
                </div>

                <a href="manage-appointments.php" class="btn btn-light rounded-3 fw-bold">
                    Clear Filters
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
                                        <strong><?php echo escapeOutput($appointment["parent_name"]); ?></strong>
                                        <div class="small text-muted">
                                            <?php echo escapeOutput($appointment["parent_email"]); ?>
                                        </div>
                                    </td>

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

                                            <?php if ($appointment["status"] === "pending") { ?>
                                                <form method="POST" action="" onsubmit="return confirm('Approve this appointment?');">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment["appointment_id"]; ?>">

                                                    <button type="submit" name="approve_appointment" class="small-action-btn btn-approve">
                                                        Approve
                                                    </button>
                                                </form>

                                                <button 
                                                    type="button"
                                                    class="small-action-btn btn-reject"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#rejectAppointmentModal"
                                                    data-appointment-id="<?php echo $appointment["appointment_id"]; ?>"
                                                    data-parent-name="<?php echo escapeOutput($appointment["parent_name"]); ?>"
                                                    data-child-name="<?php echo escapeOutput($appointment["child_name"]); ?>"
                                                    data-therapist-name="<?php echo escapeOutput($appointment["therapist_name"]); ?>"
                                                    data-date="<?php echo formatDateNice($appointment["appointment_date"]); ?>"
                                                    data-time="<?php echo formatTimeNice($appointment["start_time"]) . ' - ' . formatTimeNice($appointment["end_time"]); ?>"
                                                >
                                                    Reject
                                                </button>
                                            <?php } ?>

                                            <?php if (in_array($appointment["status"], ["pending", "approved"])) { ?>
                                                <button 
                                                    type="button"
                                                    class="small-action-btn btn-cancel"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#cancelAppointmentModal"
                                                    data-appointment-id="<?php echo $appointment["appointment_id"]; ?>"
                                                    data-parent-name="<?php echo escapeOutput($appointment["parent_name"]); ?>"
                                                    data-child-name="<?php echo escapeOutput($appointment["child_name"]); ?>"
                                                    data-therapist-name="<?php echo escapeOutput($appointment["therapist_name"]); ?>"
                                                >
                                                    Cancel
                                                </button>
                                            <?php } ?>

                                        </div>
                                    </td>
                                </tr>

                                <!-- View Appointment Modal -->
                                <div class="modal fade" id="<?php echo $view_modal_id; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered modal-xl">
                                        <div class="modal-content">

                                            <div class="modal-header">
                                                <div>
                                                    <h5 class="modal-title">Appointment Details</h5>
                                                    <p class="text-muted small mb-0">
                                                        Parent, child, therapist, appointment, and session note details.
                                                    </p>
                                                </div>

                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>

                                            <div class="modal-body">

                                                <div class="row g-4 mb-4">
                                                    <div class="col-md-4">
                                                        <div class="detail-box">
                                                            <h5 class="fw-bold mb-3">Parent Details</h5>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Name</span>
                                                                <span class="detail-value"><?php echo escapeOutput($appointment["parent_name"]); ?></span>
                                                            </div>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Email</span>
                                                                <span class="detail-value"><?php echo escapeOutput($appointment["parent_email"]); ?></span>
                                                            </div>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Phone</span>
                                                                <span class="detail-value"><?php echo escapeOutput($appointment["parent_phone"]); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>

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
                                                            <h5 class="fw-bold mb-3">Appointment Details</h5>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Therapist</span>
                                                                <span class="detail-value"><?php echo escapeOutput($appointment["therapist_name"]); ?></span>
                                                            </div>

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
                                                        <h5 class="fw-bold mb-3">Parent Message</h5>
                                                        <div class="note-box">
                                                            <?php echo !empty($appointment["parent_message"]) ? escapeOutput($appointment["parent_message"]) : "No parent message."; ?>
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

                                                <?php if (!empty($appointment["note_id"])) { ?>
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
                                                <?php } else { ?>
                                                    <div class="note-box">
                                                        No session notes added yet.
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
                                        <p class="mb-0">Appointment requests will appear here after parents book sessions.</p>
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

<!-- Reject Appointment Modal -->
<div class="modal fade" id="rejectAppointmentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="">
                <input type="hidden" name="appointment_id" id="rejectAppointmentId">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Reject Appointment</h5>
                        <p class="text-muted small mb-0">Parent will see this rejection reason.</p>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <span class="text-muted small">Parent</span>
                        <div class="fw-bold" id="rejectParentName">-</div>
                    </div>

                    <div class="mb-3">
                        <span class="text-muted small">Child</span>
                        <div class="fw-bold" id="rejectChildName">-</div>
                    </div>

                    <div class="mb-3">
                        <span class="text-muted small">Therapist</span>
                        <div class="fw-bold" id="rejectTherapistName">-</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <span class="text-muted small">Date</span>
                            <div class="fw-bold" id="rejectDate">-</div>
                        </div>

                        <div class="col-md-6">
                            <span class="text-muted small">Time</span>
                            <div class="fw-bold" id="rejectTime">-</div>
                        </div>
                    </div>

                    <label class="form-label fw-bold">Rejection Reason</label>
                    <textarea 
                        name="rejection_reason" 
                        class="form-control"
                        placeholder="Example: Therapist is unavailable at this time. Please choose another slot."
                        required
                    ></textarea>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" name="reject_appointment" class="small-action-btn btn-reject">
                        Confirm Reject
                    </button>
                </div>
            </form>

        </div>
    </div>
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
                        <p class="text-muted small mb-0">This will release the slot again.</p>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-warning border-0 rounded-4">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        The appointment will be cancelled and the slot will become available again.
                    </div>

                    <div class="mb-3">
                        <span class="text-muted small">Parent</span>
                        <div class="fw-bold" id="cancelParentName">-</div>
                    </div>

                    <div class="mb-3">
                        <span class="text-muted small">Child</span>
                        <div class="fw-bold" id="cancelChildName">-</div>
                    </div>

                    <div class="mb-3">
                        <span class="text-muted small">Therapist</span>
                        <div class="fw-bold" id="cancelTherapistName">-</div>
                    </div>

                    <label class="form-label fw-bold">Cancellation Reason</label>
                    <textarea 
                        name="cancel_reason" 
                        class="form-control"
                        placeholder="Example: Clinic schedule changed. Please choose another slot."
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
const rejectModal = document.getElementById("rejectAppointmentModal");

if (rejectModal) {
    rejectModal.addEventListener("show.bs.modal", function (event) {
        const button = event.relatedTarget;

        document.getElementById("rejectAppointmentId").value = button.getAttribute("data-appointment-id");
        document.getElementById("rejectParentName").textContent = button.getAttribute("data-parent-name");
        document.getElementById("rejectChildName").textContent = button.getAttribute("data-child-name");
        document.getElementById("rejectTherapistName").textContent = button.getAttribute("data-therapist-name");
        document.getElementById("rejectDate").textContent = button.getAttribute("data-date");
        document.getElementById("rejectTime").textContent = button.getAttribute("data-time");
    });
}

const cancelModal = document.getElementById("cancelAppointmentModal");

if (cancelModal) {
    cancelModal.addEventListener("show.bs.modal", function (event) {
        const button = event.relatedTarget;

        document.getElementById("cancelAppointmentId").value = button.getAttribute("data-appointment-id");
        document.getElementById("cancelParentName").textContent = button.getAttribute("data-parent-name");
        document.getElementById("cancelChildName").textContent = button.getAttribute("data-child-name");
        document.getElementById("cancelTherapistName").textContent = button.getAttribute("data-therapist-name");
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>