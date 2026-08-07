<?php
include __DIR__ . "/config/db.php";

$token = $_GET['token'] ?? '';

if (!$token) {
    die("Invalid or missing token.");
}

// get therapist
$sql = "SELECT therapist_id, token_expiry FROM therapists WHERE reset_token = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $token);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$therapist = mysqli_fetch_assoc($result);

if (!$therapist) {
    die("Invalid token.");
}

if (strtotime($therapist['token_expiry']) < time()) {
    die("Token expired.");
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $password = $_POST["password"];
    $confirm = $_POST["confirm"];

    if ($password !== $confirm) {
        $error = "Passwords do not match";
    } else {

        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // IMPORTANT: update using therapist_id
        $update = "UPDATE therapists 
                   SET password = ?, reset_token = NULL, token_expiry = NULL 
                   WHERE therapist_id = ?";

        $stmt = mysqli_prepare($conn, $update);
        mysqli_stmt_bind_param($stmt, "si", $hashed, $therapist['therapist_id']);
        mysqli_stmt_execute($stmt);

        echo "Password set successfully. You can now login.";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set Password - SpeechCare</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #e8f1ff, #f7fbff);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
        }

        .card-box {
            width: 100%;
            max-width: 420px;
            border: none;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        .brand {
            font-size: 24px;
            font-weight: 800;
            color: #2F80ED;
        }

        .subtitle {
            font-size: 14px;
            color: #6c757d;
        }

        .form-control {
            border-radius: 12px;
            padding: 12px;
        }

        .btn-primary {
            background: #2F80ED;
            border: none;
            border-radius: 12px;
            padding: 12px;
            font-weight: 700;
        }

        .btn-primary:hover {
            background: #1f6fd6;
        }

        .error-box {
            background: #ffe5e5;
            color: #c0392b;
            padding: 10px;
            border-radius: 10px;
            font-size: 14px;
        }

        .success-box {
            background: #e6f8f0;
            color: #1e7e34;
            padding: 10px;
            border-radius: 10px;
            font-size: 14px;
        }
    </style>
</head>

<body>

<div class="card card-box p-4">

    <div class="text-center mb-3">
        <div class="brand">SpeechCare</div>
        <div class="subtitle">Set your secure password</div>
    </div>

    <?php if (!empty($error)) { ?>
        <div class="error-box mb-3">
            <?php echo $error; ?>
        </div>
    <?php } ?>

    <?php if (!empty($success)) { ?>
        <div class="success-box mb-3">
            <?php echo $success; ?>
        </div>
    <?php } ?>

    <form method="POST">

        <div class="mb-3">
            <label class="form-label fw-bold">New Password</label>
            <input type="password" name="password" class="form-control" placeholder="Enter new password" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Confirm Password</label>
            <input type="password" name="confirm" class="form-control" placeholder="Confirm password" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            Set Password
        </button>

    </form>

    <div class="text-center mt-3">
        <small class="text-muted">
            Secure login for SpeechCare therapists
        </small>
    </div>

</div>

</body>
</html>