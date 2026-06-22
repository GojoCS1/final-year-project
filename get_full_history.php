<?php
include 'db.php';
header('Content-Type: application/json');

$mrn = $_GET['mrn'] ?? '';

$response = [
    'demographics' => null,
    'history' => []
];

if (!empty($mrn)) {

    // 1️⃣ Demographics
    $stmt = $conn->prepare("SELECT * FROM patients WHERE mrn = ?");
    $stmt->bind_param("s", $mrn);
    $stmt->execute();
    $response['demographics'] = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 2️⃣ Collect All History Tables
    $tables = [
        "patient_updates"     => "Update Info",
        "progress_notes"      => "Progress Note",
        "order_sheets"        => "Order Sheet",
        "lab_requests"        => "Lab Request",
        "medication_followup" => "Follow Up",
        "discharge_summaries" => "Discharge Summary",
        "prescriptions"       => "Prescription",
        "appointments"        => "Appointment",
        "referrals"           => "External Referral"
    ];

    foreach ($tables as $table => $label) {

        // ✅ Lab Requests — show "Doctor: Name" or "Nurse: Name" + lab tech name
        if ($table === 'lab_requests') {
            $sql = "SELECT lr.*, '$label' AS action_type,
                        CASE
                            WHEN doc.role = 'doctor' THEN CONCAT('Doctor: ', doc.full_name)
                            WHEN doc.role = 'nurse'  THEN CONCAT('Nurse: ',  doc.full_name)
                            ELSE COALESCE(doc.full_name, lr.ordered_by)
                        END AS ordered_by,
                        COALESCE(lab.full_name, lr.assigned_to_lab_tech) AS assigned_to_lab_tech
                    FROM lab_requests lr
                    LEFT JOIN users doc ON lr.ordered_by           = doc.staff_id
                    LEFT JOIN users lab ON lr.assigned_to_lab_tech = lab.staff_id
                    WHERE lr.mrn = ?";

        // ✅ Prescriptions — show pharmacist name, exclude is_read
        } elseif ($table === 'prescriptions') {
            $sql = "SELECT pr.id, pr.mrn, pr.medication_name, pr.dosage_instruction,
                        pr.status, pr.created_at, '$label' AS action_type,
                        COALESCE(ph.full_name, pr.assigned_to_pharmacist) AS assigned_to_pharmacist
                    FROM prescriptions pr
                    LEFT JOIN users ph ON pr.assigned_to_pharmacist = ph.staff_id
                    WHERE pr.mrn = ?";

        // Everything else — original unchanged
        } else {
            $sql = "SELECT *, '$label' AS action_type FROM $table WHERE mrn = ?";
        }

        $stmt2 = $conn->prepare($sql);
        if ($stmt2) {
            $stmt2->bind_param("s", $mrn);
            $stmt2->execute();
            $res = $stmt2->get_result();
            while ($row = $res->fetch_assoc()) {
                $response['history'][] = $row;
            }
            $stmt2->close();
        }
    }

    // Original sort — unchanged
    usort($response['history'], function($a, $b) {
        return strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0');
    });
}

// Original unread count — unchanged
$unread_sql = "SELECT COUNT(*) as unread FROM lab_requests WHERE mrn = ? AND status = 'Completed' AND is_read_lab = 0";
$unread_stmt = $conn->prepare($unread_sql);
$unread_stmt->bind_param("s", $mrn);
$unread_stmt->execute();
$response['unread_labs'] = $unread_stmt->get_result()->fetch_assoc()['unread'];

echo json_encode($response);
exit;
?>