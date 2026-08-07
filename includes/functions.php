<?php
function cleanInput($data) {
    return trim($data);
}

function escapeOutput($data) {
    return htmlspecialchars($data ?? "", ENT_QUOTES, "UTF-8");
}

function redirectTo($path) {
    header("Location: " . $path);
    exit();
}

function formatDateNice($date) {
    if (empty($date)) {
        return "-";
    }

    return date("M d, Y", strtotime($date));
}

function formatTimeNice($time) {
    if (empty($time)) {
        return "-";
    }

    return date("h:i A", strtotime($time));
}

function getAppointmentStatusBadgeClass($status) {
    if ($status === "pending") {
        return "bg-warning text-dark";
    }

    if ($status === "approved") {
        return "bg-success";
    }

    if ($status === "rejected") {
        return "bg-danger";
    }

    if ($status === "completed") {
        return "bg-primary";
    }

    if ($status === "cancelled") {
        return "bg-secondary";
    }

    if ($status === "needs_rescheduling") {
        return "bg-danger";
    }

    if ($status === "rescheduled") {
        return "bg-info text-dark";
    }

    return "bg-secondary";
}

function getSlotStatusBadgeClass($status) {
    if ($status === "available") {
        return "bg-success";
    }

    if ($status === "pending") {
        return "bg-warning text-dark";
    }

    if ($status === "booked") {
        return "bg-danger";
    }

    if ($status === "blocked_for_leave") {
        return "bg-dark";
    }

    if ($status === "completed") {
        return "bg-primary";
    }

    if ($status === "cancelled" || $status === "unavailable") {
        return "bg-secondary";
    }

    return "bg-secondary";
}

function displayStatusText($status) {
    return ucwords(str_replace("_", " ", $status));
}
?>