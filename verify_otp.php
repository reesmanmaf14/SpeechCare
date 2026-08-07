<?php
session_start();
include "config/db.php";

if (!isset($_SESSION["reset_email"])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION["reset_email"];
$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $otp = trim($_POST["otp"]);

    $sql = "SELECT *
            FROM password_otps
            WHERE email = ?
            AND otp_code = ?
            AND is_used = 0
            ORDER BY id DESC
            LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $email, $otp);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {

        if (strtotime($row["expires_at"]) < time()) {
            $msg = "OTP has expired.";
        } else {

            $update = mysqli_prepare(
                $conn,
                "UPDATE password_otps SET is_used = 1 WHERE id = ?"
            );

            mysqli_stmt_bind_param($update, "i", $row["id"]);
            mysqli_stmt_execute($update);

            $_SESSION["otp_verified"] = true;

            header("Location: reset_password.php");
            exit();
        }

    } else {
        $msg = "Invalid OTP code.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Verify OTP - SpeechCare</title>

<meta name="viewport" content="width=device-width, initial-scale=1">

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

.verify-card{
    width:100%;
    max-width:500px;
    background:#fff;
    border-radius:20px;
    padding:35px;
    box-shadow:0 15px 40px rgba(57,152,232,.12);
}

.logo{
    font-size:30px;
    font-weight:800;
    color:#3998E8;
}

.otp-input{
    text-align:center;
    letter-spacing:12px;
    font-size:28px;
    font-weight:700;
    height:65px;
}

.primary-btn{
    background:#3998E8;
    color:#fff;
    border:none;
    border-radius:12px;
    padding:12px;
    width:100%;
    font-weight:600;
}

.primary-btn:hover{
    background:#2487dc;
}

.email-badge{
    background:#eef7ff;
    color:#3998E8;
    padding:10px 15px;
    border-radius:10px;
    font-size:14px;
}
</style>
</head>
<body>

<div class="verify-card">

    <div class="text-center">

        <div class="mb-3">
            <i class="bi bi-shield-lock-fill text-primary" style="font-size:50px;"></i>
        </div>

        <div class="logo">SpeechCare</div>

        <h3 class="mt-3">Verify OTP</h3>

        <p class="text-muted">
            We sent a verification code to
        </p>

        <div class="email-badge mb-4">
            <?php echo htmlspecialchars($email); ?>
        </div>

    </div>

    <?php if($msg): ?>
        <div class="alert alert-danger">
            <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <div class="mb-4">

            <input
                type="text"
                name="otp"
                maxlength="6"
                class="form-control otp-input"
                placeholder="000000"
                required
            >

        </div>

        <button type="submit" class="primary-btn">
            Verify OTP
        </button>

    </form>

    <div class="text-center mt-4">

        <a href="forgot_password.php"
           class="text-decoration-none">

            <i class="bi bi-arrow-left"></i>
            Change Email

        </a>

    </div>

</div>

</body>
</html>