<?php
session_start();
include "config/db.php";

$error = "";

/*
|--------------------------------------------------------------------------
| If already logged in, redirect to correct dashboard
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
| Login Submit
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {
        $error = "Please enter your email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {

        /*
        |--------------------------------------------------------------------------
        | 1. Check Admin Login
        |--------------------------------------------------------------------------
        */
        $admin_sql = "SELECT admin_id, full_name, email, password 
                      FROM admins 
                      WHERE email = ?   
                      LIMIT 1";  

        $admin_stmt = mysqli_prepare($conn, $admin_sql);  
        mysqli_stmt_bind_param($admin_stmt, "s", $email);  
        mysqli_stmt_execute($admin_stmt);  

        $admin_result = mysqli_stmt_get_result($admin_stmt); 

        if (mysqli_num_rows($admin_result) === 1) {  
            $admin = mysqli_fetch_assoc($admin_result);   

            if (password_verify($password, $admin["password"])) {
                session_regenerate_id(true);

                $_SESSION["user_role"] = "admin";
                $_SESSION["admin_id"] = $admin["admin_id"];
                $_SESSION["admin_name"] = $admin["full_name"];
                $_SESSION["admin_email"] = $admin["email"];

                $_SESSION["user_id"] = $admin["admin_id"];
                $_SESSION["name"] = $admin["full_name"];
                $_SESSION["email"] = $admin["email"];

                header("Location: admin/dashboard.php");
                exit();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Check Therapist Login
        |--------------------------------------------------------------------------
        */
        $therapist_sql = "SELECT therapist_id, full_name, email, password, status 
                          FROM therapists 
                          WHERE email = ? 
                          LIMIT 1";

        $therapist_stmt = mysqli_prepare($conn, $therapist_sql);
        mysqli_stmt_bind_param($therapist_stmt, "s", $email);
        mysqli_stmt_execute($therapist_stmt);

        $therapist_result = mysqli_stmt_get_result($therapist_stmt);

        if (mysqli_num_rows($therapist_result) === 1) {
            $therapist = mysqli_fetch_assoc($therapist_result);

            if (password_verify($password, $therapist["password"])) {
                if ($therapist["status"] !== "active") {
                    $error = "Your therapist account is inactive. Please contact admin.";
                } else {
                    session_regenerate_id(true);

                    $_SESSION["user_role"] = "therapist";
                    $_SESSION["therapist_id"] = $therapist["therapist_id"];
                    $_SESSION["therapist_name"] = $therapist["full_name"];
                    $_SESSION["therapist_email"] = $therapist["email"];

                    $_SESSION["user_id"] = $therapist["therapist_id"];
                    $_SESSION["name"] = $therapist["full_name"];
                    $_SESSION["email"] = $therapist["email"];

                    header("Location: therapist/dashboard.php");
                    exit();
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Check Parent Login
        |--------------------------------------------------------------------------
        */
        $parent_sql = "SELECT parent_id, full_name, email, password, status 
                       FROM parents 
                       WHERE email = ? 
                       LIMIT 1";

        $parent_stmt = mysqli_prepare($conn, $parent_sql);
        mysqli_stmt_bind_param($parent_stmt, "s", $email);
        mysqli_stmt_execute($parent_stmt);

        $parent_result = mysqli_stmt_get_result($parent_stmt);

        if (mysqli_num_rows($parent_result) === 1) {
            $parent = mysqli_fetch_assoc($parent_result);

            if (password_verify($password, $parent["password"])) {
                if ($parent["status"] !== "active") {
                    $error = "Your parent account is blocked. Please contact the clinic.";
                } else {
                    session_regenerate_id(true);

                    $_SESSION["user_role"] = "parent";
                    $_SESSION["parent_id"] = $parent["parent_id"];
                    $_SESSION["parent_name"] = $parent["full_name"];
                    $_SESSION["parent_email"] = $parent["email"];

                    $_SESSION["user_id"] = $parent["parent_id"];
                    $_SESSION["name"] = $parent["full_name"];
                    $_SESSION["email"] = $parent["email"];

                    header("Location: parent/dashboard.php");
                    exit();
                }
            }
        }

        if (empty($error)) {
            $error = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Login - SpeechCare</title>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>

<div class="auth-page">
    <div class="auth-card">

        <a href="home.php" class="back-home">
            <i class="bi bi-arrow-left me-1"></i>
            Back to home
        </a>

        <a href="home.php" class="auth-logo-link">
            <div class="auth-logo">SpeechCare</div>
        </a>

        <h1 class="auth-title">Welcome Back</h1>

        <p class="auth-subtitle">
            Login to access your SpeechCare account
        </p>

        <?php if (isset($_GET["registered"]) && $_GET["registered"] === "success") { ?> 
            <div class="alert alert-success border-0">
                <i class="bi bi-check-circle-fill me-2"></i>
                Registration successful. Please login.
            </div>
        <?php } ?>

        <?php if (isset($_GET["logout"]) && $_GET["logout"] === "success") { ?>
            <div class="alert alert-success border-0">
                <i class="bi bi-check-circle-fill me-2"></i>
                You have been logged out successfully.
            </div>
        <?php } ?>

        <?php if (isset($_GET["access"]) && $_GET["access"] === "denied") { ?>
            <div class="alert alert-warning border-0">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                Please login to access that page.
            </div>
        <?php } ?>

        <?php if (!empty($error)) { ?>
            <div class="alert alert-danger border-0">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php } ?>

        <?php if (isset($_GET["reset"]) && $_GET["reset"] === "success") { ?>
    <div class="alert alert-success border-0">
        <i class="bi bi-check-circle-fill me-2"></i>
        Password reset successful. Please login with your new password.
    </div>
<?php } ?>

        <form method="POST" action="">

            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input 
                    type="email" 
                    name="email" 
                    class="form-control" 
                    placeholder="Enter your email address"
                    required
                >
            </div>

          <div class="mb-3">
    <label class="form-label">Password</label>
    <div class="input-group">
        <input 
            type="password" 
            name="password" 
            id="password"
            class="form-control" 
            placeholder="Enter your password"
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

<div class="text-end mb-3">
    <a href="forgot_password.php" class="small text-decoration-none">
        Forgot Password?
    </a>
</div>
           

            <button type="submit" class="primary-btn">
                Login
               
            </button>
        </form>

        <p class="auth-link">
            Don't have an account?
            <a href="parent_register.php">Register</a>
        </p>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Toggle Password Visibility
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = "password";
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
});
</script>


</body>
</html>