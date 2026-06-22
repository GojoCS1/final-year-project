<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $result = $_POST['result'];
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
    $image_path = NULL;

    // 1. ምስል ካለ ወደ uploads ፎልደር መጫን
    if (isset($_FILES['result_image']) && $_FILES['result_image']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) { 
            mkdir($target_dir, 0777, true); 
        }
        
        $file_name = time() . "_" . basename($_FILES["result_image"]["name"]);
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES["result_image"]["tmp_name"], $target_file)) {
            $image_path = $target_file;
        }
    }

    // 2. ዳታቤዙን ማደስ
    if ($image_path != NULL) {
        // ምስል ሲኖር
        $sql = "UPDATE lab_requests SET result = ?, result_image = ?, notes = ?, status = 'Completed' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $result, $image_path, $notes, $id);
    } else {
        // ምስል ሳይኖር ሲቀሩ (የቆየውን ምስል እንዳያጠፋው result_image እዚህ ጋር አንነካውም)
        $sql = "UPDATE lab_requests SET result = ?, notes = ?, status = 'Completed', is_read_lab = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $result, $notes, $id);
    }

    if ($stmt->execute()) {
        echo "Success";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>