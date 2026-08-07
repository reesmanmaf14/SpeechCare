<?php
include "../includes/auth_therapist.php";
include "../includes/functions.php";
include "../includes/notification_functions.php";
include "../includes/email-functions.php";

$success = "";
$error = "";

$therapist_id = $_SESSION["therapist_id"];
$therapist_name = $_SESSION["therapist_name"] ?? "Therapist";

$allowed_leave_types = ["full_day", "partial_day"];

/*
|--------------------------------------------------------------------------
| Notify all admins helper
|--------------------------------------------------------------------------
*/
function notifyAllAdminsAboutLeaveRequest($conn, $therapist_name, $leave_date, $leave_type) {
    $admin_result = mysqli_query($conn, "SELECT admin_id FROM admins");

    while ($admin = mysqli_fetch_assoc($admin_result)) {
        createNotification(
            $conn,
            "admin",
            $admin["admin_id"],
            "New Therapist Leave Request",
            $therapist_name . " submitted a " . displayStatusText($leave_type) . " leave request for " . formatDateNice($leave_date) . "."
        );
    }
}

function notifyAllAdminsAboutLeaveCancellation($conn, $therapist_name, $leave_date) {
    $admin_result = mysqli_query($conn, "SELECT admin_id FROM admins");

    while ($admin = mysqli_fetch_assoc($admin_result)) {
        createNotification(
            $conn,
            "admin",
            $admin["admin_id"],
            "Leave Request Cancelled",
            $therapist_name . " cancelled a pending leave request for " . formatDateNice($leave_date) . "."
        );
    }
}

