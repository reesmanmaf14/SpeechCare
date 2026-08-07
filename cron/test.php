<?php
date_default_timezone_set('Asia/Colombo');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email-functions.php';

/*
|--------------------------------------------------------------------------
| Cron Execution Log
|--------------------------------------------------------------------------
*/
file_put_contents(
    __DIR__ . '/cron-log.txt',
    date('Y-m-d H:i:s') . " - Reminder cron started\n",
    FILE_APPEND
);

echo "<h3>SpeechCare Appointment Reminder Cron</h3>";

/*
|--------------------------------------------------------------------------
| Get Approved Appointments
|--------------------------------------------------------------------------
*/
$sql = "
SELECT
    a.appointment_id,
    a.appointment_date,
    a.start_time,
    a.end_time,
    a.parent_24hr_reminder_sent,
    a.therapist_24hr_reminder_sent,

    p.full_name AS parent_name,
    p.email AS parent_email,

    c.child_name,

    t.full_name AS therapist_name,
    t.email AS therapist_email

FROM appointments a

INNER JOIN parents p
    ON a.parent_id = p.parent_id

INNER JOIN children c
    ON a.child_id = c.child_id

INNER JOIN therapists t
    ON a.therapist_id = t.therapist_id

WHERE a.status = 'approved'
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    file_put_contents(
        __DIR__ . '/cron-log.txt',
        date('Y-m-d H:i:s') . " - SQL ERROR: " . mysqli_error($conn) . "\n",
        FILE_APPEND
    );

    die(mysqli_error($conn));
}

$sentCount = 0;

/*
|--------------------------------------------------------------------------
| Process Appointments
|--------------------------------------------------------------------------
*/
while ($appointment = mysqli_fetch_assoc($result)) {

    $appointmentDateTime = strtotime(
        $appointment['appointment_date'] . ' ' .
        $appointment['start_time']
    );

    $hoursRemaining = ($appointmentDateTime - time()) / 3600;

    file_put_contents(
        __DIR__ . '/cron-log.txt',
        date('Y-m-d H:i:s') .
        " - Checking Appointment #" .
        $appointment['appointment_id'] .
        " (" . round($hoursRemaining, 2) . " hrs remaining)\n",
        FILE_APPEND
    );

    /*
    |--------------------------------------------------------------------------
    | Send reminders only within next 24 hours
    |--------------------------------------------------------------------------
    */
    if ($hoursRemaining > 0 && $hoursRemaining <= 24) {

        /*
        |--------------------------------------------------------------------------
        | Parent Reminder
        |--------------------------------------------------------------------------
        */
        if ((int)$appointment['parent_24hr_reminder_sent'] === 0) {

            $sent = sendParentAppointmentReminderEmail(
                $appointment['parent_email'],
                $appointment['parent_name'],
                $appointment['child_name'],
                $appointment['appointment_date'],
                $appointment['start_time'],
                $appointment['end_time'],
                $appointment['therapist_name']
            );

            if ($sent) {

                mysqli_query(
                    $conn,
                    "UPDATE appointments
                     SET parent_24hr_reminder_sent = 1
                     WHERE appointment_id = " .
                    (int)$appointment['appointment_id']
                );

                file_put_contents(
                    __DIR__ . '/cron-log.txt',
                    date('Y-m-d H:i:s') .
                    " - Parent reminder sent for Appointment #" .
                    $appointment['appointment_id'] . "\n",
                    FILE_APPEND
                );

                echo "Parent reminder sent for Appointment #"
                    . $appointment['appointment_id']
                    . "<br>";

                $sentCount++;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Therapist Reminder
        |--------------------------------------------------------------------------
        */
        if ((int)$appointment['therapist_24hr_reminder_sent'] === 0) {

            $sent = sendTherapistAppointmentReminderEmail(
                $appointment['therapist_email'],
                $appointment['therapist_name'],
                $appointment['child_name'],
                $appointment['appointment_date'],
                $appointment['start_time'],
                $appointment['end_time']
            );

            if ($sent) {

                mysqli_query(
                    $conn,
                    "UPDATE appointments
                     SET therapist_24hr_reminder_sent = 1
                     WHERE appointment_id = " .
                    (int)$appointment['appointment_id']
                );

                file_put_contents(
                    __DIR__ . '/cron-log.txt',
                    date('Y-m-d H:i:s') .
                    " - Therapist reminder sent for Appointment #" .
                    $appointment['appointment_id'] . "\n",
                    FILE_APPEND
                );

                echo "Therapist reminder sent for Appointment #"
                    . $appointment['appointment_id']
                    . "<br>";

                $sentCount++;
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Finish Log
|--------------------------------------------------------------------------
*/
file_put_contents(
    __DIR__ . '/cron-log.txt',
    date('Y-m-d H:i:s') .
    " - Cron completed. Emails Sent: {$sentCount}\n",
    FILE_APPEND
);

echo "<br><strong>Total Emails Sent: {$sentCount}</strong>";