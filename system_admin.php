<?php
include 'db.php'; // Uses Port 3307
session_start();

// Redirect if not logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'system_admin') {
    header("Location: login.html"); 
    exit();
}

$current_staff_id = $_SESSION['staff_id'];

// 1. Fetch current admin details for the Profile section
$user_query = $conn->prepare("SELECT * FROM users WHERE staff_id = ?");
$user_query->bind_param("s", $current_staff_id);
$user_query->execute();
$current_user = $user_query->get_result()->fetch_assoc();

// 2. Fetch System Settings (Lock and Backup status)
$settings_query = $conn->query("SELECT * FROM system_settings");
$sys = [];
if($settings_query) {
    while($row = $settings_query->fetch_assoc()) {
        $sys[$row['setting_key']] = $row['setting_value'];
    }
}
$isBackup = $sys['backup_status'] ?? '0';
$isLocked = $sys['system_lock'] ?? '0';

// 3. Fetch all staff for the "Delete" and "Reset" tables
$staff_query = $conn->query("SELECT id, full_name, role, staff_id FROM users WHERE role != 'system_admin'");
$staff_list = [];
if ($staff_query) {
    while($row = $staff_query->fetch_assoc()) {
        $staff_list[] = $row;
    }
}

// 4. Fetch commands from Staff Admin
$command_query = $conn->query("SELECT * FROM admin_commands ORDER BY created_at DESC");
$access_query = $conn->query("SELECT staff_id, full_name, gender, age, phone, email, role FROM users WHERE role != 'system_admin'");
$access_staff_list = [];
if ($access_query) {
    while($row = $access_query->fetch_assoc()) {
        $access_staff_list[] = $row;
    }
}
$commands = [];
if ($command_query) {
    while($row = $command_query->fetch_assoc()) {
        $commands[] = $row;
    }
}

// 5. Get list of staff IDs requested for DELETION
$requested_deletions = [];
$req_del_q = $conn->query("SELECT target_staff_id FROM admin_commands WHERE command_type = 'Delete Account' AND (reply IS NULL OR reply != 'Already Deleted')");
if($req_del_q) {
    while($r = $req_del_q->fetch_assoc()) {
        $requested_deletions[] = $r['target_staff_id'];
    }
}

