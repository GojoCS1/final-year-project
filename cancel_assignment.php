<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mrn = $_POST['mrn'];

    // የታካሚውን ምደባ ወደ NULL በመቀየር ከዶክተሩ ዝርዝር ውስጥ እንዲጠፋ እናደርጋለን
    $sql = "UPDATE patients SET assigned_to_dept = NULL, assigned_at = NULL, is_read = 0 WHERE mrn = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $mrn);

    if ($stmt->execute()) {
        echo "Assignment cancelled successfully. The patient is now back to waiting list.";
    } else {
        echo "Error cancelling assignment: " . $conn->error;
    }
}
?>