<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';


/*SpeechCare Email Configuration*/

define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'speechcareclinic123@gmail.com');
define('MAIL_PASSWORD', 'iqgnwqgzoysutabq');
define('MAIL_FROM_EMAIL', 'speechcareclinic123@gmail.com');
define('MAIL_FROM_NAME', 'SpeechCare Clinic');

/* Admin Email
Admin receives registration and appointment request notifications.*/

define('ADMIN_EMAIL', 'speechcareclinic123@gmail.com');
define('ADMIN_NAME', 'SpeechCare Admin');

/*Logging*/

function logEmailError($message) {
    $logDirectory = __DIR__ . '/../logs';

    if (!is_dir($logDirectory)) {
        mkdir($logDirectory, 0775, true);
    }

    $logFile = $logDirectory . '/email-errors.log';

    $date = date('Y-m-d H:i:s');
    error_log("[$date] $message" . PHP_EOL, 3, $logFile);
}

/*Basic Escape Helper*/

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*Date and Time Formatter*/

function formatEmailDate($date) {
    if (empty($date)) {
        return '-';
    }

    return date('M d, Y', strtotime($date));
}

function formatEmailTime($time) {
    if (empty($time)) {
        return '-';
    }

    return date('h:i A', strtotime($time));
}


/*Email Layout*/
function speechcareEmailLayout($title, $preheader, $bodyHtml, $buttonText = null, $buttonUrl = null) {
    $buttonHtml = '';

    if (!empty($buttonText) && !empty($buttonUrl)) {
        $buttonHtml = '
            <tr>
                <td style="padding-top:24px;">
                    <a href="' . e($buttonUrl) . '" 
                       style="background:#3998E8;color:#ffffff;text-decoration:none;
                              padding:13px 22px;border-radius:10px;font-weight:700;
                              display:inline-block;">
                        ' . e($buttonText) . '
                    </a>
                </td>
            </tr>
        ';
    }

    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . e($title) . '</title>
    </head>
    <body style="margin:0;padding:0;background:#F7FBFF;font-family:Arial, sans-serif;color:#19192D;">
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
            ' . e($preheader) . '
        </div>

        <table width="100%" cellpadding="0" cellspacing="0" style="background:#F7FBFF;padding:30px 15px;">
            <tr>
                <td align="center">
                    <table width="100%" cellpadding="0" cellspacing="0" 
                           style="max-width:620px;background:#ffffff;border:1px solid #DDEEFF;
                                  border-radius:20px;overflow:hidden;box-shadow:0 12px 35px rgba(57,152,232,0.12);">

                        <tr>
                            <td style="background:#3998E8;padding:28px 32px;color:#ffffff;">
                                <div style="font-size:28px;font-weight:800;letter-spacing:-0.5px;">
                                    SpeechCare
                                </div>
                                <div style="font-size:14px;margin-top:5px;opacity:0.9;">
                                    Speech Therapy Clinic
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:34px 32px;">
                                <h1 style="font-size:26px;line-height:1.3;margin:0 0 14px;color:#19192D;">
                                    ' . e($title) . '
                                </h1>

                                <div style="font-size:15px;line-height:1.8;color:#666B7A;">
                                    ' . $bodyHtml . '
                                </div>

                                <table cellpadding="0" cellspacing="0">
                                    ' . $buttonHtml . '
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td style="background:#F7FBFF;border-top:1px solid #DDEEFF;padding:20px 32px;">
                                <p style="margin:0;font-size:13px;color:#666B7A;line-height:1.6;">
                                    This is an automated message from SpeechCare. Please do not reply directly to this email.
                                </p>
                            </td>
                        </tr>

                    </table>

                    <p style="font-size:12px;color:#9AA3B2;margin-top:16px;">
                        © ' . date('Y') . ' SpeechCare Clinic. All rights reserved.
                    </p>
                </td>
            </tr>
        </table>
    </body>
    </html>';
}

