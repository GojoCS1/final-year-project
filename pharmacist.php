<?php
include 'db.php';
// 1. System Lock Check
$lock_query = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'system_lock'");
$lock_data = $lock_query ? $lock_query->fetch_assoc() : ['setting_value' => '0'];

if ($lock_data['setting_value'] == '1') {
    session_start();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'system_admin') {
        echo "<body style='background:darkcyan; color:white; font-family:Arial; text-align:center; padding-top:100px;'>
                <h1>⛔ SYSTEM TEMPORARILY LOCKED</h1>
                <p>The Administrator has locked the system for maintenance.</p>
                <a href='login.html' style='color:greenyellow;'>Return to Login</a>
              </body>";
        exit();
    }
}

session_start();
if (!isset($_SESSION['staff_id']) || $_SESSION['role'] !== 'pharmacist') {
    header("Location: login.html"); exit();
}

$current_staff_id = $_SESSION['staff_id'];

// 2. Fetch User Profile
$user_query = $conn->prepare("SELECT * FROM users WHERE staff_id = ?");
$user_query->bind_param("s", $current_staff_id);
$user_query->execute();
$current_user = $user_query->get_result()->fetch_assoc();

// 3. Fetch Real Assigned Prescriptions
$query = "SELECT p.id, p.mrn, p.medication_name, p.dosage_instruction, p.status, pt.full_name 
          FROM prescriptions p 
          JOIN patients pt ON p.mrn = pt.mrn 
          WHERE p.assigned_to_pharmacist = ?
          ORDER BY p.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $current_staff_id);
$stmt->execute();
$result = $stmt->get_result();

