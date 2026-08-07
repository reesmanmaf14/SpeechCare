<?php
include "../includes/auth_parent.php";
include "../includes/functions.php";
include "../includes/notification_functions.php";
include "../includes/email-functions.php";

$success = "";
$error = "";

$parent_id = $_SESSION["parent_id"];
$parent_name = $_SESSION["parent_name"] ?? "Parent";

$appointment_id = isset($_GET["appointment_id"]) ? intval($_GET["appointment_id"]) : 0;

/*
|--------------------------------------------------------------------------
| Notify all admins helper
|--------------------------------------------------------------------------
*/
function notifyAllAdminsAboutReschedule($conn, $parent_name, $child_name) {
    $admin_result = mysqli_query($conn, "SELECT admin_id FROM admins");

    while ($admin = mysqli_fetch_assoc($admin_result)) {
        createNotification(
            $conn,
            "admin",
            $admin["admin_id"],
            "Appointment Rescheduled",
            $parent_name . " rescheduled an appointment for " . $child_name . "."
        );
    }
}

/*
|--------------------------------------------------------------------------
| Get appointment details
|--------------------------------------------------------------------------
*/
if ($appointment_id <= 0) {
    header("Location: my-appointments.php");
    exit();
}

$appointment_sql = "SELECT 
                        a.appointment_id,
                        a.parent_id,
                        a.child_id,
                        a.therapist_id,
                        a.slot_id,
                        a.appointment_date,
                        a.start_time,
                        a.end_time,
                        a.status,
                        a.reschedule_reason,
                        c.child_name,
                        c.age,
                        c.speech_concern,
                        t.full_name AS therapist_name,
                        t.specialization,
                        t.qualification,
                        t.experience
                    FROM appointments a
                    INNER JOIN children c ON a.child_id = c.child_id
                    INNER JOIN therapists t ON a.therapist_id = t.therapist_id
                    WHERE a.appointment_id = ?
                    AND a.parent_id = ?
                    LIMIT 1";

$appointment_stmt = mysqli_prepare($conn, $appointment_sql);
mysqli_stmt_bind_param($appointment_stmt, "ii", $appointment_id, $parent_id);
mysqli_stmt_execute($appointment_stmt);

$appointment_result = mysqli_stmt_get_result($appointment_stmt);

if (mysqli_num_rows($appointment_result) !== 1) {
    header("Location: my-appointments.php");
    exit();
}

$appointment = mysqli_fetch_assoc($appointment_result);

