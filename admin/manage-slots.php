<?php
include "../includes/admin-auth.php";
include "../config/db.php";

$success = "";
$error = "";

/* CREATE NEW SLOT */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_slot"])) {
    $slot_date = $_POST["slot_date"];
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];

    if (empty($slot_date) || empty($start_time) || empty($end_time)) {
        $error = "Please fill all slot fields.";
    } elseif ($end_time <= $start_time) {
        $error = "End time must be after start time.";
    } else {
        $check_sql = "SELECT slot_id FROM time_slots 
                      WHERE slot_date = ? 
                      AND start_time = ? 
                      AND end_time = ?";

        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "sss", $slot_date, $start_time, $end_time);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {
            $error = "This time slot already exists.";
        } else {
            $insert_sql = "INSERT INTO time_slots (slot_date, start_time, end_time, status) 
                           VALUES (?, ?, ?, 'available')";

            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "sss", $slot_date, $start_time, $end_time);

            if (mysqli_stmt_execute($insert_stmt)) {
                header("Location: manage-slots.php?success=created");
                exit();
            } else {
                $error = "Failed to create slot. Please try again.";
            }
        }
    }
}

/* DELETE SLOT */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_slot"])) {
    $slot_id = intval($_POST["slot_id"]);

    $slot_sql = "SELECT status FROM time_slots WHERE slot_id = ?";
    $slot_stmt = mysqli_prepare($conn, $slot_sql);
    mysqli_stmt_bind_param($slot_stmt, "i", $slot_id);
    mysqli_stmt_execute($slot_stmt);
    $slot_result = mysqli_stmt_get_result($slot_stmt);

    if (mysqli_num_rows($slot_result) === 1) {
        $slot = mysqli_fetch_assoc($slot_result);

        if ($slot["status"] === "booked") {
            $error = "Booked slots cannot be deleted.";
        } else {
            $appointment_check_sql = "SELECT appointment_id FROM appointments WHERE slot_id = ?";
            $appointment_check_stmt = mysqli_prepare($conn, $appointment_check_sql);
            mysqli_stmt_bind_param($appointment_check_stmt, "i", $slot_id);
            mysqli_stmt_execute($appointment_check_stmt);
            $appointment_check_result = mysqli_stmt_get_result($appointment_check_stmt);

            if (mysqli_num_rows($appointment_check_result) > 0) {
                $error = "This slot has appointment records and cannot be deleted.";
            } else {
                $delete_sql = "DELETE FROM time_slots WHERE slot_id = ?";
                $delete_stmt = mysqli_prepare($conn, $delete_sql);
                mysqli_stmt_bind_param($delete_stmt, "i", $slot_id);

                if (mysqli_stmt_execute($delete_stmt)) {
                    header("Location: manage-slots.php?success=deleted");
                    exit();
                } else {
                    $error = "Failed to delete slot.";
                }
            }
        }
    } else {
        $error = "Slot not found.";
    }
}

/* SUCCESS MESSAGE */
if (isset($_GET["success"])) {
    if ($_GET["success"] === "created") {
        $success = "New time slot created successfully.";
    }

    if ($_GET["success"] === "deleted") {
        $success = "Time slot deleted successfully.";
    }
}

/* FILTER */
$filter = isset($_GET["filter"]) ? $_GET["filter"] : "all";

if (!in_array($filter, ["all", "available", "booked"])) {
    $filter = "all";
}

/* COUNTS */
$total_slots = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM time_slots"))["total"];
$available_slots = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM time_slots WHERE status = 'available'"))["total"];
$booked_slots = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM time_slots WHERE status = 'booked'"))["total"];

