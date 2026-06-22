<?php
include 'db.php'; // Port 3307
$sql = "SELECT staff_id, full_name, role FROM users WHERE role IN ('doctor', 'Doctor', 'nurse', 'Nurse')";
$result = $conn->query($sql);
$staff = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }
}
header('Content-Type: application/json');
echo json_encode($staff);
?>