<?php
include "../includes/auth_admin.php";
include "../includes/functions.php";
include "../includes/email-functions.php";

$success = "";
$error = "";

/*
|--------------------------------------------------------------------------
| ADD THERAPIST
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_therapist"])) {
    $full_name = cleanInput($_POST["full_name"]);
    $email = cleanInput($_POST["email"]);
    $phone = cleanInput($_POST["phone"]);
    $qualification = cleanInput($_POST["qualification"]);
    $specialization = cleanInput($_POST["specialization"]);
    $experience = cleanInput($_POST["experience"]);
   

    if (
        empty($full_name) ||
        empty($email) ||
        empty($phone) ||
        empty($qualification) ||
        empty($specialization) 
       
    ) {
        $error = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $check_sql = "SELECT therapist_id FROM therapists WHERE email = ? LIMIT 1";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $email);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $error = "This therapist email already exists.";
        } else {
           

            $insert_sql = "INSERT INTO therapists
                            (full_name, email, phone, qualification, specialization, experience, password, status)
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";

            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param(
                $insert_stmt,
                "sssssss",
                $full_name,
                $email,
                $phone,
                $qualification,
                $specialization,
                $experience,
                $hashed_password
            );

           if (mysqli_stmt_execute($insert_stmt)) {

             // Generate secure token
         $therapist_id = mysqli_insert_id($conn);

// 1. Generate token
$token = bin2hex(random_bytes(32));
$expiry = date("Y-m-d H:i:s", strtotime("+1 day"));

// 2. Save token in DB
$update_token_sql = "UPDATE therapists 
                     SET reset_token = ?, token_expiry = ? 
                     WHERE therapist_id = ?";

$token_stmt = mysqli_prepare($conn, $update_token_sql);
mysqli_stmt_bind_param($token_stmt, "ssi", $token, $expiry, $therapist_id);
mysqli_stmt_execute($token_stmt);
    sendTherapistWelcomeEmail(
        $email,
        $full_name,
        $token
    );

    header("Location: manage-therapists.php?success=added");
    exit();

            } else {
                $error = "Failed to create therapist account.";
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| EDIT THERAPIST
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_therapist"])) {
    $therapist_id = intval($_POST["therapist_id"]);
    $full_name = cleanInput($_POST["full_name"]);
    $email = cleanInput($_POST["email"]);
    $phone = cleanInput($_POST["phone"]);
    $qualification = cleanInput($_POST["qualification"]);
    $specialization = cleanInput($_POST["specialization"]);
    $experience = cleanInput($_POST["experience"]);

    if ($therapist_id <= 0) {
        $error = "Invalid therapist selected.";
    } elseif (empty($full_name) || empty($email) || empty($phone) || empty($qualification) || empty($specialization)) {
        $error = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $check_sql = "SELECT therapist_id FROM therapists WHERE email = ? AND therapist_id != ? LIMIT 1";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "si", $email, $therapist_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $error = "Another therapist already uses this email.";
        } else {
            $update_sql = "UPDATE therapists
                           SET full_name = ?, email = ?, phone = ?, qualification = ?, specialization = ?, experience = ?
                           WHERE therapist_id = ?";

            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param(
                $update_stmt,
                "ssssssi",
                $full_name,
                $email,
                $phone,
                $qualification,
                $specialization,
                $experience,
                $therapist_id
            );

            if (mysqli_stmt_execute($update_stmt)) {
                header("Location: manage-therapists.php?success=updated");
                exit();
            } else {
                $error = "Failed to update therapist.";
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| ACTIVATE / DEACTIVATE THERAPIST
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {
    $therapist_id = intval($_POST["therapist_id"]);
    $new_status = cleanInput($_POST["new_status"]);

    if ($therapist_id <= 0) {
        $error = "Invalid therapist selected.";
    } elseif (!in_array($new_status, ["active", "inactive"])) {
        $error = "Invalid therapist status.";
    } else {
        $update_sql = "UPDATE therapists SET status = ? WHERE therapist_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "si", $new_status, $therapist_id);

        if (mysqli_stmt_execute($update_stmt)) {
            header("Location: manage-therapists.php?success=status_updated");
            exit();
        } else {
            $error = "Failed to update therapist status.";
        }
    }
}



/*
|--------------------------------------------------------------------------
| DELETE THERAPIST
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_therapist"])) {
    $therapist_id = intval($_POST["therapist_id"]);

    if ($therapist_id <= 0) {
        $error = "Invalid therapist selected.";
    } else {
        $check_sql = "SELECT appointment_id FROM appointments WHERE therapist_id = ? LIMIT 1";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $therapist_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $error = "This therapist already has appointments. Please deactivate instead of deleting.";
        } else {
            $delete_sql = "DELETE FROM therapists WHERE therapist_id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_sql);
            mysqli_stmt_bind_param($delete_stmt, "i", $therapist_id);

            if (mysqli_stmt_execute($delete_stmt)) {
                header("Location: manage-therapists.php?success=deleted");
                exit();
            } else {
                $error = "Failed to delete therapist.";
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
    if ($_GET["success"] === "added") {
        $success = "Therapist account created successfully.";
    } elseif ($_GET["success"] === "updated") {
        $success = "Therapist details updated successfully.";
    } elseif ($_GET["success"] === "status_updated") {
        $success = "Therapist status updated successfully.";
    } elseif ($_GET["success"] === "password_reset") {
        $success = "Therapist password reset successfully.";
    } elseif ($_GET["success"] === "deleted") {
        $success = "Therapist deleted successfully.";
    }
}

/*
|--------------------------------------------------------------------------
| SEARCH + FILTER
|--------------------------------------------------------------------------
*/
$search = isset($_GET["search"]) ? cleanInput($_GET["search"]) : "";
$filter = isset($_GET["filter"]) ? cleanInput($_GET["filter"]) : "all";

