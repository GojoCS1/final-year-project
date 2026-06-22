<?php
include 'db.php';
session_start();

if (!isset($_SESSION['staff_id'])) exit();

$staff_id = $_SESSION['staff_id'];
$role     = $_SESSION['role'] ?? '';
$type     = $_GET['type'] ?? '';
$mrn      = $_GET['mrn']  ?? '';

if ($type === 'patient') {
    $conn->query("UPDATE patients SET is_read = 1 WHERE assigned_to_dept = '$staff_id'");
    $conn->query("UPDATE appointments SET is_read = 1 WHERE staff_id = '$staff_id'");
}

if ($type === 'lab' && $mrn !== '') {
    $conn->query("UPDATE lab_requests SET is_read_lab = 1 WHERE mrn = '$mrn' AND status = 'Completed'");
}

if ($type === 'lab_requests' && $role === 'lab_technician') {
    $conn->query("UPDATE lab_requests SET is_read = 1 WHERE assigned_to_lab_tech = '$staff_id'");
}

if ($type === 'commands' && $role === 'system_admin') {
    $conn->query("UPDATE admin_commands SET is_read = 1");
}

// ✅ NEW: Clear prescription notifications for pharmacist
if ($type === 'prescriptions' && $role === 'pharmacist') {
    $conn->query("UPDATE prescriptions SET is_read = 1 WHERE assigned_to_pharmacist = '$staff_id'");
}

echo "Success";
?>