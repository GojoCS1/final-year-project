<?php
include 'db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $day = $_POST['day'];
    $shift = $_POST['shift'];
    $area = $_POST['area'];
    $room = isset($_POST['room']) ? $_POST['room'] : '';

    // የሰራተኛውን ስም እና ሙያ ማምጣት
    $res = $conn->query("SELECT staff_name_id FROM schedules WHERE id = $id");
    $row = $res->fetch_assoc();
    $name = $row['staff_name_id'];

    preg_match('/\((.*?)\)/', $name, $matches);
    $role_part = isset($matches[0]) ? $matches[0] : '';

    // 1. የሰራተኛ መደራረብ ቼክ
    $check_staff = $conn->prepare("SELECT id FROM schedules WHERE staff_name_id = ? AND shift_day = ? AND shift_time = ? AND id != ?");
    $check_staff->bind_param("sssi", $name, $day, $shift, $id);
    $check_staff->execute();
    if ($check_staff->get_result()->num_rows > 0) {
        echo "<script>alert('Error: Staff already assigned elsewhere!'); window.history.back();</script>";
        exit();
    }

    // 2. የሙያ መደራረብ ቼክ (ተመሳሳይ ሙያ ያለው ሰው በዛ ቦታ ካለ)
    if (!empty($area) || !empty($room)) {
        $check_role = $conn->prepare("SELECT id FROM schedules WHERE shift_day = ? AND shift_time = ? AND assigned_area = ? AND room = ? AND staff_name_id LIKE ? AND id != ?");
        $role_search = "%" . $role_part . "%";
        $check_role->bind_param("sssssi", $day, $shift, $area, $room, $role_search, $id);
        $check_role->execute();
        
        if ($check_role->get_result()->num_rows > 0) {
            echo "<script>alert('Error: Another staff with the same profession is already in this spot!'); window.history.back();</script>";
            exit();
        }
    }

    // 3. Update
    $sql = "UPDATE schedules SET shift_day = ?, shift_time = ?, assigned_area = ?, room = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $day, $shift, $area, $room, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Update Successful!'); window.location.href='staff_admin.php?task=all-schedules';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>