/*Core Mail Sender*/
function sendSpeechcareEmail($toEmail, $toName, $subject, $htmlBody, $plainTextBody) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $plainTextBody;

        return $mail->send();
    } catch (Exception $exception) {
        logEmailError(
            'Failed to send email to ' . $toEmail .
            ' | Subject: ' . $subject .
            ' | Error: ' . $mail->ErrorInfo
        );

        return false;
    }
}

/*1. Parent Welcome Email*/

function sendParentWelcomeEmail($parentEmail, $parentName, $childName) {
    $title = 'Welcome to SpeechCare';
    $preheader = 'Your SpeechCare parent account has been created successfully.';

    $body = '
        <p>Hello <strong>' . e($parentName) . '</strong>,</p>

        <p>
            Welcome to <strong>SpeechCare</strong>. Your parent account has been created successfully.
        </p>

        <p>
            You can now login, view available speech therapy time slots, book appointments,
            and track the status of your child\'s therapy sessions.
        </p>

        <div style="background:#E8F4FF;border:1px solid #DDEEFF;border-radius:14px;padding:16px;margin-top:18px;">
            <strong>Child Profile:</strong><br>
            ' . e($childName) . '
        </div>
    ';

    $html = speechcareEmailLayout($title, $preheader, $body);

    $text = "Hello $parentName,\n\n"
        . "Welcome to SpeechCare. Your parent account has been created successfully.\n"
        . "Child Profile: $childName\n\n"
        . "You can now login and book therapy sessions.";

    return sendSpeechcareEmail($parentEmail, $parentName, $title, $html, $text);
}

/*2. Admin New Registration Email*/

function sendAdminNewRegistrationEmail($parentName, $parentEmail, $phone, $childName, $childAge, $therapyConcern) {
    $title = 'New Parent Registration';
    $preheader = 'A new parent has registered on SpeechCare.';

    $body = '
        <p>A new parent has registered on the SpeechCare system.</p>

        <div style="background:#F7FBFF;border:1px solid #DDEEFF;border-radius:14px;padding:16px;margin-top:18px;">
            <p><strong>Parent Name:</strong> ' . e($parentName) . '</p>
            <p><strong>Email:</strong> ' . e($parentEmail) . '</p>
            <p><strong>Phone:</strong> ' . e($phone) . '</p>
            <p><strong>Child Name:</strong> ' . e($childName) . '</p>
            <p><strong>Child Age:</strong> ' . e($childAge) . '</p>
            <p><strong>Therapy Concern:</strong> ' . e($therapyConcern ?: 'Not provided') . '</p>
        </div>
    ';

    $html = speechcareEmailLayout($title, $preheader, $body);

    $text = "New Parent Registration\n\n"
        . "Parent: $parentName\n"
        . "Email: $parentEmail\n"
        . "Phone: $phone\n"
        . "Child: $childName\n"
        . "Age: $childAge\n"
        . "Concern: " . ($therapyConcern ?: 'Not provided');

    return sendSpeechcareEmail(ADMIN_EMAIL, ADMIN_NAME, $title, $html, $text);
}

/*3. Parent Booking Pending Email*/

function sendParentBookingPendingEmail($parentEmail, $parentName, $childName, $slotDate, $startTime, $endTime) {
    $title = 'Appointment Request Received';
    $preheader = 'Your appointment request has been submitted and is pending approval.';

    $body = '
        <p>Hello <strong>' . e($parentName) . '</strong>,</p>

        <p>
            Your appointment request has been received successfully and is currently
            <strong style="color:#B7791F;">Pending</strong> admin approval.
        </p>

        <div style="background:#FFF4DE;border:1px solid #F6D58B;border-radius:14px;padding:16px;margin-top:18px;">
            <p><strong>Child:</strong> ' . e($childName) . '</p>
            <p><strong>Date:</strong> ' . e(formatEmailDate($slotDate)) . '</p>
            <p><strong>Time:</strong> ' . e(formatEmailTime($startTime)) . ' - ' . e(formatEmailTime($endTime)) . '</p>
            <p><strong>Status:</strong> Pending</p>
        </div>

        <p>
            You will receive another email once the clinic admin approves or rejects your appointment request.
        </p>
    ';

    $html = speechcareEmailLayout($title, $preheader, $body);

    $text = "Hello $parentName,\n\n"
        . "Your appointment request has been received and is pending approval.\n"
        . "Child: $childName\n"
        . "Date: " . formatEmailDate($slotDate) . "\n"
        . "Time: " . formatEmailTime($startTime) . " - " . formatEmailTime($endTime) . "\n"
        . "Status: Pending";

    return sendSpeechcareEmail($parentEmail, $parentName, $title, $html, $text);
}


