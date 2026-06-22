<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $rx_id = $_POST['rx_id'];

    // Update the status to 'Dispensed'
    $sql = "UPDATE prescriptions SET status = 'Dispensed' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $rx_id);

    if ($stmt->execute()) {
        echo "Success";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>