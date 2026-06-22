<?php
include 'db.php'; // Ensure your db.php with port 3307 is included

$sql = "SELECT staff_id, full_name FROM users WHERE role = 'doctor'";
$result = $conn->query($sql);

$doctors = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
}

// Return the data as JSON
header('Content-Type: application/json');
echo json_encode($doctors);
?>