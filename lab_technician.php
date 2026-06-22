<?php
include 'db.php';
// Check if the System Admin has locked the system
$lock_query = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'system_lock'");
$lock_data = $lock_query->fetch_assoc();

if ($lock_data['setting_value'] == '1') {
    // Block everyone except System Admin
    session_start();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'system_admin') {
        echo "<body style='background:darkcyan; color:white; font-family:Arial; text-align:center; padding-top:100px;'>
                <h1>⛔ SYSTEM TEMPORARILY LOCKED</h1>
                <p>The Administrator has locked the system for maintenance or security reasons.</p>
                <p>Please contact the System Administrator to continue.</p>
                <br><a href='login.html' style='color:greenyellow;'>Return to Login</a>
              </body>";
        exit();
    }
}
?>

<?php
include 'db.php';
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['staff_id']) || $_SESSION['role'] !== 'lab_technician') {
    header("Location: login.html"); 
    exit();
}

$current_staff_id = $_SESSION['staff_id'];

// 1. Fetch current Technician details for the Profile section
$user_query = $conn->prepare("SELECT * FROM users WHERE staff_id = ?");
$user_query->bind_param("s", $current_staff_id);
$user_query->execute();
$current_user = $user_query->get_result()->fetch_assoc();

// 2. Fetch all Pending Lab Requests from the database
$query = "SELECT lr.id, lr.mrn, lr.test_type, lr.status, lr.created_at, lr.ordered_by, lr.notes, p.full_name 
          FROM lab_requests lr 
          JOIN patients p ON lr.mrn = p.mrn 
          WHERE lr.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
          ORDER BY lr.created_at DESC";
$result = $conn->query($query);