/* FETCH SLOTS */
if ($filter === "all") {
    $slots_sql = "SELECT * FROM time_slots ORDER BY slot_date ASC, start_time ASC";
    $slots_result = mysqli_query($conn, $slots_sql);
} else {
    $slots_sql = "SELECT * FROM time_slots WHERE status = ? ORDER BY slot_date ASC, start_time ASC";
    $slots_stmt = mysqli_prepare($conn, $slots_sql);
    mysqli_stmt_bind_param($slots_stmt, "s", $filter);
    mysqli_stmt_execute($slots_stmt);
    $slots_result = mysqli_stmt_get_result($slots_stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Time Slots - SpeechCare</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Custom CSS -->
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

<!-- Mobile Offcanvas -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenu">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title sidebar-logo">SpeechCare</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body sidebar-menu">
        <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="manage-slots.php" class="active"><i class="bi bi-calendar-plus"></i> Manage Time Slots</a>
        <a href="manage-appointments.php"><i class="bi bi-calendar-check"></i> Manage Appointments</a>
        <a href="manage-users.php"><i class="bi bi-people"></i> Manage Users</a>
        <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>

<div class="dashboard-wrapper">

    <!-- Desktop Sidebar -->
    <aside class="sidebar admin-sidebar">
        <div class="sidebar-logo">SpeechCare</div>
        <div class="sidebar-subtitle">Admin Panel</div>

        <nav class="sidebar-menu">
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="manage-slots.php" class="active"><i class="bi bi-calendar-plus"></i> Manage Time Slots</a>
            <a href="manage-appointments.php"><i class="bi bi-calendar-check"></i> Manage Appointments</a>
            <a href="manage-users.php"><i class="bi bi-people"></i> Manage Users</a>
            <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <!-- Top Bar -->
        <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="page-title">Schedule Availability</h1>
                <p class="page-subtitle">Create, view, and manage appointment time slots.</p>
            </div>

            <button class="action-btn-primary" data-bs-toggle="modal" data-bs-target="#createSlotModal">
                <i class="bi bi-plus-circle me-1"></i>
                Create New Slot
            </button>
        </div>

        <!-- Messages -->
        <?php if (!empty($success)) { ?>
            <div class="alert alert-success border-0 rounded-4 mb-4">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php } ?>

        <?php if (!empty($error)) { ?>
            <div class="alert alert-danger border-0 rounded-4 mb-4">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php } ?>

        <!-- Metric Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-calendar3"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_slots; ?></div>
                    <div class="stat-label">Total Slots</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $available_slots; ?></div>
                    <div class="stat-label">Available Slots</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stat-number"><?php echo $booked_slots; ?></div>
                    <div class="stat-label">Booked Slots</div>
                </div>
            </div>
        </div>

        <!-- Slots Table -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="mb-1 fw-bold">All Created Slots</h4>
                    <p class="mb-0 text-muted small">Parents can only book slots marked as available.</p>
                </div>

                <div class="filter-tabs">
                    <a href="manage-slots.php?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        All
                    </a>
                    <a href="manage-slots.php?filter=available" class="filter-tab <?php echo $filter === 'available' ? 'active' : ''; ?>">
                        Available
                    </a>
                    <a href="manage-slots.php?filter=booked" class="filter-tab <?php echo $filter === 'booked' ? 'active' : ''; ?>">
                        Booked
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (mysqli_num_rows($slots_result) > 0) { ?>
                            <?php while ($slot = mysqli_fetch_assoc($slots_result)) { ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date("M d, Y", strtotime($slot["slot_date"])); ?></strong>
                                        <div class="small text-muted">
                                            <?php echo date("l", strtotime($slot["slot_date"])); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?php echo date("h:i A", strtotime($slot["start_time"])); ?>
                                        -
                                        <?php echo date("h:i A", strtotime($slot["end_time"])); ?>
                                    </td>

                                    <td>
                                        <?php if ($slot["status"] === "available") { ?>
                                            <span class="slot-status slot-available">Available</span>
                                        <?php } else { ?>
                                            <span class="slot-status slot-booked">Booked</span>
                                        <?php } ?>
                                    </td>

                                    <td class="text-end">
                                        <?php if ($slot["status"] === "available") { ?>
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this slot?');">
                                                <input type="hidden" name="slot_id" value="<?php echo $slot["slot_id"]; ?>">
                                                <button type="submit" name="delete_slot" class="delete-icon-btn" title="Delete Slot">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php } else { ?>
                                            <button class="disabled-delete" disabled title="Booked slots cannot be deleted">
                                                <i class="bi bi-lock"></i>
                                            </button>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <i class="bi bi-calendar-x"></i>
                                        <h5 class="fw-bold">No slots found</h5>
                                        <p class="mb-0">Create a new slot to allow parents to book appointments.</p>
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

<!-- Create Slot Modal -->
<div class="modal fade" id="createSlotModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Create New Time Slot</h5>
                        <p class="text-muted small mb-0">Add a new available appointment slot for parents.</p>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Date</label>
                        <input type="date" name="slot_date" class="form-control" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Start Time</label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">End Time</label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                    </div>

                    <div class="alert alert-info border-0 rounded-4 mb-0" style="background:#E8F4FF;color:#19192D;">
                        <i class="bi bi-info-circle-fill text-primary me-2"></i>
                        New slots are automatically saved as <strong>Available</strong>.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" name="create_slot" class="action-btn-primary">
                        Save Slot
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<?php include "../includes/back-button-protection.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>