$prescriptions = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $prescriptions[] = [
            'id' => $row['id'],
            'patient' => $row['full_name'],
            'mrn' => $row['mrn'],
            'medication' => $row['medication_name'],
            'dosage' => $row['dosage_instruction'],
            'status' => $row['status']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pharmacist Dashboard - Adigrat Hospital</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; display: flex; height: 100vh; }
        .sidebar { width: 250px; background-color: #329f92; color: white; display: flex; flex-direction: column; padding: 20px; box-shadow: 2px 0 5px rgba(0,0,0,0.2); }
        .sidebar h2 { font-size: 1.2rem; border-bottom: 1px solid white; padding-bottom: 10px; margin-bottom: 20px; }
        .nav-btn { background: #1c6e61; color: white; border: none; padding: 15px; margin-bottom: 10px; text-align: left; cursor: pointer; border-radius: 4px; font-weight: bold; transition: 0.3s;
                   /* ✅ Required for badge positioning */
                   position: relative; }
        .nav-btn:hover { background: #19ef9d; color: white; }
        .logout-btn { margin-top: auto; background: #0c0a0a; width: fit-content; align-self: center; padding: 10px 30px; position: static; }
        .logout-btn:hover { background: #f70000; color: white; }
        .main-content { flex: 1; padding: 40px; background-color: #f4f4f4; overflow-y: auto; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        .status-pending { color: orange; font-weight: bold; }
        .status-dispensed { color: green; font-weight: bold; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #333; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .action-btn { background: darkcyan; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        .dispense-btn { background: #28a745; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 4px; }
        .id-label-display { font-size: 18px; font-weight: bold; color: darkcyan; background: #e7f3f3; padding: 10px; border-radius: 4px; display: inline-block; border: 1px solid darkcyan; margin-bottom: 10px; }
        #profile-msg { margin-top: 10px; font-weight: bold; }

        /* ✅ Notification Badge — same pattern as system_admin.php */
        .badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 2px 7px;
            font-size: 11px;
            font-weight: bold;
            display: none;   /* hidden until count > 0 */
        }

        /* Password Toggle */
        .password-wrapper { position: relative; display: flex; align-items: center; }
        .password-wrapper input { padding-right: 40px; }
        .toggle-password { position: absolute; right: 10px; cursor: pointer; color: #555; user-select: none; font-size: 18px; }
        .toggle-password:hover { color: darkcyan; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>PHARMACY PANEL</h2>

        <!-- ✅ Notification badge added to Patient Orders button -->
        <button class="nav-btn" onclick="showOrders()">
            Patient Orders
            <span id="notif-badge" class="badge">0</span>
        </button>

        <button class="nav-btn" onclick="show('view')">View Prescriptions</button>
        <button class="nav-btn" onclick="show('my-schedule')">View My Schedule</button>
        <button class="nav-btn" onclick="show('profile')">Update Personal Profile</button>
        <button class="nav-btn logout-btn" onclick="logout()">Logout</button>
    </div>

    <div class="main-content">
        <div class="card" id="display-area">
            <h1>Welcome, Pharmacist <?php echo $current_user['full_name']; ?></h1>
            <p>Select "Patient Orders" to see incoming medications assigned to your ID.</p>
        </div>
    </div>

    <script>
        const prescriptions = <?php echo json_encode($prescriptions); ?>;

        // ✅ Wrapper: clears notifications THEN shows orders
        async function showOrders() {
            await fetch('clear_notif.php?type=prescriptions');
            checkNotifications(); // immediately update badge to 0
            show('Ordered');
        }

        function getIDHeader(patientId) {
            return patientId ? `<div class="id-label-display">Processing Prescription for MRN: ${patientId}</div>` :
                               `<p style="font-weight: bold; color: #555;">Select an ID to fulfill the order:</p>`;
        }

        function show(action, rxId = '') {
            const area = document.getElementById('display-area');

            if(action === 'Ordered') {
                let incoming = prescriptions.filter(p => p.status === "Pending");
                let idListHTML = incoming.map(p =>
                    `<button onclick="show('dispense', '${p.mrn}')" style="background:darkcyan; color:white; padding:5px 10px; border-radius:15px; margin-right:5px; font-size:0.8em; border:none; cursor:pointer;">${p.mrn}</button>`
                ).join('');

                area.innerHTML = `
                    <h1>Incoming Patient Orders</h1>
                    <div style="background:#e7f3f3; padding:15px; border-radius:8px; margin-bottom:20px;">
                        <h4 style="margin-top:0; color:#006666;">Incoming Prescription IDs (Assigned to you):</h4>
                        <div>${idListHTML || 'No pending orders for your ID.'}</div>
                    </div>
                    ${getIDHeader('')}
                `;
            }

            else if(action === 'view') {
                let rows = prescriptions.map(p => `
                    <tr>
                        <td>${p.mrn}</td>
                        <td>${p.patient}</td>
                        <td>${p.medication}</td>
                        <td class="status-${p.status.toLowerCase()}">${p.status}</td>
                    </tr>
                `).join('');

                area.innerHTML = `
                    <h1>Prescription History</h1>
                    <table>
                        <tr><th>MRN</th><th>Patient Name</th><th>Medication</th><th>Status</th></tr>
                        ${rows || '<tr><td colspan="4">No records found.</td></tr>'}
                    </table>
                `;
            }

            else if(action === 'dispense') {
                let displayData = rxId ? prescriptions.filter(p => p.mrn == rxId) : prescriptions.filter(p => p.status === "Pending");

                let rows = displayData.map(p => `
                    <tr>
                        <td>${p.mrn}</td>
                        <td>${p.patient}</td>
                        <td>${p.medication}</td>
                        <td>
                            ${p.status === 'Pending' ? `<button class="dispense-btn" onclick="processDispense('${p.id}')">Dispense</button>` : `<span class="status-dispensed">Received</span>`}
                        </td>
                    </tr>
                `).join('');

                area.innerHTML = `
                    <h1>Dispense Medication</h1>
                    ${rxId ? getIDHeader(rxId) : ''}
                    <table>
                        <tr><th>MRN</th><th>Patient</th><th>Medication</th><th>Action</th></tr>
                        ${rows || '<tr><td colspan="4">No pending prescriptions found.</td></tr>'}
                    </table>
                `;
            }

            else if(action === 'my-schedule') {
    area.innerHTML = `<h1>My Duty Schedule</h1><hr><div id="schedule-output"><p>Loading schedule...</p></div>`;
    
    // የፋርማሲስቱን ስም ከ PHP እንወስዳለን
    const pharmName = "<?php echo $current_user['full_name']; ?>"; 
    
    fetch(`get_my_schedule.php?name=${encodeURIComponent(pharmName)}`)
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


            else if(action === 'profile') {
        area.innerHTML = `
            <h1>Update Personal Profile</h1>
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
                <h3>change password</h3>
            <div class="form-group"><label>Enter Current Password</label><div class="password-wrapper"><input type="password" id="curr-pass"><span class="toggle-password" onclick="togglePass('curr-pass', this)">👁️</span></div></div>
            <div class="form-group"><label>Enter New Password</label><div class="password-wrapper"><input type="password" id="new-pass"><span class="toggle-password" onclick="togglePass('new-pass', this)">👁️</span></div></div>
            <div class="form-group"><label>Confirm New Password</label><div class="password-wrapper"><input type="password" id="confirm-pass"><span class="toggle-password" onclick="togglePass('confirm-pass', this)">👁️</span></div></div>
            <button class="action-btn" onclick="updateProfileLogic()">Update Profile & Password</button>
            <p id="profile-msg"></p>`;
    }
}

        async function processDispense(rxId) {
            if(!confirm("Mark this as dispensed?")) return;
            const formData = new FormData();
            formData.append('rx_id', rxId);
            try {
                const response = await fetch('dispense_med.php', { method: 'POST', body: formData });
                const result = await response.text();
                if(result.includes("Success")) {
                    alert("Medication Dispensed Successfully!");
                    const rxIndex = prescriptions.findIndex(p => p.id == rxId);
                    if (rxIndex !== -1) prescriptions[rxIndex].status = "Dispensed";
                    show('Ordered');
                } else {
                    alert("Error: " + result);
                }
            } catch (e) {
                alert("Connection error. Could not update status.");
            }
        }

        function isStrongPassword(password) {
    // ህጉ: 8+ chars, 1 lowercase, 1 UPPERCASE, 1 number, 1 symbol (@$!%*?&.)
    const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.]).{8,}$/;
    return regex.test(password);
}

        async function updateProfileLogic() {
            const phone = document.getElementById('prof-phone').value.trim();
            if (phone.length < 10 || phone.length > 15) {
                alert("Error: Phone number must be between 10 and 15 digits!");
                return;
            }
            const newPass = document.getElementById('new-pass').value;
            const confirmPass = document.getElementById('confirm-pass').value;
            const msg = document.getElementById('profile-msg');
            if (newPass !== "") {
                if (!isStrongPassword(newPass)) {
                    msg.innerText = "Password too weak! Must be 8+ characters with uppercase, lowercase, numbers, and symbols (e.g. Staff@123).";
                    msg.style.color = "red";
                    return;
                }
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

        // ✅ Notification polling — same pattern as system_admin.php
        async function checkNotifications() {
            try {
                const res = await fetch('get_notif_count.php');
                const data = await res.json();
                const badge = document.getElementById('notif-badge');
                if (data.count > 0) {
                    badge.innerText = data.count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            } catch (e) { console.error("Notification check failed."); }
        }

        // ✅ Poll every 10 seconds + run immediately on load
        setInterval(checkNotifications, 10000);
        window.onload = checkNotifications;

        function logout() { if(confirm("Logout?")) window.location.href = "index.php"; }

        function togglePass(inputId, iconElement) {
            const passwordInput = document.getElementById(inputId);
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                iconElement.textContent = "🙈";
            } else {
                passwordInput.type = "password";
                iconElement.textContent = "👁️";
            }
        }
    </script>
</body>
</html>