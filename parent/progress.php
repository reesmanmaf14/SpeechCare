<?php
include "../includes/auth_parent.php";
include "../includes/functions.php";

$parent_id = $_SESSION["parent_id"];

/*
|--------------------------------------------------------------------------
| FETCH ALL CHILDREN BELONGING TO THIS PARENT
|--------------------------------------------------------------------------
*/
$children_sql  = "SELECT c.child_id, c.child_name
                  FROM children c
                  WHERE c.child_id IN (
                      SELECT DISTINCT child_id FROM appointments WHERE parent_id = ?
                  )
                  ORDER BY c.child_name ASC";
$children_stmt = mysqli_prepare($conn, $children_sql);
mysqli_stmt_bind_param($children_stmt, "i", $parent_id);
mysqli_stmt_execute($children_stmt);
$children_result = mysqli_stmt_get_result($children_stmt);

$children = [];
while ($row = mysqli_fetch_assoc($children_result)) {
    $children[] = $row;
}

// Determine active child — validate GET param belongs to this parent
$active_child_id = null;
if (!empty($children)) {
    $requested_id = isset($_GET["child_id"]) ? intval($_GET["child_id"]) : 0;
    foreach ($children as $c) {
        if ((int)$c["child_id"] === $requested_id) {
            $active_child_id = $requested_id;
            break;
        }
    }
    // Default to first child if none matched
    if ($active_child_id === null) {
        $active_child_id = (int)$children[0]["child_id"];
    }
}

/*
|--------------------------------------------------------------------------
| EARLY EXIT — no children
|--------------------------------------------------------------------------
*/
$child_info      = null;
$stats             = [];
$rating_trend      = [];
$recent_sessions   = [];

