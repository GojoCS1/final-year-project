<?php
include 'db.php';
header('Content-Type: application/json');
session_start();

$current_staff = $_SESSION['staff_id'] ?? '';
$mrn = $_GET['mrn'] ?? '';

if (!$mrn || !$current_staff) { 
    echo json_encode([]); 
    exit(); 
}

// እዚህ ጋር 'notes' ተጨምሯል
$sql = "SELECT 
            id,
            mrn,
            test_type,
            result,
            result_image, 
            notes, 
            status,
            created_at,
            assigned_to_lab_tech
        FROM lab_requests 
        WHERE mrn = ? 
        AND ordered_by = ? 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 12 HOUR)
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $mrn, $current_staff);
$stmt->execute();
$res = $stmt->get_result();

$results = [];
while ($row = $res->fetch_assoc()) {
    $results[] = $row;
}

echo json_encode($results);
?>