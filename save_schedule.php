<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name  = $_POST['staff_name']; 
    $day   = $_POST['shift_day'];
    $shift = $_POST['shift'];
    $area  = isset($_POST['area']) ? $_POST['area'] : '';
    $room  = isset($_POST['room']) ? $_POST['room'] : '';

    // ሙያውን ከቅንፍ ውስጥ ማውጣት (e.g. Doctor, Nurse)
    preg_match('/\((.*?)\)/', $name, $matches);
    $role_part = isset($matches[1]) ? $matches[1] : ''; 

    // 1. ሰራተኛው በዛ ሰዓት ሌላ ቦታ ተመድቦ እንደሆነ መፈተሽ
    $check_staff = $conn->prepare("SELECT id FROM schedules WHERE staff_name_id = ? AND shift_day = ? AND shift_time = ?");
    $check_staff->bind_param("sss", $name, $day, $shift);
    $check_staff->execute();
    if ($check_staff->get_result()->num_rows > 0) {
        echo "<script>alert('Error: This staff member is already assigned elsewhere in this shift!'); window.history.back();</script>";
        exit();
    }

    // 2. የክፍል አጠቃቀም ህጎችን መፈተሽ
    if (!empty($room)) {
        // በክፍሉ ውስጥ አስቀድሞ ያለን ሰራተኛ እናምጣ (አንድ ቢሆንም)
        $check_room = $conn->prepare("SELECT staff_name_id, assigned_area FROM schedules WHERE shift_day = ? AND shift_time = ? AND room = ? LIMIT 1");
        $check_room->bind_param("sss", $day, $shift, $room);
        $check_room->execute();
        $room_res = $check_room->get_result();

        if ($room_res->num_rows > 0) {
            $existing = $room_res->fetch_assoc();
            $existing_name_id = $existing['staff_name_id'];
            $existing_area    = $existing['assigned_area'];

            // ሙያዎችን ለይቶ ማወቅ
            $is_new_doc   = (stripos($role_part, 'doctor') !== false);
            $is_new_nurse = (stripos($role_part, 'nurse') !== false);
            
            $is_ext_doc   = (stripos($existing_name_id, 'doctor') !== false);
            $is_ext_nurse = (stripos($existing_name_id, 'nurse') !== false);

            // --- ህግ ሀ: ለዶክተር እና ለነርስ (Sharing Rule) ---
            if ($is_new_doc || $is_new_nurse) {
                // ክፍሉ ውስጥ ያለው የተለየ ሙያ ከሆነ (Recep/Lab/Pharm) - አይፈቀድም
                if (!$is_ext_doc && !$is_ext_nurse) {
                    echo "<script>alert('Error: This room is occupied by a different profession. Doctors/Nurses cannot share with Lab/Recep/Pharm!'); window.history.back();</script>";
                    exit();
                }
                // ተመሳሳይ ሙያ (Doc-Doc ወይም Nurse-Nurse) - አይፈቀድም
                if (($is_new_doc && $is_ext_doc) || ($is_new_nurse && $is_ext_nurse)) {
                    echo "<script>alert('Error: Two staff members of the same clinical profession (Doc-Doc or Nurse-Nurse) cannot share a room!'); window.history.back();</script>";
                    exit();
                }
                // ስራቸው (Area) ከተለያየ - አይፈቀድም
                if ($existing_area !== $area) {
                    echo "<script>alert('Error: Shared Room! Doctor and Nurse must work on the same task/area ($existing_area)!'); window.history.back();</script>";
                    exit();
                }
            } 
            // --- ህግ ለ: ለሌሎች ሙያዎች (Lab, Pharmacy, Receptionist) ---
            else {
                // አዲሱ ሙያ በክፍሉ ውስጥ ካለው ጋር አንድ አይነት ካልሆነ (e.g. Lab vs Recep) - አይፈቀድም
                if (stripos($existing_name_id, $role_part) === false) {
                    echo "<script>alert('Error: This room is occupied by a different profession. You can only join if you have the exact same profession!'); window.history.back();</script>";
                    exit();
                }
                // ማሳሰቢያ፡ ሙያው ተመሳሳይ ከሆነ (e.g. Recep + Recep) ይህ ኮድ እንዲያልፍ ይፈቅዳል
            }
        }
    }

    // 3. ሁሉም ህጎች ከተከበሩ ሴቭ እናደርጋለን
    $sql = "INSERT INTO schedules (staff_name_id, shift_day, shift_time, assigned_area, room) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $name, $day, $shift, $area, $room);

    if ($stmt->execute()) {
        echo "<script>alert('Schedule Saved Successfully!'); window.location.href='staff_admin.php?task=assign';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>