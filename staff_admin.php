<?php
include 'db.php'; 
session_start();

// System Lock Check
$lock_query = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'system_lock'");
$lock_data = $lock_query ? $lock_query->fetch_assoc() : ['setting_value' => '0'];

if ($lock_data['setting_value'] == '1') {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'system_admin') {
        echo "<body style='background:darkcyan; color:white; font-family:Arial; text-align:center; padding-top:100px;'>
                <h1>⛔ SYSTEM TEMPORARILY LOCKED</h1>
                <p>The Administrator has locked the system for maintenance.</p>
                <a href='login.html' style='color:greenyellow;'>Return to Login</a>
              </body>";
        exit();
    }
}

if (!isset($_SESSION['staff_id']) || $_SESSION['role'] !== 'staff_admin') {
    header("Location: login.html"); exit();
}

$current_staff_id = $_SESSION['staff_id'];
$user_query = $conn->prepare("SELECT * FROM users WHERE staff_id = ?");
$user_query->bind_param("s", $current_staff_id);
$user_query->execute();
$current_user = $user_query->get_result()->fetch_assoc();

$staff_query_res = $conn->query("SELECT staff_id, full_name, role FROM users WHERE role NOT IN ('system_admin', 'staff_admin')");
$staff_data_for_js = [];
while($row = $staff_query_res->fetch_assoc()) { 
    $staff_data_for_js[] = $row; 
}
$sq = $conn->query("SELECT * FROM schedules ORDER BY id DESC");
$schedules = [];
while($r = $sq->fetch_assoc()) { $schedules[] = $r; }

$replies_query = $conn->query("SELECT * FROM admin_commands WHERE sender_role = 'staff_admin' ORDER BY created_at DESC");
$replies = [];
while($row = $replies_query->fetch_assoc()) { $replies[] = $row; }

