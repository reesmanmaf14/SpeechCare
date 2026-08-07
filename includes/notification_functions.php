<?php
function createNotification($conn, $user_type, $user_id, $title, $message) {
    $allowed_user_types = ["parent", "therapist", "admin"];

    if (!in_array($user_type, $allowed_user_types)) {
        return false;
    }

    $sql = "INSERT INTO notifications (user_type, user_id, title, message, status)
            VALUES (?, ?, ?, ?, 'unread')";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "siss", $user_type, $user_id, $title, $message);

    return mysqli_stmt_execute($stmt);
}

function getUnreadNotificationCount($conn, $user_type, $user_id) {
    $sql = "SELECT COUNT(*) AS total 
            FROM notifications 
            WHERE user_type = ? 
            AND user_id = ? 
            AND status = 'unread'";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $user_type, $user_id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    return $row["total"] ?? 0;
}
?>