if ($appointment["status"] !== "needs_rescheduling") {
    header("Location: my-appointments.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Submit reschedule
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["reschedule_appointment"])) {
    $new_slot_id = intval($_POST["new_slot_id"]);
    $reason = cleanInput($_POST["reason"] ?? "");

    if ($new_slot_id <= 0) {
        $error = "Please select a new available slot.";
    } else {
        mysqli_begin_transaction($conn);

        try {
            /*
                Lock appointment.
            */
            $lock_appointment_sql = "SELECT 
                                        a.appointment_id,
                                        a.parent_id,
                                        a.child_id,
                                        a.therapist_id,
                                        a.slot_id,
                                        a.appointment_date,
                                        a.start_time,
                                        a.end_time,
                                        a.status,
                                        c.child_name,
                                        t.full_name AS therapist_name
                                     FROM appointments a
                                     INNER JOIN children c ON a.child_id = c.child_id
                                     INNER JOIN therapists t ON a.therapist_id = t.therapist_id
                                     WHERE a.appointment_id = ?
                                     AND a.parent_id = ?
                                     LIMIT 1
                                     FOR UPDATE";

            $lock_appointment_stmt = mysqli_prepare($conn, $lock_appointment_sql);
            mysqli_stmt_bind_param($lock_appointment_stmt, "ii", $appointment_id, $parent_id);
            mysqli_stmt_execute($lock_appointment_stmt);

            $lock_appointment_result = mysqli_stmt_get_result($lock_appointment_stmt);

            if (mysqli_num_rows($lock_appointment_result) !== 1) {
                throw new Exception("Appointment not found.");
            }

            $locked_appointment = mysqli_fetch_assoc($lock_appointment_result);

            if ($locked_appointment["status"] !== "needs_rescheduling") {
                throw new Exception("This appointment does not need rescheduling.");
            }

            /*
                Lock new slot.
            */
            $slot_sql = "SELECT 
                            slot_id,
                            therapist_id,
                            slot_date,
                            start_time,
                            end_time,
                            status
                         FROM time_slots
                         WHERE slot_id = ?
                         AND therapist_id = ?
                         LIMIT 1
                         FOR UPDATE";

            $slot_stmt = mysqli_prepare($conn, $slot_sql);
            mysqli_stmt_bind_param($slot_stmt, "ii", $new_slot_id, $locked_appointment["therapist_id"]);
            mysqli_stmt_execute($slot_stmt);

            $slot_result = mysqli_stmt_get_result($slot_stmt);

            if (mysqli_num_rows($slot_result) !== 1) {
                throw new Exception("Selected slot was not found.");
            }

            $new_slot = mysqli_fetch_assoc($slot_result);

            if ($new_slot["status"] !== "available") {
                throw new Exception("Selected slot is no longer available.");
            }

            if (strtotime($new_slot["slot_date"]) < strtotime(date("Y-m-d"))) {
                throw new Exception("Cannot reschedule to a past date.");
            }

            /*
                Save reschedule history.
            */
            $history_sql = "INSERT INTO appointment_reschedule_history
                            (
                                appointment_id,
                                parent_id,
                                therapist_id,
                                old_slot_id,
                                new_slot_id,
                                old_date,
                                old_start_time,
                                old_end_time,
                                new_date,
                                new_start_time,
                                new_end_time,
                                reason,
                                rescheduled_by,
                                rescheduled_by_role
                            )
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'parent')";

            $history_stmt = mysqli_prepare($conn, $history_sql);
            mysqli_stmt_bind_param(
                $history_stmt,
                "iiiiisssssssi",
                $locked_appointment["appointment_id"],
                $locked_appointment["parent_id"],
                $locked_appointment["therapist_id"],
                $locked_appointment["slot_id"],
                $new_slot["slot_id"],
                $locked_appointment["appointment_date"],
                $locked_appointment["start_time"],
                $locked_appointment["end_time"],
                $new_slot["slot_date"],
                $new_slot["start_time"],
                $new_slot["end_time"],
                $reason,
                $parent_id
            );

            if (!mysqli_stmt_execute($history_stmt)) {
                throw new Exception("Failed to save reschedule history.");
            }

            /*
                Update appointment with new slot and make it approved again.
            */
$oldDate = $appointment['appointment_date'];
$oldStartTime = $appointment['start_time'];
$oldEndTime = $appointment['end_time'];

$newDate = $new_slot['slot_date'];
$newStartTime = $new_slot['start_time'];
$newEndTime = $new_slot['end_time'];

            $update_appointment_sql = "UPDATE appointments
                                       SET 
                                            slot_id = ?,
                                            appointment_date = ?,
                                            start_time = ?,
                                            end_time = ?,
                                            status = 'approved',
                                            reschedule_reason = ?,
                                            updated_at = NOW()
                                       WHERE appointment_id = ?";

            $update_appointment_stmt = mysqli_prepare($conn, $update_appointment_sql);
            mysqli_stmt_bind_param(
                $update_appointment_stmt,
                "issssi",
                $new_slot["slot_id"],
                $new_slot["slot_date"],
                $new_slot["start_time"],
                $new_slot["end_time"],
                $reason,
                $locked_appointment["appointment_id"]
            );

            if (!mysqli_stmt_execute($update_appointment_stmt)) {
                throw new Exception("Failed to update appointment.");
            }

            /*
                New slot becomes booked.
                Old slot stays blocked_for_leave.
            */
            $update_new_slot_sql = "UPDATE time_slots
                                    SET status = 'booked', updated_at = NOW()
                                    WHERE slot_id = ?";

            $update_new_slot_stmt = mysqli_prepare($conn, $update_new_slot_sql);
            mysqli_stmt_bind_param($update_new_slot_stmt, "i", $new_slot["slot_id"]);

            if (!mysqli_stmt_execute($update_new_slot_stmt)) {
                throw new Exception("Failed to book new slot.");
            }

            createNotification(
                $conn,
                "parent",
                $parent_id,
                "Appointment Rescheduled",
                "Your appointment has been rescheduled successfully."
            );

            createNotification(
                $conn,
                "therapist",
                $locked_appointment["therapist_id"],
                "Appointment Rescheduled",
                $parent_name . " rescheduled the appointment for " . $locked_appointment["child_name"] . "."
            );

            notifyAllAdminsAboutReschedule($conn, $parent_name, $locked_appointment["child_name"]);


            $therapistQuery = mysqli_query($conn,"
SELECT full_name,email
FROM therapists
WHERE therapist_id = {$appointment['therapist_id']}
");

$therapist = mysqli_fetch_assoc($therapistQuery);

$childQuery = mysqli_query($conn,"
SELECT child_name
FROM children
WHERE child_id = {$appointment['child_id']}
");

$child = mysqli_fetch_assoc($childQuery);

sendTherapistAppointmentRescheduledEmail(
    $therapist['email'],
    $therapist['full_name'],
    $child['child_name'],

    $oldDate,
    $oldStartTime,
    $oldEndTime,

    $newDate,
    $newStartTime,
    $newEndTime,

    $reason
);



            mysqli_commit($conn);

            header("Location: my-appointments.php?success=rescheduled");
            exit();

        } catch (Exception $exception) {
            mysqli_rollback($conn);
            $error = $exception->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Filters for available slots
|--------------------------------------------------------------------------
*/
$selected_date = isset($_GET["slot_date"]) ? cleanInput($_GET["slot_date"]) : date("Y-m-d");

/*
|--------------------------------------------------------------------------
| Fetch available slots for same therapist
|--------------------------------------------------------------------------
*/
$slots_sql = "SELECT 
                slot_id,
                therapist_id,
                slot_date,
                start_time,
                end_time,
                status
              FROM time_slots
              WHERE therapist_id = ?
              AND slot_date = ?
              AND status = 'available'
              AND slot_date >= CURDATE()
              ORDER BY start_time ASC";

$slots_stmt = mysqli_prepare($conn, $slots_sql);
mysqli_stmt_bind_param($slots_stmt, "is", $appointment["therapist_id"], $selected_date);
mysqli_stmt_execute($slots_stmt);

$slots_result = mysqli_stmt_get_result($slots_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reschedule Appointment - SpeechCare</title>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Same dashboard design -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <style>
        .reschedule-card {
            background: #ffffff;
            border: 1px solid var(--light-border);
            border-radius: 22px;
            padding: 24px;
            box-shadow: 0 12px 35px rgba(57, 152, 232, 0.08);
            margin-bottom: 24px;
        }

        .info-panel {
            background: #F7FBFF;
            border: 1px solid var(--light-border);
            border-radius: 18px;
            padding: 18px;
            height: 100%;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            border-bottom: 1px solid var(--light-border);
            padding: 10px 0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--gray-text);
            font-weight: 600;
        }

        .info-value {
            color: var(--dark-text);
            font-weight: 800;
            text-align: right;
        }

        .slot-card {
            border: 1px solid var(--light-border);
            background: #ffffff;
            border-radius: 20px;
            padding: 20px;
            height: 100%;
            transition: 0.2s ease;
        }

        .slot-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 35px rgba(57, 152, 232, 0.14);
        }

        .slot-date {
            font-weight: 800;
            color: var(--dark-text);
            font-size: 17px;
        }

        .slot-time {
            font-weight: 800;
            color: var(--primary-blue);
            font-size: 20px;
            margin: 8px 0;
        }

        .select-slot-btn {
            background: var(--primary-blue);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            padding: 11px 16px;
            font-weight: 800;
            width: 100%;
            transition: 0.2s ease;
        }

        .select-slot-btn:hover {
            background: var(--primary-blue-dark);
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

        .empty-box {
            background: #ffffff;
            border: 1px dashed var(--light-border);
            border-radius: 22px;
            padding: 36px;
            text-align: center;
            color: var(--gray-text);
        }

        .empty-box i {
            font-size: 42px;
            color: var(--primary-blue);
            margin-bottom: 12px;
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
            <a href="notifications.php"><i class="bi bi-bell"></i> Notifications</a>
            <a href="profile.php"><i class="bi bi-person-circle"></i> Profile</a>
            <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="page-title">Reschedule Appointment</h1>
                <p class="page-subtitle">Choose another available slot with the same therapist.</p>
            </div>

            <a href="my-appointments.php" class="btn btn-light rounded-3 fw-bold">
                Back to My Appointments
            </a>
        </div>

        <?php if (!empty($error)) { ?>
            <div class="alert alert-danger border-0 rounded-4 mb-4">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo escapeOutput($error); ?>
            </div>
        <?php } ?>

        <div class="alert alert-warning border-0 rounded-4 mb-4">
            <i class="bi bi-arrow-repeat me-2"></i>
            This appointment needs rescheduling because the original therapist slot is no longer available.
        </div>

        <!-- Current Appointment -->
        <div class="reschedule-card">
            <h4 class="fw-bold mb-3">Current Appointment Details</h4>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="info-panel">
                        <h5 class="fw-bold mb-3">Child</h5>

                        <div class="info-row">
                            <span class="info-label">Name</span>
                            <span class="info-value"><?php echo escapeOutput($appointment["child_name"]); ?></span>
                        </div>

                        <div class="info-row">
                            <span class="info-label">Age</span>
                            <span class="info-value"><?php echo escapeOutput($appointment["age"]); ?></span>
                        </div>

                        <div class="info-row">
                            <span class="info-label">Concern</span>
                            <span class="info-value"><?php echo escapeOutput($appointment["speech_concern"]); ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="info-panel">
                        <h5 class="fw-bold mb-3">Therapist</h5>

                        <div class="info-row">
                            <span class="info-label">Name</span>
                            <span class="info-value"><?php echo escapeOutput($appointment["therapist_name"]); ?></span>
                        </div>

                        <div class="info-row">
                            <span class="info-label">Specialization</span>
                            <span class="info-value"><?php echo escapeOutput($appointment["specialization"]); ?></span>
                        </div>

                        <div class="info-row">
                            <span class="info-label">Experience</span>
                            <span class="info-value"><?php echo escapeOutput($appointment["experience"] ?: "Not added"); ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="info-panel">
                        <h5 class="fw-bold mb-3">Old Slot</h5>

                        <div class="info-row">
                            <span class="info-label">Date</span>
                            <span class="info-value"><?php echo formatDateNice($appointment["appointment_date"]); ?></span>
                        </div>

                        <div class="info-row">
                            <span class="info-label">Time</span>
                            <span class="info-value">
                                <?php echo formatTimeNice($appointment["start_time"]); ?>
                                -
                                <?php echo formatTimeNice($appointment["end_time"]); ?>
                            </span>
                        </div>

                        <div class="info-row">
                            <span class="info-label">Status</span>
                            <span class="info-value"><?php echo displayStatusText($appointment["status"]); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($appointment["reschedule_reason"])) { ?>
                <div class="alert alert-info border-0 rounded-4 mt-4 mb-0">
                    <strong>Reason:</strong>
                    <?php echo escapeOutput($appointment["reschedule_reason"]); ?>
                </div>
            <?php } ?>
        </div>

        <!-- Select Date -->
        <div class="reschedule-card">
            <h4 class="fw-bold mb-3">Select New Date</h4>

            <form method="GET" action="" class="row g-3 align-items-end">
                <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">

                <div class="col-md-8">
                    <label class="form-label fw-bold">New Appointment Date</label>
                    <input 
                        type="date" 
                        name="slot_date" 
                        class="form-control"
                        min="<?php echo date("Y-m-d"); ?>"
                        value="<?php echo escapeOutput($selected_date); ?>"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <button type="submit" class="action-btn-primary w-100">
                        <i class="bi bi-search me-1"></i>
                        Check Slots
                    </button>
                </div>
            </form>
        </div>

        <!-- Available Slots -->
        <div class="reschedule-card">
            <h4 class="fw-bold mb-3">Available Slots</h4>

            <p class="text-muted small mb-4">
                Showing available slots for
                <strong><?php echo escapeOutput($appointment["therapist_name"]); ?></strong>
                on
                <strong><?php echo formatDateNice($selected_date); ?></strong>.
            </p>

            <div class="row g-4">
                <?php if (mysqli_num_rows($slots_result) > 0) { ?>
                    <?php while ($slot = mysqli_fetch_assoc($slots_result)) { ?>
                        <?php
                            $slot_date_display = formatDateNice($slot["slot_date"]);
                            $slot_time_display = formatTimeNice($slot["start_time"]) . " - " . formatTimeNice($slot["end_time"]);
                        ?>

                        <div class="col-lg-4 col-md-6">
                            <div class="slot-card">
                                <div class="slot-date">
                                    <?php echo escapeOutput($slot_date_display); ?>
                                </div>

                                <div class="small text-muted">
                                    <?php echo date("l", strtotime($slot["slot_date"])); ?>
                                </div>

                                <div class="slot-time">
                                    <?php echo escapeOutput($slot_time_display); ?>
                                </div>

                                <button 
                                    type="button"
                                    class="select-slot-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#confirmRescheduleModal"
                                    data-slot-id="<?php echo $slot["slot_id"]; ?>"
                                    data-slot-date="<?php echo escapeOutput($slot_date_display); ?>"
                                    data-slot-time="<?php echo escapeOutput($slot_time_display); ?>"
                                >
                                    Select This Slot
                                </button>
                            </div>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="col-12">
                        <div class="empty-box">
                            <i class="bi bi-calendar-x"></i>
                            <h5 class="fw-bold">No available slots found</h5>
                            <p class="mb-0">Try another date or contact the clinic for support.</p>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>

    </main>
</div>

<!-- Confirm Reschedule Modal -->
<div class="modal fade" id="confirmRescheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="reschedule-appointment.php?appointment_id=<?php echo $appointment_id; ?>&slot_date=<?php echo urlencode($selected_date); ?>">
                <input type="hidden" name="new_slot_id" id="newSlotId">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Confirm Reschedule</h5>
                        <p class="text-muted small mb-0">
                            This will update your appointment with the selected new slot.
                        </p>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="alert alert-info border-0 rounded-4">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        After rescheduling, your appointment will become approved again.
                    </div>

                    <div class="mb-3">
                        <span class="text-muted small">New Date</span>
                        <div class="fw-bold" id="newSlotDate">-</div>
                    </div>

                    <div class="mb-3">
                        <span class="text-muted small">New Time</span>
                        <div class="fw-bold" id="newSlotTime">-</div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold">Reason / Note</label>
                        <textarea 
                            name="reason" 
                            class="form-control"
                            placeholder="Optional: Add a note about this reschedule."
                        ></textarea>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" name="reschedule_appointment" class="action-btn-primary">
                        Confirm Reschedule
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
const confirmModal = document.getElementById("confirmRescheduleModal");

if (confirmModal) {
    confirmModal.addEventListener("show.bs.modal", function (event) {
        const button = event.relatedTarget;

        document.getElementById("newSlotId").value = button.getAttribute("data-slot-id");
        document.getElementById("newSlotDate").textContent = button.getAttribute("data-slot-date");
        document.getElementById("newSlotTime").textContent = button.getAttribute("data-slot-time");
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>