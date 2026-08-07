<?php
require_once __DIR__ . '/config/db.php';

// Drop the therapist_leave_requests table
$drop_sql = "DROP TABLE IF EXISTS therapist_leave_requests";
if (mysqli_query($conn, $drop_sql)) {
    echo "Table therapist_leave_requests dropped successfully.\n";
} else {
    echo "Error dropping table: " . mysqli_error($conn) . "\n";
}

// Update time_slots where status = 'blocked_for_leave' to 'available'
$update_sql = "UPDATE time_slots SET status = 'available' WHERE status = 'blocked_for_leave'";
if (mysqli_query($conn, $update_sql)) {
    echo "time_slots updated successfully. " . mysqli_affected_rows($conn) . " rows affected.\n";
} else {
    echo "Error updating time_slots: " . mysqli_error($conn) . "\n";
}
?>