/*5. Parent Appointment Status Email*/

function sendParentAppointmentStatusEmail($parentEmail, $parentName, $childName, $status, $slotDate, $startTime, $endTime, $adminNote = '') {
    $status = strtolower($status);

    $statusLabels = [
        'approved' => 'Appointment Approved',
        'rejected' => 'Appointment Rejected',
        'completed' => 'Appointment Completed',
        'cancelled' => 'Appointment Cancelled'
    ];

    $statusColors = [
        'approved' => '#2CB67D',
        'rejected' => '#E85C5C',
        'completed' => '#1F7FD1',
        'cancelled' => '#E85C5C'
    ];

    $title = $statusLabels[$status] ?? 'Appointment Status Updated';
    $preheader = 'Your SpeechCare appointment status has been updated.';

    $displayStatus = ucfirst($status);
    $statusColor = $statusColors[$status] ?? '#3998E8';

    $message = '';

    if ($status === 'approved') {
        $message = 'Your appointment request has been approved by the clinic admin.';
    } elseif ($status === 'rejected') {
        $message = 'Your appointment request has been rejected. Please book another available time slot.';
    } elseif ($status === 'completed') {
        $message = 'Your therapy session has been marked as completed. Thank you for using SpeechCare.';
    } elseif ($status === 'cancelled') {
        $message = 'Your appointment has been cancelled. Please book another available time slot.';
    } else {
        $message = 'Your appointment status has been updated.';
    }

    $body = '
        <p>Hello <strong>' . e($parentName) . '</strong>,</p>

        <p>' . e($message) . '</p>

        <div style="background:#F7FBFF;border:1px solid #DDEEFF;border-radius:14px;padding:16px;margin-top:18px;">
            <p><strong>Child:</strong> ' . e($childName) . '</p>
            <p><strong>Date:</strong> ' . e(formatEmailDate($slotDate)) . '</p>
            <p><strong>Time:</strong> ' . e(formatEmailTime($startTime)) . ' - ' . e(formatEmailTime($endTime)) . '</p>
            <p><strong>Status:</strong> 
                <span style="color:' . e($statusColor) . ';font-weight:800;">' . e($displayStatus) . '</span>
            </p>
            <p><strong>Admin Note:</strong> ' . e($adminNote ?: 'No additional note') . '</p>
        </div>
    ';

    $html = speechcareEmailLayout($title, $preheader, $body);

    $text = "Hello $parentName,\n\n"
        . "$message\n\n"
        . "Child: $childName\n"
        . "Date: " . formatEmailDate($slotDate) . "\n"
        . "Time: " . formatEmailTime($startTime) . " - " . formatEmailTime($endTime) . "\n"
        . "Status: $displayStatus\n"
        . "Admin Note: " . ($adminNote ?: 'No additional note');

    return sendSpeechcareEmail($parentEmail, $parentName, $title, $html, $text);
}

