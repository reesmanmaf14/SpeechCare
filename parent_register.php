<?php
session_start();

include "config/db.php";
include "includes/functions.php";
include "includes/email-functions.php";

$success = "";
$error = "";

/*
|--------------------------------------------------------------------------
| If already logged in, redirect
|--------------------------------------------------------------------------
*/
if (isset($_SESSION["user_role"])) {
    if ($_SESSION["user_role"] === "admin") {
        header("Location: admin/dashboard.php");
        exit();
    }

    if ($_SESSION["user_role"] === "therapist") {
        header("Location: therapist/dashboard.php");
        exit();
    }

    if ($_SESSION["user_role"] === "parent") {
        header("Location: parent/dashboard.php");
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| Parent Registration
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = cleanInput($_POST["full_name"]);
    $email = cleanInput($_POST["email"]);
    $phone = cleanInput($_POST["phone"]);
    $address = cleanInput($_POST["address"]);

    $child_name = cleanInput($_POST["child_name"]);
    $age = intval($_POST["age"]);
    $gender = cleanInput($_POST["gender"]);
    $speech_concern = cleanInput($_POST["speech_concern"]);
    $previous_therapy_history = cleanInput($_POST["previous_therapy_history"]);
    $additional_notes = cleanInput($_POST["additional_notes"]);

    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    if (
        empty($full_name) ||
        empty($email) ||
        empty($phone) ||
        empty($child_name) ||
        empty($age) ||
        empty($gender) ||
        empty($speech_concern) ||
        empty($password) ||
        empty($confirm_password)
    ) {
        $error = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!in_array($gender, ["male", "female", "other"])) {
        $error = "Please select a valid gender.";
    } elseif ($age <= 0 || $age > 18) {
        $error = "Please enter a valid child age.";
    } elseif ($password !== $confirm_password) {
        $error = "Password and confirm password do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {

        /*
            Important:
            Since one login page checks admin, therapist, and parent,
            the same email should not exist in any stakeholder table.
        */
        $email_exists = false;

        $check_parent_sql = "SELECT parent_id FROM parents WHERE email = ? LIMIT 1";
        $check_parent_stmt = mysqli_prepare($conn, $check_parent_sql);
        mysqli_stmt_bind_param($check_parent_stmt, "s", $email);
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
            $error = "This email address is already registered.";
        } else {
            mysqli_begin_transaction($conn);

            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $parent_sql = "INSERT INTO parents
                                (full_name, email, phone, address, password, status)
                               VALUES (?, ?, ?, ?, ?, 'active')";

                $parent_stmt = mysqli_prepare($conn, $parent_sql);
                mysqli_stmt_bind_param(
                    $parent_stmt,
                    "sssss",
                    $full_name,
                    $email,
                    $phone,
                    $address,
                    $hashed_password
                );

                if (!mysqli_stmt_execute($parent_stmt)) {
                    throw new Exception("Failed to create parent account.");
                }

                $parent_id = mysqli_insert_id($conn);

                $child_sql = "INSERT INTO children
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

                $child_stmt = mysqli_prepare($conn, $child_sql);
                mysqli_stmt_bind_param(
                    $child_stmt,
                    "isissss",
                    $parent_id,
                    $child_name,
                    $age,
                    $gender,
                    $speech_concern,
                    $previous_therapy_history,
                    $additional_notes
                );

                if (!mysqli_stmt_execute($child_stmt)) {
                    throw new Exception("Failed to save child details.");
                }

             mysqli_commit($conn);

/* ====================== EMAIL NOTIFICATIONS ====================== */
$parent_full_name = $full_name;   // We already have full_name

sendParentWelcomeEmail(
    $email,
    $parent_full_name,
    $child_name
);

sendAdminNewRegistrationEmail(
    $parent_full_name,
    $email,
    $phone,
    $child_name,
    $age,
    $speech_concern
);
/* ================================================================ */

header("Location: login.php?registered=success");
exit();

            } catch (Exception $exception) {
                mysqli_rollback($conn);
                $error = $exception->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Parent Registration - SpeechCare</title>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Same auth design -->
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>

<div class="auth-page d-flex justify-content-center align-items-center">
    <div class="auth-card" style="max-width: 760px; width: 100%;">

        <div class="auth-logo">SpeechCare</div>

        <h1 class="auth-title">Create Your SpeechCare Account</h1>

        <p class="auth-subtitle">
            Register to book and manage your child's therapy sessions.
        </p>

        <?php if (!empty($error)) { ?>
            <div class="alert alert-danger border-0">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo escapeOutput($error); ?>
            </div>
        <?php } ?>

        <form method="POST" action="">

            <h5 class="mb-3">Parent Details</h5>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input 
                        type="text" 
                        name="full_name" 
                        class="form-control" 
                        placeholder="Enter parent full name"
                        value="<?php echo escapeOutput($_POST["full_name"] ?? ""); ?>"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input 
                        type="email" 
                        name="email" 
                        class="form-control" 
                        placeholder="Enter email address"
                        value="<?php echo escapeOutput($_POST["email"] ?? ""); ?>"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                    <input 
                        type="text" 
                        name="phone" 
                        class="form-control" 
                        placeholder="Enter phone number"
                        value="<?php echo escapeOutput($_POST["phone"] ?? ""); ?>"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">Address</label>
                    <input 
                        type="text" 
                        name="address" 
                        class="form-control" 
                        placeholder="Enter address"
                        value="<?php echo escapeOutput($_POST["address"] ?? ""); ?>"
                    >
                </div>
            </div>

           

            <h5 class="mb-3">Child Details</h5>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Child Name <span class="text-danger">*</span></label>
                    <input 
                        type="text" 
                        name="child_name" 
                        class="form-control" 
                        placeholder="Enter child name"
                        value="<?php echo escapeOutput($_POST["child_name"] ?? ""); ?>"
                        required
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">Age <span class="text-danger">*</span></label>
                    <input 
                        type="number" 
                        name="age" 
                        class="form-control" 
                        placeholder="Age"
                        min="1"
                        max="18"
                        value="<?php echo escapeOutput($_POST["age"] ?? ""); ?>"
                        required
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">Gender <span class="text-danger">*</span></label>
                    <select name="gender" class="form-control" required>
                        <option value="">Select</option>
                        <option value="male" <?php echo ($_POST["gender"] ?? "") === "male" ? "selected" : ""; ?>>Male</option>
                        <option value="female" <?php echo ($_POST["gender"] ?? "") === "female" ? "selected" : ""; ?>>Female</option>
                        <option value="other" <?php echo ($_POST["gender"] ?? "") === "other" ? "selected" : ""; ?>>Other</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Speech Concern <span class="text-danger">*</span></label>
                    <textarea 
                        name="speech_concern" 
                        class="form-control" 
                        placeholder="Example: pronunciation difficulty, delayed speech, unclear words..."
                        style="min-height: 100px;"
                        required
                    ><?php echo escapeOutput($_POST["speech_concern"] ?? ""); ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Previous Therapy History</label>
                    <textarea 
                        name="previous_therapy_history" 
                        class="form-control" 
                        placeholder="Optional"
                        style="min-height: 90px;"
                    ><?php echo escapeOutput($_POST["previous_therapy_history"] ?? ""); ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Additional Notes</label>
                    <textarea 
                        name="additional_notes" 
                        class="form-control" 
                        placeholder="Optional"
                        style="min-height: 90px;"
                    ><?php echo escapeOutput($_POST["additional_notes"] ?? ""); ?></textarea>
                </div>
            </div>

            

         <h5 class="mb-3">Account Security</h5>

<div class="row g-3">
    <!-- Password Field -->
    <div class="col-md-6">
        <label class="form-label">Password <span class="text-danger">*</span></label>
        <div class="input-group">
            <input 
                type="password" 
                name="password" 
                id="password"
                class="form-control" 
                placeholder="Create password"
                required
            >
            <button 
                class="btn btn-outline-secondary" 
                type="button"
                id="togglePassword"
                aria-label="Toggle password visibility">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>

    <!-- Confirm Password Field -->
    <div class="col-md-6">
        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
        <div class="input-group">
            <input 
                type="password" 
                name="confirm_password" 
                id="confirm_password"
                class="form-control" 
                placeholder="Confirm password"
                required
            >
            <button 
                class="btn btn-outline-secondary" 
                type="button"
                id="toggleConfirmPassword"
                aria-label="Toggle password visibility">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>
</div>
           
            <button type="submit" class="btn-register">
                Create Account

            </button>
        </form>

        <p class="auth-link">
            Already have an account?
            <a href="login.php">Login</a>
        </p>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


<script>
// Toggle Password Visibility
function togglePasswordVisibility(inputId, iconId) {
    const passwordInput = document.getElementById(inputId);
    const icon = document.getElementById(iconId).querySelector('i');
    
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = "password";
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

// Password Toggle
document.getElementById('togglePassword').addEventListener('click', function() {
    togglePasswordVisibility('password', 'togglePassword');
});

// Confirm Password Toggle
document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
    togglePasswordVisibility('confirm_password', 'toggleConfirmPassword');
});
</script>

</body>
</html>