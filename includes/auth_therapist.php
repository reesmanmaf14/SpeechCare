<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/cache_control.php";
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION["user_role"]) || $_SESSION["user_role"] !== "therapist") {
   header("Location: ../login.php?access=denied");
    exit();
}

if (!isset($_SESSION["therapist_id"])) {
    session_destroy();
   header("Location: ../login.php?inactive=1");
    exit();
}

$therapist_id = intval($_SESSION["therapist_id"]);

$sql = "SELECT therapist_id, status FROM therapists WHERE therapist_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $therapist_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) !== 1) {
    session_destroy();
    header("Location: ../therapist_login.php?access=denied");
    exit();
}

$therapist = mysqli_fetch_assoc($result);

if ($therapist["status"] !== "active") {
    session_destroy();
    header("Location: ../therapist_login.php?inactive=1");
    exit();
}
?>