/*6. Reminder Email*/
function sendParentAppointmentReminderEmail(
    $parentEmail,
    $parentName,
    $childName,
    $appointmentDate,
    $startTime,
    $endTime,
    $therapistName
) {

    $title = 'Appointment Reminder';
    $preheader = 'Your SpeechCare appointment is scheduled within the next 24 hours.';

    $body = '
        <p>Hello <strong>' . e($parentName) . '</strong>,</p>

        <p>
            This is a friendly reminder that your child has a speech therapy
            appointment scheduled within the next 24 hours.
        </p>

        <div style="background:#E8F4FF;border:1px solid #DDEEFF;border-radius:14px;padding:16px;">
            <p><strong>Child:</strong> ' . e($childName) . '</p>
            <p><strong>Therapist:</strong> ' . e($therapistName) . '</p>
            <p><strong>Date:</strong> ' . e(formatEmailDate($appointmentDate)) . '</p>
            <p><strong>Time:</strong> ' . e(formatEmailTime($startTime)) . ' - ' . e(formatEmailTime($endTime)) . '</p>
        </div>

        <p>
            Please arrive on time for your session.
        </p>
    ';

    $html = speechcareEmailLayout($title, $preheader, $body);

    $text = "Appointment Reminder\n\n"
        . "Child: $childName\n"
        . "Therapist: $therapistName\n"
        . "Date: " . formatEmailDate($appointmentDate) . "\n"
        . "Time: " . formatEmailTime($startTime) . " - " . formatEmailTime($endTime);

    return sendSpeechcareEmail(
        $parentEmail,
        $parentName,
        $title,
        $html,
        $text
    );
}

function sendTherapistWelcomeEmail($email, $name, $token) {

    $link = "http://192.168.8.102/speechcare_demo/set-password.php?token=" . $token;

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('no-reply@speechcare.com', 'SpeechCare');
        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = "Set Your SpeechCare Password";

        $mail->Body = "
            <h2>Welcome to SpeechCare</h2>
            <p>Dear $name,</p>

            <p>Your therapist account is ready.</p>

            <p><a href='$link'>Click here to set your password</a></p>

            <p>This link will expire in 24 hours.</p>
        ";

        $mail->send();

    } catch (Exception $e) {
        error_log($mail->ErrorInfo);
    }
}

function sendTherapistNewAppointmentEmail(
    $therapistEmail,
    $therapistName,
    $childName,
    $appointmentDate,
    $startTime,
    $endTime
) {
    $title = "New Appointment Assigned";
    $preheader = "A new appointment has been assigned to you on SpeechCare.";
    
    $displayStatus = "Pending";
    $statusColor = "#3998E8"; // Neutral info blue

    $body = '
        <p>Dear <strong>' . e($therapistName) . '</strong>,</p>
        <p>A new appointment has been assigned to you. Please login to SpeechCare to view details.</p>

        <div style="background:#F7FBFF;border:1px solid #DDEEFF;border-radius:14px;padding:16px;margin-top:18px;">
            <p><strong>Child:</strong> ' . e($childName) . '</p>
            <p><strong>Date:</strong> ' . e($appointmentDate) . '</p>
            <p><strong>Time:</strong> ' . e($startTime) . ' - ' . e($endTime) . '</p>
            <p><strong>Status:</strong> 
                <span style="color:' . e($statusColor) . ';font-weight:800;">' . e($displayStatus) . '</span>
            </p>
        </div>
    ';

    $html = speechcareEmailLayout($title, $preheader, $body);

    $plainText = "Dear $therapistName,\n\n"
        . "A new appointment has been assigned to you.\n\n"
        . "Child: $childName\n"
        . "Date: $appointmentDate\n"
        . "Time: $startTime - $endTime\n"
        . "Status: $displayStatus\n\n"
        . "Please login to SpeechCare to view details.\n\n"
        . "SpeechCare Team";

    return sendSpeechcareEmail(
        $therapistEmail,
        $therapistName,
        "New Appointment Assigned - SpeechCare",
        $html,
        $plainText
    );
}

