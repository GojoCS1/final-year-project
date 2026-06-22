<?php
include 'db.php';
if(isset($_GET['id'])) {
    $id = intval($_GET['id']); // ለደህንነት ሲባል intval ተጨምሯል
    $conn->query("DELETE FROM schedules WHERE id = $id");
    
    // ከዴሌት በኋላ ወደ "View All Schedules" ታብ እንዲመለስ ?task=all-schedules እንጨምራለን
    echo "<script>alert('Deleted Successfully!'); window.location.href='staff_admin.php?task=all-schedules';</script>";
}
?>