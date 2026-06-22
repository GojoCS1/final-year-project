<?php
include 'db.php'; // Uses Port 3307
$sql = "SELECT staff_id, full_name FROM users WHERE role = 'pharmacist' OR role = 'Pharmacist'";
$result = $conn->query($sql);
$pharms = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $pharms[] = $row;
    }
}
header('Content-Type: application/json');
echo json_encode($pharms);
?>