if ($active_child_id !== null) {

    /*
    | 1. Child info: name, age, therapist, next appointment
    */
    $info_sql  = "SELECT
                    c.child_name,
                    c.age,
                    t.full_name AS therapist_name,
                    (
                        SELECT a2.appointment_date
                        FROM appointments a2
                        WHERE a2.child_id = c.child_id
                          AND a2.parent_id = ?
                          AND a2.status = 'approved'
                          AND a2.appointment_date >= CURDATE()
                        ORDER BY a2.appointment_date ASC, a2.start_time ASC
                        LIMIT 1
                    ) AS next_date,
                    (
                        SELECT a3.start_time
                        FROM appointments a3
                        WHERE a3.child_id = c.child_id
                          AND a3.parent_id = ?
                          AND a3.status = 'approved'
                          AND a3.appointment_date >= CURDATE()
                        ORDER BY a3.appointment_date ASC, a3.start_time ASC
                        LIMIT 1
                    ) AS next_time
                  FROM children c
                  LEFT JOIN appointments a ON a.child_id = c.child_id AND a.parent_id = ?
                  LEFT JOIN therapists t   ON a.therapist_id = t.therapist_id
                  WHERE c.child_id = ?
                  ORDER BY a.created_at DESC
                  LIMIT 1";
    $info_stmt = mysqli_prepare($conn, $info_sql);
    mysqli_stmt_bind_param($info_stmt, "iiii", $parent_id, $parent_id, $parent_id, $active_child_id);
    mysqli_stmt_execute($info_stmt);
    $child_info = mysqli_fetch_assoc(mysqli_stmt_get_result($info_stmt));

    /*
    | 2. Stats: completed count, last session date
    */
    $stats_sql  = "SELECT
                     (SELECT COUNT(*) FROM appointments
                      WHERE child_id = ? AND parent_id = ? AND status = 'completed') AS completed_count,
                     (SELECT MAX(appointment_date) FROM appointments
                      WHERE child_id = ? AND parent_id = ? AND status = 'completed') AS last_session_date";
    $stats_stmt = mysqli_prepare($conn, $stats_sql);
    mysqli_stmt_bind_param($stats_stmt, "iiii",
        $active_child_id, $parent_id,
        $active_child_id, $parent_id
    );
    mysqli_stmt_execute($stats_stmt);
    $stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stats_stmt));

    /*
    | 3. Overall session rating trend
    */
    $rating_sql  = "SELECT a.appointment_date, sn.performance_rating
                    FROM appointments a
                    INNER JOIN session_notes sn ON a.appointment_id = sn.appointment_id
                    WHERE a.child_id = ? AND a.parent_id = ? AND a.status = 'completed'
                      AND sn.performance_rating IS NOT NULL
                    ORDER BY a.appointment_date ASC";
    $rating_stmt = mysqli_prepare($conn, $rating_sql);
    mysqli_stmt_bind_param($rating_stmt, "ii", $active_child_id, $parent_id);
    mysqli_stmt_execute($rating_stmt);
    $rating_result = mysqli_stmt_get_result($rating_stmt);
    while ($row = mysqli_fetch_assoc($rating_result)) {
        $rating_trend[] = $row;
    }

    /*
    | 4. Recent sessions (limit 10) 
    */
    $sessions_sql  = "SELECT
                        a.appointment_id,
                        a.appointment_date,
                        sn.performance_rating,
                        sn.parent_summary,
                        sn.progress_notes,
                        sn.home_practice
                      FROM appointments a
                      INNER JOIN session_notes sn ON a.appointment_id = sn.appointment_id
                      WHERE a.child_id = ? AND a.parent_id = ? AND a.status = 'completed'
                      ORDER BY a.appointment_date DESC
                      LIMIT 10";
    $sessions_stmt = mysqli_prepare($conn, $sessions_sql);
    mysqli_stmt_bind_param($sessions_stmt, "ii", $active_child_id, $parent_id);
    mysqli_stmt_execute($sessions_stmt);
    $sessions_result = mysqli_stmt_get_result($sessions_stmt);
    while ($row = mysqli_fetch_assoc($sessions_result)) {
        $recent_sessions[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function ratingLabel(int $r): string {
    return match($r) {
        1 => "Difficult",
        2 => "Below average",
        3 => "Average",
        4 => "Good",
        5 => "Excellent",
        default => "—",
    };
}

function ratingBadgeClass(int $r): string {
    return match(true) {
        $r >= 5 => "text-bg-success",
        $r >= 4 => "text-bg-primary",
        $r >= 3 => "text-bg-warning",
        default => "text-bg-danger",
    };
}

$parent_name = $_SESSION["parent_name"] ?? "Parent";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Child Progress - SpeechCare</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <style>
        /* ── Sidebar ───────────────────────────────────────────── */
        .parent-sidebar {
            width: 280px;
            min-height: 100vh;
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            box-shadow: 4px 0 20px rgba(0, 0, 0, .04);
            padding: 24px 18px;
            position: fixed;
            left: 0;
            top: 0;
        }
        .parent-sidebar .sidebar-logo {
            font-family: 'Segoe UI', sans-serif;
            font-size: 32px;
            font-weight: 600;
            letter-spacing: -1.4px;
            color: #3998E8;
            margin-bottom: 6px;
            line-height: 1;
        }
        .parent-sidebar .sidebar-subtitle {
            font-size: 13px;
            font-weight: 500;
            color: #666B7A;
            letter-spacing: .5px;
            margin-bottom: 34px;
            opacity: .85;
            text-transform: uppercase;
        }
        .parent-sidebar .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 12px;
            color: #666B7A;
            text-decoration: none;
            margin-bottom: 6px;
            font-weight: 600;
            transition: .2s ease;
        }
        .parent-sidebar .sidebar-menu a:hover,
        .parent-sidebar .sidebar-menu a.active {
            background: #eff6ff;
            color: #3998E8;
            box-shadow: 0 4px 12px rgba(37, 99, 235, .12);
        }

        /* ── Child switcher ────────────────────────────────────── */
        .child-switcher {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        .child-switcher label {
            font-weight: 700;
            color: #444;
            white-space: nowrap;
        }
        .child-switcher select {
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 8px 14px;
            font-weight: 600;
            color: #222;
            background: #f8fafd;
            cursor: pointer;
            flex: 1;
            min-width: 180px;
        }

        /* ── Page header bar ───────────────────────────────────── */
        .progress-header {
            background: linear-gradient(135deg, #3998E8 0%, #2563eb 100%);
            border-radius: 20px;
            padding: 24px 28px;
            color: #fff;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .progress-header .child-name {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -.5px;
            margin-bottom: 2px;
        }
        .progress-header .therapist-line {
            font-size: 14px;
            opacity: .85;
        }
        .progress-header .next-session-box {
            background: rgba(255,255,255,.15);
            border-radius: 14px;
            padding: 12px 18px;
            text-align: center;
            backdrop-filter: blur(6px);
        }
        .progress-header .next-session-box .ns-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: .8;
            margin-bottom: 4px;
        }
        .progress-header .next-session-box .ns-date {
            font-size: 17px;
            font-weight: 700;
        }
        .progress-header .next-session-box .ns-time {
            font-size: 13px;
            opacity: .75;
            font-weight: 700;
        }

        /* ── Stat cards ────────────────────────────────────────── */
        .stat-card {
            background: #fff;
            font-weight: 700;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 22px 24px;
            box-shadow: 0 4px 20px rgba(57, 152, 232, .07);
            height: 100%;
        }
        .stat-card .stat-icon {
            width: 46px;
            height: 46px;
            background: #eff6ff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            color: #3998E8;
            margin-bottom: 14px;
        }
        .stat-card .stat-number {
            font-size: 32px;
            font-weight: 700;
            font-weight: 800;
            color: #1a1a2e;
            letter-spacing: -1px;
            line-height: 1;
            margin-bottom: 4px;
        }
        .stat-card .stat-label {
            font-size: 13px;
            font-weight: 700;
            color: #666B7A;
            font-weight: 600;
        }

        /* ── Section titles ────────────────────────────────────── */
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title i {
            color: #3998E8;
            font-size: 20px;
        }

        /* ── Chart containers ──────────────────────────────────── */
        .chart-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(57, 152, 232, .07);
            margin-bottom: 24px;
        }
        .chart-card canvas {
            max-height: 220px;
        }

        /* ── Accordion / sessions ──────────────────────────────── */
        .accordion-item {
            border: 1px solid #e2e8f0 !important;
            border-radius: 16px !important;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .accordion-button {
            font-weight: 700;
            color: #1a1a2e;
            background: #fff;
            border-radius: 16px !important;
        }
        .accordion-button:not(.collapsed) {
            background: #eff6ff;
            color: #3998E8;
            box-shadow: none;
        }
        .home-practice-box {
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            border-radius: 14px;
            padding: 14px 16px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .home-practice-box .hp-icon {
            width: 36px;
            height: 36px;
            background: #22C55E;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 17px;
            flex-shrink: 0;
        }
        .home-practice-box .hp-text {
            font-size: 14px;
            color: #166534;
            line-height: 1.6;
        }
        .session-note-text {
            background: #F7FBFF;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            color: #444;
            line-height: 1.65;
        }
        .therapist-note-label {
            font-size: 11px;
            color: #aaa;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            margin-bottom: 6px;
        }

        /* ── Empty state ───────────────────────────────────────── */
        .empty-state-sm {
            text-align: center;
            padding: 32px 20px;
            color: #bbb;
        }
        .empty-state-sm i {
            font-size: 36px;
            margin-bottom: 10px;
            display: block;
        }
        .empty-state-sm p {
            font-size: 14px;
            margin: 0;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .progress-header { flex-direction: column; }
            .progress-header .next-session-box { width: 100%; }
        }
    </style>
</head>
<body>

<div class="mobile-navbar">
    <div class="sidebar-logo mb-0">SpeechCare</div>
    <button class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu">
        <i class="bi bi-list"></i>
    </button>
</div>

<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenu">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title sidebar-logo">SpeechCare</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body sidebar-menu">
         <a href="dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
            <a href="book-appointment.php"><i class="bi bi-calendar-plus"></i> Book Appointment</a>
            <a href="my-appointments.php"><i class="bi bi-calendar-check"></i> My Appointments</a>
            <a href="progress.php" class="active"><i class="bi bi-graph-up-arrow"></i> Progress</a>
            <a href="notifications.php"><i class="bi bi-bell"></i> Notifications</a>
            <a href="profile.php"><i class="bi bi-person-circle"></i> Profile</a>
            <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>

<div class="dashboard-wrapper">

    <aside class="sidebar parent-sidebar">
        <div class="sidebar-logo">SpeechCare</div>
        <div class="sidebar-subtitle">Parent Portal</div>
        <nav class="sidebar-menu">
            <a href="dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
            <a href="book-appointment.php"><i class="bi bi-calendar-plus"></i> Book Appointment</a>
            <a href="my-appointments.php"><i class="bi bi-calendar-check"></i> My Appointments</a>
            <a href="progress.php" class="active"><i class="bi bi-graph-up-arrow"></i> Progress</a>
            <a href="notifications.php"><i class="bi bi-bell"></i> Notifications</a>
            <a href="profile.php"><i class="bi bi-person-circle"></i> Profile</a>
            <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">

        <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="page-title">Child Progress</h1>
                <p class="page-subtitle">Track session ratings and recent activity.</p>
            </div>
            <div class="profile-circle">
                <?php echo strtoupper(substr($parent_name, 0, 1)); ?>
            </div>
        </div>

        <?php if (empty($children)) { ?>
            <div class="chart-card text-center py-5">
                <i class="bi bi-person-x" style="font-size:48px;color:#ddd;"></i>
                <h5 class="fw-bold mt-3 text-muted">No children found</h5>
                <p class="text-muted">Your progress data will appear here once an appointment has been booked and approved.</p>
                <a href="book-appointment.php" class="action-btn-primary mt-2">Book an appointment</a>
            </div>
        <?php } else { ?>

        <?php if (count($children) > 1) { ?>
        <div class="child-switcher">
            <label for="childSelect"><i class="bi bi-person-fill me-1"></i> Viewing:</label>
            <select id="childSelect" onchange="switchChild(this.value)">
                <?php foreach ($children as $c) { ?>
                    <option value="<?php echo (int)$c["child_id"]; ?>"
                        <?php echo (int)$c["child_id"] === $active_child_id ? "selected" : ""; ?>>
                        <?php echo escapeOutput($c["child_name"]); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <?php } ?>

        <?php if ($child_info) { ?>
        <div class="progress-header">
            <div>
                <div class="child-name"><?php echo escapeOutput($child_info["child_name"]); ?></div>
                <div class="therapist-line">
                    <i class="bi bi-person-badge me-1"></i>
                    <?php echo $child_info["therapist_name"]
                        ? "Therapist: " . escapeOutput($child_info["therapist_name"])
                        : "No therapist assigned yet"; ?>
                    &nbsp;·&nbsp;
                    <i class="bi bi-calendar3 me-1"></i>Age <?php echo escapeOutput($child_info["age"]); ?>
                </div>
            </div>
            <div class="next-session-box">
                <div class="ns-label"><i class="bi bi-alarm me-1"></i>Next Session</div>
                <?php if (!empty($child_info["next_date"])) { ?>
                    <div class="ns-date"><?php echo formatDateNice($child_info["next_date"]); ?></div>
                    <?php if (!empty($child_info["next_time"])) { ?>
                        <div class="ns-time"><?php echo formatTimeNice($child_info["next_time"]); ?></div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="ns-date" style="font-size:15px;opacity:.7;">Not scheduled</div>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-patch-check"></i></div>
                    <div class="stat-number"><?php echo (int)($stats["completed_count"] ?? 0); ?></div>
                    <div class="stat-label">Sessions Completed</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-calendar-event"></i></div>
                    <div class="stat-number" style="font-size:20px;letter-spacing:0;">
                        <?php echo !empty($stats["last_session_date"])
                            ? formatDateNice($stats["last_session_date"])
                            : "—"; ?>
                    </div>
                    <div class="stat-label">Last Session Date</div>
                </div>
            </div>
        </div>

        <div class="section-title"><i class="bi bi-graph-up"></i> Overall Session Ratings</div>
        <div class="chart-card mb-4">
            <?php if (!empty($rating_trend)) {
                $rating_dates  = json_encode(array_column($rating_trend, "appointment_date"));
                $rating_values = json_encode(array_map(fn($r) => (int)$r["performance_rating"], $rating_trend));
            ?>
            <canvas id="ratingChart"></canvas>
            <?php } else { ?>
            <div class="empty-state-sm">
                <i class="bi bi-bar-chart-line"></i>
                <p>No session ratings recorded yet.<br>Ratings appear once your therapist completes and scores a session.</p>
            </div>
            <?php } ?>
        </div>

        <div class="section-title"><i class="bi bi-journals"></i> Recent Sessions</div>

        <?php if (empty($recent_sessions)) { ?>
            <div class="chart-card">
                <div class="empty-state-sm">
                    <i class="bi bi-calendar-x"></i>
                    <p>No completed sessions yet. Session details will appear here after each therapy visit.</p>
                </div>
            </div>
        <?php } else { ?>
        <div class="accordion mb-4" id="sessionsAccordion">
            <?php foreach ($recent_sessions as $idx => $session) {
                $session_id    = (int)$session["appointment_id"];
                $acc_id        = "session_acc_{$session_id}";
                $acc_target    = "session_body_{$session_id}";
                $is_first      = ($idx === 0);
                $summary_text  = !empty($session["parent_summary"]) ? $session["parent_summary"] : null;
                $fallback_note = $session["progress_notes"] ?? "";
                $rating        = !empty($session["performance_rating"]) ? (int)$session["performance_rating"] : null;
            ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="<?php echo $acc_id; ?>">
                    <button class="accordion-button <?php echo $is_first ? "" : "collapsed"; ?>"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#<?php echo $acc_target; ?>"
                            aria-expanded="<?php echo $is_first ? "true" : "false"; ?>">
                        <div class="d-flex align-items-center gap-3 flex-wrap w-100">
                            <span class="fw-800"><?php echo formatDateNice($session["appointment_date"]); ?></span>
                            <?php if ($rating) { ?>
                                <span class="badge <?php echo ratingBadgeClass($rating); ?> rounded-pill ms-auto me-2">
                                    <i class="bi bi-star-fill me-1"></i><?php echo $rating; ?>/5 — <?php echo ratingLabel($rating); ?>
                                </span>
                            <?php } else { ?>
                                <span class="badge text-bg-secondary rounded-pill ms-auto me-2">Not rated</span>
                            <?php } ?>
                        </div>
                    </button>
                </h2>

                <div id="<?php echo $acc_target; ?>"
                     class="accordion-collapse collapse <?php echo $is_first ? "show" : ""; ?>"
                     data-bs-parent="#sessionsAccordion">
                    <div class="accordion-body pt-2">

                        <div class="mb-3">
                            <?php if ($summary_text !== null) { ?>
                                <div class="session-note-text"><?php echo escapeOutput($summary_text); ?></div>
                            <?php } elseif (!empty($fallback_note)) { ?>
                                <div class="therapist-note-label">Therapist's progress note</div>
                                <div class="session-note-text"><?php echo escapeOutput($fallback_note); ?></div>
                            <?php } else { ?>
                                <div class="session-note-text text-muted fst-italic">No session summary available.</div>
                            <?php } ?>
                        </div>

                        <?php if (!empty($session["home_practice"])) { ?>
                        <div class="home-practice-box">
                            <div class="hp-icon"><i class="bi bi-house-heart-fill"></i></div>
                            <div>
                                <div class="fw-bold small text-success mb-1">Home Practice</div>
                                <div class="hp-text"><?php echo escapeOutput($session["home_practice"]); ?></div>
                            </div>
                        </div>
                        <?php } ?>

                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
        <?php } ?>

        <?php } /* end if children */ ?>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
function switchChild(childId) {
    const url = new URL(window.location.href);
    url.searchParams.set("child_id", childId);
    window.location.href = url.toString();
}

Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
Chart.defaults.font.size   = 12;
Chart.defaults.color       = "#666B7A";

const BLUE      = "#3998E8";
const BLUE_FILL = "rgba(57,152,232,0.10)";

function baseLineConfig(labels, data, color, fillColor, yMax, yLabel) {
    return {
        type: "line",
        data: {
            labels: labels,
            datasets: [{
                data: data,
                borderColor: color,
                backgroundColor: fillColor,
                borderWidth: 2.5,
                pointBackgroundColor: color,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.35,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: { mode: "index", intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: "#fff",
                    borderColor: "#e2e8f0",
                    borderWidth: 1,
                    titleColor: "#1a1a2e",
                    bodyColor: "#444",
                    padding: 10,
                    callbacks: {
                        label: function(ctx) {
                            return " " + ctx.parsed.y + "/5";
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: "#f0f4f8" },
                    ticks: { maxTicksLimit: 6 }
                },
                y: {
                    min: 0,
                    max: yMax,
                    grid: { color: "#f0f4f8" },
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    };
}

<?php if (!empty($rating_trend)) { ?>
(function () {
    const ctx = document.getElementById("ratingChart");
    if (!ctx) return;
    const labels = <?php echo $rating_dates; ?>;
    const data   = <?php echo $rating_values; ?>;
    new Chart(ctx, baseLineConfig(labels, data, BLUE, BLUE_FILL, 5, "rating"));
})();
<?php } ?>
</script>

</body>
</html>