<?php
if (!isset($notification_user_type) || !isset($notification_user_id)) {
    die("Notification page configuration missing.");
}

$success = "";
$error = "";

$allowed_filter_statuses = ["all", "unread", "read"];

$status_filter = isset($_GET["status"]) ? cleanInput($_GET["status"]) : "all";

if (!in_array($status_filter, $allowed_filter_statuses)) {
    $status_filter = "all";
}

/*
|--------------------------------------------------------------------------
| Mark one notification as read
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["mark_read"])) {
    $notification_id = intval($_POST["notification_id"]);

    if ($notification_id <= 0) {
        $error = "Invalid notification selected.";
    } else {
        $update_sql = "UPDATE notifications
                       SET status = 'read'
                       WHERE notification_id = ?
                       AND user_type = ?
                       AND user_id = ?";

        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param(
            $update_stmt,
            "isi",
            $notification_id,
            $notification_user_type,
            $notification_user_id
        );

        if (mysqli_stmt_execute($update_stmt)) {
            header("Location: notifications.php?success=read");
            exit();
        } else {
            $error = "Failed to update notification.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| Mark all as read
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["mark_all_read"])) {
    $update_sql = "UPDATE notifications
                   SET status = 'read'
                   WHERE user_type = ?
                   AND user_id = ?
                   AND status = 'unread'";

    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param(
        $update_stmt,
        "si",
        $notification_user_type,
        $notification_user_id
    );

    if (mysqli_stmt_execute($update_stmt)) {
        header("Location: notifications.php?success=all_read");
        exit();
    } else {
        $error = "Failed to mark notifications as read.";
    }
}

/*
|--------------------------------------------------------------------------
| Delete notification
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_notification"])) {
    $notification_id = intval($_POST["notification_id"]);

    if ($notification_id <= 0) {
        $error = "Invalid notification selected.";
    } else {
        $delete_sql = "DELETE FROM notifications
                       WHERE notification_id = ?
                       AND user_type = ?
                       AND user_id = ?";

        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param(
            $delete_stmt,
            "isi",
            $notification_id,
            $notification_user_type,
            $notification_user_id
        );

        if (mysqli_stmt_execute($delete_stmt)) {
            header("Location: notifications.php?success=deleted");
            exit();
        } else {
            $error = "Failed to delete notification.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| Success messages
|--------------------------------------------------------------------------
*/
if (isset($_GET["success"])) {
    if ($_GET["success"] === "read") {
        $success = "Notification marked as read.";
    } elseif ($_GET["success"] === "all_read") {
        $success = "All notifications marked as read.";
    } elseif ($_GET["success"] === "deleted") {
        $success = "Notification deleted successfully.";
    }
}

/*
|--------------------------------------------------------------------------
| Fetch notifications
|--------------------------------------------------------------------------
*/
$where_parts = [
    "user_type = ?",
    "user_id = ?"
];

$params = [$notification_user_type, $notification_user_id];
$types = "si";

