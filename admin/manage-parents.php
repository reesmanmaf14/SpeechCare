<?php
include "../includes/auth_admin.php";
include "../includes/functions.php";

$success = "";
$error = "";

$allowed_filters = ["all", "active", "blocked"];

/*
|--------------------------------------------------------------------------
| Parent status badge class
|--------------------------------------------------------------------------
*/
function parentStatusClass($status) {
    if ($status === "active") {
        return "status-active";
    }

    if ($status === "blocked") {
        return "status-blocked";
    }

    return "status-blocked";
}

/*
|--------------------------------------------------------------------------
| BLOCK / UNBLOCK PARENT
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {
    $parent_id = intval($_POST["parent_id"]);
    $new_status = cleanInput($_POST["new_status"]);

    if ($parent_id <= 0) {
        $error = "Invalid parent selected.";
    } elseif (!in_array($new_status, ["active", "blocked"])) {
        $error = "Invalid status selected.";
    } else {
        $update_sql = "UPDATE parents SET status = ? WHERE parent_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "si", $new_status, $parent_id);

        if (mysqli_stmt_execute($update_stmt)) {
            header("Location: manage-parents.php?success=status_updated");
            exit();
        } else {
            $error = "Failed to update parent status.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| DELETE PARENT
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_parent"])) {
    $parent_id = intval($_POST["parent_id"]);

    if ($parent_id <= 0) {
        $error = "Invalid parent selected.";
    } else {
        /*
            Safety rule:
            Do not delete parent if appointments already exist.
            Use block instead.
        */
        $check_sql = "SELECT appointment_id FROM appointments WHERE parent_id = ? LIMIT 1";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $parent_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $error = "This parent already has appointment records. Please block the account instead of deleting.";
        } else {
            $delete_sql = "DELETE FROM parents WHERE parent_id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_sql);
            mysqli_stmt_bind_param($delete_stmt, "i", $parent_id);

            if (mysqli_stmt_execute($delete_stmt)) {
                header("Location: manage-parents.php?success=deleted");
                exit();
            } else {
                $error = "Failed to delete parent.";
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGES
|--------------------------------------------------------------------------
*/
if (isset($_GET["success"])) {
    if ($_GET["success"] === "status_updated") {
        $success = "Parent account status updated successfully.";
    } elseif ($_GET["success"] === "password_reset") {
        $success = "Parent password reset successfully.";
    } elseif ($_GET["success"] === "deleted") {
        $success = "Parent account deleted successfully.";
    }
}

/*
|--------------------------------------------------------------------------
| SEARCH + FILTER
|--------------------------------------------------------------------------
*/
$search = isset($_GET["search"]) ? cleanInput($_GET["search"]) : "";
$filter = isset($_GET["filter"]) ? cleanInput($_GET["filter"]) : "all";

if (!in_array($filter, $allowed_filters)) {
    $filter = "all";
}

$where_parts = [];
$params = [];
$types = "";

if ($filter !== "all") {
    $where_parts[] = "p.status = ?";
    $params[] = $filter;
    $types .= "s";
}

if (!empty($search)) {
    $where_parts[] = "(
        p.full_name LIKE ?
        OR p.email LIKE ?
        OR p.phone LIKE ?
        OR c.child_name LIKE ?
        OR c.speech_concern LIKE ?
    )";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "sssss";
}

$where_sql = "";

if (count($where_parts) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_parts);
}

/*
|--------------------------------------------------------------------------
| FETCH PARENTS
|--------------------------------------------------------------------------
*/
$parents_sql = "SELECT 
                    p.parent_id,
                    p.full_name,
                    p.email,
                    p.phone,
                    p.address,
                    p.status,
                    p.created_at,

                    c.child_id,
                    c.child_name,
                    c.age,
                    c.gender,
                    c.speech_concern,
                    c.previous_therapy_history,
                    c.additional_notes,

                    COUNT(a.appointment_id) AS appointment_count
                FROM parents p
                LEFT JOIN children c ON p.parent_id = c.parent_id
                LEFT JOIN appointments a ON p.parent_id = a.parent_id
                $where_sql
                GROUP BY 
                    p.parent_id,
                    c.child_id
                ORDER BY p.created_at DESC";

$parents_stmt = mysqli_prepare($conn, $parents_sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($parents_stmt, $types, ...$params);
}

