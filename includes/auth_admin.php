<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/cache_control.php";
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION["user_role"]) || $_SESSION["user_role"] !== "admin") {
   header("Location: ../login.php?access=denied");
    exit();
}

if (!isset($_SESSION["admin_id"])) {
    session_destroy();
   header("Location: ../login.php?access=denied");
    exit();
}

$admin_id = intval($_SESSION["admin_id"]);

$sql = "SELECT admin_id FROM admins WHERE admin_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $admin_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) !== 1) {
    session_destroy();
    header("Location: ../admin_login.php?access=denied");
    exit();
}
?>