function sendTherapistAppointmentApprovedEmail(
    $therapistEmail,
    $therapistName,
    $childName,
    $appointmentDate,
    $startTime,
    $endTime
) {
    $title = "Appointment Approved";
    $preheader = "An assigned appointment has been approved by the administrator.";

    $displayStatus = "Approved";
    $statusColor = "#2CB67D"; // Success green

    $body = '
        <p>Dear <strong>' . e($therapistName) . '</strong>,</p>
        <p>The following appointment has been approved by the administrator. Please prepare for the therapy session.</p>

        <div style="background:#F7FBFF;border:1px solid #DDEEFF;border-radius:14px;padding:16px;margin-top:18px;">
            <p><strong>Child:</strong> ' . e($childName) . '</p>
            <p><strong>Date:</strong> ' . e($appointmentDate) . '</p>
            <p><strong>Time:</strong> ' . e($startTime) . ' - ' . e($endTime) . '</p>
            <p><strong>Status:</strong> 
                <span style="color:' . e($statusColor) . ';font-weight:800;">' . e($displayStatus) . '</span>
            </p>
        </div>
    ';

    $html = speechcareEmailLayout($title, $preheader, $body);

    $plainText = "Dear $therapistName,\n\n"
        . "The following appointment has been approved by the administrator.\n\n"
        . "Child: $childName\n"
        . "Date: $appointmentDate\n"
        . "Time: $startTime - $endTime\n"
        . "Status: $displayStatus\n\n"
        . "Please prepare for the therapy session.\n\n"
        . "SpeechCare Team";

    return sendSpeechcareEmail(
        $therapistEmail,
        $therapistName,
        "Appointment Approved - SpeechCare",
        $html,
        $plainText
    );
}

function sendTherapistAppointmentRejectedEmail(
    $therapistEmail,
    $therapistName,
    $childName,
    $appointmentDate,
    $startTime,
    $endTime,
    $rejectionReason
) {
    $title = "Appointment Rejected";
    $preheader = "An assigned appointment request has been rejected.";

    $displayStatus = "Rejected";
    $statusColor = "#E85C5C"; // Danger red

    $body = '
        <p>Dear <strong>' . e($therapistName) . '</strong>,</p>
        <p>The following appointment request has been rejected by the administrator. This appointment will not proceed.</p>

        <div style="background:#F7FBFF;border:1px solid #DDEEFF;border-radius:14px;padding:16px;margin-top:18px;">
            <p><strong>Child:</strong> ' . e($childName) . '</p>
            <p><strong>Date:</strong> ' . e($appointmentDate) . '</p>
            <p><strong>Time:</strong> ' . e($startTime) . ' - ' . e($endTime) . '</p>
            <p><strong>Status:</strong> 
                <span style="color:' . e($statusColor) . ';font-weight:800;">' . e($displayStatus) . '</span>
            </p>
            <p><strong>Reason:</strong> ' . e($rejectionReason ?: 'No reason provided') . '</p>
        </div>
    ';

    $html = speechcareEmailLayout($title, $preheader, $body);

    $plainText = "Dear $therapistName,\n\n"
        . "The following appointment request has been rejected by the administrator.\n\n"
        . "Child: $childName\n"
        . "Date: $appointmentDate\n"
        . "Time: $startTime - $endTime\n"
        . "Reason: $rejectionReason\n\n"
        . "This appointment will not proceed.\n\n"
        . "SpeechCare Team";

    return sendSpeechcareEmail(
        $therapistEmail,
        $therapistName,
        "Appointment Rejected - SpeechCare",
        $html,
        $plainText
    );
}

