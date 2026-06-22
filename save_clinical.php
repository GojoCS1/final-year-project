<?php
include 'db.php';
header('Content-Type: application/json');
session_start();

$action = $_POST['action'] ?? '';
$mrn = $_POST['patient_id'] ?? '';
$staff = $_SESSION['staff_id'] ?? '';

try {

    $stmt = null;

    /* ================= UPDATE INFO ================= */
    if ($action == 'Update Info') {

        $sql = "INSERT INTO patient_updates
        (mrn, staff_id, ward, bed, adm_date, dept, cc, hpi, pe, assessment, plan)
        VALUES (?, ?, ?, ?, CURRENT_DATE, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssss",
            $mrn, $staff,
            $_POST['ward'],
            $_POST['bed_number'],
            $_POST['department'],
            $_POST['cc_chief_complaint_'],
            $_POST['hpi_history_of_present_illness_'],
            $_POST['p_e_physical_examination_'],
            $_POST['assessiment_'],
            $_POST['plane_']
        );
    }

    /* ================= PROGRESS NOTE ================= */
    elseif ($action == 'Progress Note') {

        $sql = "INSERT INTO progress_notes
        (mrn, staff_id, ward, bed, adm_date, problem_list, mgmt_summary,
         history_upd, pe_upd, assessment, plan)
        VALUES (?, ?, ?, ?, CURRENT_DATE, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssss",
            $mrn, $staff,
            $_POST['ward'],
            $_POST['bed_number'],
            $_POST['current_problem_list_'],
            $_POST['current_management_summary_'],
            $_POST['update_in_history'],
            $_POST['update_in_physical_examination_'],
            $_POST['current_assesment_'],
            $_POST['suggested_plan_']
        );
    }

    /* ================= ORDER SHEET ================= */
    elseif ($action == 'Order Sheet') {
        $sql = "INSERT INTO order_sheets 
        (mrn, staff_id, ward, bed, diagnosis, patient_condition, vital_signs, nursing_care, diet, investigation, management) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        // 11 ቦታዎች (sssssssssss) እና 11 ቫሪያብሎች
        $stmt->bind_param("sssssssssss",
            $mrn,
            $staff,
            $_POST['ward'],
            $_POST['bed_number'], // ይህ ወደ 'bed' ኮለም ይገባል
            $_POST['diagnosis_text'],
            $_POST['condition_text'],
            $_POST['vital_signs'],
            $_POST['nursing_care'],
            $_POST['diet'],
            $_POST['investigation'],
            $_POST['management_text']
        );
    }

/* ================= FOLLOW UP ================= */
elseif ($action == 'Follow Up') {
    $status = (!empty($_POST['condition_status_'])) ? $_POST['condition_status_'] : 'Ongoing';
    $ward = $_POST['ward'] ?? '';
    $bed = $_POST['bed_number'] ?? '';
    $now = date('Y-m-d H:i:s');

    // adm_date ከዚህ በታች ካለው SQL ወጥቷል
    $sql = "INSERT INTO medication_followup 
            (mrn, staff_id, ward, bed_number, row_index, item_description, hrs, days_checked, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    for ($r = 1; $r <= 5; $r++) { 
        $item = $_POST["item_$r"] ?? '';
        $hrs = $_POST["hrs_$r"] ?? '';
        
        if (!empty($item)) {
            $days = [];
            for ($d = 1; $d <= 30; $d++) {
                if (isset($_POST["ch_{$r}_d{$d}"])) { $days[] = $d; }
            }
            $days_string = implode(',', $days);

            // bind_param ላይ የነበረው $adm_date ተወግዷል (10 's' ብቻ)
            $stmt->bind_param("ssssisssss", 
                $mrn, $staff, $ward, $bed, $r, $item, $hrs, $days_string, $status, $now
            );
            $stmt->execute();
        }
    }
    echo json_encode(["status"=>"success"]);
    exit();
}

    /* ================= DISCHARGE SUMMARY ================= */
    elseif ($action == 'Discharge Summary') {

        $sql = "INSERT INTO discharge_summaries
        (mrn, staff_id, ward, bed, adm_date, history, pe,
         lab_investigation, final_diagnosis,
         treatment_course, discharge_plan, condition_status)
        VALUES (?, ?, ?, ?, CURRENT_DATE, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssss",
            $mrn,
            $staff,
            $_POST['ward'],
            $_POST['bed_number'],
            $_POST['brief_history_'],
            $_POST['physical_examination_'],
            $_POST['lab__investigation_'],
            $_POST['final_diagnosis_'],
            $_POST['course_of_treatment_'],
            $_POST['plan_of_discharge_'],
            $_POST['condition']
        );
    }

    /* ================= LAB REQUEST ================= */
    elseif ($action == 'Lab Request') {

        $test = ($_POST['select_test_type'] == 'Other')
            ? $_POST['please_specify_other_test_']
            : $_POST['select_test_type'];

        // doctor_id የሚለው በ ordered_by ተተክቷል
        $sql = "INSERT INTO lab_requests
(mrn, ordered_by, test_type, assigned_to_lab_tech, status, is_read_lab)
VALUES (?, ?, ?, ?, 'Pending', 0)";
        $stmt = $conn->prepare($sql);
        // $staff የሚለው ተለዋዋጭ (variable) ቀድሞውኑ $_SESSION['staff_id'] ይዟል
        $stmt->bind_param("ssss",
            $mrn,
            $staff, 
            $test,
            $_POST['assigned_to_lab_technician']
        );
    }

    /* ================= Prescription ================= */
    elseif ($action == 'Prescription') {

        $sql = "INSERT INTO prescriptions
        (mrn, medication_name, dosage_instruction,
         assigned_to_pharmacist, status)
        VALUES (?, ?, ?, ?, 'Pending')";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss",
            $mrn,
            $_POST['medication_name'],
            $_POST['dosage_instruction'],
            $_POST['assigned_pharmacist']
        );
    }

    /* ================= APPOINTMENT ================= */
    elseif ($action == 'Appointment') {

        $sql = "INSERT INTO appointments
        (mrn, staff_id, appointment_date)
        VALUES (?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss",
            $mrn,
            $staff,
            $_POST['appointment_date']
        );
    }

    /* ================= REASSIGN ================= */
    elseif ($action == 'Reassignment') {
    $assigned_by = $_SESSION['role']; // will be 'doctor' or 'nurse' automatically
$sql = "UPDATE patients SET assigned_to_dept = ?, assigned_at = NOW(), is_read = 0, assigned_by = ? WHERE mrn = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $_POST['re_assign_to_staff_'], $assigned_by, $mrn);
$stmt->execute();

    // 2. ከዚህ ዶክተር ጋር የነበረውን ቀጠሮ ማጥፋት (ከዳሽቦርዱ እንዲጠፋ ዋናው መፍትሄ ይህ ነው)
    $del_appt = $conn->prepare("DELETE FROM appointments WHERE mrn = ? AND staff_id = ?");
    $del_appt->bind_param("ss", $mrn, $staff);
    $del_appt->execute();

    echo json_encode(["status"=>"success"]);
    exit();
}

    if ($stmt && $stmt->execute()) {
        // 1. ታካሚውን ከሰልፍ የሚያስወጡ ስራዎች ዝርዝር
        $clear_actions = ['Prescription', 'Appointment', 'Reassignment', 'Discharge Summary'];

        if (in_array($action, $clear_actions)) {
            // ሀ. ስታተስ መቀየር (ከ Waiting Patients ዝርዝር ውስጥ እንዲወጣ)
            $new_status = ($action == 'Discharge Summary') ? 'Discharged' : 'Seen';
            $conn->query("UPDATE patients SET status = '$new_status' WHERE mrn = '$mrn'");

            // ለ. የቀጠሮውን ስታተስ መቀየር (ከ Today's Appt ዝርዝር ውስጥ እንዲወጣ)
            $conn->query("UPDATE appointments SET status = 'Completed' 
                          WHERE mrn = '$mrn' 
                          AND staff_id = '$staff' 
                          AND DATE(appointment_date) = CURDATE()");
        }

        echo json_encode(["status"=>"success"]);
    } else {
        // $stmt ባዶ ከሆነ ወይም execute ካላደረገ እዚህ ይገባል
        echo json_encode(["status"=>"error","error"=> ($stmt ? $stmt->error : "Action failed")]);
    }

} catch (Exception $e) {
    echo json_encode(["status"=>"error", "error"=>$e->getMessage()]);
}
?>