<?php
include 'db.php';
header('Content-Type: application/json');

$query_param = isset($_GET['query']) ? $_GET['query'] : '';

if (empty($query_param)) {
    echo json_encode(['error' => 'No search term provided']);
    exit();
}

$search_term = "%$query_param%";
$sql = "SELECT p.*, u.full_name AS doc_name, u.role AS staff_role, p.assigned_by
        FROM patients p 
        LEFT JOIN users u ON p.assigned_to_dept = u.staff_id 
        WHERE p.mrn = ? OR p.phone = ? OR p.full_name LIKE ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $query_param, $query_param, $search_term);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'No patient found with that info.']);
    exit();
} elseif ($result->num_rows > 1) {
    $patients = [];
    while($row = $result->fetch_assoc()) $patients[] = $row;
    echo json_encode($patients);
    exit();
}

$patient = $result->fetch_assoc();

// *** Appointments — table ስምህን ንገረኝ ***
$appt_stmt = $conn->prepare("
    SELECT a.id AS appointment_id, 
           DATE_FORMAT(a.appointment_date, '%Y-%m-%d %H:%i') AS date, 
           u.full_name AS staff_name
    FROM appointments a
    JOIN users u ON a.staff_id = u.staff_id
    WHERE a.mrn = ?
    ORDER BY a.appointment_date ASC
");
if ($appt_stmt) {
    $appt_stmt->bind_param("s", $patient['mrn']);
    $appt_stmt->execute();
    $appt_result = $appt_stmt->get_result();
    $appointments = [];
    while ($row = $appt_result->fetch_assoc()) $appointments[] = $row;
    $patient['appointments'] = $appointments;
} else {
    // Table ካልተገኘ empty array ብቻ ይመልሳል — search አይሰበርም
    $patient['appointments'] = [];
}

echo json_encode($patient);
?>