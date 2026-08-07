<?php
include "../includes/auth_therapist.php";
include "../includes/functions.php";

$success = "";
$error = "";

$therapist_id = $_SESSION["therapist_id"];

/*
|--------------------------------------------------------------------------
| Fetch therapist profile
|--------------------------------------------------------------------------
*/
$profile_sql = "SELECT 
                    therapist_id,
                    full_name,
                    email,
                    phone,
                    qualification,
                    specialization,
                    experience,
                    password,
                    status,
                    created_at
                FROM therapists
                WHERE therapist_id = ?
                LIMIT 1";

$profile_stmt = mysqli_prepare($conn, $profile_sql);
mysqli_stmt_bind_param($profile_stmt, "i", $therapist_id);
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
    $qualification = cleanInput($_POST["qualification"]);
    $specialization = cleanInput($_POST["specialization"]);
    $experience = cleanInput($_POST["experience"]);

    if (
        empty($full_name) ||
        empty($email) ||
        empty($phone) ||
        empty($qualification) ||
        empty($specialization)
    ) {
        $error = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        /*
            Since we use one login page for admin, therapist, and parent,
            email must be unique across all stakeholder tables.
        */
        $email_exists = false;

        $check_therapist_sql = "SELECT therapist_id FROM therapists WHERE email = ? AND therapist_id != ? LIMIT 1";
        $check_therapist_stmt = mysqli_prepare($conn, $check_therapist_sql);
        mysqli_stmt_bind_param($check_therapist_stmt, "si", $email, $therapist_id);
        mysqli_stmt_execute($check_therapist_stmt);
        mysqli_stmt_store_result($check_therapist_stmt);

        if (mysqli_stmt_num_rows($check_therapist_stmt) > 0) {
            $email_exists = true;
        }

        $check_parent_sql = "SELECT parent_id FROM parents WHERE email = ? LIMIT 1";
        $check_parent_stmt = mysqli_prepare($conn, $check_parent_sql);
        mysqli_stmt_bind_param($check_parent_stmt, "s", $email);
        mysqli_stmt_execute($check_parent_stmt);
        mysqli_stmt_store_result($check_parent_stmt);

        if (mysqli_stmt_num_rows($check_parent_stmt) > 0) {
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
            $update_sql = "UPDATE therapists
                           SET 
                                full_name = ?,
                                email = ?,
                                phone = ?,
                                qualification = ?,
                                specialization = ?,
                                experience = ?,
                                updated_at = NOW()
                           WHERE therapist_id = ?";

            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param(
                $update_stmt,
                "ssssssi",
                $full_name,
                $email,
                $phone,
                $qualification,
                $specialization,
                $experience,
                $therapist_id
            );

            if (mysqli_stmt_execute($update_stmt)) {
                $_SESSION["therapist_name"] = $full_name;
                $_SESSION["therapist_email"] = $email;
                $_SESSION["name"] = $full_name;
                $_SESSION["email"] = $email;

                header("Location: profile.php?success=updated");
                exit();
            } else {
                $error = "Failed to update profile.";
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Success messages
|--------------------------------------------------------------------------
*/
if (isset($_GET["success"])) {
    if ($_GET["success"] === "updated") {
        $success = "Profile updated successfully.";
    } elseif ($_GET["success"] === "password_changed") {
        $success = "Password changed successfully.";
    }
}

/*
|--------------------------------------------------------------------------
| Refresh profile
|--------------------------------------------------------------------------
*/
$profile_stmt = mysqli_prepare($conn, $profile_sql);
mysqli_stmt_bind_param($profile_stmt, "i", $therapist_id);
mysqli_stmt_execute($profile_stmt);

$profile_result = mysqli_stmt_get_result($profile_stmt);
$profile = mysqli_fetch_assoc($profile_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Therapist Profile - SpeechCare</title>

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

        .form-control {
            border: 1px solid var(--light-border);
            border-radius: 14px;
            min-height: 50px;
            padding: 11px 14px;
        }

        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.2rem rgba(57, 152, 232, 0.15);
        }

        .info-box-profile {
            background: #E8F4FF;
            border: 1px solid #CFE8FF;
            border-radius: 18px;
            padding: 18px;
            color: #19192D;
            line-height: 1.7;
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
        <a href="appointments.php"><i class="bi bi-calendar-check"></i> My Appointments</a>
        <a href="notifications.php"><i class="bi bi-bell"></i> Notifications</a>
        <a href="profile.php" class="active"><i class="bi bi-person-circle"></i> Profile</a>
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
            <a href="appointments.php"><i class="bi bi-calendar-check"></i> My Appointments</a>
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
                <p class="page-subtitle">Update your therapist account details and password.</p>
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

        <!-- Header -->
        <div class="profile-header-box">
            <div class="profile-large-circle">
                <?php echo strtoupper(substr($profile["full_name"], 0, 1)); ?>
            </div>

            <div>
                <h4 class="fw-bold mb-1"><?php echo escapeOutput($profile["full_name"]); ?></h4>

                <p class="text-muted mb-1">
                    <?php echo escapeOutput($profile["email"]); ?>
                </p>

                <p class="text-muted mb-2">
                    <?php echo escapeOutput($profile["specialization"]); ?>
                </p>

                <?php if ($profile["status"] === "active") { ?>
                    <span class="badge bg-success">Active</span>
                <?php } else { ?>
                    <span class="badge bg-danger">Inactive</span>
                <?php } ?>
            </div>
        </div>

       

        <!-- Profile Form -->
        <div class="profile-card">
            <h4 class="fw-bold mb-3">Therapist Details</h4>

            <form method="POST" action="">

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            name="full_name" 
                            class="form-control" 
                            value="<?php echo escapeOutput($profile["full_name"]); ?>" 
                            required
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                        <input 
                            type="email" 
                            name="email" 
                            class="form-control" 
                            value="<?php echo escapeOutput($profile["email"]); ?>" 
                            required
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Phone <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            name="phone" 
                            class="form-control" 
                            value="<?php echo escapeOutput($profile["phone"]); ?>" 
                            required
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Qualification <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            name="qualification" 
                            class="form-control" 
                            value="<?php echo escapeOutput($profile["qualification"]); ?>" 
                            required
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Specialization <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            name="specialization" 
                            class="form-control" 
                            value="<?php echo escapeOutput($profile["specialization"]); ?>" 
                            required
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Experience</label>
                        <input 
                            type="text" 
                            name="experience" 
                            class="form-control" 
                            value="<?php echo escapeOutput($profile["experience"]); ?>" 
                            placeholder="Example: 5 years"
                        >
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