function sendTherapistAppointmentRescheduledEmail(
    $therapistEmail,
    $therapistName,
    $childName,
    $oldDate,
    $oldStartTime,
    $oldEndTime,
    $newDate,
    $newStartTime,
    $newEndTime,
    $rescheduleReason
) {
    $title = "Appointment Rescheduled";
    $preheader = "An appointment assigned to you has been rescheduled.";

    $body = '
        <p>Dear <strong>' . e($therapistName) . '</strong>,</p>
        <p>An appointment assigned to you has been rescheduled. Please review your schedule update below.</p>

        <div style="background:#F7FBFF;border:1px solid #DDEEFF;border-radius:14px;padding:16px;margin-top:18px;">
            <p><strong>Child:</strong> ' . e($childName) . '</p>
            <hr style="border:0;border-top:1px dashed #DDEEFF;margin:12px 0;" />
            <p style="color:#778899;margin-bottom:4px;"><strong>Previous Schedule:</strong></p>
            <p style="margin-left:8px;"><strong>Date:</strong> ' . e(formatEmailDate($oldDate)) . '</p>
            <p style="margin-left:8px;"><strong>Time:</strong> ' . e(formatEmailTime($oldStartTime)) . ' - ' . e(formatEmailTime($oldEndTime)) . '</p>
            <hr style="border:0;border-top:1px dashed #DDEEFF;margin:12px 0;" />
            <p style="color:#0d6efd;margin-bottom:4px;"><strong>New Schedule:</strong></p>
            <p style="margin-left:8px;"><strong>Date:</strong> ' . e(formatEmailDate($newDate)) . '</p>
            <p style="margin-left:8px;"><strong>Time:</strong> ' . e(formatEmailTime($newStartTime)) . ' - ' . e(formatEmailTime($newEndTime)) . '</p>
            <hr style="border:0;border-top:1px dashed #DDEEFF;margin:12px 0;" />
            <p><strong>Reason:</strong> ' . e($rescheduleReason) . '</p>
        </div>
    ';

    $html = speechcareEmailLayout($title, $preheader, $body);

    $plainText = "Dear $therapistName,\n\n"
        . "An appointment assigned to you has been rescheduled.\n\n"
        . "Child: $childName\n\n"
        . "Previous Schedule:\n"
        . "Date: $oldDate\n"
        . "Time: $oldStartTime - $oldEndTime\n\n"
        . "New Schedule:\n"
        . "Date: $newDate\n"
        . "Time: $newStartTime - $newEndTime\n\n"
        . "Reason: $rescheduleReason\n\n"
        . "Please review your schedule.\n\n"
        . "SpeechCare Team";

    return sendSpeechcareEmail(
        $therapistEmail,
        $therapistName,
        "Appointment Rescheduled - SpeechCare",
        $html,
        $plainText
    );
}

function sendAdminLeaveRequestNotification(
    $therapistName,
    $leaveDate,
    $leaveType,
    $reason
) {
    $title = "New Leave Request";
    $preheader = "A therapist has submitted a leave request on SpeechCare.";

    $body = '
        <p>Hello Administrator,</p>
        <p>A therapist has submitted a leave request. Please login to SpeechCare to review and manage the request.</p>

        <div style="background:#F7FBFF;border:1px solid #DDEEFF;border-radius:14px;padding:16px;margin-top:18px;">
            <p><strong>Therapist:</strong> ' . e($therapistName) . '</p>
            <p><strong>Date:</strong> ' . e($leaveDate) . '</p>
            <p><strong>Leave Type:</strong> ' . e($leaveType) . '</p>
            <p><strong>Reason:</strong> ' . e($reason) . '</p>
        </div>
    ';

    $html = speechcareEmailLayout($title, $preheader, $body);

    $plainText = "New Leave Request\n\n"
        . "Therapist: $therapistName\n"
        . "Date: $leaveDate\n"
        . "Type: $leaveType\n"
        . "Reason: $reason\n\n"
        . "Please login to SpeechCare and review the request.";

    return sendSpeechcareEmail(
        ADMIN_EMAIL,
        'Administrator',
        "New Leave Request - SpeechCare",
        $html,
        $plainText
    );
}

function sendLeaveApprovedEmail(
    $therapistEmail,
    $therapistName,
    $leaveDate,
    $leaveType
) {
    $title = "Leave Request Approved";
    $preheader = "Your leave request has been approved.";

    $displayStatus = "Approved";
    $statusColor = "#2CB67D"; // Success green

    $body = '
        <p>Dear <strong>' . e($therapistName) . '</strong>,</p>
        <p>Your leave request has been approved by the clinic admin.</p>

        <div style="background:#F7FBFF;border:1px solid #DDEEFF;border-radius:14px;padding:16px;margin-top:18px;">
            <p><strong>Date:</strong> ' . e($leaveDate) . '</p>
            <p><strong>Type:</strong> ' . e($leaveType) . '</p>
            <p><strong>Status:</strong> 
                <span style="color:' . e($statusColor) . ';font-weight:800;">' . e($displayStatus) . '</span>
            </p>
        </div>
    ';

    $html = speechcareEmailLayout($title, $preheader, $body);

    $plainText = "Dear $therapistName,\n\n"
        . "Your leave request has been approved.\n\n"
        . "Date: $leaveDate\n"
        . "Type: $leaveType\n\n"
        . "SpeechCare Team";

    return sendSpeechcareEmail(
        $therapistEmail,
        $therapistName,
        "Leave Request Approved - SpeechCare",
        $html,
        $plainText
    );
}

