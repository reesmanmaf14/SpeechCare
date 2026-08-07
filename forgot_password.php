<?php
session_start();
include "config/db.php";
include "includes/email-functions.php";

$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"]);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Invalid email address.";
    } else {

        // Find user + role
        $tables = [
            "admin" => ["admins", "admin_id"],
            "therapist" => ["therapists", "therapist_id"],
            "parent" => ["parents", "parent_id"]
        ];

        $role = null;
        $userName = null;

        foreach ($tables as $r => $data) {

            $sql = "SELECT full_name FROM {$data[0]} WHERE email=?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            if ($row = mysqli_fetch_assoc($res)) {
                $role = $r;
                $userName = $row["full_name"];
                break;
            }
        }

        // Always show same message (security)
        $msg = "If the email exists, an OTP has been sent.";

        if ($role) {

            // Generate OTP
            $otp = rand(100000, 999999);
            $expires = date("Y-m-d H:i:s", strtotime("+5 minutes"));

            // Store OTP
            $sql = "INSERT INTO password_otps (email, role, otp_code, expires_at)
                    VALUES (?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssss", $email, $role, $otp, $expires);
            mysqli_stmt_execute($stmt);

            // Send Email
            sendOtpEmail($email, $userName, $otp);

             $_SESSION["reset_email"] = $email;
             $_SESSION["reset_role"] = $role;

            header("Location: verify_otp.php");
exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Forgot Password - SpeechCare</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>

body{
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background:linear-gradient(135deg,#eef6ff,#f8fbff);
}

.auth-card{
    width:100%;
    max-width:520px;
    background:#fff;
    border-radius:24px;
    padding:40px;
    box-shadow:0 15px 40px rgba(57,152,232,.12);
}

.logo{
    font-size:32px;
    font-weight:800;
    color:#3998E8;
}

.primary-btn{
    width:100%;
    border:none;
    border-radius:12px;
    padding:13px;
    background:#3998E8;
    color:#fff;
    font-weight:600;
}

.primary-btn:hover{
    background:#2487dc;
}

.back-link{
    text-decoration:none;
    color:#3998E8;
}

</style>

</head>
<body>

<div class="auth-card">

    <div class="text-center">

        <i class="bi bi-shield-lock-fill text-primary"
           style="font-size:55px;"></i>

        <div class="logo mt-2">
            SpeechCare
        </div>

        <h2 class="mt-3">
            Forgot Password
        </h2>

        <p class="text-muted">
            Enter your email address and we'll send a verification code.
        </p>

    </div>

    <?php if(!empty($msg)) { ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php } ?>

    <form method="POST">

        <div class="mb-4">

            <label class="form-label">
                Email Address
            </label>

            <div class="input-group">

                <span class="input-group-text">
                    <i class="bi bi-envelope"></i>
                </span>

                <input
                    type="email"
                    name="email"
                    class="form-control"
                    placeholder="Enter your email address"
                    required
                >

            </div>

        </div>

        <button type="submit" class="primary-btn">
            <i class="bi bi-send-fill me-2"></i>
            Send OTP
        </button>

    </form>

    <div class="text-center mt-4">

        <a href="login.php" class="back-link">
            <i class="bi bi-arrow-left"></i>
            Back to Login
        </a>

    </div>

</div>

</body>
</html>