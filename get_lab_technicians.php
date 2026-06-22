<?php
include 'db.php'; // Uses Port 3307
$sql = "SELECT staff_id, full_name FROM users WHERE role = 'lab_technician' OR role = 'Lab Technician'";
$result = $conn->query($sql);
$techs = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $techs[] = $row;
    }
}
header('Content-Type: application/json');
echo json_encode($techs);
?>