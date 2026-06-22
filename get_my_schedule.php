<?php
include 'db.php';
header('Content-Type: application/json');

$name = $_GET['name'] ?? '';

// staff_name_id ውስጥ ስሙ ካለ ፈልጎ ያመጣል
// Staff Admin ሲመዘግብ "Full Name (Role)" ብሎ ስለሚመዘግብ LIKE እንጠቀማለን
$stmt = $conn->prepare("SELECT * FROM schedules WHERE staff_name_id LIKE ? ORDER BY FIELD(shift_day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')");
$searchName = "%" . $name . "%";
$stmt->bind_param("s", $searchName);
$stmt->execute();
$result = $stmt->get_result();

$my_schedule = [];
while($row = $result->fetch_assoc()) {
    $my_schedule[] = $row;
}

echo json_encode($my_schedule);
?>