<?php
include 'db.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mrn = $_POST['mrn'];
    $target_staff_id = $_POST['target']; 
    $complaint = $_POST['complaint'];

    // 1. Check if already assigned in last 12 hours
    $check_sql = "SELECT u.full_name FROM patients p 
                  JOIN users u ON p.assigned_to_dept = u.staff_id 
                  WHERE p.mrn = ? AND p.assigned_at >= DATE_SUB(NOW(), INTERVAL 12 HOUR)";
    
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $mrn);
    $check_stmt->execute();
    $res = $check_stmt->get_result();
    
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo "The ID is already assigned to " . $row['full_name'];
        exit();
    }

    // 2. Assign patient — use is_read = 0 ONLY (no staff_seen, no doctor_notified)
    $update_sql = "UPDATE patients 
               SET assigned_to_dept = ?, 
                   initial_complaint = ?, 
                   assigned_at = NOW(), 
                   is_read = 0,
                   assigned_by = 'receptionist'
               WHERE mrn = ?";

    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sss", $target_staff_id, $complaint, $mrn);
    
    if ($update_stmt->execute()) {
        echo "Patient assigned successfully!";
    } else {
        echo "Error saving assignment: " . $conn->error;
    }
}
?>