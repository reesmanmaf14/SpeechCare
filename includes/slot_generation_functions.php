<?php
function getDayNumberFromName($day_of_week) {
    $days = [
        "sunday" => 0,
        "monday" => 1,
        "tuesday" => 2,
        "wednesday" => 3,
        "thursday" => 4,
        "friday" => 5,
        "saturday" => 6
    ];

    return $days[$day_of_week] ?? null;
}

function generateSlotsForAvailability($conn, $therapist_id, $day_of_week, $start_time, $end_time, $session_duration, $break_duration, $days_ahead = 30) {
    $target_day_number = getDayNumberFromName($day_of_week);

    if ($target_day_number === null) {
        return 0;
    }

    $generated_count = 0;
    $today = new DateTime();

    for ($i = 0; $i <= $days_ahead; $i++) {
        $date = clone $today;
        $date->modify("+$i days");

        if (intval($date->format("w")) !== $target_day_number) {
            continue;
        }

        $slot_date = $date->format("Y-m-d");

        $current_start = new DateTime($slot_date . " " . $start_time);
        $availability_end = new DateTime($slot_date . " " . $end_time);

        while (true) {
            $current_end = clone $current_start;
            $current_end->modify("+$session_duration minutes");

            if ($current_end > $availability_end) {
                break;
            }

            $slot_start_time = $current_start->format("H:i:s");
            $slot_end_time = $current_end->format("H:i:s");

            $insert_sql = "INSERT IGNORE INTO time_slots
                            (therapist_id, slot_date, start_time, end_time, status, generated_by)
                           VALUES (?, ?, ?, ?, 'available', 'system')";

            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param(
                $insert_stmt,
                "isss",
                $therapist_id,
                $slot_date,
                $slot_start_time,
                $slot_end_time
            );

            mysqli_stmt_execute($insert_stmt);

            if (mysqli_stmt_affected_rows($insert_stmt) > 0) {
                $generated_count++;
            }

            $current_start = clone $current_end;
            $current_start->modify("+$break_duration minutes");
        }
    }

    return $generated_count;
}

function deleteFutureAvailableGeneratedSlotsForDay($conn, $therapist_id, $day_of_week) {
    $day_name_sql = ucfirst($day_of_week);

    $delete_sql = "DELETE FROM time_slots
                   WHERE therapist_id = ?
                   AND generated_by = 'system'
                   AND status = 'available'
                   AND slot_date >= CURDATE()
                   AND DAYNAME(slot_date) = ?";

    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($delete_stmt, "is", $therapist_id, $day_name_sql);

    return mysqli_stmt_execute($delete_stmt);
}
?>