<?php
include 'db.php';
header('Content-Type: application/json');

$mrn = $_GET['mrn'] ?? '';

if (!$mrn) { echo json_encode([]); exit; }

// የመጨረሻ 24 ሰዓት ትዕዛዞችን ብቻ ያሳያል
$query = "SELECT p.medication_name, p.dosage_instruction, p.status, p.created_at, u.full_name as pharmacist_name 
          FROM prescriptions p 
          LEFT JOIN users u ON p.assigned_to_pharmacist = u.staff_id 
          WHERE p.mrn = ? AND p.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
          ORDER BY p.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $mrn);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while($row = $result->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data);
?>