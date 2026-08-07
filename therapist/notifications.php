<?php
include "../includes/auth_therapist.php";
include "../includes/functions.php";

$notification_user_type = "therapist";
$notification_user_id = $_SESSION["therapist_id"];

$panel_name = "Therapist Panel";
$sidebar_class = "therapist-sidebar";

$sidebar_links = [
    [
        "href" => "dashboard.php",
        "icon" => "bi-speedometer2",
        "label" => "Dashboard",
        "active" => false
    ],
    [
        "href" => "manage-availability.php",
        "icon" => "bi-calendar-week",
        "label" => "Manage Availability",
        "active" => false
    ],
    [
        "href" => "apply-leave.php",
        "icon" => "bi-calendar-x",
        "label" => "Apply Leave",
        "active" => false
    ],
    [
        "href" => "appointments.php",
        "icon" => "bi-calendar-check",
        "label" => "My Appointments",
        "active" => false
    ],
    [
        "href" => "notifications.php",
        "icon" => "bi-bell",
        "label" => "Notifications",
        "active" => true
    ],
    [
        "href" => "profile.php",
        "icon" => "bi-person-circle",
        "label" => "Profile",
        "active" => false
    ],
    [
        "href" => "../logout.php",
        "icon" => "bi-box-arrow-right",
        "label" => "Logout",
        "active" => false
    ]
];

include "../includes/notifications_page.php";
?>