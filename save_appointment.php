<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mrn      = $_POST['mrn'];
    $staff_id = $_POST['staff_id']; 
    $date     = $_POST['date'];

    // 1. Get staff name for conflict message
    $user_q = $conn->prepare("SELECT full_name FROM users WHERE staff_id = ?");
    $user_q->bind_param("s", $staff_id);
    $user_q->execute();
    $staff_name = $user_q->get_result()->fetch_assoc()['full_name'];

    // 2. Check for 30-minute scheduling conflict
    $check_stmt = $conn->prepare(
        "SELECT id FROM appointments 
         WHERE staff_id = ? 
         AND ABS(TIMESTAMPDIFF(MINUTE, appointment_date, ?)) < 30"
    );
    $check_stmt->bind_param("ss", $staff_id, $date);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        echo "$staff_name has a schedule";
    } else {
        // Insert with is_read = 0 so doctor gets notified
        $insert_stmt = $conn->prepare(
            "INSERT INTO appointments (mrn, staff_id, appointment_date, status, is_read) 
             VALUES (?, ?, ?, 'Scheduled', 0)"
        );
        $insert_stmt->bind_param("sss", $mrn, $staff_id, $date);
        
        if ($insert_stmt->execute()) {
            echo "Appointment scheduled successfully!";
        } else {
            echo "Error: " . $conn->error;
        }
    }
}
?>