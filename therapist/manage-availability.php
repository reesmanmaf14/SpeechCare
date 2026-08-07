<?php
include "../includes/auth_therapist.php";
include "../includes/functions.php";
include "../includes/slot_generation_functions.php";

$success = "";
$error = "";

$therapist_id = $_SESSION["therapist_id"];
$therapist_name = $_SESSION["therapist_name"] ?? "Therapist";

$allowed_days = [
    "monday",
    "tuesday",
    "wednesday",
    "thursday",
    "friday",
    "saturday",
    "sunday"
];

/*
|--------------------------------------------------------------------------
| ADD AVAILABILITY
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_availability"])) {
    $day_of_week = cleanInput($_POST["day_of_week"]);
    $start_time = cleanInput($_POST["start_time"]);
    $end_time = cleanInput($_POST["end_time"]);
    $session_duration = intval($_POST["session_duration"]);
    $break_duration = intval($_POST["break_duration"]);

    if (empty($day_of_week) || empty($start_time) || empty($end_time)) {
        $error = "Please fill all required fields.";
    } elseif (!in_array($day_of_week, $allowed_days)) {
        $error = "Invalid day selected.";
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $error = "Start time must be before end time.";
    } elseif ($session_duration < 15) {
        $error = "Session duration must be at least 15 minutes.";
    } elseif ($break_duration < 0) {
        $error = "Break duration cannot be negative.";
    } else {
        /*
            Prevent overlapping availability for the same therapist and same day.
        */
        $overlap_sql = "SELECT availability_id
                        FROM therapist_availability
                        WHERE therapist_id = ?
                        AND day_of_week = ?
                        AND status = 'active'
                        AND (? < end_time AND ? > start_time)
                        LIMIT 1";

        $overlap_stmt = mysqli_prepare($conn, $overlap_sql);
        mysqli_stmt_bind_param(
            $overlap_stmt,
            "isss",
            $therapist_id,
            $day_of_week,
            $start_time,
            $end_time
        );
        mysqli_stmt_execute($overlap_stmt);
        mysqli_stmt_store_result($overlap_stmt);

        if (mysqli_stmt_num_rows($overlap_stmt) > 0) {
            $error = "This availability overlaps with an existing active availability.";
        } else {
            mysqli_begin_transaction($conn);

            try {
                $insert_sql = "INSERT INTO therapist_availability
                                (therapist_id, day_of_week, start_time, end_time, session_duration, break_duration, status)
                               VALUES (?, ?, ?, ?, ?, ?, 'active')";

                $insert_stmt = mysqli_prepare($conn, $insert_sql);
                mysqli_stmt_bind_param(
                    $insert_stmt,
                    "isssii",
                    $therapist_id,
                    $day_of_week,
                    $start_time,
                    $end_time,
                    $session_duration,
                    $break_duration
                );

                if (!mysqli_stmt_execute($insert_stmt)) {
                    throw new Exception("Failed to add availability.");
                }

                $generated_count = generateSlotsForAvailability(
                    $conn,
                    $therapist_id,
                    $day_of_week,
                    $start_time,
                    $end_time,
                    $session_duration,
                    $break_duration,
                    30
                );

                mysqli_commit($conn);

                header("Location: manage-availability.php?success=added&generated=" . $generated_count);
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
| EDIT AVAILABILITY
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_availability"])) {
    $availability_id = intval($_POST["availability_id"]);
    $day_of_week = cleanInput($_POST["day_of_week"]);
    $start_time = cleanInput($_POST["start_time"]);
    $end_time = cleanInput($_POST["end_time"]);
    $session_duration = intval($_POST["session_duration"]);
    $break_duration = intval($_POST["break_duration"]);
    $status = cleanInput($_POST["status"]);

    if ($availability_id <= 0) {
        $error = "Invalid availability selected.";
    } elseif (empty($day_of_week) || empty($start_time) || empty($end_time)) {
        $error = "Please fill all required fields.";
    } elseif (!in_array($day_of_week, $allowed_days)) {
        $error = "Invalid day selected.";
    } elseif (!in_array($status, ["active", "inactive"])) {
        $error = "Invalid status selected.";
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $error = "Start time must be before end time.";
    } elseif ($session_duration < 15) {
        $error = "Session duration must be at least 15 minutes.";
    } elseif ($break_duration < 0) {
        $error = "Break duration cannot be negative.";
    } else {
        $old_sql = "SELECT day_of_week
                    FROM therapist_availability
                    WHERE availability_id = ?
                    AND therapist_id = ?
                    LIMIT 1";

        $old_stmt = mysqli_prepare($conn, $old_sql);
        mysqli_stmt_bind_param($old_stmt, "ii", $availability_id, $therapist_id);
        mysqli_stmt_execute($old_stmt);
        $old_result = mysqli_stmt_get_result($old_stmt);

        if (mysqli_num_rows($old_result) !== 1) {
            $error = "Availability record not found.";
        } else {
            $old_availability = mysqli_fetch_assoc($old_result);
            $old_day = $old_availability["day_of_week"];

            /*
                Prevent overlap with other active records.
            */
            $overlap_sql = "SELECT availability_id
                            FROM therapist_availability
                            WHERE therapist_id = ?
                            AND day_of_week = ?
                            AND status = 'active'
                            AND availability_id != ?
                            AND (? < end_time AND ? > start_time)
                            LIMIT 1";

            $overlap_stmt = mysqli_prepare($conn, $overlap_sql);
            mysqli_stmt_bind_param(
                $overlap_stmt,
                "isiss",
                $therapist_id,
                $day_of_week,
                $availability_id,
                $start_time,
                $end_time
            );
            mysqli_stmt_execute($overlap_stmt);
            mysqli_stmt_store_result($overlap_stmt);

            if ($status === "active" && mysqli_stmt_num_rows($overlap_stmt) > 0) {
                $error = "This availability overlaps with another active availability.";
            } else {
                mysqli_begin_transaction($conn);

                try {
                    $update_sql = "UPDATE therapist_availability
                                   SET 
                                        day_of_week = ?,
                                        start_time = ?,
                                        end_time = ?,
                                        session_duration = ?,
                                        break_duration = ?,
                                        status = ?
                                   WHERE availability_id = ?
                                   AND therapist_id = ?";

                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param(
                        $update_stmt,
                        "sssiisii",
                        $day_of_week,
                        $start_time,
                        $end_time,
                        $session_duration,
                        $break_duration,
                        $status,
                        $availability_id,
                        $therapist_id
                    );

                    if (!mysqli_stmt_execute($update_stmt)) {
                        throw new Exception("Failed to update availability.");
                    }

                    /*
                        Remove old future available generated slots for the old day.
                        We only remove available system-generated slots.
                        Booked/pending/completed slots are not deleted.
                    */
                    deleteFutureAvailableGeneratedSlotsForDay($conn, $therapist_id, $old_day);

                    if ($status === "active") {
                        $generated_count = generateSlotsForAvailability(
                            $conn,
                            $therapist_id,
                            $day_of_week,
                            $start_time,
                            $end_time,
                            $session_duration,
                            $break_duration,
                            30
                        );
                    } else {
                        $generated_count = 0;
                    }

                    mysqli_commit($conn);

                    header("Location: manage-availability.php?success=updated&generated=" . $generated_count);
                    exit();

                } catch (Exception $exception) {
                    mysqli_rollback($conn);
                    $error = $exception->getMessage();
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| DELETE AVAILABILITY
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_availability"])) {
    $availability_id = intval($_POST["availability_id"]);

    if ($availability_id <= 0) {
        $error = "Invalid availability selected.";
    } else {
        $check_sql = "SELECT day_of_week
                      FROM therapist_availability
                      WHERE availability_id = ?
                      AND therapist_id = ?
                      LIMIT 1";

        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "ii", $availability_id, $therapist_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) !== 1) {
            $error = "Availability record not found.";
        } else {
            $availability = mysqli_fetch_assoc($check_result);
            $day_of_week = $availability["day_of_week"];

            mysqli_begin_transaction($conn);

            try {
                deleteFutureAvailableGeneratedSlotsForDay($conn, $therapist_id, $day_of_week);

                $delete_sql = "DELETE FROM therapist_availability
                               WHERE availability_id = ?
                               AND therapist_id = ?";

                $delete_stmt = mysqli_prepare($conn, $delete_sql);
                mysqli_stmt_bind_param($delete_stmt, "ii", $availability_id, $therapist_id);

                if (!mysqli_stmt_execute($delete_stmt)) {
                    throw new Exception("Failed to delete availability.");
                }

                mysqli_commit($conn);

                header("Location: manage-availability.php?success=deleted");
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
| SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/
if (isset($_GET["success"])) {
    $generated = isset($_GET["generated"]) ? intval($_GET["generated"]) : 0;

    if ($_GET["success"] === "added") {
        $success = "Availability added successfully. Generated future slots: " . $generated;
    } elseif ($_GET["success"] === "updated") {
        $success = "Availability updated successfully. Generated future slots: " . $generated;
    } elseif ($_GET["success"] === "deleted") {
        $success = "Availability deleted successfully. Future available generated slots were removed.";
    }
}

/*
|--------------------------------------------------------------------------
| FETCH AVAILABILITY
|--------------------------------------------------------------------------
*/
$availability_sql = "SELECT 
                        availability_id,
                        day_of_week,
                        start_time,
                        end_time,
                        session_duration,
                        break_duration,
                        status,
                        created_at,
                        updated_at
                     FROM therapist_availability
                     WHERE therapist_id = ?
                     ORDER BY 
                        FIELD(day_of_week, 'monday','tuesday','wednesday','thursday','friday','saturday','sunday'),
                        start_time ASC";

$availability_stmt = mysqli_prepare($conn, $availability_sql);
mysqli_stmt_bind_param($availability_stmt, "i", $therapist_id);
mysqli_stmt_execute($availability_stmt);
$availability_result = mysqli_stmt_get_result($availability_stmt);

/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/
$total_availability_sql = "SELECT COUNT(*) AS total 
                           FROM therapist_availability 
                           WHERE therapist_id = ?";

$total_stmt = mysqli_prepare($conn, $total_availability_sql);
mysqli_stmt_bind_param($total_stmt, "i", $therapist_id);
mysqli_stmt_execute($total_stmt);
$total_result = mysqli_stmt_get_result($total_stmt);
$total_availability = mysqli_fetch_assoc($total_result)["total"];

$active_availability_sql = "SELECT COUNT(*) AS total 
                            FROM therapist_availability 
                            WHERE therapist_id = ?
                            AND status = 'active'";

$active_stmt = mysqli_prepare($conn, $active_availability_sql);
mysqli_stmt_bind_param($active_stmt, "i", $therapist_id);
mysqli_stmt_execute($active_stmt);
$active_result = mysqli_stmt_get_result($active_stmt);
$active_availability = mysqli_fetch_assoc($active_result)["total"];

$future_slots_sql = "SELECT COUNT(*) AS total 
                     FROM time_slots 
                     WHERE therapist_id = ?
                     AND slot_date >= CURDATE()
                     AND status = 'available'";

$future_slots_stmt = mysqli_prepare($conn, $future_slots_sql);
mysqli_stmt_bind_param($future_slots_stmt, "i", $therapist_id);
mysqli_stmt_execute($future_slots_stmt);
$future_slots_result = mysqli_stmt_get_result($future_slots_stmt);
$future_available_slots = mysqli_fetch_assoc($future_slots_result)["total"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Availability - SpeechCare</title>

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

        .status-pill {
            border-radius: 999px;
            padding: 7px 14px;
            font-size: 13px;
            font-weight: 800;
            display: inline-block;
        }

        .status-active {
            background: #E6F8F0;
            color: var(--success);
        }

        .status-inactive {
            background: #FFECEC;
            color: var(--danger);
        }

        .small-action-btn {
            border: none;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 800;
            transition: 0.2s ease;
        }

        .btn-edit {
            background: var(--primary-blue-light);
            color: var(--primary-blue);
        }

        .btn-edit:hover {
            background: var(--primary-blue);
            color: #ffffff;
        }

        .btn-delete {
            background: #FFECEC;
            color: var(--danger);
        }

        .btn-delete:hover {
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

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.2rem rgba(57, 152, 232, 0.15);
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
        <a href="manage-availability.php" class="active"><i class="bi bi-calendar-week"></i> Manage Availability</a>
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
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="manage-availability.php" class="active"><i class="bi bi-calendar-week"></i> Manage Availability</a>
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
                <h1 class="page-title">Manage Availability</h1>
                <p class="page-subtitle">Set your weekly working times.</p>
            </div>

            <button class="action-btn-primary" data-bs-toggle="modal" data-bs-target="#addAvailabilityModal">
                <i class="bi bi-plus-circle me-1"></i>
                Add Availability
            </button>
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
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-calendar-week"></i></div>
                    <div class="stat-number"><?php echo $total_availability; ?></div>
                    <div class="stat-label">Total Availability</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                    <div class="stat-number"><?php echo $active_availability; ?></div>
                    <div class="stat-label">Active Availability</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-clock"></i></div>
                    <div class="stat-number"><?php echo $future_available_slots; ?></div>
                    <div class="stat-label">Future Available Slots</div>
                </div>
            </div>
        </div>

        

        <!-- Availability Table -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="fw-bold mb-1">My Weekly Availability</h4>
                    <p class="text-muted small mb-0">Your active availability controls future slot generation.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Session Duration</th>
                            <th>Break</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (mysqli_num_rows($availability_result) > 0) { ?>
                            <?php while ($availability = mysqli_fetch_assoc($availability_result)) { ?>
                                <?php $edit_modal_id = "editAvailabilityModal" . $availability["availability_id"]; ?>

                                <tr>
                                    <td><strong><?php echo displayStatusText($availability["day_of_week"]); ?></strong></td>

                                    <td>
                                        <?php echo formatTimeNice($availability["start_time"]); ?>
                                        -
                                        <?php echo formatTimeNice($availability["end_time"]); ?>
                                    </td>

                                    <td><?php echo escapeOutput($availability["session_duration"]); ?> minutes</td>

                                    <td><?php echo escapeOutput($availability["break_duration"]); ?> minutes</td>

                                    <td>
                                        <?php if ($availability["status"] === "active") { ?>
                                            <span class="status-pill status-active">Active</span>
                                        <?php } else { ?>
                                            <span class="status-pill status-inactive">Inactive</span>
                                        <?php } ?>
                                    </td>

                                    <td><?php echo formatDateNice($availability["updated_at"]); ?></td>

                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2 flex-wrap">
                                            <button class="small-action-btn btn-edit" data-bs-toggle="modal" data-bs-target="#<?php echo $edit_modal_id; ?>">
                                                Edit
                                            </button>

                                            <form method="POST" action="" onsubmit="return confirm('Delete this availability? Future available generated slots for this day will be removed.');">
                                                <input type="hidden" name="availability_id" value="<?php echo $availability["availability_id"]; ?>">
                                                <button type="submit" name="delete_availability" class="small-action-btn btn-delete">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Edit Availability Modal -->
                                <div class="modal fade" id="<?php echo $edit_modal_id; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">

                                            <form method="POST" action="">
                                                <input type="hidden" name="availability_id" value="<?php echo $availability["availability_id"]; ?>">

                                                <div class="modal-header">
                                                    <div>
                                                        <h5 class="modal-title">Edit Availability</h5>
                                                        <p class="text-muted small mb-0">Update weekly availability and regenerate future slots.</p>
                                                    </div>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>

                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Day of Week</label>
                                                        <select name="day_of_week" class="form-select" required>
                                                            <?php foreach ($allowed_days as $day) { ?>
                                                                <option value="<?php echo $day; ?>" <?php echo $availability["day_of_week"] === $day ? "selected" : ""; ?>>
                                                                    <?php echo displayStatusText($day); ?>
                                                                </option>
                                                            <?php } ?>
                                                        </select>
                                                    </div>

                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold">Start Time</label>
                                                            <input type="time" name="start_time" class="form-control" value="<?php echo escapeOutput($availability["start_time"]); ?>" required>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold">End Time</label>
                                                            <input type="time" name="end_time" class="form-control" value="<?php echo escapeOutput($availability["end_time"]); ?>" required>
                                                        </div>
                                                    </div>

                                                    <div class="row g-3 mt-1">
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold">Session Duration</label>
                                                            <input type="number" name="session_duration" class="form-control" min="15" value="<?php echo escapeOutput($availability["session_duration"]); ?>" required>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold">Break Duration</label>
                                                            <input type="number" name="break_duration" class="form-control" min="0" value="<?php echo escapeOutput($availability["break_duration"]); ?>" required>
                                                        </div>
                                                    </div>

                                                    <div class="mt-3">
                                                        <label class="form-label fw-bold">Status</label>
                                                        <select name="status" class="form-select" required>
                                                            <option value="active" <?php echo $availability["status"] === "active" ? "selected" : ""; ?>>Active</option>
                                                            <option value="inactive" <?php echo $availability["status"] === "inactive" ? "selected" : ""; ?>>Inactive</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">
                                                        Cancel
                                                    </button>

                                                    <button type="submit" name="edit_availability" class="action-btn-primary">
                                                        Save Changes
                                                    </button>
                                                </div>
                                            </form>

                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="bi bi-calendar-week"></i>
                                        <h5 class="fw-bold">No availability added yet</h5>
                                        <p class="mb-0">Click Add Availability to create your weekly working schedule.</p>
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

<!-- Add Availability Modal -->
<div class="modal fade" id="addAvailabilityModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Add Availability</h5>
                        <p class="text-muted small mb-0">Create weekly availability and generate slots.</p>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Day of Week</label>
                        <select name="day_of_week" class="form-select" required>
                            <option value="">Select day</option>
                            <?php foreach ($allowed_days as $day) { ?>
                                <option value="<?php echo $day; ?>">
                                    <?php echo displayStatusText($day); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Start Time</label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">End Time</label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Session Duration</label>
                            <input type="number" name="session_duration" class="form-control" min="15" value="30" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Break Duration</label>
                            <input type="number" name="break_duration" class="form-control" min="0" value="0" required>
                        </div>
                    </div>

                   

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" name="add_availability" class="action-btn-primary">
                        Save & Generate Slots
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>