mysqli_stmt_execute($parents_stmt);
$parents_result = mysqli_stmt_get_result($parents_stmt);

/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/
$total_parents = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM parents"))["total"];
$active_parents = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM parents WHERE status = 'active'"))["total"];
$blocked_parents = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM parents WHERE status = 'blocked'"))["total"];
$total_children = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM children"))["total"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Parents - SpeechCare</title>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Same dashboard design -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <style>
        .family-main-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .family-avatar {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: var(--primary-blue-light);
            color: var(--primary-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            flex-shrink: 0;
        }

        .parent-name {
            font-weight: 800;
            color: var(--dark-text);
        }

        .parent-email {
            font-size: 13px;
            color: var(--gray-text);
        }

        .status-pill {
            border-radius: 999px;
            padding: 7px 14px;
            font-size: 13px;
            font-weight: 800;
            display: inline-block;
        }

        .status-active {
            background: #E6F8F0;
            color: var(--success);
        }

        .status-blocked {
            background: #FFECEC;
            color: var(--danger);
        }

        .small-action-btn {
            border: none;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 800;
            transition: 0.2s ease;
        }

        .btn-view,
        .btn-reset {
            background: var(--primary-blue-light);
            color: var(--primary-blue);
        }

        .btn-view:hover,
        .btn-reset:hover {
            background: var(--primary-blue);
            color: #ffffff;
        }

        .btn-block {
            background: #FFF4DE;
            color: #B7791F;
        }

        .btn-block:hover {
            background: #F6B23D;
            color: #ffffff;
        }

        .btn-unblock {
            background: #E6F8F0;
            color: var(--success);
        }

        .btn-unblock:hover {
            background: var(--success);
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

        .search-box input {
            min-width: 280px;
            border: 1px solid var(--light-border);
            border-radius: 12px;
            padding: 11px 14px;
        }

        .search-box input:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.2rem rgba(57, 152, 232, 0.15);
            outline: none;
        }

        .detail-box {
            background: #F7FBFF;
            border: 1px solid var(--light-border);
            border-radius: 16px;
            padding: 16px;
            height: 100%;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            border-bottom: 1px solid var(--light-border);
            padding: 10px 0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--gray-text);
            font-weight: 600;
        }

        .detail-value {
            color: var(--dark-text);
            font-weight: 800;
            text-align: right;
        }

        .note-box {
            background: #ffffff;
            border: 1px solid var(--light-border);
            border-radius: 14px;
            padding: 14px;
            color: var(--gray-text);
            line-height: 1.7;
            min-height: 80px;
        }

        .form-control {
            border: 1px solid var(--light-border);
            border-radius: 14px;
            min-height: 50px;
            padding: 11px 14px;
        }

        @media (max-width: 576px) {
            .search-box input {
                min-width: 100%;
            }

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
        <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="manage-therapists.php"><i class="bi bi-person-badge"></i> Manage Therapists</a>
        <a href="manage-parents.php" class="active"><i class="bi bi-people"></i> Manage Parents</a>
        <a href="manage-appointments.php"><i class="bi bi-calendar-check"></i> Manage Appointments</a>
        <a href="manage-leave-requests.php"><i class="bi bi-calendar-x"></i> Leave Requests</a>
        <a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
        <a href="notifications.php"><i class="bi bi-bell"></i> Notifications</a>
        <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>

<div class="dashboard-wrapper">

    <!-- Sidebar -->
    <aside class="sidebar admin-sidebar">
        <div class="sidebar-logo">SpeechCare</div>
        <div class="sidebar-subtitle">Admin Panel</div>

        <nav class="sidebar-menu">
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="manage-therapists.php"><i class="bi bi-person-badge"></i> Manage Therapists</a>
            <a href="manage-parents.php" class="active"><i class="bi bi-people"></i> Manage Parents</a>
            <a href="manage-appointments.php"><i class="bi bi-calendar-check"></i> Manage Appointments</a>
            <a href="manage-leave-requests.php"><i class="bi bi-calendar-x"></i> Leave Requests</a>
            <a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
            <a href="notifications.php"><i class="bi bi-bell"></i> Notifications</a>
            <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="page-title">Manage Parents</h1>
                <p class="page-subtitle">View registered families and manage parent account access.</p>
            </div>
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
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-people"></i></div>
                    <div class="stat-number"><?php echo $total_parents; ?></div>
                    <div class="stat-label">Total Parents</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-person-check"></i></div>
                    <div class="stat-number"><?php echo $active_parents; ?></div>
                    <div class="stat-label">Active Parents</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-person-x"></i></div>
                    <div class="stat-number"><?php echo $blocked_parents; ?></div>
                    <div class="stat-label">Blocked Parents</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-person-hearts"></i></div>
                    <div class="stat-number"><?php echo $total_children; ?></div>
                    <div class="stat-label">Registered Children</div>
                </div>
            </div>
        </div>

        <!-- Parent Table -->
        <div class="content-card">

            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="fw-bold mb-1">Registered Families</h4>
                    <p class="text-muted small mb-0">
                        Search, view details, block/unblock, or reset parent accounts.
                    </p>
                </div>

                <form method="GET" action="" class="search-box d-flex gap-2 flex-wrap">
                    <input 
                        type="text" 
                        name="search" 
                        value="<?php echo escapeOutput($search); ?>" 
                        placeholder="Search parent or child..."
                    >

                    <input type="hidden" name="filter" value="<?php echo escapeOutput($filter); ?>">

                    <button type="submit" class="action-btn-primary">
                        <i class="bi bi-search me-1"></i>
                        Search
                    </button>

                    <?php if (!empty($search)) { ?>
                        <a href="manage-parents.php?filter=<?php echo escapeOutput($filter); ?>" class="btn btn-light rounded-3 fw-bold">
                            Clear
                        </a>
                    <?php } ?>
                </form>
            </div>

            <div class="d-flex justify-content-end mb-4">
                <div class="filter-tabs">
                    <a href="manage-parents.php?filter=all" class="<?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                    <a href="manage-parents.php?filter=active" class="<?php echo $filter === 'active' ? 'active' : ''; ?>">Active</a>
                    <a href="manage-parents.php?filter=blocked" class="<?php echo $filter === 'blocked' ? 'active' : ''; ?>">Blocked</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Parent</th>
                            <th>Phone</th>
                            <th>Child</th>
                            <th>Age</th>
                            <th>Speech Concern</th>
                            <th>Appointments</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (mysqli_num_rows($parents_result) > 0) { ?>
                            <?php while ($parent = mysqli_fetch_assoc($parents_result)) { ?>
                                <?php
                                    $initial = strtoupper(substr($parent["full_name"], 0, 1));
                                    $view_modal_id = "viewParentModal" . $parent["parent_id"];
                                    $password_modal_id = "resetParentPasswordModal" . $parent["parent_id"];
                                ?>

                                <tr>
                                    <td>
                                        <div class="family-main-cell">
                                            <div class="family-avatar">
                                                <?php echo escapeOutput($initial); ?>
                                            </div>

                                            <div>
                                                <div class="parent-name">
                                                    <?php echo escapeOutput($parent["full_name"]); ?>
                                                </div>
                                                <div class="parent-email">
                                                    <?php echo escapeOutput($parent["email"]); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td><?php echo escapeOutput($parent["phone"]); ?></td>

                                    <td>
                                        <strong><?php echo escapeOutput($parent["child_name"] ?: "No child added"); ?></strong>
                                    </td>

                                    <td><?php echo escapeOutput($parent["age"] ?: "-"); ?></td>

                                    <td style="min-width:180px;">
                                        <?php echo escapeOutput($parent["speech_concern"] ?: "Not added"); ?>
                                    </td>

                                    <td><?php echo escapeOutput($parent["appointment_count"]); ?></td>

                                    <td>
                                        <span class="status-pill <?php echo parentStatusClass($parent["status"]); ?>">
                                            <?php echo displayStatusText($parent["status"]); ?>
                                        </span>
                                    </td>

                                    <td><?php echo formatDateNice($parent["created_at"]); ?></td>

                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2 flex-wrap">

                                            <button 
                                                type="button" 
                                                class="small-action-btn btn-view"
                                                data-bs-toggle="modal"
                                                data-bs-target="#<?php echo $view_modal_id; ?>"
                                            >
                                                View
                                            </button>

                                          
                                            <?php if ($parent["status"] === "active") { ?>
                                                <form method="POST" action="" onsubmit="return confirm('Block this parent account?');">
                                                    <input type="hidden" name="parent_id" value="<?php echo $parent["parent_id"]; ?>">
                                                    <input type="hidden" name="new_status" value="blocked">

                                                    <button type="submit" name="update_status" class="small-action-btn btn-block">
                                                        Block
                                                    </button>
                                                </form>
                                            <?php } else { ?>
                                                <form method="POST" action="" onsubmit="return confirm('Unblock this parent account?');">
                                                    <input type="hidden" name="parent_id" value="<?php echo $parent["parent_id"]; ?>">
                                                    <input type="hidden" name="new_status" value="active">

                                                    <button type="submit" name="update_status" class="small-action-btn btn-unblock">
                                                        Unblock
                                                    </button>
                                                </form>
                                            <?php } ?>

                                            <form method="POST" action="" onsubmit="return confirm('Delete this parent? This is allowed only if there are no appointments.');">
                                                <input type="hidden" name="parent_id" value="<?php echo $parent["parent_id"]; ?>">

                                                <button type="submit" name="delete_parent" class="small-action-btn btn-delete">
                                                    Delete
                                                </button>
                                            </form>

                                        </div>
                                    </td>
                                </tr>

                                <!-- View Parent Modal -->
                                <div class="modal fade" id="<?php echo $view_modal_id; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered modal-xl">
                                        <div class="modal-content">

                                            <div class="modal-header">
                                                <div>
                                                    <h5 class="modal-title">Family Details</h5>
                                                    <p class="text-muted small mb-0">
                                                        Parent account and child therapy information.
                                                    </p>
                                                </div>

                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>

                                            <div class="modal-body">

                                                <div class="row g-4 mb-4">
                                                    <div class="col-md-6">
                                                        <div class="detail-box">
                                                            <h5 class="fw-bold mb-3">Parent Details</h5>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Name</span>
                                                                <span class="detail-value"><?php echo escapeOutput($parent["full_name"]); ?></span>
                                                            </div>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Email</span>
                                                                <span class="detail-value"><?php echo escapeOutput($parent["email"]); ?></span>
                                                            </div>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Phone</span>
                                                                <span class="detail-value"><?php echo escapeOutput($parent["phone"]); ?></span>
                                                            </div>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Status</span>
                                                                <span class="detail-value"><?php echo displayStatusText($parent["status"]); ?></span>
                                                            </div>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Appointments</span>
                                                                <span class="detail-value"><?php echo escapeOutput($parent["appointment_count"]); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <div class="detail-box">
                                                            <h5 class="fw-bold mb-3">Child Details</h5>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Name</span>
                                                                <span class="detail-value"><?php echo escapeOutput($parent["child_name"] ?: "No child added"); ?></span>
                                                            </div>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Age</span>
                                                                <span class="detail-value"><?php echo escapeOutput($parent["age"] ?: "-"); ?></span>
                                                            </div>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Gender</span>
                                                                <span class="detail-value"><?php echo !empty($parent["gender"]) ? displayStatusText($parent["gender"]) : "-"; ?></span>
                                                            </div>

                                                            <div class="detail-row">
                                                                <span class="detail-label">Registered</span>
                                                                <span class="detail-value"><?php echo formatDateNice($parent["created_at"]); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row g-4">
                                                    <div class="col-md-6">
                                                        <h5 class="fw-bold mb-3">Address</h5>
                                                        <div class="note-box">
                                                            <?php echo !empty($parent["address"]) ? escapeOutput($parent["address"]) : "No address added."; ?>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <h5 class="fw-bold mb-3">Speech Concern</h5>
                                                        <div class="note-box">
                                                            <?php echo !empty($parent["speech_concern"]) ? escapeOutput($parent["speech_concern"]) : "No speech concern added."; ?>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <h5 class="fw-bold mb-3">Previous Therapy History</h5>
                                                        <div class="note-box">
                                                            <?php echo !empty($parent["previous_therapy_history"]) ? escapeOutput($parent["previous_therapy_history"]) : "No previous therapy history."; ?>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <h5 class="fw-bold mb-3">Additional Notes</h5>
                                                        <div class="note-box">
                                                            <?php echo !empty($parent["additional_notes"]) ? escapeOutput($parent["additional_notes"]) : "No additional notes."; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>

                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">
                                                    Close
                                                </button>
                                            </div>

                                        </div>
                                    </div>
                                </div>

                                <!-- Reset Password Modal -->
                               

                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i class="bi bi-people"></i>
                                        <h5 class="fw-bold">No parents found</h5>
                                        <p class="mb-0">Registered parent accounts will appear here.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>

                </table>
            </div>

        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>