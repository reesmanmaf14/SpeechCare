<?php
include "../includes/auth_admin.php";
include "../includes/functions.php";

$notification_user_type = "admin";
$notification_user_id = $_SESSION["admin_id"];

$panel_name = "Admin Panel";
$sidebar_class = "admin-sidebar";

$sidebar_links = [
    [
        "href" => "dashboard.php",
        "icon" => "bi-speedometer2",
        "label" => "Dashboard",
        "active" => false
    ],
    [
        "href" => "manage-therapists.php",
        "icon" => "bi-person-badge",
        "label" => "Manage Therapists",
        "active" => false
    ],
    [
        "href" => "manage-parents.php",
        "icon" => "bi-people",
        "label" => "Manage Parents",
        "active" => false
    ],
    [
        "href" => "manage-appointments.php",
        "icon" => "bi-calendar-check",
        "label" => "Manage Appointments",
        "active" => false
    ],
    [
        "href" => "manage-leave-requests.php",
        "icon" => "bi-calendar-x",
        "label" => "Leave Requests",
        "active" => false
    ],
    [
        "href" => "reports.php",
        "icon" => "bi-bar-chart",
        "label" => "Reports",
        "active" => false
    ],
    [
        "href" => "notifications.php",
        "icon" => "bi-bell",
        "label" => "Notifications",
        "active" => true
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