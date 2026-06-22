<?php
include 'db.php';
session_start();

if (!isset($_SESSION['staff_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

$staff_id = $_SESSION['staff_id'];
$role     = $_SESSION['role'] ?? '';

if ($role === 'system_admin') {
    $result = $conn->query(
        "SELECT COUNT(*) as total FROM admin_commands WHERE is_read = 0"
    );
    $count = $result->fetch_assoc()['total'];

} elseif ($role === 'lab_technician') {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as total FROM lab_requests 
         WHERE assigned_to_lab_tech = ? AND is_read = 0"
    );
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['total'];

} elseif ($role === 'pharmacist') {
    // Count unread prescriptions assigned to this pharmacist
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as total FROM prescriptions 
         WHERE assigned_to_pharmacist = ? AND is_read = 0"
    );
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['total'];

} else {
    // Doctor, Nurse, Receptionist — patients + appointments
    $stmt1 = $conn->prepare(
        "SELECT COUNT(*) as total FROM patients 
         WHERE assigned_to_dept = ? AND is_read = 0"
    );
    $stmt1->bind_param("s", $staff_id);
    $stmt1->execute();
    $count1 = $stmt1->get_result()->fetch_assoc()['total'];

    $stmt2 = $conn->prepare(
        "SELECT COUNT(*) as total FROM appointments 
         WHERE staff_id = ? AND is_read = 0"
    );
    $stmt2->bind_param("s", $staff_id);
    $stmt2->execute();
    $count2 = $stmt2->get_result()->fetch_assoc()['total'];

   $stmt3 = $conn->prepare("SELECT COUNT(*) as total FROM lab_requests WHERE ordered_by = ? AND status = 'Completed' AND is_read_lab = 0");
    $stmt3->bind_param("s", $staff_id);
    $stmt3->execute();
    $count3 = $stmt3->get_result()->fetch_assoc()['total'];

    $count = $count1 + $count2 + $count3;
}

echo json_encode(['count' => (int)$count]);
?>