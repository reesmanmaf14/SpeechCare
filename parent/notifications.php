<?php
include "../includes/auth_parent.php";
include "../includes/functions.php";

$notification_user_type = "parent";
$notification_user_id = $_SESSION["parent_id"];

$panel_name = "Parent Panel";
$sidebar_class = "";

$sidebar_links = [
    [
        "href" => "dashboard.php",
        "icon" => "bi-house-door",
        "label" => "Dashboard",
        "active" => false
    ],
    [
        "href" => "book-appointment.php",
        "icon" => "bi-calendar-plus",
        "label" => "Book Appointment",
        "active" => false
    ],
    [
        "href" => "my-appointments.php",  
        "icon" => "bi-calendar-check",
        "label" => "My Appointments",
        "active" => false
    ],
     [
        "href" => "progress.php",
        "icon" => "bi-graph-up-arrow",
        "label" => "Progress",
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