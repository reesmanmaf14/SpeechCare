<?php
include "../includes/auth_parent.php";
include "../includes/functions.php";
include "../includes/notification_functions.php";
include "../includes/email-functions.php";

$success = "";
$error = "";

$parent_id = $_SESSION["parent_id"];
$parent_name = $_SESSION["parent_name"] ?? "Parent";  /* Null Coalescing Operator  */

/*
|--------------------------------------------------------------------------
| Get child details
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
$child_name = $child["child_name"] ?? "Child";    /* prevent errors if no child records exists (undefined array key)   */
$child_age = $child["age"] ?? "-";

/*
|--------------------------------------------------------------------------
| Notify all admins helper
|--------------------------------------------------------------------------
*/
function notifyAllAdminsAboutBooking($conn, $parent_name, $child_name) {
    $admin_result = mysqli_query($conn, "SELECT admin_id FROM admins");

    while ($admin = mysqli_fetch_assoc($admin_result)) {
        createNotification(
            $conn,
            "admin",
            $admin["admin_id"],
            "New Appointment Request",
            $parent_name . " submitted a new appointment request for " . $child_name . "."
        );
    }
}

/*
|--------------------------------------------------------------------------
| Book appointment submit
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["book_appointment"])) {
    $therapist_id = intval($_POST["therapist_id"]);
    $slot_id = intval($_POST["slot_id"]);
    $parent_message = cleanInput($_POST["parent_message"] ?? "");

    if ($child_id <= 0) {
        $error = "Child details not found. Cannot book appointment.";
    } elseif ($therapist_id <= 0 || $slot_id <= 0) {
        $error = "Invalid therapist or time slot selected.";
    } else {
        mysqli_begin_transaction($conn);

        try {
            /*
                Lock selected slot to prevent double booking.
            */
            $slot_sql = "SELECT 
                            ts.slot_id,
                            ts.therapist_id,
                            ts.slot_date,
                            ts.start_time,
                            ts.end_time,
                            ts.status,
                            t.full_name AS therapist_name,
                            t.status AS therapist_status
                         FROM time_slots ts
                         INNER JOIN therapists t ON ts.therapist_id = t.therapist_id
                         WHERE ts.slot_id = ?
                         AND ts.therapist_id = ?
                         LIMIT 1
                         FOR UPDATE";

            $slot_stmt = mysqli_prepare($conn, $slot_sql);
            mysqli_stmt_bind_param($slot_stmt, "ii", $slot_id, $therapist_id);
            mysqli_stmt_execute($slot_stmt);

            $slot_result = mysqli_stmt_get_result($slot_stmt);

            if (mysqli_num_rows($slot_result) !== 1) {
                throw new Exception("Selected slot was not found.");
            }

            $slot = mysqli_fetch_assoc($slot_result);

            if ($slot["therapist_status"] !== "active") {
                throw new Exception("Selected therapist is currently unavailable.");
            }

            if ($slot["status"] !== "available") {
                throw new Exception("This time slot is no longer available.");
            }

            if (strtotime($slot["slot_date"]) < strtotime(date("Y-m-d"))) {
                throw new Exception("Cannot book appointments for past dates.");
            }

            /*
                Prevent duplicate request for same child and same slot.
            */
            $duplicate_sql = "SELECT appointment_id
                              FROM appointments
                              WHERE child_id = ?
                              AND slot_id = ?
                              AND status IN ('pending', 'approved', 'needs_rescheduling')
                              LIMIT 1";

            $duplicate_stmt = mysqli_prepare($conn, $duplicate_sql);
            mysqli_stmt_bind_param($duplicate_stmt, "ii", $child_id, $slot_id);
            mysqli_stmt_execute($duplicate_stmt);
            mysqli_stmt_store_result($duplicate_stmt);

            if (mysqli_stmt_num_rows($duplicate_stmt) > 0) {
                throw new Exception("You already have an appointment request for this slot.");
            }

            /*
                Insert appointment as pending.
            */
            $insert_sql = "INSERT INTO appointments
                            (
                                parent_id,
                                child_id,
                                therapist_id,
                                slot_id,
                                appointment_date,
                                start_time,
                                end_time,
                                status,
                                parent_message
                            )
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)";

            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param(
                $insert_stmt,
                "iiiissss",
                $parent_id,
                $child_id,
                $therapist_id,
                $slot_id,
                $slot["slot_date"],
                $slot["start_time"],
                $slot["end_time"],
                $parent_message
            );

            if (!mysqli_stmt_execute($insert_stmt)) {
                throw new Exception("Failed to submit appointment request.");
            }

            /*
                Slot becomes pending until admin approves/rejects.
            */
            $update_slot_sql = "UPDATE time_slots
                                SET status = 'pending'
                                WHERE slot_id = ?";

            $update_slot_stmt = mysqli_prepare($conn, $update_slot_sql);
            mysqli_stmt_bind_param($update_slot_stmt, "i", $slot_id);

            if (!mysqli_stmt_execute($update_slot_stmt)) {
                throw new Exception("Failed to reserve selected slot.");
            }

            /*
                Notifications
            */
            notifyAllAdminsAboutBooking($conn, $parent_name, $child_name);

            createNotification(
                $conn,
                "parent",
                $parent_id,
                "Appointment Request Submitted",
                "Your appointment request has been submitted and is waiting for admin approval."
            );

            /* ====================== EMAIL NOTIFICATIONS ====================== */
            // Get parent email and full name
            $parent_email_sql = "SELECT email, full_name FROM parents WHERE parent_id = ?";
            $parent_email_stmt = mysqli_prepare($conn, $parent_email_sql);
            mysqli_stmt_bind_param($parent_email_stmt, "i", $parent_id);
            mysqli_stmt_execute($parent_email_stmt);
            $parent_data = mysqli_fetch_assoc(mysqli_stmt_get_result($parent_email_stmt));

            $parent_email = $parent_data['email'] ?? '';
            $parent_full_name = $parent_data['full_name'] ?? $parent_name;

            // 2. Fetch Notification Recipient Details (Therapist Info)
            $therapist_sql = "SELECT full_name, email FROM therapists WHERE therapist_id = ?";
            $therapist_stmt = mysqli_prepare($conn, $therapist_sql);
            mysqli_stmt_bind_param($therapist_stmt, "i", $therapist_id);
            mysqli_stmt_execute($therapist_stmt);
            $therapist_data = mysqli_fetch_assoc(mysqli_stmt_get_result($therapist_stmt));

            if (!empty($parent_email)) {
                sendParentBookingPendingEmail(
                    $parent_email,
                    $parent_full_name,
                    $child_name,
                    $slot["slot_date"],
                    $slot["start_time"],
                    $slot["end_time"]
                );


            }

            // Send notification email to the assigned therapist
            if (!empty($therapist_data['email'])) {
                sendTherapistNewAppointmentEmail(
                    $therapist_data['email'],
                    $therapist_data['full_name'],
                    $child_name, // Reusing your existing global $child_name variable safely
                    $slot["slot_date"],
                    $slot["start_time"],
                    $slot["end_time"]
                );
            }
            /* ================================================================ */
            mysqli_commit($conn);

            header("Location: book-appointment.php?success=booked");
            exit();

        } catch (Exception $exception) {
            mysqli_rollback($conn);
            $error = $exception->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Success message
|--------------------------------------------------------------------------
*/
if (isset($_GET["success"]) && $_GET["success"] === "booked") {
    $success = "Appointment request submitted successfully. Please wait for admin approval.";
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$selected_therapist_id = isset($_GET["therapist_id"]) ? intval($_GET["therapist_id"]) : 0;
$selected_date = isset($_GET["slot_date"]) ? cleanInput($_GET["slot_date"]) : date("Y-m-d");

/*
|--------------------------------------------------------------------------
| Fetch active therapists
|--------------------------------------------------------------------------
*/
$therapists_sql = "SELECT 
                    therapist_id,
                    full_name,
                    qualification,
                    specialization,
                    experience
                   FROM therapists
                   WHERE status = 'active'
                   ORDER BY full_name ASC";

$therapists_result = mysqli_query($conn, $therapists_sql);

/*
|--------------------------------------------------------------------------
| Fetch selected therapist
|--------------------------------------------------------------------------
*/
$selected_therapist = null;

if ($selected_therapist_id > 0) {
    $selected_therapist_sql = "SELECT 
                                therapist_id,
                                full_name,
                                qualification,
                                specialization,
                                experience
                               FROM therapists
                               WHERE therapist_id = ?
                               AND status = 'active'
                               LIMIT 1";

    $selected_therapist_stmt = mysqli_prepare($conn, $selected_therapist_sql);
    mysqli_stmt_bind_param($selected_therapist_stmt, "i", $selected_therapist_id);
    mysqli_stmt_execute($selected_therapist_stmt);

    $selected_therapist_result = mysqli_stmt_get_result($selected_therapist_stmt);
    $selected_therapist = mysqli_fetch_assoc($selected_therapist_result);
}

/*
|--------------------------------------------------------------------------
| Fetch available slots
|--------------------------------------------------------------------------
*/
$slots_result = null;

if ($selected_therapist_id > 0 && !empty($selected_date)) {
    $slots_sql = "SELECT 
                    ts.slot_id,
                    ts.therapist_id,
                    ts.slot_date,
                    ts.start_time,
                    ts.end_time,
                    t.full_name AS therapist_name,
                    t.specialization
                  FROM time_slots ts
                  INNER JOIN therapists t ON ts.therapist_id = t.therapist_id
                  WHERE ts.therapist_id = ?
                  AND ts.slot_date = ?
                  AND ts.status = 'available'
                  AND t.status = 'active'
                 AND (
    ts.slot_date > CURDATE()
    OR (
        ts.slot_date = CURDATE()
        AND ts.start_time > CURTIME()
    )
)
                  ORDER BY ts.start_time ASC";

    $slots_stmt = mysqli_prepare($conn, $slots_sql);
    mysqli_stmt_bind_param($slots_stmt, "is", $selected_therapist_id, $selected_date);
    mysqli_stmt_execute($slots_stmt);

    $slots_result = mysqli_stmt_get_result($slots_stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Book Appointment - SpeechCare</title>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Same dashboard design -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <style>
        .booking-card {
            background: #ffffff;
            border: 1px solid var(--light-border);
            border-radius: 22px;
            padding: 24px;
            box-shadow: 0 12px 35px rgba(57, 152, 232, 0.08);
            margin-bottom: 24px;
        }

        .step-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--dark-text);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .step-number {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            background: var(--primary-blue-light);
            color: var(--primary-blue);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
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

        .therapist-card,
        .slot-card {
            border: 1px solid var(--light-border);
            background: #ffffff;
            border-radius: 20px;
            padding: 20px;
            height: 100%;
            transition: 0.2s ease;
        }

        .therapist-card:hover,
        .slot-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 35px rgba(57, 152, 232, 0.14);
        }

        .therapist-avatar {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            background: var(--primary-blue-light);
            color: var(--primary-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 22px;
            margin-bottom: 14px;
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

        .child-info-card {
            background: #E8F4FF;
            color: #19192D;
            border-radius: 18px;
            padding: 18px;
            border: 1px solid #CFE8FF;
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

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
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
        <a href="book-appointment.php" class="active"><i class="bi bi-calendar-plus"></i> Book Appointment</a>
        <a href="my-appointments.php"><i class="bi bi-calendar-check"></i> My Appointments</a>
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
            <a href="book-appointment.php" class="active"><i class="bi bi-calendar-plus"></i> Book Appointment</a>
            <a href="my-appointments.php"><i class="bi bi-calendar-check"></i> My Appointments</a>
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
                <h1 class="page-title">Book a New Session</h1>
                <p class="page-subtitle">Choose a therapist, date, and available generated slot.</p>
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


        <!-- Step 1 -->
        <div class="booking-card">
            <div class="step-title">
                <span class="step-number">1</span>
                Select Therapist and Date
            </div>

            <p class="text-muted small mb-4">
                Select a therapist and date to view available appointment slots.
            </p>

            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-lg-6 col-md-6">
                    <label class="form-label fw-bold">Therapist</label>
                    <select name="therapist_id" class="form-select" required>
                        <option value="">Select therapist</option>

                        <?php mysqli_data_seek($therapists_result, 0); ?>
                        <?php while ($therapist = mysqli_fetch_assoc($therapists_result)) { ?>
                            <option value="<?php echo $therapist["therapist_id"]; ?>" <?php echo $selected_therapist_id == $therapist["therapist_id"] ? "selected" : ""; ?>>
                                <?php echo escapeOutput($therapist["full_name"] . " - " . $therapist["specialization"]); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="col-lg-4 col-md-6">
                    <label class="form-label fw-bold">Appointment Date</label>
                    <input 
                        type="date" 
                        name="slot_date" 
                        class="form-control"
                        min="<?php echo date("Y-m-d"); ?>"
                        value="<?php echo escapeOutput($selected_date); ?>"
                        required
                    >
                </div>

                <div class="col-lg-2 col-md-12">
                    <button type="submit" class="action-btn-primary w-100">
                        <i class="bi bi-search me-1"></i>
                        Check
                    </button>
                </div>
            </form>
        </div>

        <!-- Step 2 -->
        <div class="booking-card">
            <div class="step-title">
                <span class="step-number">2</span>
                Available Therapists
            </div>

            <p class="text-muted small mb-4">
                These therapists are active and available for booking.
            </p>

            <div class="row g-4">
                <?php mysqli_data_seek($therapists_result, 0); ?>

                <?php if (mysqli_num_rows($therapists_result) > 0) { ?>
                    <?php while ($therapist = mysqli_fetch_assoc($therapists_result)) { ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="therapist-card">
                                <div class="therapist-avatar">
                                    <?php echo strtoupper(substr($therapist["full_name"], 0, 1)); ?>
                                </div>

                                <h5 class="fw-bold mb-1">
                                    <?php echo escapeOutput($therapist["full_name"]); ?>
                                </h5>

                                <div class="text-primary fw-bold mb-2">
                                    <?php echo escapeOutput($therapist["specialization"]); ?>
                                </div>

                                <p class="text-muted small mb-2">
                                    <strong>Qualification:</strong>
                                    <?php echo escapeOutput($therapist["qualification"]); ?>
                                </p>

                                <p class="text-muted small mb-3">
                                    <strong>Experience:</strong>
                                    <?php echo escapeOutput($therapist["experience"] ?: "Not added"); ?>
                                </p>

                                <a 
                                    href="book-appointment.php?therapist_id=<?php echo $therapist["therapist_id"]; ?>&slot_date=<?php echo urlencode($selected_date); ?>" 
                                    class="action-btn-primary text-decoration-none d-inline-block w-100 text-center"
                                >
                                    Select Therapist
                                </a>
                            </div>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="col-12">
                        <div class="empty-box">
                            <i class="bi bi-person-x"></i>
                            <h5 class="fw-bold">No active therapists found</h5>
                            <p class="mb-0">Please contact the clinic for assistance.</p>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- Step 3 -->
        <div class="booking-card">
            <div class="step-title">
                <span class="step-number">3</span>
                Choose Available Slot
            </div>

            <?php if ($selected_therapist && !empty($selected_date)) { ?>
                <p class="text-muted small mb-4">
                    Showing available slots for
                    <strong><?php echo escapeOutput($selected_therapist["full_name"]); ?></strong>
                    on
                    <strong><?php echo formatDateNice($selected_date); ?></strong>.
                </p>
            <?php } else { ?>
                <p class="text-muted small mb-4">
                    Select a therapist and date first.
                </p>
            <?php } ?>

            <div class="row g-4">
                <?php if ($slots_result && mysqli_num_rows($slots_result) > 0) { ?>
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

                                <div class="small text-muted mb-3">
                                    Therapist:
                                    <strong><?php echo escapeOutput($slot["therapist_name"]); ?></strong>
                                </div>

                                <button 
                                    type="button"
                                    class="select-slot-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#confirmBookingModal"
                                    data-slot-id="<?php echo $slot["slot_id"]; ?>"
                                    data-therapist-id="<?php echo $slot["therapist_id"]; ?>"
                                    data-therapist-name="<?php echo escapeOutput($slot["therapist_name"]); ?>"
                                    data-slot-date="<?php echo escapeOutput($slot_date_display); ?>"
                                    data-slot-time="<?php echo escapeOutput($slot_time_display); ?>"
                                >
                                    Select Slot
                                </button>
                            </div>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="col-12">
                        <div class="empty-box">
                            <i class="bi bi-calendar-x"></i>

                            <?php if ($selected_therapist_id > 0) { ?>
                                <h5 class="fw-bold">No available slots found</h5>
                                <p class="mb-0">
                                    Try another date or ask the clinic if slots are not generated yet.
                                </p>
                            <?php } else { ?>
                                <h5 class="fw-bold">Select therapist and date</h5>
                                <p class="mb-0">
                                    Available generated slots will appear here.
                                </p>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>

    </main>
</div>

<!-- Confirm Booking Modal -->
<div class="modal fade" id="confirmBookingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="">
                <input type="hidden" name="slot_id" id="bookingSlotId">
                <input type="hidden" name="therapist_id" id="bookingTherapistId">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Confirm Appointment Request</h5>
                        <p class="text-muted small mb-0">
                            Your request will be sent to admin for approval.
                        </p>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-info border-0 rounded-4">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        Appointment status will be <strong>Pending</strong> until admin approves it.
                    </div>

                    <div class="mb-3">
                        <span class="text-muted small">Therapist</span>
                        <div class="fw-bold" id="bookingTherapistName">-</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <span class="text-muted small">Date</span>
                            <div class="fw-bold" id="bookingSlotDate">-</div>
                        </div>

                        <div class="col-md-6">
                            <span class="text-muted small">Time</span>
                            <div class="fw-bold" id="bookingSlotTime">-</div>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold">Message / Reason</label>
                        <textarea 
                            name="parent_message" 
                            class="form-control"
                            placeholder="Optional: Tell the clinic why you are booking this session."
                        ></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" name="book_appointment" class="action-btn-primary">
                        Submit Request
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
const confirmBookingModal = document.getElementById("confirmBookingModal");

if (confirmBookingModal) {
    confirmBookingModal.addEventListener("show.bs.modal", function (event) {
        const button = event.relatedTarget;

        document.getElementById("bookingSlotId").value = button.getAttribute("data-slot-id");
        document.getElementById("bookingTherapistId").value = button.getAttribute("data-therapist-id");
        document.getElementById("bookingTherapistName").textContent = button.getAttribute("data-therapist-name");
        document.getElementById("bookingSlotDate").textContent = button.getAttribute("data-slot-date");
        document.getElementById("bookingSlotTime").textContent = button.getAttribute("data-slot-time");
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>