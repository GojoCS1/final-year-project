<?php
include 'db.php';
date_default_timezone_set('Africa/Addis_Ababa');

$day = isset($_GET['day']) ? $_GET['day'] : date('l');
$area = isset($_GET['area']) ? $_GET['area'] : "";
$role_req = isset($_GET['role']) ? $_GET['role'] : "medical"; // medical, lab, or pharmacist

 // የሰዓት ገደብ
if (!empty($_GET['shift'])) {
    $shift = $_GET['shift'] . "%"; // ከ URL የመጣውን ይጠቀማል
} else {
    $hour = (int)date('H');
    $ampm = date('A');
    if ($ampm === 'AM' && $hour >= 8) { $shift = "Morning%"; }
    else if ($ampm === 'PM' && $hour < 18) { $shift = "Afternoon%"; }
    else { $shift = "Night%"; }
}

// የኳዌሪው መሠረት
$sql = "SELECT u.staff_id, u.full_name, u.role, s.assigned_area, s.room 
        FROM schedules s
        JOIN users u ON s.staff_name_id LIKE CONCAT(u.full_name, ' (', u.role, ')%')
        WHERE s.shift_day = ? AND s.shift_time LIKE ?";

// እንደ ሚፈለገው የሙያ አይነት ገደብ መጨመር
if ($role_req === 'medical') {
    $sql .= " AND u.role IN ('doctor', 'nurse')";
} else if ($role_req === 'lab') {
    $sql .= " AND u.role IN ('lab_technician', 'Lab Technician')";
} else if ($role_req === 'pharmacist') {
    $sql .= " AND u.role IN ('pharmacist', 'Pharmacist')";
}

// ቦታ (Area) ከተላከ በዛ ቦታ ብቻ እንዲፈልግ
if (!empty($area)) {
    $sql .= " AND s.assigned_area LIKE ?";
    $stmt = $conn->prepare($sql);
    $area_param = "%$area%";
    $stmt->bind_param("sss", $day, $shift, $area_param);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $day, $shift);
}

$stmt->execute();
$result = $stmt->get_result();
$staff = [];
while($row = $result->fetch_assoc()){ $staff[] = $row; }
echo json_encode($staff);
?>