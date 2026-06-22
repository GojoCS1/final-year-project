<?php
// 1. Prevent Browser Caching (English Comment: Stops the browser from storing the page in memory)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 2. Start session only once
session_start();

include 'db.php';

// 3. Authentication Check: If the user is not logged in, send them to login.html
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.html"); 
    exit();
}

$current_staff_id = $_SESSION['staff_id'];

// 4. Fetch User Profile (English Comment: This defines the $current_user variable to fix your error)
$user_query = $conn->prepare("SELECT * FROM users WHERE staff_id = ?");
$user_query->bind_param("s", $current_staff_id);
$user_query->execute();
$current_user = $user_query->get_result()->fetch_assoc();

// 5. System Lock Check (Optional)
$lock_query = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'system_lock'");
$lock_data = $lock_query ? $lock_query->fetch_assoc() : ['setting_value' => '0'];

if ($lock_data['setting_value'] == '1' && $_SESSION['role'] !== 'system_admin') {
    session_destroy();
    echo "<body style='background:darkcyan; color:white; font-family:Arial; text-align:center; padding-top:100px;'>
            <h1>⛔ SYSTEM TEMPORARILY LOCKED</h1>
            <p>Maintenance in progress. Please contact Admin.</p>
            <a href='login.html' style='color:greenyellow;'>Return to Login</a>
          </body>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receptionist Dashboard - Adigrat Hospital</title>
    <style>
        /* ALL ORIGINAL STYLES PRESERVED EXACTLY */
        body { margin: 0; font-family: Arial, sans-serif; display: flex; height: 100vh; }
        .sidebar { width: 250px; background-color: #329f92; color: white; display: flex; flex-direction: column; padding: 20px; box-shadow: 2px 0 5px rgba(0,0,0,0.2); }
        .sidebar h2 { font-size: 1.2rem; border-bottom: 1px solid white; padding-bottom: 10px; margin-bottom: 20px; }
        .nav-btn { background: #1c6e61; color: white; border: none; padding: 15px; margin-bottom: 10px; text-align: left; cursor: pointer; border-radius: 4px; font-weight: bold; transition: 0.3s; }
        .nav-btn:hover { background: #19ef9d; color: white; }
        .logout-btn { margin-top: auto; background: #0c0a0a; width: fit-content; align-self: center; padding: 10px 30px;}
        .logout-btn:hover { background: #f70000;; color: white; }
        .main-content { flex: 1; padding: 40px; background-color: #f4f4f4; overflow-y: auto; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #333; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .action-btn { background: darkcyan; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        .action-btn:hover { background: #006666; }
        .back-btn { background: #666; margin-bottom: 15px; } /* Style for the Back Button */
        #reg-msg { color: green; font-weight: bold; margin-top: 10px; }

        /* Styling for Password Toggle */
.password-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}
.password-wrapper input {
    padding-right: 40px; /* Make space for the icon */
}
.toggle-password {
    position: absolute;
    right: 10px;
    cursor: pointer;
    color: #555;
    user-select: none;
    font-size: 18px;
}
.toggle-password:hover {
    color: darkcyan;
}
/* Language Toggle Button Style */
.lang-switch { 
    position: absolute; top: 10px; right: 20px; 
    background: #ffc107; color: black; border: none; 
    padding: 8px 15px; cursor: pointer; border-radius: 5px; font-weight: bold; 
}
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>RECEPTION PANEL</h2>
        <button class="nav-btn" onclick="show('register')">Register New Patients</button>
        <button class="nav-btn" onclick="show('assign')">Assign patient</button>
        <button class="nav-btn" onclick="show('schedule')">Schedule Appointment</button>
        <button class="nav-btn" onclick="show('workload')">View Number of Queues</button>
        <button class="nav-btn" onclick="show('my-schedule')">View My Schedule</button>
        <button class="nav-btn" onclick="show('profile')">Update Personal Profile</button>
        <button class="nav-btn logout-btn" onclick="logout()">Logout</button>
    </div>

    <div class="main-content">
        <div class="card" id="display-area">
            <h1>Welcome, <?php echo $current_user['full_name']; ?></h1>
            <p>Please select an action from the left sidebar to start patient intake.</p>
        </div>
    </div>

    <script>
        function toggleOtherField(selectObj, divId) {
            const otherDiv = document.getElementById(divId);
            if (selectObj.value === "Other") otherDiv.style.display = "block";
            else otherDiv.style.display = "none";
        }

        // *** ይህን searchPatient ፋንክሽን ተኩለው ***
async function searchPatient(inputId) {
    const search_query = document.getElementById(inputId).value;
    if (!search_query) { alert("Please enter MRN, Name, or Phone first"); return; }

    const res = await fetch(`get_patient_details.php?query=${encodeURIComponent(search_query)}`);
    const data = await res.json();

    const infoDiv = document.getElementById('search-info-display');
    const actionFields = document.getElementById('action-fields');

    if (data.error) {
        infoDiv.innerHTML = `<p style="color:red;">${data.error}</p>`;
        if (actionFields) actionFields.style.display = 'none';
    } else if (Array.isArray(data)) {
        let listHtml = "<h4>Multiple patients found. Please click to select:</h4>";
        data.forEach(p => {
            listHtml += `<div onclick="selectPatient('${p.mrn}', '${inputId}')" 
                style="cursor:pointer; background:#eee; padding:5px; margin-bottom:5px; border-radius:3px;">
                ID: ${p.mrn} | Name: ${p.full_name} | Phone: ${p.phone}
            </div>`;
        });
        infoDiv.innerHTML = listHtml;
        if (actionFields) actionFields.style.display = 'none';
    } else {
        // *** inputId pass አድርግ ***
        displayPatientInfo(data, inputId);
        if (actionFields) actionFields.style.display = 'block';
        document.getElementById(inputId).value = data.mrn;
    }
}

// *** ይህን displayPatientInfo ፋንክሽን ተኩለው ***
function displayPatientInfo(data, inputId) {
    const infoDiv = document.getElementById('search-info-display');

    // context: assign ወይስ schedule?
    const isSchedule = (inputId === 'appt_id');

    const assignedAt = new Date(data.assigned_at);
const now = new Date();
const isExpired = (now - assignedAt) / (1000 * 60 * 60) >= 12;

if (data.assigned_to_dept && data.doc_name && !isExpired);

    // ---- Assignment Status Block (assign ቦታ ብቻ) ----
    let statusContent = "";
    if (!isSchedule) {
        if (data.assigned_to_dept && data.doc_name) {
            let roleTitle = data.staff_role
                ? data.staff_role.charAt(0).toUpperCase() + data.staff_role.slice(1)
                : "Staff";
            const canCancel = (data.assigned_by === 'receptionist' || !data.assigned_by);
            statusContent = `
                <div style="background:#eef6ff; padding:12px; border-radius:6px; border-left:5px solid #2196f3; margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <b style="color:#0d47a1;">📍 Current Status:</b>
                        <span style="color:#333;"> Assigned to ${data.assigned_to_dept} (${roleTitle}: ${data.doc_name})</span>
                    </div>
                    ${canCancel
                        ? `<button class="action-btn" style="background:#ff4d4d; padding:5px 12px; font-size:12px;" onclick="cancelAssignment('${data.mrn}')">❌ Cancel</button>`
                        : `<span style="color:#888; font-size:11px; font-style:italic;">🔒 Assigned to ${roleTitle}</span>`
                    }
                </div>`;
        } else {
            statusContent = `
                <div style="background:#f0fff4; padding:12px; border-radius:6px; border-left:5px solid #4caf50; margin-bottom:15px;">
                    <b style="color:#1b5e20;">✅ Status:</b>
                    <span style="color:#2e7d32;"> Waiting (Not Assigned)</span>
                </div>`;
        }
    }

    // ---- Appointments Block (schedule ቦታ ብቻ) ----
    let apptHtml = "";
    if (isSchedule) {
        if (data.appointments && data.appointments.length > 0) {
            apptHtml = `
                <div style="margin-top:15px; border-top:1px solid #ff9800; padding-top:10px;">
                    <h4 style="color:#e65100; margin-bottom:10px;">📅 Scheduled Appointments:</h4>`;
            data.appointments.forEach(appt => {
                apptHtml += `
                    <div style="background:#fff3e0; padding:10px; border-radius:5px; border:1px solid #ffe0b2; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                        <div style="font-size:13px;">
                            <b>Date:</b> ${appt.date} | <b>With:</b> ${appt.staff_name}
                        </div>
                        <button class="action-btn" style="background:#f44336; padding:3px 8px; font-size:11px;"
                            onclick="deleteAppointment('${appt.appointment_id}')">❌ Cancel</button>
                    </div>`;
            });
            apptHtml += `</div>`;
        } else {
            apptHtml = `<p style="color:#999; font-style:italic; margin-top:10px;">No upcoming appointments found.</p>`;
        }
    }

    // ---- Patient Info Card ----
    infoDiv.innerHTML = `
    <div style="background:white; border:1px solid #ccc; border-radius:8px; padding:20px; margin-top:15px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid darkcyan; padding-bottom:8px; margin-bottom:15px;">
            <h3 style="margin:0; color:darkcyan;">📋 Patient Individual Folder (ID: ${data.mrn})</h3>
            <button class="action-btn" style="background:#f39c12;" onclick='openEditProfile(${JSON.stringify(data)})'>✏️ Edit Profile</button>
        </div>

            ${statusContent}

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; font-size:14px; line-height:1.6;">
                <div style="background:#f9f9f9; padding:10px; border-radius:5px;">
                    <p style="margin:5px 0;"><strong>Full Name:</strong> ${data.full_name}</p>
                    <p style="margin:5px 0;"><strong>Age:</strong> ${data.age}</p>
                    <p style="margin:5px 0;"><strong>Gender:</strong> ${data.gender}</p>
                    <p style="margin:5px 0;"><strong>Date of Birth:</strong> ${data.dob || 'N/A'}</p>
                    <p style="margin:5px 0;"><strong>Phone:</strong> ${data.phone || 'None'}</p>
                </div>
                <div style="background:#f9f9f9; padding:10px; border-radius:5px;">
                    <p style="margin:5px 0;"><strong>Nationality:</strong> ${data.nationality || 'Ethiopian'}</p>
                    <p style="margin:5px 0;"><strong>Region:</strong> ${data.region || 'N/A'},</p>
                    <p style="margin:5px 0;"><strong>Wereda/Subcity:</strong> ${data.wereda || 'N/A'}</p>
                    <p style="margin:5px 0;"><strong>Kebele:</strong> ${data.kebele || 'N/A'}</p> 
                    <p style="margin:5px 0;"><strong>Ketena/Gott:</strong> ${data.ketena || 'N/A'}</p>
                    <p style="margin:5px 0;"><strong>House No:</strong> ${data.house || 'N/A'}</p>
                </div>
            </div>

            ${apptHtml}

            ${!isSchedule ? `
            <div style="margin-top:15px; border-top:1px solid #eee; padding-top:15px;">
                <label style="color:darkcyan; font-weight:bold; display:block; margin-bottom:5px;">Reason for Visit (Today's Message):</label>
                <textarea id="today_complaint" rows="2" style="width:100%; border:1px solid #ccc; border-radius:4px; padding:8px; box-sizing:border-box;" placeholder="Type why the patient is here today..."></textarea>
            </div>` : ''}
        </div>`;
}
// ይህ ሰረዛውን የሚፈጽመው ፋንክሽን ነው (መኖሩን አረጋግጥ)
async function cancelAssignment(mrn) {
    if (!confirm("Are you sure you want to cancel this patient's current assignment?")) return;

    const fd = new FormData();
    fd.append('mrn', mrn);

    try {
        const res = await fetch('cancel_assignment.php', { method: 'POST', body: fd });
        const msg = await res.text();
        alert(msg);
        searchPatient('assign_mrn'); // መረጃውን እንዲያድሰው
    } catch (e) {
        alert("Error: Connection failed.");
    }
}
// ብዙ ታካሚ ሲመጣ አንዱን ለመምረጥ
function selectPatient(mrn, inputId) {
    document.getElementById(inputId).value = mrn;
    searchPatient(inputId);
}
 function show(action, prefillID = "", editData = null) {
    const area = document.getElementById('display-area');
    
    // --- 1. PATIENT REGISTRATION (Individual Folder) ---
    if(action === 'register') {
    area.innerHTML = `
        <button class="lang-switch" id="lang-btn" onclick="toggleLanguage()">Eng / ኣማ</button>
        <h1 id="t-folder">INDIVIDUAL FOLDER</h1>
        <p id="t-desc">Enter details to generate a new Hospital ID.</p>
        
        <div style="display:flex; gap:20px;">
            <div class="form-group" style="flex:0.33;">
                <label id="l-mrn">Medical Record Number:</label>
                <input type="text" id="p_mrn" readonly style="background:#eee;">
            </div>
            <div class="form-group" style="flex:1;"><label id="l-name">Full Name:</label><input type="text" id="p_name" placeholder="Enter patient name"></div>
        </div>

        <div style="display:flex; gap:10px;">
            <div class="form-group" style="flex:1;">
    <label id="l-reg-date">Date of Registration:</label>
    <input type="Date" id="p_reg_date" readonly style="background:#eee; cursor:not-allowed;">
</div>
            <div class="form-group" style="flex:1;"><label id="l-dob">Date of Birth:</label><input type="Date" id="p_dob" max="<?php echo date('Y-m-d'); ?>" onchange="calculateAge()"></div>
            <div class="form-group" style="flex:0.5;"><label id="l-gender">Gender:</label><select id="p_gender"><option>Male</option><option>Female</option><option>Other</option></select></div>
        </div>

        <div style="display:flex; gap:20px;">
            <div class="form-group" style="flex:1;">
                <label id="l-nat">Nationality:</label>
                <select id="p_nationality" onchange="toggleOtherField(this, 'nat_other_div')">
                    <option>Ethiopian</option><option value="Other">Other</option>
                </select>
                <div id="nat_other_div" style="display:none; margin-top:10px;"><input type="text" id="p_nat_other" placeholder="Specify nationality"></div>
            </div>
            <div class="form-group" style="flex:1;">
                <label id="l-region">Region:</label>
                <select id="p_region" onchange="toggleOtherField(this, 'reg_other_div')">
                    <option>Tigray</option><option>Afar</option><option>Amhara</option><option>Oromia</option><option>Addis Ababa</option>
                    <option>DireDawa</option><option>Sidama</option><option>Harar</option><option>Somalia</option>
                    <option>Benishangul Gumuz</option><option>South Ethiopia</option><option value="Other">Other</option> 
                </select>
                <div id="reg_other_div" style="display:none; margin-top:10px;"><input type="text" id="p_reg_other" placeholder="Specify region"></div>
            </div>
        </div>

        <div style="display:flex; gap:20px;">
            <div class="form-group" style="flex:1;"><label id="l-wereda">Wereda/SubCity:</label><input type="text" id="p_wereda"></div>
            <div class="form-group" style="flex:1;"><label id="l-kebele">Kebele:</label><input type="text" id="p_kebele"></div>
            <div class="form-group" style="flex:1;"><label id="l-ketena">Ketena/Gott:</label><input type="text" id="p_ketena"></div>
        </div>

        <div style="display:flex; gap:20px;">
            <div class="form-group" style="flex:1;"><label id="l-house">House Number:</label><input type="text" id="p_house"></div>
            <div class="form-group" style="flex:1;">
                <label id="l-phone">Phone Number (Optional):</label>
                <input type="text" id="p_number" oninput="this.value = this.value.replace(/[^0-9+]/g, '')" maxlength="15" placeholder="10-15 digits">
            </div>
        </div>

        <button class="action-btn" id="reg-btn-text" onclick="processRegistration()">Register</button>
        <p id="reg-msg"></p>`;

        const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const formattedToday = `${yyyy}-${mm}-${dd}`;

    const regDateInput = document.getElementById('p_reg_date');
    regDateInput.value = formattedToday;
    regDateInput.max = formattedToday; 
    
    document.getElementById('p_reg_date').valueAsDate = new Date();
    if (editData) {
    document.getElementById('p_mrn').value = editData.mrn;
    document.getElementById('p_name').value = editData.full_name;
    document.getElementById('p_dob').value = editData.dob;
    document.getElementById('p_gender').value = editData.gender;
    document.getElementById('p_nationality').value = editData.nationality;
    document.getElementById('p_region').value = editData.region;
    document.getElementById('p_wereda').value = editData.wereda;
    document.getElementById('p_kebele').value = editData.kebele;
    document.getElementById('p_ketena').value = editData.ketena;
    document.getElementById('p_house').value = editData.house || editData.house_number;
    document.getElementById('p_number').value = editData.phone;
    document.getElementById('reg-btn-text').innerText = "Update Profile";
    document.getElementById('t-folder').innerText = "UPDATE PATIENT PROFILE";
} else {
    fetch('get_next_mrn.php').then(res => res.text()).then(data => { 
        document.getElementById('p_mrn').value = data.trim(); 
    });
}
    }

    // --- 2. ASSIGN PATIENT (ክላሲፊኬሽን መርጦ ዶክተር መመደብ) ---
    else if(action === 'assign') {
        area.innerHTML = `
            <h1>Assign Patient</h1>
            <div style="background:#e0f2f1; padding:15px; border-radius:8px; margin-bottom:20px;">
                <h3>1. Search Patient</h3>
                <div style="display:flex; gap:10px;">
                    <input type="text" id="assign_mrn" placeholder="MRN/Name/Phone" onkeydown="if(event.key==='Enter') searchPatient('assign_mrn')">
                    <button class="action-btn" onclick="searchPatient('assign_mrn')">🔍 Search</button>
                </div>
                <div id="search-info-display"></div>
            </div>

            <div id="action-fields" style="display:none;">
                <h3>2. Select Location</h3>
                <div class="form-group">
                    <label>Main Category:</label>
                    <select id="main_cat_assign" onchange="updateSubAreas('main_cat_assign', 'sub_cat_assign', 'sub_wrapper_assign')">
                        <option value="">-- Select Category --</option>
                        <option>Surgical</option><option>Medical</option><option>Pediatric</option><option>GYM</option>
                    </select>
                </div>
                <div class="form-group" id="sub_wrapper_assign" style="display:none;">
                    <label>Specific Area:</label>
                    <select id="sub_cat_assign" onchange="filterStaffByArea('assign', 'sub_cat_assign', 'assign_target')">
    <option value="">-- Select Specific Area --</option>
</select>
                </div>
                
                <h3>3. Assign Staff</h3>
                <div class="form-group">
                    <label>Staff Working Now (Based on Ethiopian Shift):</label>
                    <select id="assign_target"><option value="">Select location first...</option></select>
                </div>
                <button class="action-btn" onclick="saveAssignment()">Confirm Assignment</button>
            </div>`;

        // ታካሚው እንደተመዘገበ በቀጥታ ገጹ እንዲመጣ የሚያደርግ
        if(prefillID) {
            setTimeout(() => {
                document.getElementById('assign_mrn').value = prefillID;
                searchPatient('assign_mrn');
            }, 100);
        }
    }

    // --- 3. SCHEDULE APPOINTMENT (ቀጠሮ መያዝ) ---
    else if(action === 'schedule') {
        area.innerHTML = `
            <h1>Schedule Appointment</h1>
            <div style="background:#e0f2f1; padding:15px; border-radius:8px; margin-bottom:20px;">
                <h3>1. Search Patient</h3>
                <div style="display:flex; gap:10px;">
                    <input type="text" id="appt_id" placeholder="MRN/Name/Phone" onkeydown="if(event.key==='Enter') searchPatient('appt_id')">
                    <button class="action-btn" onclick="searchPatient('appt_id')">🔍 Search</button>
                </div>
                <div id="search-info-display"></div>
            </div>

            <div id="action-fields" style="display:none;">
                <h3>2. Choose Time (Ethiopian Shift)</h3>
                <div style="display:flex; gap:15px;">
                    <div class="form-group" style="flex:1;"><label>Appointment Date:</label><input type="date" id="appt_date_only" onchange="loadScheduledStaffForAppt()"></div>
                    <div class="form-group" style="flex:1;"><label>Shift:</label>
                        <select id="appt_shift_select" onchange="loadScheduledStaffForAppt()">
                            <option value="Morning">Morning (8AM-12PM)</option>
                            <option value="Afternoon">Afternoon (12PM-6PM)</option>
                            <option value="Night">Night (6PM-8AM)</option>
                        </select>
                    </div>
                </div>

                <h3>3. Select Location & Staff</h3>
                <div class="form-group">
                    <label>Department:</label>
                    <select id="main_cat_sch" onchange="updateSubAreas('main_cat_sch', 'sub_cat_sch', 'sub_wrapper_sch')">
                        <option value="">-- Select Category --</option>
                        <option>Surgical</option><option>Medical</option><option>Pediatric</option><option>GYM</option>
                    </select>
                </div>
                <div class="form-group" id="sub_wrapper_sch" style="display:none;">
                    <label>Specific Area:</label>
                    <select id="sub_cat_sch" onchange="loadScheduledStaffForAppt()"></select>
                </div>

                <div class="form-group">
                    <label>Available Staff:</label>
                    <select id="appt_staff"><option value="">Fill details above...</option></select>
                </div>
                <div class="form-group"><label>Exact Time (Optional):</label><input type="time" id="appt_time_only"></div>
                <button class="action-btn" onclick="saveAppointmentFixed()">Confirm Appointment</button>
            </div>`;

        if(prefillID) {
            setTimeout(() => {
                document.getElementById('appt_id').value = prefillID;
                searchPatient('appt_id');
            }, 100);
        }
    }

   else if(action === 'workload') {
    area.innerHTML = `<h1>Staff Workload Monitor</h1><hr>
        <p>Real-time view of duty staff for <b>${new Date().toLocaleDateString('en-US', {weekday: 'long'})}</b>.</p>
        <div id="workload-container" style="padding:20px; background:#f9f9f9; border-radius:8px; border: 1px solid #ddd;">🔄 Fetching staff data...</div>
        <button class="action-btn" style="margin-top:20px;" onclick="show('workload')">🔄 Refresh Monitor</button>`;

    fetch('get_staff_workload.php')
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('workload-container');
            
            if (data.error) {
                container.innerHTML = `<p style="color:red; font-weight:bold;">⚠️ Error: ${data.error}</p>`;
                return;
            }

            if (data.length === 0) {
                container.innerHTML = `<p style="color:orange; font-weight:bold;">ℹ️ No Doctors or Nurses are scheduled for today in the database.</p>`;
                return;
            }

            let html = `<table style="width:100%; border-collapse:collapse; margin-top:10px; background:white; border:2px solid darkcyan;">
                <thead>
                    <tr style="background:darkcyan; color:white;">
                        <th style="padding:12px; border:1px solid #ccc;">Duty Staff Name</th>
                        <th style="padding:12px; border:1px solid #ccc;">Profession</th>
                        <th style="padding:12px; border:1px solid #ccc;">Waiting Patients</th>
                        <th style="padding:12px; border:1px solid #ccc;">Today's Appt.</th>
                    </tr>
                </thead>
                <tbody>`;

            data.forEach(s => {
                // ሰልፍ ከ 5 በላይ ከሆነ በቀይ እንዲበራ
                let color = (s.active_patients > 5) ? 'red' : 'green';
                html += `<tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px; font-weight:bold; color:#333;">${s.full_name}</td>
                    <td style="padding:12px; text-transform:uppercase; font-size:12px; color:#666;">${s.role}</td>
                    <td style="padding:12px; text-align:center; color:${color}; font-weight:bold; font-size:20px;">${s.active_patients}</td>
                    <td style="padding:12px; text-align:center; font-weight:bold;">${s.today_appointments}</td>
                </tr>`;
            });
            html += `</tbody></table>`;
            container.innerHTML = html;
        })
        .catch(err => {
            document.getElementById('workload-container').innerHTML = `<p style="color:red;">⚠️ Server connection failed. Check XAMPP.</p>`;
        });
}

    else if(action === 'my-schedule') {
    area.innerHTML = `<h1>My Duty Schedule</h1><hr><div id="schedule-output"><p>Loading schedule...</p></div>`;
    
    // የሪሴፕሽኒስቱን ስም ከ PHP እንወስዳለን
    const recepName = "<?php echo $current_user['full_name']; ?>"; 
    
    fetch(`get_my_schedule.php?name=${encodeURIComponent(recepName)}`)
        .then(res => res.json())
        .then(data => {
            const out = document.getElementById('schedule-output');
            if(data.length === 0) {
                out.innerHTML = "<div style='padding:20px; background:#fff3cd; color:#856404; border-radius:5px;'>No schedule found for you at this time.</div>";
                return;
            }
            // 1. በእያንዳንዱ td ላይ border: 1px solid #000 መጨመሩን አስተውል
let rows = data.map(s => `
    <tr>
        <td style="padding:10px; border:1px solid #000; font-weight:bold; color:darkcyan;">${s.shift_day}</td>
        <td style="padding:10px; border:1px solid #000;">${s.shift_time}</td>
        <td style="padding:10px; border:1px solid #000;">${s.assigned_area || '-'}</td>
        <td style="padding:10px; border:1px solid #000; font-weight:bold;">${s.room || '-'}</td>
    </tr>`).join('');

// 2. በ table ዙሪያ ደማቅ መስመር (2px) እና በ th ላይም መስመር ተጨምሯል
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

    // --- 4. UPDATE PROFILE ---
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

        async function saveAssignment() {
    const mrn = document.getElementById('assign_mrn').value;
    const targetId = document.getElementById('assign_target').value;
    const complaint = document.getElementById('today_complaint').value; // በሳጥኑ የተጻፈው

    if(!mrn || !targetId || !complaint) { 
        alert("Please search patient and write Reason for Visit!"); 
        return; 
    }

    const fd = new FormData(); 
    fd.append('mrn', mrn); 
    fd.append('target', targetId); // የዶክተሩ ID
    fd.append('complaint', complaint); // መልዕክቱ

    const res = await fetch('assign_patient.php', { method: 'POST', body: fd });
    alert(await res.text());
}

        async function saveAppointment() {
            const mrn = document.getElementById('appt_id').value;
            const targetId = document.getElementById('appt_staff').value;
            const dateInput = document.getElementById('appt_date');
            const dateValue = dateInput.value;

            if(!mrn || !dateValue || !targetId) { alert("Fill all fields"); return; }

            const now = new Date();
            const selectedDate = new Date(dateValue);

            if (selectedDate < now) {
                alert("The date is already past, You make Error!");
                return;
            }

            const fd = new FormData(); 
            fd.append('mrn', mrn); 
            fd.append('staff_id', targetId); 
            fd.append('date', dateValue);
            const res = await fetch('save_appointment.php', { method: 'POST', body: fd });
            const msg = await res.text();
            alert(msg);
        }

        async function processRegistration() {
    const phone = document.getElementById('p_number').value.trim();
    
     const nameInput = document.getElementById('p_name');
    const fullName = nameInput.value.trim();
    
    // ስም ባዶ ከሆነ
    if (fullName === "") {
        nameInput.style.border = "2px solid red"; // ቦክሱን ቀይ ያደርገዋል
        nameInput.placeholder = "Full Name is Required! (ስም መሞላት አለበት)"; // መልዕክቱን ቦክሱ ውስጥ ያሳያል
        nameInput.focus(); // ተጠቃሚው እንዲጽፍ እዛው ላይ ያቆመዋል
        return; 
    } else {
        nameInput.style.border = "1px solid #ccc"; // ትክክል ከሆነ ወደ ቀድሞው ይመልሰዋል
    }
    
    // ስልኩ ከተሞላ ብቻ ርዝመቱን ቼክ ያደርጋል፣ ካልተሞላ ግን ያሳልፋል
    if (phone !== "") {
        if (phone.length < 10 || phone.length > 15) {
            alert("Error: Patient phone number must be between 10 and 15 digits!");
            return; 
        }
    }
    const dobInput = document.getElementById('p_dob').value;
    if(!dobInput) { alert("Please select Date of Birth!"); return; }

// ዕድሜን ከልደት ቀን (DOB) የማስላተ ስራ
 const birthDate = new Date(dobInput);
    const today = new Date();
    
    let years = today.getFullYear() - birthDate.getFullYear();
    let months = today.getMonth() - birthDate.getMonth();
    if (today.getDate() < birthDate.getDate()) { months--; }

    let totalMonths = (years * 12) + months;
    let finalAgeDisplay;

    if (totalMonths < 12) {
        // ከ1 ዓመት በታች ከሆነ ለምሳሌ "5/12" ይላል
        finalAgeDisplay = (totalMonths <= 0 ? 0 : totalMonths) + "/12";
    } else {
        // ከ1 ዓመት በላይ ከሆነ ዓመቱን ብቻ ለምሳሌ "25"
        finalAgeDisplay = Math.floor(totalMonths / 12).toString();
    }
    // ----------------------------

    const fd = new FormData();
    fd.append('mrn', document.getElementById('p_mrn').value);
    fd.append('full_name', document.getElementById('p_name').value);
    fd.append('reg_date', document.getElementById('p_reg_date').value);
    fd.append('age', finalAgeDisplay); // እዚህ ጋር ነው "5/12" የሚለውን የሚልከው
    fd.append('gender', document.getElementById('p_gender').value);
    fd.append('dob', dobInput);
    
    let nat = document.getElementById('p_nationality').value;
    if(nat === 'Other') nat = document.getElementById('p_nat_other').value;
    fd.append('nationality', nat);

    let reg = document.getElementById('p_region').value;
    if(reg === 'Other') reg = document.getElementById('p_reg_other').value;
    fd.append('region', reg);

    fd.append('wereda', document.getElementById('p_wereda').value);
    fd.append('kebele', document.getElementById('p_kebele').value);
    fd.append('ketena', document.getElementById('p_ketena').value);
    fd.append('house', document.getElementById('p_house').value);
    fd.append('phone', phone); // የተጣራው ስልክ ቁጥር

    const res = await fetch('save_patient.php', { method: 'POST', body: fd });
    const data = await res.text();
    
    if(data.includes("Success")) {
        const mrn = document.getElementById('p_mrn').value;
        document.getElementById('reg-msg').innerHTML = `
            <div style="background:#e8f5e9; color:green; padding:15px; border-radius:5px; margin-top:15px;">
                ✅ <b>Registration Successful!</b> MRN: <b>${mrn}</b><br><br>
                <button class="action-btn" onclick="show('assign', '${mrn}')" style="background:orange; margin-right:10px;">Assign Now</button>
                <button class="action-btn" onclick="show('schedule', '${mrn}')">Schedule Now</button>
            </div>`;
    } else { alert("Error: " + data); }
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

   
   function togglePass(inputId, iconElement) {
    const passwordInput = document.getElementById(inputId);
    
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        iconElement.textContent = "🙈"; // Icon changes to "hidden" state
    } else {
        passwordInput.type = "password";
        iconElement.textContent = "👁️"; // Icon changes back to "visible" state
    }
}
function logout() { if(confirm("Confirm Logout?")) window.location.href = "index.php"; }
   // ሰዓት ለይቶ ለመስራት ይጠቅማል
    async function loadScheduledStaff(targetSelectId, dateValue = null) {
    const select = document.getElementById(targetSelectId);
    select.innerHTML = '<option value="">Loading Scheduled Staff...</option>';
    
    let url = 'get_scheduled_staff.php';
    if(dateValue) url += `?date=${encodeURIComponent(dateValue)}`;

    try {
        const res = await fetch(url);
        const data = await res.json();
        
        select.innerHTML = '<option value="">-- Select Staff (Available Now) --</option>';
        if(data.length === 0) {
            select.innerHTML = '<option value="">❌ No staff scheduled for this time</option>';
        } else {
            data.forEach(s => {
                select.innerHTML += `<option value="${s.staff_id}">${s.full_name} (${s.role}) - ${s.assigned_area} Room:${s.room}</option>`;
            });
        }
    } catch(e) {
        select.innerHTML = '<option value="">Error loading staff</option>';
    }
}

   const areaMapping = {
    "Surgical": ["Ward", "Operation", "OPD", "Other"],
    "Medical": ["ART(HR)", "Ward", "OPD", "Other"],
    "Pediatric": ["Micu", "Ward", "OPD", "Other"],
    "GYM": ["MCC (Mother & Child Care)", "Delivery Room", "Postnatal Care", "Other"]
};

// 1. Classification Area ለመቀያየር
function updateSubAreas(mainId, subId, wrapperId) {
    const main = document.getElementById(mainId).value;
    const sub = document.getElementById(subId);
    const wrapper = document.getElementById(wrapperId);
    
    sub.innerHTML = '<option value="">-- Select Specific Area --</option>';
    if (main && areaMapping[main]) {
        wrapper.style.display = 'block';
        areaMapping[main].forEach(a => {
            let opt = document.createElement('option');
            opt.value = a; opt.textContent = a;
            sub.appendChild(opt);
        });
    } else {
        wrapper.style.display = 'none';
    }
}

// 2. ሰራተኞችን ፊልተር አድርጎ ለማምጣት
async function filterStaffByArea(type, areaId, targetSelectId, extra = {}) {
    const area = document.getElementById(areaId).value;
    const select = document.getElementById(targetSelectId);
    if(!area) return;

    select.innerHTML = '<option value="">Searching staff...</option>';
    
    let url = `get_scheduled_staff.php?area=${encodeURIComponent(area)}`;
    
    // ለቀጠሮ (Schedule) ከሆነ ተጨማሪ ቀንና ሽፍት ይልካል
    if(extra.day) url += `&day=${extra.day}`;
    if(extra.shift) url += `&shift=${extra.shift}`;

    try {
        const res = await fetch(url);
        const data = await res.json();

        select.innerHTML = '<option value="">-- Select Available Staff --</option>';
        if(data.length === 0) {
            select.innerHTML = '<option value="">❌ No Doctors/Nurses assigned here for this shift</option>';
        } else {
            data.forEach(s => {
                // እዚህ ጋር የዶክተሩን ስም፣ ሙያ እና ክፍል ቁጥር ያሳያል
                select.innerHTML += `<option value="${s.staff_id}">${s.full_name} (${s.role.toUpperCase()}) - Rm: ${s.room || 'N/A'}</option>`;
            });
        }
    } catch(e) {
        select.innerHTML = '<option value="">Error fetching data</option>';
    }
}
function loadScheduledStaffForAppt() {
    const dateVal = document.getElementById('appt_date_only').value;
    const shiftVal = document.getElementById('appt_shift_select').value;
    
    if(!dateVal) { alert("Please select a date first"); return; }
    
    // ቀኑን ወደ Day Name መቀየር (e.g. Monday)
    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const d = new Date(dateVal);
    const dayName = days[d.getDay()];

    filterStaffByArea('schedule', 'sub_cat_sch', 'appt_staff', { day: dayName, shift: shiftVal });
}

// የቀጠሮ ማስቀመጫ ማስተካከያ
async function saveAppointmentFixed() {
    const mrn = document.getElementById('appt_id').value;
    const staff = document.getElementById('appt_staff').value;
    const date = document.getElementById('appt_date_only').value;
    const time = document.getElementById('appt_time_only').value;
    
    // 1. ሁሉም ቦታ መሞላቱን ማረጋገጥ
    if(!mrn || !staff || !date || !time) { 
        alert("Please fill all fields!"); 
        return; 
    }
    
    // 2. የተመረጠውን ቀን እና ሰዓት ወደ JavaScript Date መቀየር
    const selectedDateTime = new Date(date + " " + time);
    const now = new Date();
    
    // 3. ልዩነቱን በሰዓት ማስላት (milliseconds to hours)
    const diffInMilliseconds = selectedDateTime - now;
    const diffInHours = diffInMilliseconds / (1000 * 60 * 60);

    // 4. ህጉን ማረጋገጥ (ቢያንስ 5 ሰዓት መሆን አለበት)
    if (diffInHours < 5) {
        alert("⚠️ Error: Appointments must be scheduled at least 5 hours from now!");
        return; // እዚህ ጋር ይቆማል፣ ዳታቤዝ ውስጥ አይገባም
    }
    
    // 5. ህጉ ከተከበረ ወደ ዳታቤዝ እንዲገባ ይላካል
    const fullDateTime = date + " " + time;
    const fd = new FormData();
    fd.append('mrn', mrn); 
    fd.append('staff_id', staff); 
    fd.append('date', fullDateTime);
    
    try {
        const res = await fetch('save_appointment.php', { method: 'POST', body: fd });
        alert(await res.text());
    } catch (e) {
        alert("Connection Error. Failed to save appointment.");
    }
}
// 1. ዕድሜን ከልደት ቀን አስልቶ ማስገቢያ
function calculateAge() {
    const dobInput = document.getElementById('p_dob');
    if (!dobInput.value) return;

    const birthDate = new Date(dobInput.value);
    const today = new Date();
    
    // የዓመት እና የወር ልዩነትን ማስላት
    let years = today.getFullYear() - birthDate.getFullYear();
    let months = today.getMonth() - birthDate.getMonth();
    
    if (today.getDate() < birthDate.getDate()) {
        months--;
    }

    let totalMonths = (years * 12) + months;
    let ageToStore;

    if (totalMonths < 12) {
        // ከ1 ዓመት በታች ከሆነ ወር/12 ይላል
        ageToStore = (totalMonths < 0 ? 0 : totalMonths) + "/12";
    } else {
        // ከ1 ዓመት በላይ ከሆነ ዓመቱን ብቻ
        ageToStore = Math.floor(totalMonths / 12);
    }

    // በምዝገባ ፎርሙ ላይ ዕድሜ የሚታይበት ሳጥን ካለ ID ውን 'p_age' አድርገው
    const ageField = document.getElementById('p_age');
    if(ageField) ageField.value = ageToStore;

    return ageToStore;
}

// 2. ቋንቋ መቀያየሪያ (EN/Amh)
let currentLang = 'EN';
function toggleLanguage() {
    const dict = {
        'AMH': {
            't-folder': 'የታካሚ የግል ማህደር',
            't-desc': 'አዲስ ካርድ ለመክፈት መረጃዎችን እዚህ ይሙሉ',
            'l-mrn': 'የህክምና ካርድ ቁጥር (MRN):',
            'l-name': 'ሙሉ ስም:',
            'l-reg-date': 'የተመዘገበበት ቀን:',
            'l-dob': 'የልደት ቀን (DOB):',
            'l-age': 'ዕድሜ:',
            'l-gender': 'ጾታ:',
            'l-nat': 'ዜግነት:',
            'l-region': 'ክልል:',
            'l-wereda': 'ወረዳ/ክፍለ ከተማ:',
            'l-kebele': 'ቀበሌ:',
            'l-ketena': 'ቀጠና/ጎጥ:',
            'l-house': 'የቤት ቁጥር:',
            'l-phone': 'የስልክ ቁጥር:',
            'l-reason': 'የመጣበት ምክንያት / ህመም:',
            'reg-btn-text': 'መዝግብ'
        },
        'EN': {
            't-folder': 'INDIVIDUAL FOLDER',
            't-desc': 'Enter details to generate a new Hospital ID.',
            'l-mrn': 'Medical Record Number:',
            'l-name': 'Full Name:',
            'l-reg-date': 'Date of Registration:',
            'l-dob': 'Date of Birth:',
            'l-age': 'Age:',
            'l-gender': 'Gender:',
            'l-nat': 'Nationality:',
            'l-region': 'Region:',
            'l-wereda': 'Wereda/SubCity:',
            'l-kebele': 'Kebele:',
            'l-ketena': 'Ketena/Gott:',
            'l-house': 'House Number:',
            'l-phone': 'Phone Number (Optional):',
            'l-reason': 'Initial Complaint / Reason for Visit',
            'reg-btn-text': 'Register'
        }
    };

    currentLang = (currentLang === 'EN') ? 'AMH' : 'EN';
    const lang = dict[currentLang];

    for (let id in lang) {
        const el = document.getElementById(id);
        if (el) el.innerText = lang[id];
    }
}

async function cancelAssignment(mrn) {
    if (!confirm("Are you sure you want to cancel this patient's current assignment?")) return;

    const fd = new FormData();
    fd.append('mrn', mrn);

    try {
        const res = await fetch('cancel_assignment.php', { method: 'POST', body: fd });
        const msg = await res.text();
        alert(msg);
        
        // ገጹን ሳናድስ (Refresh ሳናደርግ) መረጃው እንዲቀየር ሰርች ፋንክሽኑን ድጋሚ እንጠራዋለን
        searchPatient('assign_mrn'); 
    } catch (e) {
        alert("Error: Connection failed.");
    }
}
async function deleteAppointment(apptId) {
    if (!confirm("Cancel this appointment?")) return;
    const fd = new FormData();
    fd.append('appointment_id', apptId);
    const res = await fetch('delete_appointment.php', { method: 'POST', body: fd });
    alert(await res.text());
    searchPatient('appt_id');
}
function openEditProfile(data) {
    show('register', '', data);
}
   </script>
</body>
</html>