function sendLeaveRejectedEmail(
    $therapistEmail,
    $therapistName,
    $leaveDate,
    $leaveType,
    $adminNote
) {
    $title = "Leave Request Rejected";
    $preheader = "Your leave request has been rejected.";

    $displayStatus = "Rejected";
    $statusColor = "#E85C5C"; // Danger red

    $body = '
        <p>Dear <strong>' . e($therapistName) . '</strong>,</p>
        <p>Your leave request has been reviewed and rejected.</p>

        <div style="background:#F7FBFF;border:1px solid #DDEEFF;border-radius:14px;padding:16px;margin-top:18px;">
            <p><strong>Date:</strong> ' . e($leaveDate) . '</p>
            <p><strong>Type:</strong> ' . e($leaveType) . '</p>
            <p><strong>Status:</strong> 
                <span style="color:' . e($statusColor) . ';font-weight:800;">' . e($displayStatus) . '</span>
            </p>
            <p><strong>Admin Note:</strong> ' . e($adminNote ?: 'No additional note') . '</p>
        </div>
    ';

    $html = speechcareEmailLayout($title, $preheader, $body);

    $plainText = "Dear $therapistName,\n\n"
        . "Your leave request has been rejected.\n\n"
        . "Date: $leaveDate\n"
        . "Type: $leaveType\n"
        . "Admin Note: $adminNote\n\n"
        . "SpeechCare Team";

    return sendSpeechcareEmail(
        $therapistEmail,
        $therapistName,
        "Leave Request Rejected - SpeechCare",
        $html,
        $plainText
    );
}


function sendParentNeedsReschedulingEmail(
    $parentEmail,
    $parentName,
    $childName,
    $appointmentDate,
    $startTime,
    $endTime,
    $reason
) {
    $title = "Appointment Needs Rescheduling";
    $preheader = "Your appointment needs to be rescheduled.";

    $displayStatus = "Needs Rescheduling";
    $statusColor = "#B7791F"; // Warning yellow/orange

    $body = '
        <p>Dear <strong>' . e($parentName) . '</strong>,</p>
        <p>Your upcoming appointment needs to be rescheduled because the therapist will be on leave.</p>

        <div style="background:#F7FBFF;border:1px solid #DDEEFF;border-radius:14px;padding:16px;margin-top:18px;">
            <p><strong>Child:</strong> ' . e($childName) . '</p>
            <p><strong>Date:</strong> ' . e(formatEmailDate($appointmentDate)) . '</p>
            <p><strong>Time:</strong> ' . e(formatEmailTime($startTime)) . ' - ' . e(formatEmailTime($endTime)) . '</p>
            <p><strong>Status:</strong> 
                <span style="color:' . e($statusColor) . ';font-weight:800;">' . e($displayStatus) . '</span>
            </p>
            <p><strong>Reason:</strong> ' . e($reason) . '</p>
        </div>
        
        <p>Please log in to your dashboard to reschedule this appointment.</p>
    ';

    $html = speechcareEmailLayout($title, $preheader, $body);

    $plainText = "Dear $parentName,\n\n"
        . "Your upcoming appointment needs to be rescheduled because the therapist will be on leave.\n\n"
        . "Child: $childName\n"
        . "Date: " . formatEmailDate($appointmentDate) . "\n"
        . "Time: " . formatEmailTime($startTime) . " - " . formatEmailTime($endTime) . "\n"
        . "Status: $displayStatus\n"
        . "Reason: $reason\n\n"
        . "Please log in to your dashboard to reschedule this appointment.\n\n"
        . "SpeechCare Team";

    return sendSpeechcareEmail(
        $parentEmail,
        $parentName,
        "Appointment Needs Rescheduling - SpeechCare",
        $html,
        $plainText
    );
}



