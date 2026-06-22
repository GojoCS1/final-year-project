<?php
include 'db.php';
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['cmd_id'];
    $reply = $_POST['reply'];

    // Update the reply and status for the specific command
    $sql = "UPDATE admin_commands SET reply = ?, status = 'Replied' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $reply, $id);

    if ($stmt->execute()) {
        // Return success JSON for the AJAX call
        echo json_encode(["status" => "success", "message" => "Reply sent successfully"]);
    } else {
        // Return error JSON
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
}
?>