$requests = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Laboratory Dashboard - Adigrat Hospital</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; display: flex; height: 100vh; background-color: #f4f4f4; }
        .sidebar { width: 260px; background-color: #329f92; color: white; display: flex; flex-direction: column; padding: 20px; box-shadow: 2px 0 10px rgba(0,0,0,0.2); }
        .sidebar h2 { font-size: 1.1rem; text-align: center; border-bottom: 2px solid greenyellow; padding-bottom: 15px; margin-bottom: 20px; letter-spacing: 1px; }
        .nav-btn { background: #1c6e61; color: white; border: none; padding: 14px; margin-bottom: 10px; text-align: left; cursor: pointer; border-radius: 5px; font-weight: bold; transition: 0.3s; font-size: 14px; }
        .nav-btn:hover { background: #19ef9d; color: white; }
        .active { background: #e0f2f1 !important; color: #004d40 !important; border-left: 5px solid #004d40;}
        .logout-btn { margin-top: auto; background: #0c0a0a; width: fit-content; align-self: center; padding: 10px 30px;}
        .logout-btn:hover { background: #f70000;; color: white; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .workspace-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); min-height: 450px; }
        h1 { color: darkcyan; margin-top: 0; }
        hr { border: 0; border-top: 1px solid #eee; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f8f8f8; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .submit-btn { background: darkcyan; color: white; border: none; padding: 12px 25px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        .action-btn { background: darkcyan; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        .id-label-display { font-size: 18px; font-weight: bold; color: darkcyan; background: #e7f3f3; padding: 10px; border-radius: 4px; display: inline-block; border: 1px solid darkcyan; margin-bottom: 20px; }
        #profile-msg { margin-top: 10px; font-weight: bold; }
    
        /* የአይን ምልክት መቀመጫ */
.pass-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}
.pass-wrapper input {
    width: 100%;
    padding-right: 40px !important;
}
.eye-icon {
    position: absolute;
    right: 10px;
    cursor: pointer;
    font-size: 18px;
    user-select: none;
    color: #555;
}
.badge {
    background-color: red;
    color: white;
    border-radius: 50%;
    padding: 2px 7px;
    font-size: 11px;
    font-weight: bold;
    display: none; /* ቁጥር ከሌለ ይደበቃል */
    margin-left: 5px;
}
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>LAB PANEL</h2>
        <button id="req-btn" class="nav-btn" onclick="showContent('requests', this)">
    View Lab Requests <span id="lab-badge" class="badge">0</span>
</button>
<button class="nav-btn" onclick="showContent('my-schedule', this)">View My Schedule</button>
<button class="nav-btn" onclick="showContent('profile', this)">Update Personal Profile</button> 
        <button class="nav-btn logout-btn" onclick="logout()">Logout</button>
    </div>

    <div class="main-content">
        <div class="workspace-card" id="display-area">
            <h1>Welcome, <?php echo $current_user['full_name']; ?></h1>
            <hr>
            <p>Laboratory Information System. Select "View Lab Requests" to manage pending tests.</p>
        </div>
    </div>

    <script>
        const labRequests = <?php echo json_encode($requests); ?>;

        function showContent(task, btn, requestId = '', mrn = '') {
            if (btn) {
                let buttons = document.getElementsByClassName("nav-btn");
                for (let b of buttons) { b.classList.remove("active"); }
                btn.classList.add("active");
            }

            const area = document.getElementById('display-area');

            if (task === 'requests') {
    let pending = labRequests.filter(r => r.status !== 'Completed');
    let processed = labRequests.filter(r => r.status === 'Completed');

    function createTableRows(list, isNew) {
        return list.map(r => `
            <tr>
                <td style="font-weight:bold; color:darkcyan;">STAFF_ID: ${r.ordered_by}</td> <!-- የዶክተሩ/ነርሱ ID እዚህ ገብቷል -->
                <td>${r.full_name} (${r.mrn})</td>
                <td>${r.test_type}</td>
                <td><b style="color:${isNew ? 'red' : 'green'};">${isNew ? 'NEW' : 'PROCESSED'}</b></td>
                <td>
                    ${isNew ? `<button class="submit-btn" onclick="showContent('results', null, '${r.id}', '${r.mrn}')">Process Now</button>` : `<span style="color:gray;">Completed</span>`}
                </td>
            </tr>
        `).join('');
    }

    area.innerHTML = `
        <h1>Lab Test Requests</h1><hr>
        <h3 style="color:red;">🔴 New Requests (Ordered by Doctor ID)</h3>
        <table>
            <thead><tr><th>Ordered By (ID)</th><th>Patient Name</th><th>Test Type</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>${createTableRows(pending, true) || '<tr><td colspan="5">No new requests.</td></tr>'}</tbody>
        </table>

        <br><hr style="border: 4px solid darkcyan; margin: 30px 0;"> <!-- ደማቅ መለያ መስመር -->

        <h3 style="color:darkcyan;">✅ Processed Requests (Last 24h)</h3>
        <table>
            <thead><tr><th>Ordered By (ID)</th><th>Patient Name</th><th>Test Type</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>${createTableRows(processed, false) || '<tr><td colspan="5">No processed requests.</td></tr>'}</tbody>
        </table>`;
} 
else if (task === 'results') {
    area.innerHTML = `
        <h1>Enter Lab Results</h1><hr>
        <div class="id-label-display">Processing MRN: ${mrn}</div>
        <form id="labResultForm">
            <input type="hidden" id="res_id" value="${requestId}">
            <div class="form-group">
                <label>Test Result Value (Text):</label>
                <textarea id="res_val" placeholder="Type final results here..."></textarea>
            </div>
            <div class="form-group">
                <label>Upload X-Ray/Image (Optional):</label>
                <input type="file" id="res_image" accept="image/*">
            </div>
            <div class="form-group">
                <label>Technician Notes (Visible to Doctor):</label> <!-- ኖት እዚህ ይሞላል -->
                <textarea id="res_notes" placeholder="Add specific observations..."></textarea>
            </div>
            <button type="button" class="submit-btn" onclick="submitLabResults()">Submit & Complete</button>
            <button type="button" class="action-btn" style="background:#666; margin-left:10px;" onclick="showContent('requests', document.getElementById('req-btn'))">Back</button>
        </form>`;
}

else if (task === 'my-schedule') {
    area.innerHTML = `<h1>My Duty Schedule</h1><hr><div id="schedule-output"><p>Loading schedule...</p></div>`;
    
    // የላብ ባለሙያውን ስም ከ PHP እንወስዳለን
    const techName = "<?php echo $current_user['full_name']; ?>"; 
    
    fetch(`get_my_schedule.php?name=${encodeURIComponent(techName)}`)
        .then(res => res.json())
        .then(data => {
            const out = document.getElementById('schedule-output');
            if(data.length === 0) {
                out.innerHTML = "<div style='padding:20px; background:#fff3cd; color:#856404; border-radius:5px;'>No schedule found for you at this time.</div>";
                return;
            }
    
let rows = data.map(s => `
    <tr>
        <td style="padding:10px; border:1px solid #000; font-weight:bold; color:darkcyan;">${s.shift_day}</td>
        <td style="padding:10px; border:1px solid #000;">${s.shift_time}</td>
        <td style="padding:10px; border:1px solid #000;">${s.assigned_area || '-'}</td>
        <td style="padding:10px; border:1px solid #000; font-weight:bold;">${s.room || '-'}</td>
    </tr>`).join('');

out.innerHTML = `
    <div style="background:white; padding:15px; border-radius:8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
        <table style="width:100%; border-collapse:collapse; border: 2px solid #000;">
            <thead>
                <tr style="background:#f9f9f9;">
                    <th style="padding:10px; border:1px solid #000; text-align:left;">Day</th>
                    <th style="padding:10px; border:1px solid #000; text-align:left;">Time / Shift</th>
                    <th style="padding:10px; border:1px solid #000; text-align:left;">Assigned Area</th>
                    <th style="padding:10px; border:1px solid #000; text-align:left;">Room</th>
                </tr>
            </thead>
            <tbody>
                ${rows}
            </tbody>
        </table>
    </div>`;
        });
}

            // --- UPDATED PROFILE SECTION ---
            else if (task === 'profile') {
    area.innerHTML = `
        <h1>Update Personal Profile</h1><hr>
        <div style="margin-bottom:20px; line-height:1.6; background:#f9f9f9; padding:15px; border-radius:5px;">
    <p><strong>Staff ID:</strong> <?php echo $current_user['staff_id']; ?></p>
    <p><strong>Full Name:</strong> <?php echo htmlspecialchars($current_user['full_name']); ?></p>
    <p><strong>Gender:</strong> <?php echo ucfirst($current_user['gender']); ?></p>
    <p><strong>Age:</strong> <?php echo $current_user['age']; ?></p>
    <p><strong>Username:</strong> <?php echo htmlspecialchars($current_user['username']); ?></p>
</div>

<!-- ለ JS logic አስፈላጊ ስለሆነ ስሙን በ hidden እንይዘዋለን -->
<input type="hidden" id="prof-name" value="<?php echo htmlspecialchars($current_user['full_name']); ?>">

<div class="form-group">
    <label>Phone Number:</label>
    <input type="text" id="prof-phone" value="<?php echo $current_user['phone']; ?>" 
           oninput="this.value = this.value.replace(/[^0-9+]/g, '')" maxlength="15">
</div>
<div class="form-group">
    <label>Email Address:</label>
    <input type="email" id="prof-email" value="<?php echo htmlspecialchars($current_user['email']); ?>">
</div>
            <hr>
            <h3>Change Password</h3>
            
            <div class="form-group">
                <label>1. Enter Current Password:</label>
                <div class="pass-wrapper">
                    <input type="password" id="curr-pass">
                    <span class="eye-icon" onclick="toggleVisibility('curr-pass', this)">👁️</span>
                </div>
            </div>

            <div class="form-group">
                <label>2. Enter New Password:</label>
                <div class="pass-wrapper">
                    <input type="password" id="new-pass">
                    <span class="eye-icon" onclick="toggleVisibility('new-pass', this)">👁️</span>
                </div>
            </div>

            <div class="form-group">
                <label>3. Confirm New Password:</label>
                <div class="pass-wrapper">
                    <input type="password" id="confirm-pass">
                    <span class="eye-icon" onclick="toggleVisibility('confirm-pass', this)">👁️</span>
                </div>
            </div>

            <button class="action-btn" style="width:100%;" onclick="updateProfileLogic()">4. Save Changes</button>
            <p id="profile-msg"></p>
        </div>`;
}
        }

        // 1. የፓስዎርድ ጥንካሬን ቼክ ማድረጊያ (ሁሉንም መስፈርት ማሟላቱን ያያል)
function isStrongPassword(password) {
    // ህጉ: 8+ chars, 1 lowercase, 1 UPPERCASE, 1 number, 1 symbol (@$!%*?&.)
    const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.]).{8,}$/;
    return regex.test(password);
}

// 2. የProfile Update 
async function updateProfileLogic() {
    const phone = document.getElementById('prof-phone').value.trim();
    
    // የስልክ ቁጥር ርዝመት ቼክ (ከ 10 እስከ 15 ካልሆነ ያቆመዋል)
    if (phone.length < 10 || phone.length > 15) {
        alert("Error: Phone number must be between 10 and 15 digits!");
        return; // እዚህ ጋር ይቆማል፣ ወደ ዳታቤዝ አይልክም
    }
    
    const newPass = document.getElementById('new-pass').value;
    const confirmPass = document.getElementById('confirm-pass').value;
    const msg = document.getElementById('profile-msg');

    // አዲስ ፓስዎርድ ከተሞላ ብቻ ቼክ ያደርጋል
    if (newPass !== "") {
        // የጥንካሬ ቼክ
        if (!isStrongPassword(newPass)) {
            msg.innerText = "Password too weak! Must be 8+ characters with uppercase, lowercase, numbers, and symbols (e.g. Staff@123).";
            msg.style.color = "red";
            return;
        }
        // ከConfirm ፓስዎርድ ጋር መመሳሰሉን ቼክ ማድረግ
        if (newPass !== confirmPass) {
            msg.innerText = "New password and Confirm password do not match!";
            msg.style.color = "red";
            return;
        }
    }

    const formData = new FormData();
    formData.append('name', document.getElementById('prof-name').value);
    formData.append('phone', document.getElementById('prof-phone').value);
    formData.append('email', document.getElementById('prof-email').value);
    formData.append('curr_pass', document.getElementById('curr-pass').value);
    formData.append('new_pass', newPass);

    try {
        const res = await fetch('update_profile_process.php', { method: 'POST', body: formData });
        const result = await res.json();
        if(result.status === "success") { 
            alert("Profile Updated Successfully!"); 
            location.reload(); 
        } else { 
            msg.innerText = result.message; 
            msg.style.color = "red";
        }
    } catch (e) {
        msg.innerText = "Connection error. Please try again.";
        msg.style.color = "red";
    }
}

        async function submitLabResults() {
    const reqId = document.getElementById('res_id').value;
    const val = document.getElementById('res_val').value;
    const notes = document.getElementById('res_notes').value;
    const imageFile = document.getElementById('res_image').files[0]; // ፋይሉን ለመያዝ

    if(!val && !imageFile) { alert("Please enter a result or upload an image"); return; }
    
    const formData = new FormData();
    formData.append('id', reqId);
    formData.append('result', val);
    formData.append('notes', notes);
    if(imageFile) {
        formData.append('result_image', imageFile);
    }

    const response = await fetch('update_lab_result.php', { method: 'POST', body: formData });
    const text = await response.text();
    if(text.includes("Success")) {
        alert("Lab Results and Image Submitted Successfully!");
        location.reload();
    } else { alert("Error: " + text); }
}
        function logout() { if(confirm("Confirm Logout?")) window.location.href = "index.php"; }
    
        function toggleVisibility(inputId, iconElement) {
    const inputField = document.getElementById(inputId);
    if (inputField.type === "password") {
        inputField.type = "text";
        iconElement.textContent = "🙈"; 
    } else {
        inputField.type = "password";
        iconElement.textContent = "👁️"; 
    }
}
// 1. የኖቲፊኬሽን ቁጥሩን በየ 5 ሰከንዱ ከ ሰርቨር የሚያመጣ ፋንክሽን
async function updateLabBadge() {
    try {
        const res = await fetch('get_notif_count.php'); // አንተ የሰጠኸኝ ፋይል ስም
        const data = await res.json();
        const badge = document.getElementById('lab-badge');
        
        if (data.count > 0) {
            badge.innerText = data.count;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    } catch (e) { console.log("Notification load error"); }
}

// 2. በተኑ ሲነካ ቁጥሩ እንዲጠፋ የሚያደርግ
async function clearLabNotif() {
    await fetch('clear_notif.php?type=lab_requests'); // አንተ የሰጠኸኝ ፋይል ስም
    updateLabBadge();
}

// 3. showContent ሲጠራ ኖቲፊኬሽኑ እንዲጠፋ ማድረግ
const oldShowContent = showContent;
showContent = function(task, btn, requestId = '', mrn = '') {
    if (task === 'requests') {
        clearLabNotif(); // እዚህ ጋር ቁጥሩን ያጠፋዋል
    }
    oldShowContent(task, btn, requestId, mrn);
};

// ገጹ ሲከፈት እና በየ 5 ሰከንዱ እንዲቆጥር ማድረግ
setInterval(updateLabBadge, 5000);
window.onload = updateLabBadge;
    </script>
</body>
</html>