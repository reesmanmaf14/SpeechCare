<?php
include "../includes/auth_parent.php";
include "../includes/functions.php";

$success = "";
$error = "";

$parent_id = $_SESSION["parent_id"];

/*
|--------------------------------------------------------------------------
| Fetch parent + child
|--------------------------------------------------------------------------
*/
$profile_sql = "SELECT 
                    p.parent_id,
                    p.full_name,
                    p.email,
                    p.phone,
                    p.address,
                    p.password,
                    p.status,

                    c.child_id,
                    c.child_name,
                    c.age,
                    c.gender,
                    c.speech_concern,
                    c.previous_therapy_history,
                    c.additional_notes
                FROM parents p
                LEFT JOIN children c ON p.parent_id = c.parent_id
                WHERE p.parent_id = ?
                LIMIT 1";

$profile_stmt = mysqli_prepare($conn, $profile_sql);
mysqli_stmt_bind_param($profile_stmt, "i", $parent_id);
mysqli_stmt_execute($profile_stmt);

$profile_result = mysqli_stmt_get_result($profile_stmt);

if (mysqli_num_rows($profile_result) !== 1) {
    session_destroy();
    header("Location: ../login.php?access=denied");
    exit();
}

$profile = mysqli_fetch_assoc($profile_result);

/*
|--------------------------------------------------------------------------
| Update profile details
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_profile"])) {
    $full_name = cleanInput($_POST["full_name"]);
    $email = cleanInput($_POST["email"]);
    $phone = cleanInput($_POST["phone"]);
    $address = cleanInput($_POST["address"]);

    $child_id = intval($_POST["child_id"]);
    $child_name = cleanInput($_POST["child_name"]);
    $age = intval($_POST["age"]);
    $gender = cleanInput($_POST["gender"]);
    $speech_concern = cleanInput($_POST["speech_concern"]);
    $previous_therapy_history = cleanInput($_POST["previous_therapy_history"]);
    $additional_notes = cleanInput($_POST["additional_notes"]);

    if (
        empty($full_name) ||
        empty($email) ||
        empty($phone) ||
        empty($child_name) ||
        empty($age) ||
        empty($gender) ||
        empty($speech_concern)
    ) {
        $error = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!in_array($gender, ["male", "female", "other"])) {
        $error = "Please select a valid gender.";
    } elseif ($age <= 0 || $age > 18) {
        $error = "Please enter a valid child age.";
    } else {
        /*
            Since login page is common for all stakeholders,
            email must be unique across parents, therapists, and admins.
        */
        $email_exists = false;

        $check_parent_sql = "SELECT parent_id FROM parents WHERE email = ? AND parent_id != ? LIMIT 1";
        $check_parent_stmt = mysqli_prepare($conn, $check_parent_sql);
        mysqli_stmt_bind_param($check_parent_stmt, "si", $email, $parent_id);
        mysqli_stmt_execute($check_parent_stmt);
        mysqli_stmt_store_result($check_parent_stmt);

        if (mysqli_stmt_num_rows($check_parent_stmt) > 0) {
            $email_exists = true;
        }

        $check_therapist_sql = "SELECT therapist_id FROM therapists WHERE email = ? LIMIT 1";
        $check_therapist_stmt = mysqli_prepare($conn, $check_therapist_sql);
        mysqli_stmt_bind_param($check_therapist_stmt, "s", $email);
        mysqli_stmt_execute($check_therapist_stmt);
        mysqli_stmt_store_result($check_therapist_stmt);

        if (mysqli_stmt_num_rows($check_therapist_stmt) > 0) {
            $email_exists = true;
        }

        $check_admin_sql = "SELECT admin_id FROM admins WHERE email = ? LIMIT 1";
        $check_admin_stmt = mysqli_prepare($conn, $check_admin_sql);
        mysqli_stmt_bind_param($check_admin_stmt, "s", $email);
        mysqli_stmt_execute($check_admin_stmt);
        mysqli_stmt_store_result($check_admin_stmt);

        if (mysqli_stmt_num_rows($check_admin_stmt) > 0) {
            $email_exists = true;
        }

        if ($email_exists) {
            $error = "This email address is already used by another account.";
        } else {
            mysqli_begin_transaction($conn);

            try {
                $update_parent_sql = "UPDATE parents
                                      SET 
                                        full_name = ?,
                                        email = ?,
                                        phone = ?,
                                        address = ?,
                                        updated_at = NOW()
                                      WHERE parent_id = ?";

                $update_parent_stmt = mysqli_prepare($conn, $update_parent_sql);
                mysqli_stmt_bind_param(
                    $update_parent_stmt,
                    "ssssi",
                    $full_name,
                    $email,
                    $phone,
                    $address,
                    $parent_id
                );

                if (!mysqli_stmt_execute($update_parent_stmt)) {
                    throw new Exception("Failed to update parent details.");
                }

                if ($child_id > 0) {
                    $update_child_sql = "UPDATE children
                                         SET 
                                            child_name = ?,
                                            age = ?,
                                            gender = ?,
                                            speech_concern = ?,
                                            previous_therapy_history = ?,
                                            additional_notes = ?,
                                            updated_at = NOW()
                                         WHERE child_id = ?
                                         AND parent_id = ?";

                    $update_child_stmt = mysqli_prepare($conn, $update_child_sql);
                    mysqli_stmt_bind_param(
                        $update_child_stmt,
                        "sissssii",
                        $child_name,
                        $age,
                        $gender,
                        $speech_concern,
                        $previous_therapy_history,
                        $additional_notes,
                        $child_id,
                        $parent_id
                    );

                    if (!mysqli_stmt_execute($update_child_stmt)) {
                        throw new Exception("Failed to update child details.");
                    }
                } else {
                    $insert_child_sql = "INSERT INTO children
                                         (
                                            parent_id,
                                            child_name,
                                            age,
                                            gender,
                                            speech_concern,
                                            previous_therapy_history,
                                            additional_notes
                                         )
                                         VALUES (?, ?, ?, ?, ?, ?, ?)";

                    $insert_child_stmt = mysqli_prepare($conn, $insert_child_sql);
                    mysqli_stmt_bind_param(
                        $insert_child_stmt,
                        "isissss",
                        $parent_id,
                        $child_name,
                        $age,
                        $gender,
                        $speech_concern,
                        $previous_therapy_history,
                        $additional_notes
                    );

                    if (!mysqli_stmt_execute($insert_child_stmt)) {
                        throw new Exception("Failed to add child details.");
                    }
                }

                mysqli_commit($conn);

                $_SESSION["parent_name"] = $full_name;
                $_SESSION["parent_email"] = $email;
                $_SESSION["name"] = $full_name;
                $_SESSION["email"] = $email;

                header("Location: profile.php?success=updated");
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
| Refresh profile after actions
|--------------------------------------------------------------------------
*/
$profile_stmt = mysqli_prepare($conn, $profile_sql);
mysqli_stmt_bind_param($profile_stmt, "i", $parent_id);
mysqli_stmt_execute($profile_stmt);
$profile_result = mysqli_stmt_get_result($profile_stmt);
$profile = mysqli_fetch_assoc($profile_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Profile - SpeechCare</title>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Same dashboard design -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <style>
        .profile-card {
            background: #ffffff;
            border: 1px solid var(--light-border);
            border-radius: 22px;
            padding: 24px;
            box-shadow: 0 12px 35px rgba(57, 152, 232, 0.08);
            margin-bottom: 24px;
        }

        .profile-header-box {
            background: #F7FBFF;
            border: 1px solid var(--light-border);
            border-radius: 22px;
            padding: 24px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .profile-large-circle {
            width: 76px;
            height: 76px;
            border-radius: 22px;
            background: var(--primary-blue-light);
            color: var(--primary-blue);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 30px;
            font-weight: 900;
            flex-shrink: 0;
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

        textarea.form-control {
            min-height: 110px;
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
        <a href="book-appointment.php"><i class="bi bi-calendar-plus"></i> Book Appointment</a>
        <a href="my-appointments.php"><i class="bi bi-calendar-check"></i> My Appointments</a>
         <a href="progress.php"><i class="bi bi-graph-up-arrow"></i> Progress</a>
        <a href="notifications.php"><i class="bi bi-bell"></i> Notifications</a>
        <a href="profile.php" class="active"><i class="bi bi-person-circle"></i> Profile</a>
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
            <a href="my-appointments.php"><i class="bi bi-calendar-check"></i> My Appointments</a>
             <a href="progress.php"><i class="bi bi-graph-up-arrow"></i> Progress</a>
            <a href="notifications.php"><i class="bi bi-bell"></i> Notifications</a>
            <a href="profile.php" class="active"><i class="bi bi-person-circle"></i> Profile</a>
            <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="page-title">My Profile</h1>
                <p class="page-subtitle">Update your parent account and child therapy details.</p>
            </div>

            <div class="profile-circle">
                <?php echo strtoupper(substr($profile["full_name"], 0, 1)); ?>
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

        <div class="profile-header-box">
            <div class="profile-large-circle">
                <?php echo strtoupper(substr($profile["full_name"], 0, 1)); ?>
            </div>

            <div>
                <h4 class="fw-bold mb-1"><?php echo escapeOutput($profile["full_name"]); ?></h4>
                <p class="text-muted mb-1"><?php echo escapeOutput($profile["email"]); ?></p>
                <span class="badge bg-success">
                    <?php echo displayStatusText($profile["status"]); ?>
                </span>
            </div>
        </div>

        <!-- Profile Form -->
        <div class="profile-card">
            <h4 class="fw-bold mb-3">Parent & Child Details</h4>

            <form method="POST" action="">
                <input type="hidden" name="child_id" value="<?php echo escapeOutput($profile["child_id"] ?? 0); ?>">

                <h5 class="fw-bold mb-3">Parent Details</h5>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo escapeOutput($profile["full_name"]); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?php echo escapeOutput($profile["email"]); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Phone <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" value="<?php echo escapeOutput($profile["phone"]); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Address</label>
                        <input type="text" name="address" class="form-control" value="<?php echo escapeOutput($profile["address"]); ?>">
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="fw-bold mb-3">Child Details</h5>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Child Name <span class="text-danger">*</span></label>
                        <input type="text" name="child_name" class="form-control" value="<?php echo escapeOutput($profile["child_name"]); ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Age <span class="text-danger">*</span></label>
                        <input type="number" name="age" class="form-control" min="1" max="18" value="<?php echo escapeOutput($profile["age"]); ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Gender <span class="text-danger">*</span></label>
                        <select name="gender" class="form-select" required>
                            <option value="">Select</option>
                            <option value="male" <?php echo $profile["gender"] === "male" ? "selected" : ""; ?>>Male</option>
                            <option value="female" <?php echo $profile["gender"] === "female" ? "selected" : ""; ?>>Female</option>
                            <option value="other" <?php echo $profile["gender"] === "other" ? "selected" : ""; ?>>Other</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Speech Concern <span class="text-danger">*</span></label>
                        <textarea name="speech_concern" class="form-control" required><?php echo escapeOutput($profile["speech_concern"]); ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Previous Therapy History</label>
                        <textarea name="previous_therapy_history" class="form-control"><?php echo escapeOutput($profile["previous_therapy_history"]); ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Additional Notes</label>
                        <textarea name="additional_notes" class="form-control"><?php echo escapeOutput($profile["additional_notes"]); ?></textarea>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" name="update_profile" class="action-btn-primary">
                        Save Profile Changes
                    </button>
                </div>
            </form>
        </div>

       

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>