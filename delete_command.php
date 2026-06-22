<?php
include 'db.php';
session_start();

if (isset($_GET['id'])) {
    $cmd_id = $_GET['id'];
    
    // ምላሽ (reply) ካልተሰጠው ብቻ እንዲጠፋ እናረጋግጣለን
    $check = $conn->prepare("DELETE FROM admin_commands WHERE id = ? AND reply IS NULL");
    $check->bind_param("i", $cmd_id);
    
    if ($check->execute()) {
        header("Location: staff_admin.php?task=command");
    } else {
        echo "Error: Command cannot be deleted because it is already processed.";
    }
}
?>