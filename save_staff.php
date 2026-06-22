<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sid      = strtoupper(trim($_POST['staff_id'])); // ✅ ከነበረው $sid = $_POST['staff_id'] ተቀይሯል
    $full_name = $_POST['full_name'];
    $gender   = $_POST['gender'] ?? 'other';
    $age      = $_POST['age'];
    $phone    = $_POST['phone'];
    $email    = $_POST['email'];
    $role     = $_POST['role'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    // ✅ አዲስ — Staff ID Validation (password check ከመምጣቱ በፊት)
    // 1. Format check
    if (!preg_match('/^ADH_\d{4}$/', $sid)) {
        echo "<script>alert('Invalid ID format! Must be ADH_XXXX (e.g. ADH_0008)'); history.back();</script>";
        exit();
    }

    // 2. Already taken check
    $chk = $conn->prepare("SELECT id FROM users WHERE staff_id = ?");
    $chk->bind_param("s", $sid);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        echo "<script>alert('Error: Staff ID $sid is already in use!'); history.back();</script>";
        exit();
    }

    // 3. Skip check
    $max_q = $conn->query("SELECT staff_id FROM users WHERE staff_id LIKE 'ADH_%' ORDER BY CAST(SUBSTRING(staff_id, 5) AS UNSIGNED) DESC LIMIT 1");
    $max_num = 0;
    if ($max_q && $max_q->num_rows > 0) {
        $max_num = (int)str_replace("ADH_", "", $max_q->fetch_assoc()['staff_id']);
    }
    $entered_num = (int)str_replace("ADH_", "", $sid);
    if ($entered_num > $max_num + 1) {
        $next = "ADH_" . str_pad($max_num + 1, 4, "0", STR_PAD_LEFT);
        echo "<script>alert('Cannot skip IDs! Next available new ID is $next'); history.back();</script>";
        exit();
    }
    // ✅ --- Validation End ---

    // የፓስዎርድ ጥንካሬ ማረጋገጫ
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.]).{8,}$/', $password)) {
        echo "<script>
                alert('Error: Password too weak! Must be at least 8+ characters with letters, numbers, and symbols.');
                window.history.back();
              </script>";
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (staff_id, full_name, gender, age, phone, email, role, username, password) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssisssss", $sid, $full_name, $gender, $age, $phone, $email, $role, $username, $hashed_password);

    if ($stmt->execute()) {
        echo "<script>alert('Staff Account Created Successfully!'); window.location.href='system_admin.php?tab=create';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>