// 6. Get list of staff IDs requested for PASSWORD RESET
$reset_requests = [];
$req_res_q = $conn->query("
    SELECT target_staff_id, command_type 
    FROM admin_commands 
    WHERE command_type IN ('Reset Password', 'Reset Username', 'Reset Username and Password') 
    AND (reply IS NULL OR reply NOT IN ('Password Reset Successfully', 'Username Reset Successfully', 'Credentials Reset Successfully'))
    ORDER BY created_at DESC
");
if($req_res_q) {
    while($r = $req_res_q->fetch_assoc()) {
        // ተመሳሳይ staff ID ቢኖር የኋለኛው ብቻ ይያዛል
        if (!isset($reset_requests[$r['target_staff_id']])) {
            $reset_requests[$r['target_staff_id']] = $r['command_type'];
        }
    }
}

$resettableIds = array_keys($reset_requests); // ለ backward compatibility
$id_query = $conn->query("SELECT staff_id FROM users WHERE staff_id LIKE 'ADH_%' ORDER BY staff_id DESC LIMIT 1");
$next_staff_id = "ADH_0001";
$max_staff_number = 0;
if ($id_query && $id_query->num_rows > 0) {
    $last_id = $id_query->fetch_assoc()['staff_id'];
    $max_staff_number = (int)str_replace("ADH_", "", $last_id);
    $next_staff_id = "ADH_" . str_pad($max_staff_number + 1, 4, "0", STR_PAD_LEFT);
}

$all_ids_query = $conn->query("SELECT staff_id FROM users WHERE staff_id LIKE 'ADH_%'");
$all_staff_ids = [];
while($row = $all_ids_query->fetch_assoc()) {
    $all_staff_ids[] = $row['staff_id'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Admin - Adigrat Hospital</title>
    <style>
        /* ALL ORIGINAL STYLES PRESERVED 100% */
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            display: flex;
            height: 100vh;
            background-color: #f4f4f4;
        }

        .sidebar {
            width: 260px;
            background-color: #329f92;
            color: white;
            display: flex;
            flex-direction: column;
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.2);
        }

        .sidebar h2 {
            font-size: 1.1rem;
            text-align: center;
            border-bottom: 2px solid greenyellow;
            padding-bottom: 15px;
            margin-bottom: 20px;
            letter-spacing: 1px;
        }

        .nav-btn {
            background: #1c6e61;
            color: white;
            border: none;
            padding: 14px;
            margin-bottom: 10px;
            text-align: left;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
            transition: 0.3s;
            font-size: 14px;
        }

        .nav-btn:hover { background: #19ef9d; color: white; }
        .btn-danger { color: #ffffff; }
        .btn-danger:hover { background: #ff4444; color: white; }
        .active { background: #e0f2f1 !important; color: #004d40 !important; border-left: 5px solid #004d40;}
        .logout-btn { margin-top: auto; background: #0c0a0a; width: fit-content; align-self: center; padding: 10px 30px;}
        .logout-btn:hover { background: #f70000;; color: white; }
        .main-content {
            flex: 1;
            padding: 40px;
            overflow-y: auto;
        }

        .workspace-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            min-height: 450px;
        }

        h1 { color: darkcyan; margin-top: 0; }
        hr { border: 0; border-top: 1px solid #eee; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .action-btn { background: darkcyan; color: white; border: none; padding: 12px 25px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        .action-btn:hover { background: #005a5a; }
    
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
.nav-btn { position: relative; }

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
    display: none;
}

    </style>
</head>
<body>

    <div class="sidebar">
        <h2>SYSTEM ADMIN</h2>
        <button id="nav-create" class="nav-btn" onclick="showContent('create', this)">Create Staff Account</button>
        <button id="nav-access" class="nav-btn" onclick="showContent('access', this)">Access Staff</button>
        <button id="nav-control" class="nav-btn" onclick="showContent('control', this)">Control System</button>
        <button id="nav-commands" class="nav-btn" onclick="showContent('commands', this)">
    Receive Commands
    <span id="notif-badge" class="badge">0</span>
</button>
        <button id="nav-delete" class="nav-btn btn-danger" onclick="showContent('delete', this)">Delete Account</button>
        <button id="nav-reset" class="nav-btn" onclick="showContent('reset', this)">Reset Account</button>
        <button id="nav-profile" class="nav-btn" onclick="showContent('profile', this)">Update Profile</button>
        <button class="nav-btn logout-btn" onclick="logout()">Logout</button>
    </div>

    <div class="main-content">
        <div class="workspace-card" id="display-area">
    <h1>Welcome, <?php echo htmlspecialchars($current_user['full_name']); ?></h1>
    <hr>
    <p>You have full system root access to manage hospital staff accounts, control system security, and handle administrative configurations.</p>
    <p>Please select an action from the left sidebar to begin.</p>
</div>
    </div>

    <script>
       
        // 1. የፓስዎርድ ጥንካሬን ቼክ ማድረጊያ (Regex)
function isStrongPassword(password) {
    // ህጉ: 8+ chars, 1 lowercase, 1 UPPERCASE, 1 number, 1 symbol (@$!%*?&.)
    const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.]).{8,}$/;
    return regex.test(password);
}

// 2. የ Reset Password ማስተካከያ
async function processFinalReset(sid, resetType) {
    const unameEl = document.getElementById('uname_' + sid);
    const passEl  = document.getElementById('pass_' + sid);
    const newUser = unameEl ? unameEl.value.trim() : '';
    const newPass = passEl  ? passEl.value.trim()  : '';

    // Username validation
    if ((resetType === 'Reset Username' || resetType === 'Reset Username and Password') && !newUser) {
        alert("Please enter a new username!"); return;
    }
    // Password validation
    if ((resetType === 'Reset Password' || resetType === 'Reset Username and Password') && !newPass) {
        alert("Please enter a new password!"); return;
    }

    const f = new FormData();
    f.append('staff_id',   sid);
    f.append('reset_type', resetType);
    if (newUser) f.append('new_username', newUser);
    if (newPass) f.append('new_password', newPass);

    const res = await fetch('reset_account_process.php', { method: 'POST', body: f }); // አዲስ file
    const r   = await res.json();
    if (r.status === "success") {
        alert("Reset successful!");
        location.href = "system_admin.php?tab=reset";
    } else {
        alert("Error: " + r.message);
    }
}

// 3. የ Profile Update ማስተካከያ
async function updateProfileLogic() {
    const phone = document.getElementById('prof-phone').value.trim();
    
    // የስልክ ቁጥር ርዝመት ቼክ (ከ 10 እስከ 15 ካልሆነ ያቆመዋል)
    if (phone.length < 10 || phone.length > 15) {
        alert("Error: Phone number must be between 10 and 15 digits!");
        return; // እዚህ ጋር ይቆማል፣ ወደ ዳታቤዝ አይልክም
    }
    
    const new_pass = document.getElementById('new-pass').value;
    const confirm_pass = document.getElementById('confirm-pass').value;

    // አዲስ ፓስዎርድ ከተሞላ ብቻ ቼክ ያደርጋል
    if (new_pass !== "") {
        if (!isStrongPassword(new_pass)) {
            const msg = document.getElementById('profile-msg');
            msg.innerText = "Password too weak! Must be 8+ characters with uppercase, lowercase, numbers, and symbols (e.g. Staff@123).";
            msg.style.color = "red";
            return;
        }
        if (new_pass !== confirm_pass) {
            alert("New passwords do not match!");
            return;
        }
    }

    const f = new FormData();
    f.append('name', document.getElementById('prof-name').value);
    f.append('phone', document.getElementById('prof-phone').value);
    f.append('email', document.getElementById('prof-email').value);
    f.append('curr_pass', document.getElementById('curr-pass').value);
    f.append('new_pass', new_pass);

    const res = await fetch('update_profile_process.php', { method: 'POST', body: f });
    const r = await res.json();
    if(r.status === "success") { 
        alert("Profile Updated!");
        location.href = "system_admin.php?tab=profile"; 
    } 
    else { 
        document.getElementById('profile-msg').innerText = r.message; 
        document.getElementById('profile-msg').style.color="red"; 
    }
}

const nextStaffId   = "<?php echo $next_staff_id; ?>";
const maxStaffNum   = <?php echo $max_staff_number; ?>;
const takenIds      = <?php echo json_encode($all_staff_ids); ?>;

function validateStaffId(inputId) {
    const val = inputId.trim().toUpperCase();
    const msgEl = document.getElementById('staff_id_msg');

    // 1. Format check
    if (!/^ADH_\d{4}$/.test(val)) {
        msgEl.innerText = "❌ Format: ADH_XXXX (e.g. ADH_0008)";
        msgEl.style.color = "red";
        return false;
    }

    const num = parseInt(val.replace("ADH_", ""));

    // 2. Already taken check
    if (takenIds.includes(val)) {
        msgEl.innerText = "❌ This ID is already in use!";
        msgEl.style.color = "red";
        return false;
    }

    // 3. Skip check — max+1 ን ማለፍ አይፈቀድም
    if (num > maxStaffNum + 1) {
        msgEl.innerText = `❌ Cannot skip! Next available new ID is ADH_${String(maxStaffNum + 1).padStart(4,'0')}`;
        msgEl.style.color = "red";
        return false;
    }

    msgEl.innerText = "✅ ID is available!";
    msgEl.style.color = "green";
    return true;
}
        const staffData = <?php echo json_encode($staff_list); ?>;
        const commandsData = <?php echo json_encode($commands); ?>;
        const accessStaffData = <?php echo json_encode($access_staff_list); ?>;
        const deletableIds = <?php echo json_encode($requested_deletions); ?>;
        const resettableIds  = <?php echo json_encode($resettableIds); ?>;
        const resetRequests  = <?php echo json_encode($reset_requests); ?>;
        
        let backupStatus = "<?php echo $isBackup; ?>";
        let lockStatus = "<?php echo $isLocked; ?>";

        async function showContent(task, btn, filterId = '') {
    let buttons = document.getElementsByClassName("nav-btn");
    for (let b of buttons) { b.classList.remove("active"); }
    if(btn) btn.classList.add("active");

    // አዲስ የተጨመረ፡ URL እንዲቀየር ያደርጋል (Back ሲባል ታቡ እንዳይጠፋ)
    // window.history.replaceState(null, null, "?tab=" + task);

    const area = document.getElementById('display-area');

    if (task === 'create') {
        area.innerHTML = `
            <h1>Create Staff Account</h1>
            <hr>
            <form action="save_staff.php" method="POST" onsubmit="return validateStaffForm()">
                <div class="form-group">
    <label>Staff ID (Auto-Generated):</label>
<input type="text" id="staff_id_input" name="staff_id" value="${nextStaffId}" 
       oninput="validateStaffId(this.value)"
       style="font-weight:bold; color:#1a237e; border:2px solid darkcyan; text-transform:uppercase;">
<small id="staff_id_msg" style="display:block; margin-top:4px;"></small>

                <div class="form-group"><label>Full Name:</label><input type="text" name="full_name" placeholder="Enter Full Name" required></div>
                <div class="form-group" style="display: flex; align-items: center;"><label style="margin-right: 15px;">Gender:</label>
                <div style="display: flex; gap: 10px;">
                    <input type="radio" id="male" name="gender" value="male"><label for="male">Male</label>
                    <input type="radio" id="female" name="gender" value="female"><label for="female">Female</label>
                    <input type="radio" id="other" name="gender" value="other"><label for="other">Other</label>
                </div></div>
                <div class="form-group"><label>Age:</label><input type="number" name="age" placeholder="Enter Age"></div>
                <div class="form-group">
    <label>Phone Number:</label>
    <input type="text" id="staff_phone" name="phone" 
           oninput="this.value = this.value.replace(/[^0-9+]/g, '')" 
           maxlength="15" placeholder="10-15 digits" required>
</div>
                <div class="form-group"><label>Email:</label><input type="email" name="email" placeholder="@"></div>
                <div class="form-group">
                    <label>Assigned Role:</label>
                    <select name="role">
                        <option value="system_admin">System_Admin</option><option value="Staff_Admin">Staff_Admin</option>
                        <option value="doctor">Doctor</option><option value="nurse">Nurse</option>
                        <option value="pharmacist">Pharmacist</option><option value="lab_technician">Lab Technician</option>
                        <option value="receptionist">Receptionist</option>
                    </select>
                </div>
                <div class="form-group"><label>Username:</label><input type="text" name="username" placeholder="Enter Username" required></div>
                
                <!-- ማስተካከያ፡ ለ Default Password የአይን ምልክት ተጨምሯል -->
                <div class="form-group">
                    <label>Default Password:</label>
                    <div class="pass-wrapper">
                        <input type="password" id="default-pass" name="password" value="Hospital@123" required>
                        <span class="eye-icon" onclick="toggleVisibility('default-pass', this)">👁️</span>
                    </div>
                </div>

                <button type="submit" class="action-btn">Generate Account</button>
            </form>`;
    } 
    // ... የተቀሩት (control, commands, delete, reset, profile) እንደነበሩ ይቀጥላሉ
            else if (task === 'control') {
                area.innerHTML = `
                    <h1>System Control Settings</h1><hr>
                    <div style="margin-bottom: 20px;">
                        <button class="action-btn" onclick="updateSys('backup_on')">Backup Database</button>
                        <button class="action-btn" style="background:red;" onclick="updateSys('backup_off')">Stop</button>
                        <input type="checkbox" id="chk_backup" ${backupStatus == '1' ? 'checked' : ''} disabled> Datebase Backup Request
                    </div>
                    <div>
                        <button class="action-btn" onclick="updateSys('lock_on')">System Lock</button>
                        <button class="action-btn" style="background:red;" onclick="updateSys('lock_off')">Stop</button>
                        <input type="checkbox" id="chk_lock" ${lockStatus == '1' ? 'checked' : ''} disabled> System Lock/Unlock
                    </div>`;
            }
         else if (task === 'commands') {
    await fetch('clear_notif.php?type=commands');
    checkNotifications();
                let cmds = commandsData.map(c => `
                    <div style="background:#fff; border: 1px solid #ddd; padding:20px; border-radius:8px; margin-bottom:20px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong>Type: ${c.command_type}</strong>
                            <span style="background:orange; color:white; padding:2px 8px; border-radius:4px; font-size:12px;">${c.reply ? 'PROCESSED' : 'NEW'}</span>
                        </div>
                        <p style="background:#f9f9f9; padding:10px; border-left:4px solid darkcyan; font-style:italic;">"${c.message}"</p>
                        ${c.command_type === 'Delete Account' 
                            ? `<button class="action-btn" style="background:red;" onclick="navigateToDelete('${c.target_staff_id}', ${c.id})">Process Deletion for ID: ${c.target_staff_id}</button>`
                            : (['Reset Password','Reset Username','Reset Username and Password'].includes(c.command_type)
    ? `<button class="action-btn" style="background:orange;" onclick="navigateToReset('${c.target_staff_id}', ${c.id})">🔧 Process Reset for ID: ${c.target_staff_id}</button>`
                                : `<button class="action-btn" onclick="autoReceived(${c.id})">Mark as Received</button>`)
                        }
                        ${c.reply ? `<br><small style="color:green;">✔ Last Status: ${c.reply}</small>` : ''}
                    </div>`).join('');
                area.innerHTML = `<h1>Received Commands & Actions</h1><hr>${cmds || '<p>No messages.</p>'}`;
            }
            else if (task === 'delete') {
                let rows = staffData.map(s => {
                    let isAuthorized = deletableIds.includes(s.staff_id);
                    let rowStyle = (filterId && s.staff_id === filterId) ? 'style="background:#fff9c4;"' : '';
                    return `<tr ${rowStyle}>
                        <td>${s.full_name}</td><td>${s.role.toUpperCase()}</td><td>${s.staff_id}</td>
                        <td><button class="action-btn" style="background:${isAuthorized ? 'red' : '#ccc'}; cursor:${isAuthorized ? 'pointer' : 'not-allowed'}; padding:5px 10px;" ${isAuthorized ? `onclick="processFinalDelete(${s.id}, '${s.staff_id}')"` : 'disabled'}>DELETE</button></td>
                    </tr>`;
                }).join('');
                area.innerHTML = `<h1>Manage Accounts</h1><hr><table><tr style="background:#f2f2f2;"><th>Name</th><th>Role</th><th>Staff ID</th><th>Action</th></tr>${rows}</table>`;
            }
            else if (task === 'reset') {
    
let rows = staffData.map(s => {
    const resetType = resetRequests[s.staff_id]; // "Reset Username" / "Reset Password" / "Reset Username and Password"
    let rowStyle = (filterId && s.staff_id === filterId) ? 'style="background:#e3f2fd;"' : '';

    if (!resetType) {
        return `<tr ${rowStyle}>
            <td>${s.full_name}</td><td>${s.role.toUpperCase()}</td><td>${s.staff_id}</td>
            <td><span style="color:#999; font-style:italic;">Not Requested</span></td>
        </tr>`;
    }

    // Command type ላይ ተመስርቶ ትክክለኛ input(s) ያሳያል
    let inputs = `<div style="display:flex; flex-direction:column; gap:5px;">`;
    inputs += `<small style="color:darkcyan; font-weight:bold;">📋 ${resetType}</small>`;

    if (resetType === 'Reset Username' || resetType === 'Reset Username and Password') {
        inputs += `<input type="text" id="uname_${s.staff_id}" placeholder="🔤 New Username" style="padding:6px; border:1px solid #4caf50; border-radius:4px;">`;
    }
    if (resetType === 'Reset Password' || resetType === 'Reset Username and Password') {
        inputs += `<input type="text" id="pass_${s.staff_id}" placeholder="🔑 New Password" style="padding:6px; border:1px solid #ff9800; border-radius:4px;">`;
    }
    inputs += `<button class="action-btn" style="padding:6px 12px; margin-top:3px;" 
                onclick="processFinalReset('${s.staff_id}', '${resetType}')">SAVE</button>`;
    inputs += `</div>`;

    return `<tr ${rowStyle}>
        <td>${s.full_name}</td><td>${s.role.toUpperCase()}</td><td>${s.staff_id}</td>
        <td>${inputs}</td>
    </tr>`;
}).join('');
    
    // Updated the table header below to include <th>Role</th>
    area.innerHTML = `<h1>Reset Staff Passwords</h1><hr>
                      <p>Only staff who requested a reset via Staff Admin are editable.</p>
                      <table>
                        <tr style="background:#f2f2f2;">
                            <th>Name</th>
                            <th>Role</th> <!-- Added Header -->
                            <th>Staff ID</th>
                            <th>Enter New Password</th>
                        </tr>
                        ${rows}
                      </table>`;
}
        else if (task === 'access') {
    let rows = accessStaffData.map((s, i) => {
        return `<tr id="row_${s.staff_id}" style="background:${i % 2 === 0 ? '#fff' : '#f0fafa'};">
            <td style="font-weight:bold; color:#1a237e;">${s.staff_id}</td>
            <td id="view_name_${s.staff_id}">${s.full_name || '-'}</td>
            <td id="view_gender_${s.staff_id}">${s.gender || '-'}</td>
            <td id="view_age_${s.staff_id}" style="text-align:center;">${s.age || '-'}</td>
            <td id="view_phone_${s.staff_id}">${s.phone || '-'}</td>
            <td id="view_email_${s.staff_id}">${s.email || '-'}</td>
            <td id="view_role_${s.staff_id}" style="color:darkcyan; font-weight:bold;">${s.role}</td>
            <td>
                <button onclick="enableEdit('${s.staff_id}', '${s.full_name}', '${s.gender}', '${s.age}', '${s.phone}', '${s.email}', '${s.role}')" 
                        style="background:darkcyan; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-weight:bold;">
                    ✏️ Edit
                </button>
            </td>
        </tr>`;
    }).join('');

    area.innerHTML = `
        <h1>Staff Directory</h1><hr>
        <div style="overflow-x:auto;">
        <table id="accessTable">
            <thead>
                <tr style="background:#00796b; color:white;">
                    <th>Staff ID</th>
                    <th>Full Name</th>
                    <th>Gender</th>
                    <th>Age</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
        </div>
        <!-- Edit Form (መጀመሪያ ተደብቋል) -->
        <div id="edit_form_box" style="display:none; margin-top:30px; background:#f0fafa; padding:25px; border-radius:10px; border:2px solid darkcyan; max-width:500px;">
            <h3 style="color:darkcyan; margin-top:0;">Edit Staff Info</h3>
            <input type="hidden" id="edit_staff_id">
            <div class="form-group"><label>Full Name:</label><input type="text" id="edit_name"></div>
            <div class="form-group">
                <label>Gender:</label>
                <select id="edit_gender">
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group"><label>Age:</label><input type="number" id="edit_age"></div>
            <div class="form-group">
                <label>Phone:</label>
                <input type="text" id="edit_phone" oninput="this.value = this.value.replace(/[^0-9+]/g, '')" maxlength="15">
            </div>
            <div class="form-group"><label>Email:</label><input type="email" id="edit_email"></div>
            <div class="form-group">
    <label>Role:</label>
    <input type="text" id="edit_role" readonly 
           style="background:#f0f0f0; color:#555; cursor:not-allowed; font-weight:bold;">
</div>
            <div style="display:flex; gap:10px;">
                <button class="action-btn" onclick="saveStaffEdit()" style="flex:1;">💾 Save Changes</button>
                <button onclick="document.getElementById('edit_form_box').style.display='none'" 
                        style="flex:1; background:#ccc; border:none; padding:12px; border-radius:4px; cursor:pointer; font-weight:bold;">
                    ✖ Cancel
                </button>
            </div>
            <p id="edit_msg" style="margin-top:10px;"></p>
        </div>`;
}

            else if (task === 'profile') {
    area.innerHTML = `
        <h1>Update Personal Profile</h1><hr>
        <div style="max-width:450px;">
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
            <br><hr><h3>Change Password</h3>
            
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

            <button class="action-btn" style="width:100%;" onclick="updateProfileLogic()">4. 💾 Save Changes</button>
            <p id="profile-msg"></p>
        </div>`;
}
        }

        // RESET PASSWORD LOGIC
        function navigateToReset(sid, cid) {
            const f = new FormData(); f.append('cmd_id', cid); f.append('reply', 'Admin is resetting...');
            fetch('reply_command.php', { method: 'POST', body: f });
            showContent('reset', document.getElementById('nav-reset'), sid);
        }

        // DELETE ACCOUNT LOGIC
        function navigateToDelete(sid, cid) {
            const f = new FormData(); f.append('cmd_id', cid); f.append('reply', 'Processing Deletion...');
            fetch('reply_command.php', { method: 'POST', body: f });
            showContent('delete', document.getElementById('nav-delete'), sid);
        }

        async function processFinalDelete(uid, sid) {
            if(!confirm("Delete this staff member?")) return;
            const f = new FormData(); f.append('id', uid); f.append('staff_id', sid);
            const res = await fetch('delete_staff.php', { method: 'POST', body: f });
            if((await res.text()).includes("Success")) { 
                alert("Deleted Successfully!"); 
                location.href = "system_admin.php?tab=delete";
            }
        }
//+++++++++++++++++++++++++++++++
        async function autoReceived(id) {
    const f = new FormData();
    f.append('cmd_id', id);
    f.append('reply', 'Request Received & Action Taken'); // Clearer reply message
    
    try {
        const res = await fetch('reply_command.php', { method: 'POST', body: f });
        const data = await res.json(); // Now this will work because PHP returns JSON
        
        if(data.status === "success") {
            alert("Reply sent to Staff Admin!");
            // Refresh the commands tab to show the update
            location.href = "system_admin.php?tab=commands"; 
        } else {
            alert("Error: " + data.message);
        }
    } catch (error) {
        console.error("Error:", error);
        alert("Server communication error.");
    }
}
function validateStaffForm() {
    const phone = document.getElementById('staff_phone').value;
    if (phone.length < 10 || phone.length > 15) {
        alert("Error: Phone number must be between 10 and 15 digits!");
        return false; 
    }
    // Staff ID validation
    if (!validateStaffId(document.getElementById('staff_id_input').value)) {
        alert("Please enter a valid Staff ID!");
        return false;
    }
    return true; 
}
//+++++++++++++++++++++++++++++++++++++
        async function updateSys(action) {
            const f = new FormData(); f.append('action', action);
            const res = await fetch('system_control_process.php', { method: 'POST', body: f });
            if((await res.json()).status === "success") { location.href = "system_admin.php?tab=control"; }
        }

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

        setInterval(checkNotifications, 10000);

        window.onload = function() {
    checkNotifications(); // ኖቲፊኬሽን ቼክ ያደርጋል
    
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');

    // URL ላይ ታብ ካለ ያንን ታብ ይከፍታል
    if (tab) {
        showContent(tab, null); // በተኑን null ብለን እንልካለን፣ ራሱ ፈልጎ active ያደርገዋል
    }
};
    function enableEdit(sid, name, gender, age, phone, email, role) {
    document.getElementById('edit_staff_id').value = sid;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_gender').value = gender;
    document.getElementById('edit_age').value = age;
    document.getElementById('edit_phone').value = phone;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_form_box').style.display = 'block';
    // Form ወደሚታይ ይሸብልላል
    document.getElementById('edit_form_box').scrollIntoView({behavior: 'smooth'});
}

async function saveStaffEdit() {
    const phone = document.getElementById('edit_phone').value.trim();
    if (phone.length < 10 || phone.length > 15) {
        alert("Phone must be 10-15 digits!");
        return;
    }
    const f = new FormData();
    f.append('staff_id', document.getElementById('edit_staff_id').value);
    f.append('full_name', document.getElementById('edit_name').value);
    f.append('gender', document.getElementById('edit_gender').value);
    f.append('age', document.getElementById('edit_age').value);
    f.append('phone', phone);
    f.append('email', document.getElementById('edit_email').value);

    const res = await fetch('edit_staff_process.php', { method: 'POST', body: f });
    const r = await res.json();
    const msg = document.getElementById('edit_msg');
    if (r.status === 'success') {
        alert("✅ Staff updated successfully!");
        location.href = "system_admin.php?tab=access";
    } else {
        msg.innerText = "❌ Error: " + r.message;
        msg.style.color = "red";
    }
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
    </script>
</body>
</html>