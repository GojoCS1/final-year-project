<?php
include 'db.php';
session_start();

// ቼክ: ገጹን የከፈተው staff_admin መሆኑን ማረጋገጥ
if (!isset($_SESSION['staff_id']) || $_SESSION['role'] !== 'staff_admin') {
    header("Location: login.html");
    exit();
}

// ሁሉንም ዳታ ማጥፋት
if ($conn->query("DELETE FROM schedules")) {
    header("Location: staff_admin.php?task=all-schedules");
} else {
    echo "Error deleting records: " . $conn->error;
}
?>