if (!in_array($filter, ["all", "active", "inactive"])) {
    $filter = "all";
}

$where_parts = [];
$params = [];
$types = "";

if ($filter !== "all") {
    $where_parts[] = "status = ?";
    $params[] = $filter;
    $types .= "s";
}

if (!empty($search)) {
    $where_parts[] = "(full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR qualification LIKE ? OR specialization LIKE ?)";
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

$therapists_sql = "SELECT 
                    therapist_id,
                    full_name,
                    email,
                    phone,
                    qualification,
                    specialization,
                    experience,
                    status,
                    created_at
                  FROM therapists
                  $where_sql
                  ORDER BY created_at DESC";

$therapists_stmt = mysqli_prepare($conn, $therapists_sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($therapists_stmt, $types, ...$params);
}

mysqli_stmt_execute($therapists_stmt);
$therapists_result = mysqli_stmt_get_result($therapists_stmt);

$total_therapists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM therapists"))["total"];
$active_therapists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM therapists WHERE status = 'active'"))["total"];
$inactive_therapists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM therapists WHERE status = 'inactive'"))["total"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Therapists - SpeechCare</title>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Same dashboard design -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <style>
        .therapist-main-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .therapist-avatar {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: var(--primary-blue-light);
            color: var(--primary-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            flex-shrink: 0;
        }

        .therapist-name {
            font-weight: 800;
            color: var(--dark-text);
        }

        .therapist-email {
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

        .status-inactive {
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

        .btn-edit {
            background: var(--primary-blue-light);
            color: var(--primary-blue);
        }

        .btn-edit:hover {
            background: var(--primary-blue);
            color: #ffffff;
        }

        .btn-activate {
            background: #E6F8F0;
            color: var(--success);
        }

        .btn-activate:hover {
            background: var(--success);
            color: #ffffff;
        }

        .btn-deactivate {
            background: #FFF4DE;
            color: #B7791F;
        }

        .btn-deactivate:hover {
            background: #F6B23D;
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
        <a href="manage-therapists.php" class="active"><i class="bi bi-person-badge"></i> Manage Therapists</a>
        <a href="manage-parents.php"><i class="bi bi-people"></i> Manage Parents</a>
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
            <a href="manage-therapists.php" class="active"><i class="bi bi-person-badge"></i> Manage Therapists</a>
            <a href="manage-parents.php"><i class="bi bi-people"></i> Manage Parents</a>
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
                <h1 class="page-title">Manage Therapists</h1>
                <p class="page-subtitle">Create and manage therapist login accounts.</p>
            </div>

            <button class="action-btn-primary" data-bs-toggle="modal" data-bs-target="#addTherapistModal">
                <i class="bi bi-plus-circle me-1"></i>
                Add Therapist
            </button>
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
                    <div class="stat-icon"><i class="bi bi-person-badge"></i></div>
                    <div class="stat-number"><?php echo $total_therapists; ?></div>
                    <div class="stat-label">Total Therapists</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                    <div class="stat-number"><?php echo $active_therapists; ?></div>
                    <div class="stat-label">Active Therapists</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-slash-circle"></i></div>
                    <div class="stat-number"><?php echo $inactive_therapists; ?></div>
                    <div class="stat-label">Inactive Therapists</div>
                </div>
            </div>
        </div>

        <div class="content-card">

            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="fw-bold mb-1">Therapist Accounts</h4>
                    <p class="text-muted small mb-0">
                        Therapists created here can login and manage availability.
                    </p>
                </div>

                <form method="GET" action="" class="search-box d-flex gap-2 flex-wrap">
                    <input 
                        type="text" 
                        name="search" 
                        value="<?php echo escapeOutput($search); ?>" 
                        placeholder="Search therapist..."
                    >

                    <input type="hidden" name="filter" value="<?php echo escapeOutput($filter); ?>">

                    <button type="submit" class="action-btn-primary">
                        <i class="bi bi-search me-1"></i>
                        Search
                    </button>

                    <?php if (!empty($search)) { ?>
                        <a href="manage-therapists.php?filter=<?php echo escapeOutput($filter); ?>" class="btn btn-light rounded-3 fw-bold">
                            Clear
                        </a>
                    <?php } ?>
                </form>
            </div>

            <div class="d-flex justify-content-end mb-4">
                <div class="filter-tabs">
                    <a href="manage-therapists.php?filter=all" class="<?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                    <a href="manage-therapists.php?filter=active" class="<?php echo $filter === 'active' ? 'active' : ''; ?>">Active</a>
                    <a href="manage-therapists.php?filter=inactive" class="<?php echo $filter === 'inactive' ? 'active' : ''; ?>">Inactive</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Therapist</th>
                            <th>Phone</th>
                            <th>Qualification</th>
                            <th>Specialization</th>
                            <th>Experience</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (mysqli_num_rows($therapists_result) > 0) { ?>
                            <?php while ($therapist = mysqli_fetch_assoc($therapists_result)) { ?>
                                <?php
                                    $initial = strtoupper(substr($therapist["full_name"], 0, 1));
                                    $edit_modal_id = "editTherapistModal" . $therapist["therapist_id"];
                                    $password_modal_id = "resetPasswordModal" . $therapist["therapist_id"];
                                ?>

                                <tr>
                                    <td>
                                        <div class="therapist-main-cell">
                                            <div class="therapist-avatar">
                                                <?php echo escapeOutput($initial); ?>
                                            </div>

                                            <div>
                                                <div class="therapist-name">
                                                    <?php echo escapeOutput($therapist["full_name"]); ?>
                                                </div>
                                                <div class="therapist-email">
                                                    <?php echo escapeOutput($therapist["email"]); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td><?php echo escapeOutput($therapist["phone"]); ?></td>
                                    <td><?php echo escapeOutput($therapist["qualification"]); ?></td>
                                    <td><?php echo escapeOutput($therapist["specialization"]); ?></td>
                                    <td><?php echo escapeOutput($therapist["experience"] ?: "Not added"); ?></td>

                                    <td>
                                        <?php if ($therapist["status"] === "active") { ?>
                                            <span class="status-pill status-active">Active</span>
                                        <?php } else { ?>
                                            <span class="status-pill status-inactive">Inactive</span>
                                        <?php } ?>
                                    </td>

                                    <td><?php echo formatDateNice($therapist["created_at"]); ?></td>

                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2 flex-wrap">

                                            <button class="small-action-btn btn-edit" data-bs-toggle="modal" data-bs-target="#<?php echo $edit_modal_id; ?>">
                                                Edit
                                            </button>

                                          

                                            <?php if ($therapist["status"] === "active") { ?>
                                                <form method="POST" action="" onsubmit="return confirm('Deactivate this therapist?');">
                                                    <input type="hidden" name="therapist_id" value="<?php echo $therapist["therapist_id"]; ?>">
                                                    <input type="hidden" name="new_status" value="inactive">

                                                    <button type="submit" name="update_status" class="small-action-btn btn-deactivate">
                                                        Deactivate
                                                    </button>
                                                </form>
                                            <?php } else { ?>
                                                <form method="POST" action="" onsubmit="return confirm('Activate this therapist?');">
                                                    <input type="hidden" name="therapist_id" value="<?php echo $therapist["therapist_id"]; ?>">
                                                    <input type="hidden" name="new_status" value="active">

                                                    <button type="submit" name="update_status" class="small-action-btn btn-activate">
                                                        Activate
                                                    </button>
                                                </form>
                                            <?php } ?>

                                            <form method="POST" action="" onsubmit="return confirm('Delete this therapist?');">
                                                <input type="hidden" name="therapist_id" value="<?php echo $therapist["therapist_id"]; ?>">

                                                <button type="submit" name="delete_therapist" class="small-action-btn btn-delete">
                                                    Delete
                                                </button>
                                            </form>

                                        </div>
                                    </td>
                                </tr>

                                <!-- Edit Therapist Modal -->
                                <div class="modal fade" id="<?php echo $edit_modal_id; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <input type="hidden" name="therapist_id" value="<?php echo $therapist["therapist_id"]; ?>">

                                                <div class="modal-header">
                                                    <div>
                                                        <h5 class="modal-title">Edit Therapist</h5>
                                                        <p class="text-muted small mb-0">Update therapist information.</p>
                                                    </div>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>

                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Full Name</label>
                                                        <input type="text" name="full_name" class="form-control" value="<?php echo escapeOutput($therapist["full_name"]); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Email</label>
                                                        <input type="email" name="email" class="form-control" value="<?php echo escapeOutput($therapist["email"]); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Phone</label>
                                                        <input type="text" name="phone" class="form-control" value="<?php echo escapeOutput($therapist["phone"]); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Qualification</label>
                                                        <input type="text" name="qualification" class="form-control" value="<?php echo escapeOutput($therapist["qualification"]); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Specialization</label>
                                                        <input type="text" name="specialization" class="form-control" value="<?php echo escapeOutput($therapist["specialization"]); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Experience</label>
                                                        <input type="text" name="experience" class="form-control" value="<?php echo escapeOutput($therapist["experience"]); ?>">
                                                    </div>
                                                </div>

                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">
                                                        Cancel
                                                    </button>

                                                    <button type="submit" name="edit_therapist" class="action-btn-primary">
                                                        Save Changes
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                

                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="bi bi-person-badge"></i>
                                        <h5 class="fw-bold">No therapists found</h5>
                                        <p class="mb-0">Click Add Therapist to create the first therapist account.</p>
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

<!-- Add Therapist Modal -->
<div class="modal fade" id="addTherapistModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Add Therapist</h5>
                        <p class="text-muted small mb-0">Create a login account for a therapist.</p>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Full Name</label>
                        <input type="text" name="full_name" class="form-control"  required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <input type="email" name="email" class="form-control"  required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Phone</label>
                        <input type="text" name="phone" class="form-control"  required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Qualification</label>
                        <input type="text" name="qualification" class="form-control"  required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Specialization</label>
                        <input type="text" name="specialization" class="form-control"  required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Experience</label>
                        <input type="text" name="experience" class="form-control" >
                    </div>

                 
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" name="add_therapist" class="action-btn-primary">
                        Save Therapist
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>