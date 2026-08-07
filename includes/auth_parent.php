<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}   /* This file is used to protect parent pages by ensuring that only authenticated parent users can access them.  */

require_once __DIR__ . "/cache_control.php";
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION["user_role"]) || $_SESSION["user_role"] !== "parent") {
   header("Location: ../login.php?access=denied");
    exit();
}

if (!isset($_SESSION["parent_id"])) {
    session_destroy();
   header("Location: ../login.php?blocked=1");
    exit();
}

$parent_id = intval($_SESSION["parent_id"]);

$sql = "SELECT parent_id, status FROM parents WHERE parent_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $parent_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) !== 1) {
    session_destroy();
    header("Location: ../parent_login.php?access=denied");
    exit();
}

$parent = mysqli_fetch_assoc($result);

if ($parent["status"] !== "active") {
    session_destroy();
    header("Location: ../parent_login.php?blocked=1");
    exit();
}
?>