$staff_access_query = $conn->query("SELECT staff_id, full_name, gender, age, phone, email, role FROM users");
$staff_access_list = [];
if ($staff_access_query) {
    while($row = $staff_access_query->fetch_assoc()) {
        $staff_access_list[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Admin Dashboard - Adigrat Hospital</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; display: flex; height: 100vh; background-color: #f4f4f4; }
        .sidebar { width: 260px; background-color: #329f92; color: white; display: flex; flex-direction: column; padding: 20px; box-shadow: 2px 0 10px rgba(0,0,0,0.2); }
        .sidebar h2 { font-size: 1.1rem; text-align: center; border-bottom: 2px solid greenyellow; padding-bottom: 15px; margin-bottom: 20px; letter-spacing: 1px; }
        .nav-btn { background: #1c6e61; color: white; border: none; padding: 14px; margin-bottom: 10px; text-align: left; cursor: pointer; border-radius: 5px; font-weight: bold; transition: 0.3s; font-size: 14px; }
        .nav-btn:hover { background: #19ef9d; color: while; }
        .active { background: #e0f2f1 !important; color: #004d40 !important; border-left: 5px solid #004d40;}
        .logout-btn { margin-top: auto; background: #0c0a0a; width: fit-content; align-self: center; padding: 10px 30px;}
        .logout-btn:hover { background: #f70000;; color: white; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .workspace-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); min-height: 450px; }
        h1, h3 { color: darkcyan; margin-top: 0; }
        hr { border: 0; border-top: 1px solid #eee; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f0f7f7; color: #00796b; border-bottom: 2px solid #00796b;}
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .action-btn { background: #0288d1; color: white; border: none; padding: 12px 25px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        .report-stats { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-box { background: #e0f2f1; padding: 15px; border-radius: 5px; flex: 1; min-width: 120px; text-align: center; border: 1px solid darkcyan; }
        @media print { .sidebar, .nav-btn, .action-btn, .filter-section, .no-print { display: none !important; } .main-content { padding: 0; } .workspace-card { box-shadow: none; } }
    
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
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>STAFF ADMIN</h2>
        <button class="nav-btn" onclick="showContent('access', this)">Access Staff</button>
        <button class="nav-btn" onclick="showContent('assign', this)">Assign Schedule</button>
        <button class="nav-btn" onclick="showContent('all-schedules', this)">View All Schedules</button>
        <button class="nav-btn" onclick="showContent('command', this)">Send Command</button>
        <button class="nav-btn" onclick="showContent('report', this)">Access Report</button>
        <button class="nav-btn" onclick="showContent('profile', this)">Update Personal Profile</button>
        <button class="nav-btn logout-btn" onclick="logout()">Logout</button>
    </div>
    <div class="main-content">
    <div class="workspace-card" id="display-area">
        <h1>Welcome,  <?php echo htmlspecialchars($current_user['full_name']); ?></h1>       
    </div>
</div>

<script>
    const scheduleData = <?php echo json_encode($schedules); ?>;
    const staffMembers = <?php echo json_encode($staff_data_for_js); ?>;
    const replyData = <?php echo json_encode($replies); ?>;
    const accessStaffData = <?php echo json_encode($staff_access_list); ?>;

    function toggleOtherPermission() {
        const select = document.getElementById('perm_type_select');
        const otherBox = document.getElementById('other_perm_input');
        if (select) otherBox.style.display = (select.value === 'Other') ? 'block' : 'none';
    }
    function toggleRoomInput() {
    const box = document.getElementById('room_input_container');
    // ካለ ይደብቀዋል፣ ከሌለ ያመጣዋል
    box.style.display = (box.style.display === 'none') ? 'block' : 'none';
}

    function toggleOtherCommand() {
    const select = document.getElementById('sys_cmd_type');
    const otherBox = document.getElementById('other_cmd_input');
    const idBox = document.getElementById('id_input_container');
    const idLabel = document.getElementById('id_label');
    const idInput = document.getElementById('target_id_input');
    const resetTypeBox = document.getElementById('reset_type_container'); // አዲስ

    idBox.style.display = 'none';
    otherBox.style.display = 'none';
    resetTypeBox.style.display = 'none'; // አዲስ
    idInput.required = false;

    if (select.value === 'Reset Password') {
        idBox.style.display = 'block';
        idBox.style.background = '#fff3cd';
        idBox.style.border = '1px solid #ffeeba';
        idLabel.innerText = '⚠️ : TARGET STAFF ID FOR RESET';
        idLabel.style.color = '#856404';
        idInput.required = true;
        resetTypeBox.style.display = 'block'; // አዲስ — radio buttons ያሳያል
    } 
    else if (select.value === 'Delete Account') {
        idBox.style.display = 'block';
        idBox.style.background = '#f8d7da';
        idBox.style.border = '1px solid #f5c6cb';
        idLabel.innerText = '🚨 : TARGET STAFF ID FOR DELETION';
        idLabel.style.color = '#721c24';
        idInput.required = true;
    } 
    else if (select.value === 'Other') {
        otherBox.style.display = 'block';
    }
}
    
    // 1. የቦታዎች ዝርዝር (ያልተነካ - ቁጥር 2)
const classificationMapping = {
    "Surgical": ["Ward", "Operation", "OPD", "Other"],
    "Medical": ["ART(HR)", "Ward", "OPD", "Other"],
    "Pediatric": ["Micu", "Ward", "OPD", "Other"],
    "GYM": ["MCC (Mother & Child Care)", "Delivery Room", "Postnatal Care", "Other"]
};

function filterNamesByRole() {
    const selectedRole = document.getElementById('role_filter').value;
    const nameSelect = document.getElementById('staff_name_dropdown');
    const wrapper = document.getElementById('staff_select_wrapper'); // መደበቂያ ሳጥኑ

    // መጀመሪያ ስሞቹን ማጽዳት
    nameSelect.innerHTML = '<option value="">-- Select Name --</option>';

    if (selectedRole === "") {
        wrapper.style.display = 'none'; // ሙያ ካልተመረጠ ይደበቅ
        return;
    }

    // ሙያ ከተመረጠ እንዲታይ ማድረግ
    wrapper.style.display = 'block';

    // ፊልተር ማድረጊያ ሎጅክ
    const matchedStaff = staffMembers.filter(s => s.role.toLowerCase() === selectedRole.toLowerCase());

    if (matchedStaff.length > 0) {
        matchedStaff.forEach(s => {
            const option = document.createElement('option');
            option.value = s.full_name + " (" + s.role + ")"; 
            option.textContent = s.full_name + " (ID: " + s.staff_id + ")";
            nameSelect.appendChild(option);
        });
    } else {
        const option = document.createElement('option');
        option.textContent = "No staff found for this role";
        nameSelect.appendChild(option);
    }
}
function updateSubAreas() {
    const mainArea = document.getElementById('main_classification').value;
    const subAreaDropdown = document.getElementById('sub_area_dropdown');
    const subAreaWrapper = document.getElementById('sub_area_wrapper');
    const otherContainer = document.getElementById('other_area_container');
    const roomToggle = document.getElementById('room_toggle_link');
    const roomInputCont = document.getElementById('room_input_container');
    const otherInput = document.getElementById('other_area_text');

    subAreaWrapper.style.display = 'none';
    otherContainer.style.display = 'none';
    roomToggle.style.display = 'none';
    roomInputCont.style.display = 'none';
    
    // ግዴታ (Required) እንዳይሆን false ተደርጓል
    otherInput.required = false; 

    if (mainArea === 'Other') {
        otherContainer.style.display = 'block';
        roomToggle.style.display = 'block';
    } else if (mainArea && classificationMapping[mainArea]) {
        subAreaWrapper.style.display = 'block';
        subAreaDropdown.innerHTML = '<option value="">-- Select Specific Area --</option>';
        classificationMapping[mainArea].forEach(area => {
            let opt = document.createElement('option');
            opt.value = area; opt.textContent = area;
            subAreaDropdown.appendChild(opt);
        });
    }
}

function checkSubArea() {
    const subArea = document.getElementById('sub_area_dropdown').value;
    const otherContainer = document.getElementById('other_area_container');
    const roomToggle = document.getElementById('room_toggle_link');
    const roomInputCont = document.getElementById('room_input_container');
    const otherInput = document.getElementById('other_area_text');

    if (subArea === 'Other') {
        otherContainer.style.display = 'block';
        otherInput.required = false; // እዚህም false ተደርጓል
    } else {
        otherContainer.style.display = 'none';
    }

    if (subArea !== "") {
        roomToggle.style.display = 'block';
    } else {
        roomToggle.style.display = 'none';
        roomInputCont.style.display = 'none';
    }
}

// --- በ staff_admin.php ውስጥ የሚገኝ የጃቫ ስክሪፕት ማስተካከያ ---

function prepareAreaData() {
    const main = document.getElementById('main_classification').value;
    const sub = document.getElementById('sub_area_dropdown').value;
    const other = document.getElementById('other_area_text').value.trim();

    let finalArea = ""; 
    
    if (main === 'Other') {
        // 'Other' ተመርጦ ከተጻፈ የተጻፈውን ብቻ ይልካል፣ ካልተጻፈ ባዶ ይሆናል (Other የሚለው ቃል አይሄድም)
        finalArea = other; 
    } else if (main !== "") {
        finalArea = main;
        if (sub !== "") {
            // Sub-area ላይ Other ተመርጦ ከሆነ የተጻፈውን ይጨምራል፣ ካልሆነ Sub-area ስሙን ይይዛል
            let subPart = (sub === 'Other') ? other : sub;
            if (subPart !== "") {
                finalArea += " (" + subPart + ")";
            }
        }
    }
    document.getElementById('final_area_input').value = finalArea;
}
    function filterSchedule(dept) {
        const rows = document.querySelectorAll('#scheduleTable tbody tr');
        rows.forEach(row => { row.style.display = (dept === 'all' || row.classList.contains(dept)) ? '' : 'none'; });
    }

   function showContent(task, btn) {
    // localStorage.setItem('activeTab', task);  <-- ይህንን መስመር አጥፋው ወይም በ // ዝጋው
    
    let buttons = document.getElementsByClassName("nav-btn");
    for (let b of buttons) { b.classList.remove("active"); }
    if (btn) btn.classList.add("active");
    
    const area = document.getElementById('display-area');

        if (task === 'assign') {
    area.innerHTML = `<h1>Assign Schedule</h1><hr>
        <form action="save_schedule.php" method="POST" onsubmit="prepareAreaData()">
            
           
<div style="background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid darkcyan; margin-bottom: 20px;">
    <h3 style="font-size: 16px; margin-bottom: 10px; color: darkcyan;">1. Staff Selection</h3>
    <div class="form-group">
        <label>Select Profession:</label>
        <select id="role_filter" onchange="filterNamesByRole()" required>
            <option value="">-- Choose Profession --</option>
            <option value="doctor">Doctor</option>
            <option value="nurse">Nurse</option>
            <option value="pharmacist">Pharmacist</option>
            <option value="lab_technician">Lab Technician</option>
            <option value="receptionist">Receptionist</option>
        </select>
    </div>
    <div class="form-group" id="staff_select_wrapper" style="display:none;">
        <label>Staff Member:</label>
        <select id="staff_name_dropdown" name="staff_name" required>
            <option value="">-- Select Profession First --</option>
        </select>
    </div>
</div>

            <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                <div class="form-group" style="flex: 1;"><label>Day of Week:</label><select name="shift_day"><option>Monday</option><option>Tuesday</option><option>Wednesday</option><option>Thursday</option><option>Friday</option><option>Saturday</option><option>Sunday</option></select></div>
                <div class="form-group" style="flex: 1;"><label>Time Shift:</label><select name="shift"><option>Morning (8AM-12PM)</option><option>Afternoon (12PM-6PM)</option><option>Night (6PM-8AM)</option></select></div>
            </div>

 <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid darkcyan; margin-bottom: 20px;">
            <h3 style="font-size: 16px; margin-bottom: 10px; color: darkcyan;">2. Classification Area</h3>
            <div class="form-group">
                <label>Main Category:</label>
                <select id="main_classification" onchange="updateSubAreas()">
                    <option value="">-- Select Category --</option>
                    <option value="Surgical">Surgical</option>
                    <option value="Medical">Medical</option>
                    <option value="Pediatric">Pediatric</option>
                    <option value="GYM">GYM</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group" id="sub_area_wrapper" style="display:none;">
                <label>Specific Area:</label>
                <select id="sub_area_dropdown" onchange="checkSubArea()">
                    <option value="">-- Select Specific Area --</option>
                </select>
            </div>

            <!-- መጀመሪያ ቴክስት ቦክሱ እንዲመጣ እዚህ ጋር አደረግነው -->
            <div class="form-group" id="other_area_container" style="display:none;">
                <label>Please Specify Other Area:</label>
                <input type="text" id="other_area_text" placeholder="Type the location name here...">
            </div>
            
            <!-- ከዚያ በታች የክፍል ቁጥሩ እንዲመጣ -->
            <div id="room_toggle_link" style="display:none; margin-bottom: 10px;">
                <span onclick="toggleRoomInput()" style="color: darkcyan; cursor: pointer; text-decoration: underline; font-weight: bold;">+ Add Room Number</span>
            </div>

            <div class="form-group" id="room_input_container" style="display:none;">
                <label>Room Number (Digits only):</label>
                <input type="text" name="room" id="room_input" placeholder="e.g. 012" 
                       oninput="this.value = this.value.replace(/[^0-9]/g, '')" maxlength="5">
            </div>
        </div>

        <input type="hidden" name="area" id="final_area_input">
        <button type="submit" class="action-btn" style="width: 100%; padding: 15px; background: darkcyan;">Save Schedule</button>
    </form>`;
}
        else if (task === 'all-schedules') {
    let rows = scheduleData.map(s => {
        let r = s.staff_name_id.toLowerCase();
        let cls = r.includes('doctor') ? 'doctor' : r.includes('nurse') ? 'nurse' : r.includes('lab') ? 'lab' : r.includes('pharmacist') ? 'pharmacy' : 'receptionist';
        return `<tr class="${cls}">
            <td>${s.staff_name_id}</td>
            <td>${s.shift_day}</td>
            <td>${s.shift_time}</td>
            <td>${s.assigned_area}</td>
            <td style="font-weight:bold; color:darkcyan;">${s.room || '-'}</td>
            <td class="no-print">
                <button onclick="editSchedule(${s.id}, '${s.shift_day}', '${s.shift_time}', '${s.assigned_area}', '${s.room}')" style="background:none; border:none; cursor:pointer; font-size:18px;">✏️</button>
                <button onclick="deleteSchedule(${s.id})" style="background:none; border:none; cursor:pointer; font-size:18px; color:red; margin-left:10px;">🗑️</button>
            </td>
        </tr>`;
    }).join('');
    
    area.innerHTML = `<h1>Master Staff Schedule</h1><hr>
        <div class="filter-section" style="background:#e0f2f1; padding:15px; border-radius:5px; margin-bottom:20px;">
            <label>Filter By Profession:</label>
            <select onchange="filterSchedule(this.value)">
                <option value="all">All Departments</option>
                <option value="doctor">Doctors</option>
                <option value="nurse">Nurses</option>
                <option value="lab">Lab Technicians</option>
                <option value="pharmacist">Pharmacists</option>
                <option value="receptionist">Receptionist</option>
            </select>
            <button class="action-btn" onclick="window.print()" style="float:right;">Print Report</button>
        </div>
        <table id="scheduleTable">
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Day</th>
                    <th>Shift</th>
                    <th>Assigned Area</th>
                    <th>Room</th>
                    <th class="no-print">Actions</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
        <div style="margin-top:10px; text-align:right;">
            <button class="action-btn" onclick="deleteAllSchedules()" style="background:red;">⚠️ Delete All</button>
        </div>`;
}
        else if (task === 'command') {
    area.innerHTML = `<h1>Send Command to System</h1><hr>
        <form action="send_command_to_admin.php" method="POST">
            <div class="form-group">
                <label>Command Type:</label>
                <select id="sys_cmd_type" name="type" onchange="toggleOtherCommand()" required>
                    <option value="" disabled selected>-- Select Command Type --</option>
                    <option value="Reset Password">Reset</option>
                    <option value="Delete Account">Delete Account</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <!-- ይህ የጋራ ማስጠንቀቂያ ሳጥን ነው (አንድ target_staff_id ብቻ ነው ያለው) -->
            <div id="id_input_container" style="display:none; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                <label id="id_label" style="font-weight: bold;"></label>
                <input type="text" id="target_id_input" name="target_staff_id" placeholder="Enter Staff ID">
            </div>
            <div id="reset_type_container" style="display:none; background:#e8f5e9; padding:15px; border-radius:8px; margin-bottom:15px; border: 1px solid #4caf50;">
    <label style="color:#2e7d32; font-weight:bold; margin-bottom:10px; display:block;">Select What to Reset:</label>
    <label style="display:block; margin-bottom:8px; font-weight:normal; cursor:pointer;">
        <input type="radio" name="reset_subtype" value="Reset Username" style="width:auto; margin-right:8px;"> 
        🔤 Reset Username Only
    </label>
    <label style="display:block; margin-bottom:8px; font-weight:normal; cursor:pointer;">
        <input type="radio" name="reset_subtype" value="Reset Password" style="width:auto; margin-right:8px;"> 
        🔑 Reset Password Only
    </label>
    <label style="display:block; font-weight:normal; cursor:pointer;">
        <input type="radio" name="reset_subtype" value="Reset Username and Password" style="width:auto; margin-right:8px;"> 
        🔐 Reset Username and Password
    </label>
</div>

            <div id="other_cmd_input" style="display:none;" class="form-group">
                <label>Specify Custom Command Type:</label>
                <input type="text" name="custom_type" placeholder="Type command name here">
            </div>

            <div class="form-group">
                <label>Message/Command Details:</label>
                <textarea name="message" rows="4" placeholder="Explain why you are sending this command..." required></textarea>
            </div>
            <button type="submit" class="action-btn" style="width:100%;">Send to System Admin</button>
        </form>
        
        <h3 style="margin-top:40px;">System Admin Responses Status</h3>
        <table>
            <tr style="background: #e9ecef;">
                <th>Command Sent</th>
                <th>Instruction</th>
                <th>Target ID</th>
                <th>System Reply</th> <!-- ወደ ግራ መጣ -->
                <th>Action</th>       <!-- ወደ መጨረሻ ሄደ -->
            </tr>
            ${replyData.map(r => {
                // ገና ምላሽ ካልተሰጠው (Pending ከሆነ) የማጥፊያ በተኑ እንዲሰራ
                let isPending = !r.reply;
                let actionBtn = isPending 
                    ? `<a href="delete_command.php?id=${r.id}" onclick="return confirm('Cancel this request?')" style="text-decoration:none; font-size:18px;" title="Cancel Request">❌</a>`
                    : `<span title="Processed - Cannot Cancel" style="opacity:0.3; cursor:not-allowed; font-size:18px;">🔒</span>`;

                return `
                <tr>
                    <td style="font-weight:bold; color:${r.command_type==='Delete Account'?'red':'darkcyan'};">${r.command_type}</td>
                    <td>${r.message}</td>
                    <td style="color:red; font-weight:bold;">${r.target_staff_id || '-'}</td>
                    
                    <!-- 1. System Reply መጀመሪያ -->
                    <td><b style="color:${r.reply?'green':'orange'};">${r.reply || '🕒 Pending...'}</b></td>
                    
                    <!-- 2. Action (❌) መጨረሻ ላይ -->
                    <td style="text-align:center;">${actionBtn}</td>
                </tr>`;
            }).join('')}
        </table>`;
}
       else if (task === 'report') {
    area.innerHTML = `<h1>Hospital Activity Reports</h1><hr>
        <div class="form-group no-print">
            <label>1. Select Report Period:</label>
            <select id="report_period">
                <option value="daily">Daily Activity Report</option>
                <option value="weekly">Weekly Activity Report</option>
                <option value="monthly">Monthly Activity Report</option>
                <option value="yearly">Yearly Activity Report</option>
            </select>
        </div>
        
        <div class="form-group no-print">
            <label>2. Select Category:</label>
            <select id="report_category" onchange="toggleDischargeSubOptions()">
                <option value="new_patients">Number of New Patients</option>
                <option value="lab_test">Lab Test</option>
                
                <option value="follow_ups">Follow ups</option>
                <option value="referrals">Referrals</option>
                <option value="discharges">Discharges</option>
            </select>
        </div>

        <!-- አራቱ አማራጮች ያሉት ሳጥን -->
        <div class="form-group no-print" id="discharge_sub_div" style="display:none; background: #f1f8e9; padding: 20px; border-radius: 8px; border-left: 5px solid #2e7d32; margin-top: 10px;">
    <label style="color:#2e7d32; font-weight:bold;">3. Select Discharge Outcome (Condition Status):</label>
    <select id="discharge_outcome" style="border: 1px solid #2e7d32; margin-top: 5px;">
        <option value="Cured">✅ Cured</option>
        <option value="Improved">📈 Improved</option>
        <option value="Same">⚖️ Same</option>
        <option value="Dead">⚠️ Dead</option>
    </select>
</div>

        <button class="action-btn no-print" onclick="generateReport()">View Count & Details</button>
        <div id="report_results" style="margin-top:30px;"></div>`;
}

        else if (task === 'access') {
    let rows = accessStaffData.map((s, i) => {
        return `<tr style="background:${i % 2 === 0 ? '#fff' : '#f0fafa'};">
            <td style="font-weight:bold; color:#1a237e;">${s.staff_id}</td>
            <td>${s.full_name || '-'}</td>
            <td>${s.gender || '-'}</td>
            <td style="text-align:center;">${s.age || '-'}</td>
            <td>${s.phone || '-'}</td>
            <td>${s.email || '-'}</td>
            <td style="color:darkcyan; font-weight:bold;">${s.role}</td>
        </tr>`;
    }).join('');

    area.innerHTML = `
        <h1>Staff Directory</h1><hr>
        <table>
            <thead>
                <tr style="background:#329f92; color:white;">
                    <th>Staff ID</th>
                    <th>Full Name</th>
                    <th>Gender</th>
                    <th>Age</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Role</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>`;
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

            <button class="action-btn" style="width:100%;" onclick="updateProfileLogic()">4. Save Changes</button>
            <p id="profile-msg"></p>
        </div>`;
}
    }

    async function generateReport() {
    const period = document.getElementById('report_period').value;
    const category = document.getElementById('report_category').value;
    let subStatus = "";

    if (category === 'discharges') {
        subStatus = document.getElementById('discharge_outcome').value;
    }

    const resultDiv = document.getElementById('report_results');
    resultDiv.innerHTML = "<div style='padding:20px; color:darkcyan;'><b>🔄 Generating report...</b></div>";

    try {
        const response = await fetch(`get_hospital_stats.php?period=${period}&category=${category}&sub_status=${subStatus}`);
        const text = await response.text();
        const data = JSON.parse(text);

        if(data.status !== "success") {
            resultDiv.innerHTML = `<p style="color:red;">Error: ${data.message}</p>`;
            return;
        }

        let headerBg = "#00796b"; 
        if (category === 'discharges') {
            if (subStatus === "Cured") headerBg = "#2e7d32";
            else if (subStatus === "Improved") headerBg = "#0288d1";
            else if (subStatus === "Same") headerBg = "#607d8b";
            else if (subStatus === "Dead") headerBg = "#c62828";
        } else if (category === 'lab_test') {
            headerBg = "#906fc8"; // ለላብራቶሪ የተለየ ከለር (Purple)
        }

        let categoryTitle = category.replace('_',' ').toUpperCase();
        if(subStatus !== "") categoryTitle += ` - ${subStatus.toUpperCase()}`;

        let html = `<h2 style="color:white; background:${headerBg}; padding:15px; border-radius:5px; text-align:center;">${categoryTitle} REPORT</h2>`;
        let d = data.data;

        // --- ለ Lab Test ብቻ የሚሆን ልዩ የሰንጠረዥ አወቃቀር ---
        if (category === 'lab_test') {
            html += `
            <table style="width:100%; border-collapse:collapse; margin-top:15px; border:1px solid #cfd8dc;">
                <thead>
                    <tr style="background-color: ${headerBg}; opacity: 0.9;">
                        <th style="padding:12px; border:1px solid #ddd; text-align:left; color: black;">Test Type</th>
                        <th style="padding:12px; border:1px solid #ddd; text-align:center; color: black;">Total Count</th>
                    </tr>
                </thead>
                <tbody>`;
            
            if (d.length > 0) {
                d.forEach(item => {
                    html += `
                    <tr>
                        <td style="padding:10px; border:1px solid #ddd; font-weight:bold;">${item.test_name}</td>
                        <td style="text-align:center; border:1px solid #ddd; font-size:16px;">${item.count}</td>
                    </tr>`;
                });
            } else {
                html += `<tr><td colspan="2" style="text-align:center; padding:20px;">No lab data found for this period.</td></tr>`;
            }
            html += `</tbody></table>`;
        } 
        // --- ለሌሎቹ (Patients, Referrals etc.) የነበረው ኮድ ---
        else {
            const hz = (val) => (val == 0 || !val ? "" : val);
            let ageData = (category === 'referrals') ? d.age_stats : d;
            html += `
            <table style="width:100%; border-collapse:collapse; margin-top:15px; border:1px solid #cfd8dc;">
                <thead>
                    <tr style="background-color: ${headerBg};">
                        <th style="padding:12px; border:1px solid #ddd; text-align:left; color: black;">Category / Age Group</th>
                        <th style="padding:12px; border:1px solid #ddd; color: black;">Male</th>
                        <th style="padding:12px; border:1px solid #ddd; color: black;">Female</th>
                        <th style="padding:12px; border:1px solid #ddd; color: black;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td style="padding:10px; border:1px solid #ddd;">Under 5 Years</td><td style="text-align:center; border:1px solid #ddd;">${hz(ageData.u5m)}</td><td style="text-align:center; border:1px solid #ddd;">${hz(ageData.u5f)}</td><td style="text-align:center; border:1px solid #ddd; font-weight:bold;">${hz(ageData.u5t)}</td></tr>
                    <tr><td style="padding:10px; border:1px solid #ddd;">5 - 18 Years</td><td style="text-align:center; border:1px solid #ddd;">${hz(ageData.a5_18m)}</td><td style="text-align:center; border:1px solid #ddd;">${hz(ageData.a5_18f)}</td><td style="text-align:center; border:1px solid #ddd; font-weight:bold;">${hz(ageData.a5_18t)}</td></tr>
                    <tr><td style="padding:10px; border:1px solid #ddd;">18 - 35 Years</td><td style="text-align:center; border:1px solid #ddd;">${hz(ageData.a18_35m)}</td><td style="text-align:center; border:1px solid #ddd;">${hz(ageData.a18_35f)}</td><td style="text-align:center; border:1px solid #ddd; font-weight:bold;">${hz(ageData.a18_35t)}</td></tr>
                    <tr><td style="padding:10px; border:1px solid #ddd;">35 - 60 Years</td><td style="text-align:center; border:1px solid #ddd;">${hz(ageData.a35_60m)}</td><td style="text-align:center; border:1px solid #ddd;">${hz(ageData.a35_60f)}</td><td style="text-align:center; border:1px solid #ddd; font-weight:bold;">${hz(ageData.a35_60t)}</td></tr>
                    <tr><td style="padding:10px; border:1px solid #ddd;">Over 60 Years</td><td style="text-align:center; border:1px solid #ddd;">${hz(ageData.a60m)}</td><td style="text-align:center; border:1px solid #ddd;">${hz(ageData.a60f)}</td><td style="text-align:center; border:1px solid #ddd; font-weight:bold;">${hz(ageData.a60t)}</td></tr>
                    <tr style="background:#f5f5f5; font-weight: bold; border-top: 2px solid ${headerBg};">
                        <td style="padding:12px; border:1px solid #ddd; color: ${headerBg};">OVERALL COUNTS</td>
                        <td style="padding:12px; border:1px solid #ddd; text-align:center;">${hz(ageData.male)}</td>
                        <td style="padding:12px; border:1px solid #ddd; text-align:center;">${hz(ageData.female)}</td>
                        <td style="padding:12px; border:1px solid #ddd; text-align:center; background:#f0f7f7;">${hz(ageData.total)}</td>
                    </tr>
                </tbody>
            </table><br>`;
        }

        resultDiv.innerHTML = html + `<br><button class="action-btn no-print" onclick="window.print()">Print This Report</button>`;

    } catch (e) {
        resultDiv.innerHTML = `<p style="color:red;"><b>Error:</b> Could not process report data.</p>`;
        console.error(e);
    }
}
    // 1. የፓስዎርድ ጥንካሬን ቼክ ማድረጊያ (ሁሉንም መስፈርት ማሟላቱን ያያል)
function isStrongPassword(password) {
    // ህጉ: 8+ chars, 1 lowercase, 1 UPPERCASE, 1 number, 1 symbol (@$!%*?&.)
    const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.]).{8,}$/;
    return regex.test(password);
}

// 2. የProfile Update ፕሮሰስ
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

window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const taskFromUrl = urlParams.get('task');

    if (taskFromUrl) {
        const allButtons = document.querySelectorAll('.nav-btn');
        let targetBtn = null;
        allButtons.forEach(b => {
            if (b.getAttribute('onclick') && b.getAttribute('onclick').includes(`'${taskFromUrl}'`)) {
                targetBtn = b;
            }
        });

        if (targetBtn) {
            showContent(taskFromUrl, targetBtn);
            window.history.replaceState({}, document.title, window.location.pathname);
            return; 
        }
    }

    // Refresh ሲደረግ ስሙ እንዳይጠፋ ይሄን እንጠቀማለን
    const welcomeHTML = `<h1>Welcome, <?php echo htmlspecialchars($current_user['full_name']); ?></h1><hr>
    <p>You have access to manage hospital staffing, view activity reports, and handle staff schedules.  Please select an action from the left sidebar to begin.</p>`;
    document.getElementById('display-area').innerHTML = welcomeHTML;
    
    const buttons = document.getElementsByClassName("nav-btn");
    for (let b of buttons) { b.classList.remove("active"); }
});

// 1. Delete Function
function deleteSchedule(id) {
    if(confirm("Are you sure you want to delete this schedule?")) {
        window.location.href = `delete_schedule.php?id=${id}`;
    }
}

// 2. Edit Function (ይህ ሲነካ ፎርም ወዳለበት ይወስደዋል ወይም በቀላሉ በ Prompt ይቀይረዋል)
// ለቀላል አሰራር ወደ አዲስ ገጽ እንዲሄድ እናድርገው
function editSchedule(id, day, shift, area, room) {
    window.location.href = `edit_schedule_page.php?id=${id}`;
}
// Discharge ሲመረጥ አማራጮቹን የሚያሳይ/የሚደብቅ ፈንክሽን
function toggleDischargeSubOptions() {
    const category = document.getElementById('report_category').value;
    const subDiv = document.getElementById('discharge_sub_div');
    subDiv.style.display = (category === 'discharges') ? 'block' : 'none';
}
function deleteAllSchedules() {
    if(confirm("🚨 WARNING: Are you sure you want to delete ALL schedules?")) {
        window.location.href = 'delete_all_schedules.php';
    }
}
</script>
</body>
</html>