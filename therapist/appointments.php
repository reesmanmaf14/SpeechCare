<?php
/*
|==========================================================================
| APPOINTMENTS.PHP — GOALS REMOVED
|==========================================================================
|
| SQL migrations needed before deploying this file:
|
|   ALTER TABLE session_notes
|     ADD COLUMN performance_rating TINYINT NULL COMMENT '1-5 scale',
|     ADD COLUMN parent_summary TEXT NULL;
|
|==========================================================================
*/

include "../includes/auth_therapist.php";
include "../includes/functions.php";
include "../includes/notification_functions.php";

$success = "";
$error   = "";

$therapist_id   = $_SESSION["therapist_id"];
$therapist_name = $_SESSION["therapist_name"] ?? "Therapist";

$allowed_statuses = [
    "all",
    "approved",
    "completed",
    "cancelled",
    "needs_rescheduling",
    "rescheduled",
];

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

/**
 * Validate performance_rating: 1-5 or null.
 */
function validRating($value): ?int
{
    if ($value === "" || $value === null) {
        return null;
    }
    $v = (int) $value;
    return ($v >= 1 && $v <= 5) ? $v : null;
}

/*
|--------------------------------------------------------------------------
| COMPLETE SESSION + ADD SESSION NOTE
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["complete_session"])) {
    $appointment_id = intval($_POST["appointment_id"]);
    $child_id       = intval($_POST["child_id"]);

    $session_activities          = cleanInput($_POST["session_activities"]);
    $child_performance           = cleanInput($_POST["child_performance"]);
    $progress_notes              = cleanInput($_POST["progress_notes"]);
    $home_practice               = cleanInput($_POST["home_practice"]);
    $next_session_recommendation = cleanInput($_POST["next_session_recommendation"]);
    $performance_rating          = validRating($_POST["performance_rating"] ?? "");
    $parent_summary              = cleanInput($_POST["parent_summary"] ?? "");

    if ($appointment_id <= 0 || $child_id <= 0) {
        $error = "Invalid appointment selected.";
    } elseif (empty($session_activities) || empty($progress_notes)) {
        $error = "Session activities and progress notes are required.";
    } else {
        mysqli_begin_transaction($conn);

        try {
            // Lock appointment row
            $appointment_sql  = "SELECT a.appointment_id, a.parent_id, a.child_id, a.slot_id, a.status, c.child_name
                                 FROM appointments a
                                 INNER JOIN children c ON a.child_id = c.child_id
                                 WHERE a.appointment_id = ? AND a.therapist_id = ? AND a.child_id = ?
                                 LIMIT 1 FOR UPDATE";
            $appointment_stmt = mysqli_prepare($conn, $appointment_sql);
            mysqli_stmt_bind_param($appointment_stmt, "iii", $appointment_id, $therapist_id, $child_id);
            mysqli_stmt_execute($appointment_stmt);
            $appointment_result = mysqli_stmt_get_result($appointment_stmt);

            if (mysqli_num_rows($appointment_result) !== 1) {
                throw new Exception("Appointment not found.");
            }
            $appointment = mysqli_fetch_assoc($appointment_result);

            if ($appointment["status"] !== "approved") {
                throw new Exception("Only approved appointments can be completed.");
            }

            // Insert session note
            $note_sql  = "INSERT INTO session_notes
                            (appointment_id, therapist_id, child_id,
                             session_activities, child_performance, progress_notes,
                             home_practice, next_session_recommendation,
                             performance_rating, parent_summary)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $note_stmt = mysqli_prepare($conn, $note_sql);
            mysqli_stmt_bind_param(
                $note_stmt,
                "iiisssssis",
                $appointment_id,
                $therapist_id,
                $child_id,
                $session_activities,
                $child_performance,
                $progress_notes,
                $home_practice,
                $next_session_recommendation,
                $performance_rating,
                $parent_summary
            );
            if (!mysqli_stmt_execute($note_stmt)) {
                throw new Exception("Failed to save session note.");
            }

            // Update appointment
            $upd_appt = mysqli_prepare($conn, "UPDATE appointments SET status = 'completed', updated_at = NOW() WHERE appointment_id = ?");
            mysqli_stmt_bind_param($upd_appt, "i", $appointment_id);
            if (!mysqli_stmt_execute($upd_appt)) {
                throw new Exception("Failed to complete appointment.");
            }

            // Update slot
            $upd_slot = mysqli_prepare($conn, "UPDATE time_slots SET status = 'completed', updated_at = NOW() WHERE slot_id = ?");
            mysqli_stmt_bind_param($upd_slot, "i", $appointment["slot_id"]);
            if (!mysqli_stmt_execute($upd_slot)) {
                throw new Exception("Failed to update slot status.");
            }

            createNotification(
                $conn,
                "parent",
                $appointment["parent_id"],
                "Session Completed",
                "Your child's therapy session has been completed. Session notes are now available."
            );

            mysqli_commit($conn);

            header("Location: appointments.php?success=completed");
            exit();

        } catch (Exception $exception) {
            mysqli_rollback($conn);
            $error = $exception->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| UPDATE SESSION NOTE
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_session_note"])) {
    $appointment_id = intval($_POST["appointment_id"]);

    $session_activities          = cleanInput($_POST["session_activities"]);
    $child_performance           = cleanInput($_POST["child_performance"]);
    $progress_notes              = cleanInput($_POST["progress_notes"]);
    $home_practice               = cleanInput($_POST["home_practice"]);
    $next_session_recommendation = cleanInput($_POST["next_session_recommendation"]);
    $performance_rating          = validRating($_POST["performance_rating"] ?? "");
    $parent_summary              = cleanInput($_POST["parent_summary"] ?? "");

    if ($appointment_id <= 0) {
        $error = "Invalid appointment selected.";
    } elseif (empty($session_activities) || empty($progress_notes)) {
        $error = "Session activities and progress notes are required.";
    } else {
        $check_sql  = "SELECT sn.note_id, a.child_id
                       FROM session_notes sn
                       INNER JOIN appointments a ON sn.appointment_id = a.appointment_id
                       WHERE sn.appointment_id = ? AND sn.therapist_id = ? AND a.status = 'completed'
                       LIMIT 1";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        $therapist_id = (int) $therapist_id;
        mysqli_stmt_bind_param($check_stmt, "ii", $appointment_id, $therapist_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) !== 1) {
            $error = "Session note not found.";
        } else {
            $note     = mysqli_fetch_assoc($check_result);
            mysqli_begin_transaction($conn);

            try {
                $update_note_sql  = "UPDATE session_notes
                                     SET session_activities = ?,
                                         child_performance = ?,
                                         progress_notes = ?,
                                         home_practice = ?,
                                         next_session_recommendation = ?,
                                         performance_rating = ?,
                                         parent_summary = ?,
                                         updated_at = NOW()
                                     WHERE note_id = ?";
                $update_note_stmt = mysqli_prepare($conn, $update_note_sql);
                mysqli_stmt_bind_param(
                    $update_note_stmt,
                    "sssssisi",
                    $session_activities,
                    $child_performance,
                    $progress_notes,
                    $home_practice,
                    $next_session_recommendation,
                    $performance_rating,
                    $parent_summary,
                    $note["note_id"]
                );
                if (!mysqli_stmt_execute($update_note_stmt)) {
                    throw new Exception("Failed to update session note.");
                }

                mysqli_commit($conn);

                header("Location: appointments.php?success=note_updated");
                exit();

            } catch (Exception $exception) {
                mysqli_rollback($conn);
                $error = $exception->getMessage();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGES
|--------------------------------------------------------------------------
*/
if (isset($_GET["success"])) {
    if ($_GET["success"] === "completed") {
        $success = "Session note saved and appointment marked as completed.";
    } elseif ($_GET["success"] === "note_updated") {
        $success = "Session note updated successfully.";
    }
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/
$status_filter = isset($_GET["status"]) ? cleanInput($_GET["status"]) : "all";
$date_filter   = isset($_GET["appointment_date"]) ? cleanInput($_GET["appointment_date"]) : "";

if (!in_array($status_filter, $allowed_statuses)) {
    $status_filter = "all";
}

$where_parts = ["a.therapist_id = ?"];
$params      = [$therapist_id];
$types       = "i";

if ($status_filter === "all") {
    $where_parts[] = "a.status IN ('approved', 'completed', 'cancelled', 'needs_rescheduling', 'rescheduled')";
} else {
    $where_parts[] = "a.status = ?";
    $params[]      = $status_filter;
    $types         .= "s";
}

if (!empty($date_filter)) {
    $where_parts[] = "a.appointment_date = ?";
    $params[]      = $date_filter;
    $types         .= "s";
}

$where_sql = "WHERE " . implode(" AND ", $where_parts);

/*
|--------------------------------------------------------------------------
| FETCH APPOINTMENTS
|--------------------------------------------------------------------------
*/
$appointments_sql  = "SELECT
                        a.appointment_id, a.parent_id, a.child_id, a.therapist_id, a.slot_id,
                        a.appointment_date, a.start_time, a.end_time, a.status,
                        a.parent_message, a.admin_note, a.rejection_reason, a.reschedule_reason, a.created_at,
                        p.full_name AS parent_name, p.email AS parent_email, p.phone AS parent_phone,
                        c.child_name, c.age, c.gender, c.speech_concern,
                        c.previous_therapy_history, c.additional_notes,
                        sn.note_id, sn.session_activities, sn.child_performance, sn.progress_notes,
                        sn.home_practice, sn.next_session_recommendation,
                        sn.performance_rating, sn.parent_summary,
                        sn.updated_at AS note_updated_at
                      FROM appointments a
                      INNER JOIN parents p  ON a.parent_id = p.parent_id
                      INNER JOIN children c ON a.child_id  = c.child_id
                      LEFT  JOIN session_notes sn ON a.appointment_id = sn.appointment_id
                      $where_sql
                      ORDER BY a.appointment_date DESC, a.start_time DESC";
$appointments_stmt = mysqli_prepare($conn, $appointments_sql);
mysqli_stmt_bind_param($appointments_stmt, $types, ...$params);
mysqli_stmt_execute($appointments_stmt);
$appointments_result = mysqli_stmt_get_result($appointments_stmt);

/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/
$today_sql    = "SELECT COUNT(*) AS total FROM appointments WHERE therapist_id = ? AND status = 'approved' AND appointment_date = CURDATE()";
$today_stmt   = mysqli_prepare($conn, $today_sql);
mysqli_stmt_bind_param($today_stmt, "i", $therapist_id);
mysqli_stmt_execute($today_stmt);
$today_count  = mysqli_fetch_assoc(mysqli_stmt_get_result($today_stmt))["total"];

$approved_sql   = "SELECT COUNT(*) AS total FROM appointments WHERE therapist_id = ? AND status = 'approved'";
$approved_stmt  = mysqli_prepare($conn, $approved_sql);
mysqli_stmt_bind_param($approved_stmt, "i", $therapist_id);
mysqli_stmt_execute($approved_stmt);
$approved_count = mysqli_fetch_assoc(mysqli_stmt_get_result($approved_stmt))["total"];

$completed_sql   = "SELECT COUNT(*) AS total FROM appointments WHERE therapist_id = ? AND status = 'completed'";
$completed_stmt  = mysqli_prepare($conn, $completed_sql);
mysqli_stmt_bind_param($completed_stmt, "i", $therapist_id);
mysqli_stmt_execute($completed_stmt);
$completed_count = mysqli_fetch_assoc(mysqli_stmt_get_result($completed_stmt))["total"];

$reschedule_sql   = "SELECT COUNT(*) AS total FROM appointments WHERE therapist_id = ? AND status = 'needs_rescheduling'";
$reschedule_stmt  = mysqli_prepare($conn, $reschedule_sql);
mysqli_stmt_bind_param($reschedule_stmt, "i", $therapist_id);
mysqli_stmt_execute($reschedule_stmt);
$reschedule_count = mysqli_fetch_assoc(mysqli_stmt_get_result($reschedule_stmt))["total"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Appointments - SpeechCare</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <style>
        /* ── Sidebar ──────────────────────────────────────────── */
        .therapist-sidebar {
            width: 280px; min-height: 100vh;
            background: #ffffff; border-right: 1px solid #e2e8f0;
            box-shadow: 4px 0 20px rgba(0,0,0,.04);
            padding: 24px 18px; position: fixed; left: 0; top: 0;
        }
        .therapist-sidebar .sidebar-logo {
            font-family: 'Segoe UI', sans-serif; font-size: 32px;
            font-weight: 600; letter-spacing: -1.4px; color: #3998E8;
            margin-bottom: 6px; line-height: 1;
        }
        .therapist-sidebar .sidebar-subtitle {
            font-size: 13px; font-weight: 500; color: #666B7A;
            letter-spacing: .5px; margin-bottom: 34px;
            opacity: .85; text-transform: uppercase;
        }
        .therapist-sidebar .sidebar-menu a {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 16px; border-radius: 12px;
            color: #666B7A; text-decoration: none;
            margin-bottom: 6px; font-weight: 600; transition: .2s ease;
        }
        .therapist-sidebar .sidebar-menu a:hover,
        .therapist-sidebar .sidebar-menu a.active {
            background: #eff6ff; color: #3998E8;
            box-shadow: 0 4px 12px rgba(37,99,235,.12);
        }

        /* ── Action buttons ───────────────────────────────────── */
        .small-action-btn {
            border: none; border-radius: 10px;
            padding: 8px 12px; font-size: 13px;
            font-weight: 800; transition: .2s ease;
        }
        .btn-view    { background: var(--primary-blue-light); color: var(--primary-blue); }
        .btn-view:hover    { background: var(--primary-blue); color: #fff; }
        .btn-complete      { background: #E6F8F0; color: var(--success); }
        .btn-complete:hover { background: var(--success); color: #fff; }
        .btn-note          { background: #FFF4DE; color: #B7791F; }
        .btn-note:hover    { background: #F6B23D; color: #fff; }

        /* ── Filter box ───────────────────────────────────────── */
        .filter-box {
            background: #fff; border: 1px solid var(--light-border);
            border-radius: 20px; padding: 20px;
            box-shadow: 0 12px 35px rgba(57,152,232,.08); margin-bottom: 24px;
        }
        .form-control, .form-select {
            border: 1px solid var(--light-border);
            border-radius: 14px; min-height: 50px; padding: 11px 14px;
        }
        textarea.form-control { min-height: 110px; resize: vertical; }

        /* ── Detail / note boxes ──────────────────────────────── */
        .detail-box {
            background: #F7FBFF; border: 1px solid var(--light-border);
            border-radius: 16px; padding: 16px; height: 100%;
        }
        .detail-row {
            display: flex; justify-content: space-between; gap: 15px;
            border-bottom: 1px solid var(--light-border); padding: 10px 0;
        }
        .detail-row:last-child  { border-bottom: none; }
        .detail-label { color: var(--gray-text); font-weight: 600; }
        .detail-value { color: var(--dark-text); font-weight: 800; text-align: right; }
        .note-box {
            background: #fff; border: 1px solid var(--light-border);
            border-radius: 14px; padding: 14px; color: var(--gray-text);
            line-height: 1.7; min-height: 80px;
        }

        /* ── Star rating display ──────────────────────────────── */
        .rating-badge {
            display: inline-flex; align-items: center; gap: 4px;
            background: #FFF4DE; color: #B7791F;
            border-radius: 8px; padding: 3px 10px;
            font-size: 13px; font-weight: 700;
        }

        @media (max-width: 576px) {
            .small-action-btn { width: 100%; margin-bottom: 6px; }
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
        <a href="manage-availability.php"><i class="bi bi-calendar-week"></i> Manage Availability</a>
        <a href="apply-leave.php"><i class="bi bi-calendar-x"></i> Apply Leave</a>
        <a href="appointments.php" class="active"><i class="bi bi-calendar-check"></i> My Appointments</a>
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
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="manage-availability.php"><i class="bi bi-calendar-week"></i> Manage Availability</a>
            <a href="apply-leave.php"><i class="bi bi-calendar-x"></i> Apply Leave</a>
            <a href="appointments.php" class="active"><i class="bi bi-calendar-check"></i> My Appointments</a>
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
                <p class="page-subtitle">View assigned sessions, add session notes, and complete appointments.</p>
            </div>
            <div class="profile-circle">
                <?php echo strtoupper(substr($therapist_name, 0, 1)); ?>
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
                    <div class="stat-icon"><i class="bi bi-calendar-day"></i></div>
                    <div class="stat-number"><?php echo $today_count; ?></div>
                    <div class="stat-label">Today Approved</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                    <div class="stat-number"><?php echo $approved_count; ?></div>
                    <div class="stat-label">Approved Sessions</div>
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
                    <input type="date" name="appointment_date" class="form-control"
                           value="<?php echo escapeOutput($date_filter); ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="action-btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="fw-bold mb-1">Assigned Appointments</h4>
                    <p class="text-muted small mb-0">Pending appointments are shown only after admin approval.</p>
                </div>
                <a href="appointments.php" class="btn btn-light rounded-3 fw-bold">Clear Filters</a>
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
                            <th>Note</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (mysqli_num_rows($appointments_result) > 0) { ?>
                        <?php while ($appointment = mysqli_fetch_assoc($appointments_result)) {

                            $appt_id         = (int) $appointment["appointment_id"];
                            $appt_child_id   = (int) $appointment["child_id"];
                            $view_modal_id   = "viewAppointmentModal{$appt_id}";
                            $comp_modal_id   = "completeSessionModal{$appt_id}";
                            $edit_modal_id   = "editNoteModal{$appt_id}";
                        ?>

                        <tr>
                            <td>#<?php echo escapeOutput($appt_id); ?></td>
                            <td>
                                <strong><?php echo escapeOutput($appointment["parent_name"]); ?></strong>
                                <div class="small text-muted"><?php echo escapeOutput($appointment["parent_email"]); ?></div>
                            </td>
                            <td>
                                <strong><?php echo escapeOutput($appointment["child_name"]); ?></strong>
                                <div class="small text-muted">Age <?php echo escapeOutput($appointment["age"]); ?></div>
                            </td>
                            <td style="min-width:180px;"><?php echo escapeOutput($appointment["speech_concern"]); ?></td>
                            <td><?php echo formatDateNice($appointment["appointment_date"]); ?></td>
                            <td>
                                <?php echo formatTimeNice($appointment["start_time"]); ?> -
                                <?php echo formatTimeNice($appointment["end_time"]); ?>
                            </td>
                            <td>
                                <span class="badge <?php echo getAppointmentStatusBadgeClass($appointment["status"]); ?>">
                                    <?php echo displayStatusText($appointment["status"]); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($appointment["note_id"])) { ?>
                                    <span class="badge rounded-pill text-bg-primary">Added</span>
                                <?php } else { ?>
                                    <span class="badge rounded-pill text-bg-secondary">Not Added</span>
                                <?php } ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2 flex-wrap">
                                    <button type="button" class="small-action-btn btn-view"
                                            data-bs-toggle="modal" data-bs-target="#<?php echo $view_modal_id; ?>">
                                        View
                                    </button>
                                    <?php if ($appointment["status"] === "approved") { ?>
                                        <button type="button" class="small-action-btn btn-complete"
                                                data-bs-toggle="modal" data-bs-target="#<?php echo $comp_modal_id; ?>">
                                            Complete
                                        </button>
                                    <?php } ?>
                                    <?php if ($appointment["status"] === "completed" && !empty($appointment["note_id"])) { ?>
                                        <button type="button" class="small-action-btn btn-note"
                                                data-bs-toggle="modal" data-bs-target="#<?php echo $edit_modal_id; ?>">
                                            Edit Note
                                        </button>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>

                        <!-- VIEW MODAL -->
                        <div class="modal fade" id="<?php echo $view_modal_id; ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered modal-xl">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <div>
                                            <h5 class="modal-title">Appointment Details</h5>
                                            <p class="text-muted small mb-0">View parent, child, appointment, and session note details.</p>
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
                                                        <span class="detail-label">Date</span>
                                                        <span class="detail-value"><?php echo formatDateNice($appointment["appointment_date"]); ?></span>
                                                    </div>
                                                    <div class="detail-row">
                                                        <span class="detail-label">Time</span>
                                                        <span class="detail-value">
                                                            <?php echo formatTimeNice($appointment["start_time"]); ?> -
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
                                                <div class="note-box"><?php echo escapeOutput($appointment["speech_concern"]); ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <h5 class="fw-bold mb-3">Parent Message</h5>
                                                <div class="note-box">
                                                    <?php echo !empty($appointment["parent_message"]) ? escapeOutput($appointment["parent_message"]) : "No parent message."; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <h5 class="fw-bold mb-3">Session Note</h5>

                                        <?php if (!empty($appointment["note_id"])) { ?>
                                            <div class="row g-3 mb-4">
                                                <div class="col-md-4">
                                                    <div class="detail-box">
                                                        <div class="detail-row">
                                                            <span class="detail-label">Overall Rating</span>
                                                            <span class="detail-value">
                                                                <?php if (!empty($appointment["performance_rating"])) {
                                                                    echo '<span class="rating-badge"><i class="bi bi-star-fill"></i>' . escapeOutput($appointment["performance_rating"]) . '/5</span>';
                                                                } else { echo '<span class="text-muted">—</span>'; } ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-8">
                                                    <div class="fw-bold mb-1 small">Parent Summary</div>
                                                    <div class="note-box" style="min-height:56px;">
                                                        <?php echo !empty($appointment["parent_summary"]) ? escapeOutput($appointment["parent_summary"]) : '<em class="text-muted">Not provided.</em>'; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row g-4 mb-4">
                                                <div class="col-md-6">
                                                    <div class="note-box"><strong>Session Activities:</strong><br><?php echo escapeOutput($appointment["session_activities"]); ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="note-box"><strong>Child Performance:</strong><br><?php echo escapeOutput($appointment["child_performance"]); ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="note-box"><strong>Progress Notes:</strong><br><?php echo escapeOutput($appointment["progress_notes"]); ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="note-box"><strong>Home Practice:</strong><br><?php echo escapeOutput($appointment["home_practice"]); ?></div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="note-box"><strong>Next Session Recommendation:</strong><br><?php echo escapeOutput($appointment["next_session_recommendation"]); ?></div>
                                                </div>
                                            </div>
                                        <?php } else { ?>
                                            <div class="note-box">No session note added yet.</div>
                                        <?php } ?>

                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- COMPLETE SESSION MODAL -->
                        <?php if ($appointment["status"] === "approved") { ?>
                        <div class="modal fade" id="<?php echo $comp_modal_id; ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                                <div class="modal-content">
                                    <form method="POST" action="">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appt_id; ?>">
                                        <input type="hidden" name="child_id" value="<?php echo $appt_child_id; ?>">

                                        <div class="modal-header">
                                            <div>
                                                <h5 class="modal-title">Complete Session</h5>
                                                <p class="text-muted small mb-0">Add session notes before completing this appointment.</p>
                                            </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>

                                        <div class="modal-body">
                                            <div class="alert alert-info border-0 rounded-4">
                                                <i class="bi bi-info-circle-fill me-2"></i>
                                                After completion, parents can view these session notes.
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Session Activities <span class="text-danger">*</span></label>
                                                <textarea name="session_activities" class="form-control"
                                                    placeholder="Example: Sound repetition, picture naming, pronunciation activity..."
                                                    required></textarea>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Child Performance</label>
                                                <textarea name="child_performance" class="form-control"
                                                    placeholder="Example: Child participated well and responded positively."></textarea>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Overall Session Rating</label>
                                                <select name="performance_rating" class="form-select">
                                                    <option value="">— Not rated —</option>
                                                    <option value="1">1 — Difficult session, minimal engagement</option>
                                                    <option value="2">2 — Below expectations, some progress</option>
                                                    <option value="3">3 — Average session, met most goals</option>
                                                    <option value="4">4 — Good session, exceeded some goals</option>
                                                    <option value="5">5 — Excellent — exceeded expectations</option>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Progress Notes <span class="text-danger">*</span></label>
                                                <textarea name="progress_notes" class="form-control"
                                                    placeholder="Example: Improved sound clarity. Needs more practice with sentence-level speech."
                                                    required></textarea>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Home Practice</label>
                                                <textarea name="home_practice" class="form-control"
                                                    placeholder="Example: Practice 10 minutes daily using word cards."></textarea>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label fw-bold">Next Session Recommendation</label>
                                                <textarea name="next_session_recommendation" class="form-control"
                                                    placeholder="Example: Continue articulation exercises in next session."></textarea>
                                            </div>
                                        </div>

                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="complete_session" class="action-btn-primary">
                                                Save Note &amp; Complete
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php } ?>

                        <!-- EDIT SESSION NOTE MODAL -->
                        <?php if ($appointment["status"] === "completed" && !empty($appointment["note_id"])) { ?>
                        <div class="modal fade" id="<?php echo $edit_modal_id; ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                                <div class="modal-content">
                                    <form method="POST" action="">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appt_id; ?>">

                                        <div class="modal-header">
                                            <div>
                                                <h5 class="modal-title">Edit Session Note</h5>
                                                <p class="text-muted small mb-0">Update the completed session note.</p>
                                            </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>

                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Session Activities <span class="text-danger">*</span></label>
                                                <textarea name="session_activities" class="form-control" required><?php echo escapeOutput($appointment["session_activities"]); ?></textarea>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Child Performance</label>
                                                <textarea name="child_performance" class="form-control"><?php echo escapeOutput($appointment["child_performance"]); ?></textarea>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Overall Session Rating</label>
                                                <select name="performance_rating" class="form-select">
                                                    <option value="">— Not rated —</option>
                                                    <?php for ($r = 1; $r <= 5; $r++) {
                                                        $labels = [
                                                            1 => "Difficult session, minimal engagement",
                                                            2 => "Below expectations, some progress",
                                                            3 => "Average session, met most goals",
                                                            4 => "Good session, exceeded some goals",
                                                            5 => "Excellent — exceeded expectations",
                                                        ];
                                                        $sel = ((int)$appointment["performance_rating"] === $r) ? "selected" : "";
                                                    ?>
                                                        <option value="<?php echo $r; ?>" <?php echo $sel; ?>><?php echo $r; ?> — <?php echo $labels[$r]; ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Progress Notes <span class="text-danger">*</span></label>
                                                <textarea name="progress_notes" class="form-control" required><?php echo escapeOutput($appointment["progress_notes"]); ?></textarea>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Home Practice</label>
                                                <textarea name="home_practice" class="form-control"><?php echo escapeOutput($appointment["home_practice"]); ?></textarea>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label fw-bold">Next Session Recommendation</label>
                                                <textarea name="next_session_recommendation" class="form-control"><?php echo escapeOutput($appointment["next_session_recommendation"]); ?></textarea>
                                            </div>
                                        </div>

                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="update_session_note" class="action-btn-primary">Update Note</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php } ?>

                        <?php } /* end while */ ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="bi bi-calendar-x"></i>
                                    <h5 class="fw-bold">No assigned appointments found</h5>
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
</body>
</html>