<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $staff_id = $_POST['staff_id'];

    // 1. Get Name to delete schedules
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user) {
        $full_name = $user['full_name'];
        // 2. Delete Schedules
        $search = $full_name . "%";
        $del_s = $conn->prepare("DELETE FROM schedules WHERE staff_name_id LIKE ?");
        $del_s->bind_param("s", $search);
        $del_s->execute();

        // 3. Delete User
        $del_u = $conn->prepare("DELETE FROM users WHERE id = ?");
        $del_u->bind_param("i", $id);
        
        if ($del_u->execute()) {
            // 4. Update Command
            $conn->query("UPDATE admin_commands SET reply = 'Already Deleted' WHERE target_staff_id = '$staff_id'");
            echo "Success";
        }
    }
}
?>