function sendOtpEmail($email, $name, $otp) {

    $title = "Your SpeechCare OTP";
    $preheader = "Your password reset verification code";

    $body = "
        <p>Hello <strong>$name</strong>,</p>

        <p>Your OTP code for password reset is:</p>

        <div style='font-size:28px;font-weight:800;letter-spacing:3px;color:#3998E8;'>
            $otp
        </div>

        <p>This code will expire in <strong>5 minutes</strong>.</p>
        <p>If you did not request this, ignore this email.</p>
    ";

    $html = speechcareEmailLayout($title, $preheader, $body);

    return sendSpeechcareEmail(
        $email,
        $name,
        $title,
        $html,
        "Your OTP is: $otp"
    );
}


function sendTherapistAppointmentCancelledEmail(
    $therapistEmail,
    $therapistName,
    $childName,
    $appointmentDate,
    $startTime,
    $endTime,
    $reason,
    $cancelledBy
) {
    $title = "Appointment Cancelled";
    $preheader = "An appointment has been cancelled by $cancelledBy.";

    $displayStatus = "Cancelled";
    $statusColor = "#E85C5C"; // Danger red

    $body = '
        <p>Dear <strong>' . e($therapistName) . '</strong>,</p>
        <p>An appointment assigned to you has been cancelled by <strong>' . e($cancelledBy) . '</strong>. This appointment will not proceed.</p>

        <div style="background:#F7FBFF;border:1px solid #DDEEFF;border-radius:14px;padding:16px;margin-top:18px;">
            <p><strong>Child:</strong> ' . e($childName) . '</p>
            <p><strong>Date:</strong> ' . e(formatEmailDate($appointmentDate)) . '</p>
            <p><strong>Time:</strong> ' . e(formatEmailTime($startTime)) . ' - ' . e(formatEmailTime($endTime)) . '</p>
            <p><strong>Status:</strong> 
                <span style="color:' . e($statusColor) . ';font-weight:800;">' . e($displayStatus) . '</span>
            </p>
            <p><strong>Reason:</strong> ' . e($reason) . '</p>
        </div>
    ';

    $html = speechcareEmailLayout($title, $preheader, $body);

    $plainText = "Dear $therapistName,\n\n"
        . "An appointment assigned to you has been cancelled by $cancelledBy.\n\n"
        . "Child: $childName\n"
        . "Date: " . formatEmailDate($appointmentDate) . "\n"
        . "Time: " . formatEmailTime($startTime) . " - " . formatEmailTime($endTime) . "\n"
        . "Status: $displayStatus\n"
        . "Reason: $reason\n\n"
        . "This appointment will not proceed.\n\n"
        . "SpeechCare Team";

    return sendSpeechcareEmail(
        $therapistEmail,
        $therapistName,
        "Appointment Cancelled - SpeechCare",
        $html,
        $plainText
    );
}

function sendTherapistAppointmentReminderEmail(
    $therapistEmail,
    $therapistName,
    $childName,
    $appointmentDate,
    $startTime,
    $endTime
) {

    $title = 'Upcoming Appointment Reminder';
    $preheader = 'You have an upcoming therapy appointment scheduled.';

    $body = '
        <p>Hello <strong>' . e($therapistName) . '</strong>,</p>

        <p>
            This is a reminder about an upcoming speech therapy appointment.
        </p>

        <div style="background:#E8F4FF;border:1px solid #DDEEFF;border-radius:14px;padding:16px;">
            <p><strong>Child:</strong> ' . e($childName) . '</p>
            <p><strong>Date:</strong> ' . e(formatEmailDate($appointmentDate)) . '</p>
            <p><strong>Time:</strong> ' . e(formatEmailTime($startTime)) . ' - ' . e(formatEmailTime($endTime)) . '</p>
        </div>

        <p>
            Please ensure you are available for this session.
        </p>
    ';

    $html = speechcareEmailLayout($title, $preheader, $body);

    $text = "Upcoming Appointment Reminder\n\n"
        . "Child: $childName\n"
        . "Date: " . formatEmailDate($appointmentDate) . "\n"
        . "Time: " . formatEmailTime($startTime) . " - " . formatEmailTime($endTime);

    return sendSpeechcareEmail(
        $therapistEmail,
        $therapistName,
        $title,
        $html,
        $text
    );
}

?>