if ($status_filter !== "all") {
    $where_parts[] = "status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_sql = "WHERE " . implode(" AND ", $where_parts);

$notifications_sql = "SELECT 
                        notification_id,
                        title,
                        message,
                        status,
                        created_at
                      FROM notifications
                      $where_sql
                      ORDER BY created_at DESC";

$notifications_stmt = mysqli_prepare($conn, $notifications_sql);
mysqli_stmt_bind_param($notifications_stmt, $types, ...$params);
mysqli_stmt_execute($notifications_stmt);

$notifications_result = mysqli_stmt_get_result($notifications_stmt);

/*
|--------------------------------------------------------------------------
| Counts
|--------------------------------------------------------------------------
*/
$total_sql = "SELECT COUNT(*) AS total
              FROM notifications
              WHERE user_type = ?
              AND user_id = ?";

$total_stmt = mysqli_prepare($conn, $total_sql);
mysqli_stmt_bind_param($total_stmt, "si", $notification_user_type, $notification_user_id);
mysqli_stmt_execute($total_stmt);
$total_result = mysqli_stmt_get_result($total_stmt);
$total_count = mysqli_fetch_assoc($total_result)["total"];

$unread_sql = "SELECT COUNT(*) AS total
               FROM notifications
               WHERE user_type = ?
               AND user_id = ?
               AND status = 'unread'";

$unread_stmt = mysqli_prepare($conn, $unread_sql);
mysqli_stmt_bind_param($unread_stmt, "si", $notification_user_type, $notification_user_id);
mysqli_stmt_execute($unread_stmt);
$unread_result = mysqli_stmt_get_result($unread_stmt);
$unread_count = mysqli_fetch_assoc($unread_result)["total"];

$read_sql = "SELECT COUNT(*) AS total
             FROM notifications
             WHERE user_type = ?
             AND user_id = ?
             AND status = 'read'";

$read_stmt = mysqli_prepare($conn, $read_sql);
mysqli_stmt_bind_param($read_stmt, "si", $notification_user_type, $notification_user_id);
mysqli_stmt_execute($read_stmt);
$read_result = mysqli_stmt_get_result($read_stmt);
$read_count = mysqli_fetch_assoc($read_result)["total"];

function notificationStatusClass($status) {
    if ($status === "unread") {
        return "badge bg-warning text-dark";
    }

    return "badge bg-secondary";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Notifications - SpeechCare</title>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Same dashboard design -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <style>
         .therapist-sidebar {
            width: 280px;
    min-height: 100vh;
    background: #ffffff;
    border-right: 1px solid #e2e8f0;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.04);
    padding: 24px 18px;
    position: fixed;
    left: 0;
    top: 0;
        }

        .therapist-sidebar .sidebar-logo {
           debar-logo 
    font-family: 'Segoe UI', sans-serif; 
    font-size: 32px;
    font-weight: 600;
    letter-spacing: -1.4px;
    color: #3998E8;
    margin-bottom: 6px;
    line-height: 1;
        }

        .therapist-sidebar .sidebar-subtitle {
           font-size: 13px;
    font-weight: 500;
    color: #666B7A;
    letter-spacing: 0.5px;
    margin-bottom: 34px;
    opacity: 0.85;
    text-transform: uppercase;
        }

        .therapist-sidebar .sidebar-menu a {
             display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 12px;
    color: #666B7A;
    text-decoration: none;
    margin-bottom: 6px;
    font-weight: 600;
    transition: 0.2s ease;
        }

        .therapist-sidebar .sidebar-menu a:hover,
        .therapist-sidebar .sidebar-menu a.active {
            background: #eff6ff;
    color: #3998E8;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.12);
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-tabs a {
            border: 1px solid var(--light-border);
            background: #ffffff;
            color: var(--gray-text);
            border-radius: 999px;
            padding: 9px 16px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            transition: 0.2s ease;
        }

        .filter-tabs a:hover,
        .filter-tabs a.active {
            background: var(--primary-blue-light);
            color: var(--primary-blue);
            border-color: var(--primary-blue);
        }

        .notification-item {
            border: 1px solid var(--light-border);
            border-radius: 18px;
            padding: 18px;
            margin-bottom: 14px;
            background: #ffffff;
            transition: 0.2s ease;
        }

        .notification-item.unread {
            background: #F7FBFF;
            border-color: #CFE8FF;
        }

        .notification-item:hover {
            box-shadow: 0 12px 35px rgba(57, 152, 232, 0.08);
            transform: translateY(-2px);
        }

        .notification-title {
            font-weight: 900;
            color: var(--dark-text);
            margin-bottom: 6px;
        }

        .notification-message {
            color: var(--gray-text);
            line-height: 1.7;
            margin-bottom: 10px;
        }

        .notification-date {
            font-size: 13px;
            color: var(--gray-text);
        }

        .small-action-btn {
            border: none;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 800;
            transition: 0.2s ease;
        }

        .btn-read {
            background: var(--primary-blue-light);
            color: var(--primary-blue);
        }

        .btn-read:hover {
            background: var(--primary-blue);
            color: #ffffff;
        }

        .btn-delete {
            background: #FFECEC;
            color: var(--danger);
        }

        .btn-delete:hover {
            background: var(--danger);
            color: #ffffff;
        }

        @media (max-width: 576px) {
            .small-action-btn {
                width: 100%;
                margin-bottom: 6px;
            }
        }
    </style>
</head>
<body>

<!-- Mobile Navbar -->
<div class="mobile-navbar">
    <div class="sidebar-logo mb-0">SpeechCare</div>
    <button class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu">
        <i class="bi bi-list"></i>
    </button>
</div>

<!-- Mobile Menu -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenu">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title sidebar-logo">SpeechCare</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body sidebar-menu">
        <?php foreach ($sidebar_links as $link) { ?>
            <a href="<?php echo escapeOutput($link["href"]); ?>" class="<?php echo $link["active"] ? "active" : ""; ?>">
                <i class="bi <?php echo escapeOutput($link["icon"]); ?>"></i>
                <?php echo escapeOutput($link["label"]); ?>
            </a>
        <?php } ?>
    </div>
</div>

<div class="dashboard-wrapper">

    <!-- Sidebar -->
    <aside class="sidebar <?php echo escapeOutput($sidebar_class); ?>">
        <div class="sidebar-logo">SpeechCare</div>
        <div class="sidebar-subtitle"><?php echo escapeOutput($panel_name); ?></div>

        <nav class="sidebar-menu">
            <?php foreach ($sidebar_links as $link) { ?>
                <a href="<?php echo escapeOutput($link["href"]); ?>" class="<?php echo $link["active"] ? "active" : ""; ?>">
                    <i class="bi <?php echo escapeOutput($link["icon"]); ?>"></i>
                    <?php echo escapeOutput($link["label"]); ?>
                </a>
            <?php } ?>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="page-title">Notifications</h1>
                <p class="page-subtitle">View updates related to appointments, leave requests, and clinic activity.</p>
            </div>

            <?php if ($unread_count > 0) { ?>
                <form method="POST" action="">
                    <button type="submit" name="mark_all_read" class="action-btn-primary">
                        <i class="bi bi-check2-all me-1"></i>
                        Mark All as Read
                    </button>
                </form>
            <?php } ?>
        </div>

        <?php if (!empty($success)) { ?>
            <div class="alert alert-success border-0 rounded-4 mb-4">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo escapeOutput($success); ?>
            </div>
        <?php } ?>

        <?php if (!empty($error)) { ?>
            <div class="alert alert-danger border-0 rounded-4 mb-4">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo escapeOutput($error); ?>
            </div>
        <?php } ?>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-bell"></i></div>
                    <div class="stat-number"><?php echo $total_count; ?></div>
                    <div class="stat-label">Total Notifications</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-envelope"></i></div>
                    <div class="stat-number"><?php echo $unread_count; ?></div>
                    <div class="stat-label">Unread</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-envelope-open"></i></div>
                    <div class="stat-number"><?php echo $read_count; ?></div>
                    <div class="stat-label">Read</div>
                </div>
            </div>

        </div>

        <!-- Notifications -->
        <div class="content-card">

            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="fw-bold mb-1">Notification Center</h4>
                    <p class="text-muted small mb-0">Unread notifications are highlighted.</p>
                </div>

                <div class="filter-tabs">
                    <a href="notifications.php?status=all" class="<?php echo $status_filter === 'all' ? 'active' : ''; ?>">All</a>
                    <a href="notifications.php?status=unread" class="<?php echo $status_filter === 'unread' ? 'active' : ''; ?>">Unread</a>
                    <a href="notifications.php?status=read" class="<?php echo $status_filter === 'read' ? 'active' : ''; ?>">Read</a>
                </div>
            </div>

            <?php if (mysqli_num_rows($notifications_result) > 0) { ?>
                <?php while ($notification = mysqli_fetch_assoc($notifications_result)) { ?>
                    <div class="notification-item <?php echo $notification["status"] === "unread" ? "unread" : ""; ?>">

                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                            <div style="flex: 1;">
                                <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                                    <div class="notification-title mb-0">
                                        <?php echo escapeOutput($notification["title"]); ?>
                                    </div>

                                    <span class="<?php echo notificationStatusClass($notification["status"]); ?>">
                                        <?php echo displayStatusText($notification["status"]); ?>
                                    </span>
                                </div>

                                <div class="notification-message">
                                    <?php echo escapeOutput($notification["message"]); ?>
                                </div>

                                <div class="notification-date">
                                    <i class="bi bi-clock me-1"></i>
                                    <?php echo date("M d, Y h:i A", strtotime($notification["created_at"])); ?>
                                </div>
                            </div>

                            <div class="d-flex gap-2 flex-wrap">
                                <?php if ($notification["status"] === "unread") { ?>
                                    <form method="POST" action="">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification["notification_id"]; ?>">
                                        <button type="submit" name="mark_read" class="small-action-btn btn-read">
                                            Mark Read
                                        </button>
                                    </form>
                                <?php } ?>

                                <form method="POST" action="" onsubmit="return confirm('Delete this notification?');">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification["notification_id"]; ?>">
                                    <button type="submit" name="delete_notification" class="small-action-btn btn-delete">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>
                <?php } ?>
            <?php } else { ?>
                <div class="empty-state">
                    <i class="bi bi-bell-slash"></i>
                    <h5 class="fw-bold">No notifications found</h5>
                    <p class="mb-0">Your notifications will appear here when there are updates.</p>
                </div>
            <?php } ?>

        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>