/*
|--------------------------------------------------------------------------
| APPLY LEAVE
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["apply_leave"])) {
    $leave_date = cleanInput($_POST["leave_date"]);
    $leave_type = cleanInput($_POST["leave_type"]);
    $start_time = cleanInput($_POST["start_time"] ?? "");
    $end_time = cleanInput($_POST["end_time"] ?? "");
    $reason = cleanInput($_POST["reason"] ?? "");

    if (empty($leave_date) || empty($leave_type)) {
        $error = "Please select leave date and leave type.";
    } elseif (!in_array($leave_type, $allowed_leave_types)) {
        $error = "Invalid leave type selected.";
    } elseif (strtotime($leave_date) < strtotime(date("Y-m-d"))) {
        $error = "You cannot apply leave for a past date.";
    } elseif ($leave_type === "partial_day" && (empty($start_time) || empty($end_time))) {
        $error = "Please enter start time and end time for partial-day leave.";
    } elseif ($leave_type === "partial_day" && strtotime($start_time) >= strtotime($end_time)) {
        $error = "Start time must be before end time.";
    } else {
        if ($leave_type === "full_day") {
            $start_time = null;
            $end_time = null;
        }

        /*
            Prevent duplicate/overlapping leave requests.

            Rule:
            - If full-day leave already exists, no more leave for same date.
            - If partial-day leave overlaps with another partial leave, block it.
            - Only pending/approved leave requests are checked.
        */
        $overlap_sql = "SELECT leave_id
                        FROM therapist_leave_requests
                        WHERE therapist_id = ?
                        AND leave_date = ?
                        AND status IN ('pending', 'approved')
                        AND (
                            leave_type = 'full_day'
                            OR ? = 'full_day'
                            OR (? < end_time AND ? > start_time)
                        )
                        LIMIT 1";

        $overlap_stmt = mysqli_prepare($conn, $overlap_sql);
        mysqli_stmt_bind_param(
            $overlap_stmt,
            "issss",
            $therapist_id,
            $leave_date,
            $leave_type,
            $start_time,
            $end_time
        );
        mysqli_stmt_execute($overlap_stmt);
        mysqli_stmt_store_result($overlap_stmt);

        if (mysqli_stmt_num_rows($overlap_stmt) > 0) {
            $error = "You already have a pending/approved leave request that overlaps with this date/time.";
        } else {
            $insert_sql = "INSERT INTO therapist_leave_requests
                            (
                                therapist_id,
                                leave_date,
                                leave_type,
                                start_time,
                                end_time,
                                reason,
                                status
                            )
                           VALUES (?, ?, ?, ?, ?, ?, 'pending')";

            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param(
                $insert_stmt,
                "isssss",
                $therapist_id,
                $leave_date,
                $leave_type,
                $start_time,
                $end_time,
                $reason
            );

            if (mysqli_stmt_execute($insert_stmt)) {
                notifyAllAdminsAboutLeaveRequest($conn, $therapist_name, $leave_date, $leave_type);

                createNotification(
                    $conn,
                    "therapist",
                    $therapist_id,
                    "Leave Request Submitted",
                    "Your leave request has been submitted and is waiting for admin approval."
                );

                sendAdminLeaveRequestNotification(
    $_SESSION['therapist_name'],
    $leave_date,
    $leave_type,
    $reason
);

                header("Location: apply-leave.php?success=applied");
                exit();
            } else {
                $error = "Failed to submit leave request.";
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| CANCEL PENDING LEAVE REQUEST
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cancel_leave"])) {
    $leave_id = intval($_POST["leave_id"]);

    if ($leave_id <= 0) {
        $error = "Invalid leave request selected.";
    } else {
        $check_sql = "SELECT leave_id, leave_date, status
                      FROM therapist_leave_requests
                      WHERE leave_id = ?
                      AND therapist_id = ?
                      LIMIT 1";

        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "ii", $leave_id, $therapist_id);
        mysqli_stmt_execute($check_stmt);

        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) !== 1) {
            $error = "Leave request not found.";
        } else {
            $leave = mysqli_fetch_assoc($check_result);

            if ($leave["status"] !== "pending") {
                $error = "Only pending leave requests can be cancelled.";
            } else {
                $update_sql = "UPDATE therapist_leave_requests
                               SET status = 'cancelled', updated_at = NOW()
                               WHERE leave_id = ?
                               AND therapist_id = ?";

                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "ii", $leave_id, $therapist_id);

                if (mysqli_stmt_execute($update_stmt)) {
                    notifyAllAdminsAboutLeaveCancellation($conn, $therapist_name, $leave["leave_date"]);

                    header("Location: apply-leave.php?success=cancelled");
                    exit();
                } else {
                    $error = "Failed to cancel leave request.";
                }
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
    if ($_GET["success"] === "applied") {
        $success = "Leave request submitted successfully. Please wait for admin approval.";
    } elseif ($_GET["success"] === "cancelled") {
        $success = "Leave request cancelled successfully.";
    }
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/
$status_filter = isset($_GET["status"]) ? cleanInput($_GET["status"]) : "all";

$allowed_statuses = ["all", "pending", "approved", "rejected", "cancelled"];

if (!in_array($status_filter, $allowed_statuses)) {
    $status_filter = "all";
}

$where_parts = ["therapist_id = ?"];
$params = [$therapist_id];
$types = "i";

if ($status_filter !== "all") {
    $where_parts[] = "status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_sql = "WHERE " . implode(" AND ", $where_parts);

/*
|--------------------------------------------------------------------------
| FETCH LEAVE REQUESTS
|--------------------------------------------------------------------------
*/
$leave_sql = "SELECT 
                leave_id,
                leave_date,
                leave_type,
                start_time,
                end_time,
                reason,
                status,
                admin_note,
                created_at,
                updated_at
              FROM therapist_leave_requests
              $where_sql
              ORDER BY leave_date DESC, created_at DESC";

$leave_stmt = mysqli_prepare($conn, $leave_sql);
mysqli_stmt_bind_param($leave_stmt, $types, ...$params);
mysqli_stmt_execute($leave_stmt);

$leave_result = mysqli_stmt_get_result($leave_stmt);

/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/
$pending_sql = "SELECT COUNT(*) AS total FROM therapist_leave_requests WHERE therapist_id = ? AND status = 'pending'";
$pending_stmt = mysqli_prepare($conn, $pending_sql);
mysqli_stmt_bind_param($pending_stmt, "i", $therapist_id);
mysqli_stmt_execute($pending_stmt);
$pending_result = mysqli_stmt_get_result($pending_stmt);
$pending_count = mysqli_fetch_assoc($pending_result)["total"];

$approved_sql = "SELECT COUNT(*) AS total FROM therapist_leave_requests WHERE therapist_id = ? AND status = 'approved'";
$approved_stmt = mysqli_prepare($conn, $approved_sql);
mysqli_stmt_bind_param($approved_stmt, "i", $therapist_id);
mysqli_stmt_execute($approved_stmt);
$approved_result = mysqli_stmt_get_result($approved_stmt);
$approved_count = mysqli_fetch_assoc($approved_result)["total"];

$rejected_sql = "SELECT COUNT(*) AS total FROM therapist_leave_requests WHERE therapist_id = ? AND status = 'rejected'";
$rejected_stmt = mysqli_prepare($conn, $rejected_sql);
mysqli_stmt_bind_param($rejected_stmt, "i", $therapist_id);
mysqli_stmt_execute($rejected_stmt);
$rejected_result = mysqli_stmt_get_result($rejected_stmt);
$rejected_count = mysqli_fetch_assoc($rejected_result)["total"];

$cancelled_sql = "SELECT COUNT(*) AS total FROM therapist_leave_requests WHERE therapist_id = ? AND status = 'cancelled'";
$cancelled_stmt = mysqli_prepare($conn, $cancelled_sql);
mysqli_stmt_bind_param($cancelled_stmt, "i", $therapist_id);
mysqli_stmt_execute($cancelled_stmt);
$cancelled_result = mysqli_stmt_get_result($cancelled_stmt);
$cancelled_count = mysqli_fetch_assoc($cancelled_result)["total"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Apply Leave - SpeechCare</title>

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

        .btn-cancel {
            background: #FFECEC;
            color: var(--danger);
        }

        .btn-cancel:hover {
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

        .leave-time-box {
            display: none;
        }

        @media (max-width: 576px) {
            .small-action-btn {
                width: 100%;
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
        <a href="manage-availability.php"><i class="bi bi-calendar-week"></i> Manage Availability</a>
        <a href="apply-leave.php" class="active"><i class="bi bi-calendar-x"></i> Apply Leave</a>
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
            <a href="manage-availability.php"><i class="bi bi-calendar-week"></i> Manage Availability</a>
            <a href="apply-leave.php" class="active"><i class="bi bi-calendar-x"></i> Apply Leave</a>
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
                <h1 class="page-title">Apply Leave</h1>
                <p class="page-subtitle">Submit full-day or partial-day leave requests for admin approval.</p>
            </div>

            <button class="action-btn-primary" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
                <i class="bi bi-plus-circle me-1"></i>
                Apply Leave
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
                    <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
                    <div class="stat-number"><?php echo $rejected_count; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-slash-circle"></i></div>
                    <div class="stat-number"><?php echo $cancelled_count; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>

        </div>

      

        <!-- Leave History -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="fw-bold mb-1">Leave History</h4>
                    <p class="text-muted small mb-0">Track all your submitted leave requests.</p>
                </div>

                <div class="filter-tabs">
                    <a href="apply-leave.php?status=all" class="<?php echo $status_filter === 'all' ? 'active' : ''; ?>">All</a>
                    <a href="apply-leave.php?status=pending" class="<?php echo $status_filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                    <a href="apply-leave.php?status=approved" class="<?php echo $status_filter === 'approved' ? 'active' : ''; ?>">Approved</a>
                    <a href="apply-leave.php?status=rejected" class="<?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
                    <a href="apply-leave.php?status=cancelled" class="<?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Leave Date</th>
                            <th>Type</th>
                            <th>Time</th>
                            <th>Reason</th>
                            <th>Admin Note</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (mysqli_num_rows($leave_result) > 0) { ?>
                            <?php while ($leave = mysqli_fetch_assoc($leave_result)) { ?>
                                <tr>
                                    <td>#<?php echo escapeOutput($leave["leave_id"]); ?></td>

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

                                    <td><?php echo formatDateNice($leave["created_at"]); ?></td>

                                    <td class="text-end">
                                        <?php if ($leave["status"] === "pending") { ?>
                                            <form method="POST" action="" onsubmit="return confirm('Cancel this pending leave request?');">
                                                <input type="hidden" name="leave_id" value="<?php echo $leave["leave_id"]; ?>">

                                                <button type="submit" name="cancel_leave" class="small-action-btn btn-cancel">
                                                    Cancel
                                                </button>
                                            </form>
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
                                        <p class="mb-0">Click Apply Leave to submit your first leave request.</p>
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

<!-- Apply Leave Modal -->
<div class="modal fade" id="applyLeaveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Apply Leave</h5>
                        <p class="text-muted small mb-0">Submit leave request for admin approval.</p>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Leave Date</label>
                        <input 
                            type="date" 
                            name="leave_date" 
                            class="form-control" 
                            min="<?php echo date("Y-m-d"); ?>"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Leave Type</label>
                        <select name="leave_type" id="leaveType" class="form-select" required>
                            <option value="">Select leave type</option>
                            <option value="full_day">Full Day</option>
                            <option value="partial_day">Partial Day</option>
                        </select>
                    </div>

                    <div id="partialTimeBox" class="leave-time-box">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Start Time</label>
                                <input type="time" name="start_time" id="startTime" class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">End Time</label>
                                <input type="time" name="end_time" id="endTime" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold">Reason</label>
                        <textarea 
                            name="reason" 
                            class="form-control"
                           
                        ></textarea>
                    </div>

                   

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" name="apply_leave" class="action-btn-primary">
                        Submit Leave Request
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
const leaveType = document.getElementById("leaveType");
const partialTimeBox = document.getElementById("partialTimeBox");
const startTime = document.getElementById("startTime");
const endTime = document.getElementById("endTime");

if (leaveType) {
    leaveType.addEventListener("change", function () {
        if (this.value === "partial_day") {
            partialTimeBox.style.display = "block";
            startTime.required = true;
            endTime.required = true;
        } else {
            partialTimeBox.style.display = "none";
            startTime.required = false;
            endTime.required = false;
            startTime.value = "";
            endTime.value = "";
        }
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>