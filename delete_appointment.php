<?php
include 'db.php';

if(!isset($_POST['appointment_id'])) {
    echo "Error: No appointment ID provided.";
    exit();
}

$appt_id = $_POST['appointment_id'];

$stmt = $conn->prepare("DELETE FROM appointments WHERE id = ?");
$stmt->bind_param("i", $appt_id);

if($stmt->execute()) {
    echo "Appointment cancelled successfully.";
} else {
    echo "Error: " . $conn->error;
}
?>