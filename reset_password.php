<?php
session_start();
include "config/db.php";

/*
|--------------------------------------------------------------------------
| Security Check
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION["reset_email"]) ||
    !isset($_SESSION["reset_role"]) ||
    !isset($_SESSION["otp_verified"])
) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION["reset_email"];
$role  = $_SESSION["reset_role"];

$error = "";

/*
|--------------------------------------------------------------------------
| Reset Password
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);

    if (strlen($password) < 8) {

        $error = "Password must be at least 8 characters long.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } else {

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $table = "";

        switch ($role) {

            case "admin":
                $table = "admins";
                break;

            case "therapist":
                $table = "therapists";
                break;

            case "parent":
                $table = "parents";
                break;
        }

        if (!empty($table)) {

            $sql = "UPDATE $table
                    SET password = ?
                    WHERE email = ?";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "ss",
                $hashedPassword,
                $email
            );

           if (mysqli_stmt_execute($stmt)) {

    if (mysqli_stmt_affected_rows($stmt) > 0) {

        // delete OTP
        $sql2 = "DELETE FROM password_otps WHERE email = ?";
        $stmt2 = mysqli_prepare($conn, $sql2);
        mysqli_stmt_bind_param($stmt2, "s", $email);
        mysqli_stmt_execute($stmt2);

    } else {
        $error = "No matching account found.";
    }

} else {
    $error = "Database error occurred.";
}

            /*
            |--------------------------------------------------------------------------
            | Clear Recovery Session
            |--------------------------------------------------------------------------
            */
            unset($_SESSION["reset_email"]);
            unset($_SESSION["reset_role"]);
            unset($_SESSION["otp_verified"]);

            header("Location: login.php?reset=success");
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

    <title>Reset Password - SpeechCare</title>

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

        .reset-card{
            width:100%;
            max-width:500px;
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

        .subtitle{
            color:#6c757d;
        }

        .primary-btn{
            background:#3998E8;
            border:none;
            padding:12px;
            border-radius:12px;
            width:100%;
            color:white;
            font-weight:600;
        }

        .primary-btn:hover{
            background:#2487dc;
        }

    </style>

</head>
<body>

<div class="reset-card">

    <div class="text-center mb-4">

        <i class="bi bi-shield-lock-fill text-primary"
           style="font-size:52px;"></i>

        <div class="logo mt-2">
            SpeechCare
        </div>

        <h3 class="mt-3">
            Create New Password
        </h3>

        <p class="subtitle">
            Choose a strong password for your account.
        </p>

    </div>

    <?php if(!empty($error)) { ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php } ?>

    <form method="POST">

        <div class="mb-3">

            <label class="form-label">
                New Password
            </label>

            <input
                type="password"
                name="password"
                class="form-control"
                required
            >

        </div>

        <div class="mb-4">

            <label class="form-label">
                Confirm Password
            </label>

            <input
                type="password"
                name="confirm_password"
                class="form-control"
                required
            >

        </div>

        <button type="submit" class="primary-btn">
            <i class="bi bi-check-circle-fill me-2"></i>
            Reset Password
        </button>

    </form>

</div>

</body>
</html>