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
                <p>The Administrator has locked the system for maintenance or security reasons.</p>
                <p>Please contact the System Administrator to continue.</p>
                <br><a href='login.html' style='color:greenyellow;'>Return to Login</a>
              </body>";
        exit();
    }
}

session_start();
// 2. Authentication Check
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.html"); 
    exit();
}

$current_staff_id = $_SESSION['staff_id'];

// 3. Fetch User Profile
$user_query = $conn->prepare("SELECT * FROM users WHERE staff_id = ?");
$user_query->bind_param("s", $current_staff_id);
$user_query->execute();
$current_user = $user_query->get_result()->fetch_assoc();

// 4. Fetch Two Separate Lists
// Incoming: ID disappears if Re-assigned OR Appointment exists
$direct_list = [];
$sql1 = "SELECT p.mrn, p.assigned_at as target_time 
        FROM patients p 
        WHERE p.assigned_to_dept = ? 
        AND p.assigned_at >= DATE_SUB(NOW(), INTERVAL 12 HOUR)
        AND NOT EXISTS (SELECT 1 FROM appointments a WHERE a.mrn = p.mrn AND a.staff_id = ?)";
// ማሳሰቢያ፡ "AND NOT EXISTS (... prescriptions ...)" የሚለው መስመር ተወግዷል
$stmt1 = $conn->prepare($sql1);
$stmt1->bind_param("ss", $current_staff_id, $current_staff_id);
$stmt1->execute();
$res1 = $stmt1->get_result();
while($row = $res1->fetch_assoc()){ $direct_list[] = ['id' => $row['mrn'], 'time' => $row['target_time']]; }
// Scheduled: MRN appears here as soon as Appointment is saved
$scheduled_list = [];
$sql2 = "SELECT a.mrn, a.appointment_date as target_time 
        FROM appointments a
        WHERE a.staff_id = ? 
        AND NOT EXISTS (SELECT 1 FROM prescriptions pr WHERE pr.mrn = a.mrn AND pr.created_at >= a.appointment_date)";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("s", $current_staff_id);
$stmt2->execute();
$res2 = $stmt2->get_result();
while($row = $res2->fetch_assoc()){ $scheduled_list[] = ['id' => $row['mrn'], 'time' => $row['target_time']]; }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Doctor Dashboard - Adigrat Hospital</title>
    <style>
        .form-container {
            background-color: white;
            padding: 30px;
            width: 950px;
            margin: 0 auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
        }
        th, td {
            border: 1px solid #000;
            padding: 0;
            position: relative;
            vertical-align: top;
        }
        .col-time { width: 200px; }
        .col-label { width: 150px; background-color: #f9f9f9; padding: 8px; font-weight: bold; font-size: 14px; }
        .col-content { width: auto; }
        th {
            padding: 8px;
            font-weight: bold;
            text-align: left;
            background-color: #f9f9f9;
        }
        input[type="datetime-local"], textarea {
            width: 100%;
            border: none;
            outline: none;
            padding: 8px;
            box-sizing: border-box;
            font-size: 14px;
            background: transparent;
            display: block;
            font-family: inherit;
        }
        textarea { resize: vertical; min-height: 80px; line-height: 1.5; }
        .submit-section { margin-top: 20px; text-align: right; }
        button {
            padding: 10px 25px;
            background-color: #0056b3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover { background-color: #46e58e; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; height: 100vh; }
        .sidebar {
            width: 250px;
            background-color: #329f92;
            color: white;
            display: flex;
            flex-direction: column;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.2);
        }
        .sidebar h2 { font-size: 1.2rem; border-bottom: 1px solid white; padding-bottom: 10px; margin-bottom: 20px; }
        .nav-btn {
            background: #1c6e61;
            color: white;
            border: none;
            padding: 15px;
            margin-bottom: 10px;
            text-align: left;
            cursor: pointer;
            border-radius: 4px;
            font-weight: bold;
            transition: 0.3s;
        }
        .nav-btn:hover { background: #19ef9d; color: white; }
        .nav-btn.active {
    background: #e0f2f1 !important; 
    color: #004d40 !important; 
    border-left: 6px solid #004d40 !important;
    box-shadow: inset 0 0 5px rgba(0,0,0,0.1);
}
        .logout-btn { margin-top: auto; background: #0c0a0a; width: fit-content; align-self: center; padding: 10px 30px;}
        .logout-btn:hover { background: #f70000;; color: white; }
        .main-content { flex: 1; padding: 40px; background-color: #f4f4f4; overflow-y: auto; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #333; }
        .form-group input, .form-group textarea, .form-group select { 
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
        }
        .action-btn { background: darkcyan; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        .option-menu { margin-top: 20px; padding: 15px; background: #f0f0f0; border-left: 5px solid darkcyan; border-radius: 4px; }
        .option-menu button { margin: 5px; font-size: 13px; padding: 8px 12px; }
        .id-label-display { font-size: 18px; font-weight: bold; color: darkcyan; background: #e7f3f3; padding: 10px; border-radius: 4px; display: inline-block; border: 1px solid darkcyan; }
        .back-btn { background-color: #6c757d; margin-left: 10px; }
        #php-med-sheet-v1 { margin: 20px 0 !important; overflow-x: auto; }
        .ms-table { border-collapse: collapse !important; width: auto !important; border: 1.5px solid black !important; background-color: white !important; }
        .ms-th, .ms-td { border: 1px solid black !important; height: 22px !important; padding: 0 !important; text-align: center !important; font-size: 11px !important; font-weight: bold !important; color: black !important; }
        .ms-header-bg { background-color: #e8e8e8 !important; }
        .ms-col-sn { width: 30px !important; }
        .ms-col-desc { width: 140px !important; }
        .ms-col-hrs { width: 45px !important; }
        .ms-col-day { width: 22px !important; }
        .ms-input { width: 100% !important; height: 100% !important; border: none !important; padding: 0 3px !important; box-sizing: border-box !important; outline: none !important; font-size: 11px !important; }
        .ms-check { appearance: none !important; -webkit-appearance: none !important; width: 100% !important; height: 100% !important; cursor: pointer !important; margin: 0 !important; display: block !important; }
        .ms-check:checked::after { content: '✔' !important; font-size: 13px !important; color: black !important; display: flex !important; align-items: center !important; justify-content: center !important; }
   

   
@media print {
    body * { visibility: hidden; }
    .printable-sheet, .printable-sheet * { visibility: visible; }
    .printable-sheet { 
        position: absolute; left: 0; top: 0; 
        width: 210mm; min-height: 297mm; 
        padding: 20mm; margin: 0; 
        box-shadow: none !important; border: 1px solid #ccc !important;
    }
    .no-print { display: none !important; }
}
.printable-sheet {
    background: white;
    width: 210mm;
    min-height: 297mm;
    padding: 15mm;
    margin: 20px auto;
    border: 1px solid #ddd;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    font-family: 'Times New Roman', serif;
    color: black;
    position: relative;
}
.sheet-header { text-align: center; border-bottom: 2px solid #333; margin-bottom: 20px; padding-bottom: 10px; }
.sheet-row { display: flex; margin-bottom: 10px; border-bottom: 1px dotted #ccc; padding-bottom: 5px; }
.sheet-label { font-weight: bold; width: 200px; color: #444; }
.sheet-value { flex: 1; white-space: pre-wrap; }

/* ነባር ስታይሎችህ እንዳሉ ሆነው እነዚህን ጨምር/አድስ */
.printable-sheet {
    background: white;
    width: 210mm;
    min-height: 297mm;
    padding: 20mm;
    margin: 20px auto;
    border: 1px solid #000;
    font-family: Arial, sans-serif;
    color: black;
}
.sheet-header { text-align: center; margin-bottom: 30px; }
.patient-info-box { 
    border: 2px solid #000; 
    padding: 15px; 
    margin-bottom: 25px; 
    font-size: 16px; /* መጠኑ ትልቅ እንዲሆን */
}
.info-row { display: flex; margin-bottom: 8px; border-bottom: 1px solid #eee; }
.info-label { font-weight: bold; width: 180px; }
.info-value { flex: 1; }

.sheet-body { font-size: 15px; }
.sheet-row { margin-bottom: 15px; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
.sheet-label { font-weight: bold; display: block; margin-bottom: 5px; color: #333; text-transform: uppercase; font-size: 13px; }
.sheet-value { white-space: pre-wrap; padding-left: 10px; }

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
/* የዙም ማሳያ ስታይል - በ <style> መጨረሻ ላይ ይግባ */
#labZoomOverlay {
    display: none; position: fixed; z-index: 10000; left: 0; top: 0;
    width: 100%; height: 100%; background: rgba(0,0,0,0.9);
    text-align: center; overflow: auto;
}
.zoom-tools {
    position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
    z-index: 10001; background: white; padding: 10px; border-radius: 8px;
    display: flex; gap: 10px; box-shadow: 0 0 15px #000;
}
.zoom-btn-item {
    padding: 10px 20px; font-size: 18px; cursor: pointer; font-weight: bold;
    border: 1px solid #ccc; background: #f9f9f9;
}
#zoomTargetImg {
    margin-top: 100px; transition: transform 0.2s; max-width: 90%;
}
.nav-btn { position: relative; } /* ይህ አስፈላጊ ነው ባጁን ለመለጠፍ */

/* ለዋናው ሳይድባር ባጅ */
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

/* ለታካሚው Request Lab በተን ባጅ */
.lab-badge-inline {
    background: red;
    color: white;
    border-radius: 50%;
    padding: 1px 6px;
    font-size: 10px;
    margin-left: 5px;
    font-weight: bold;
}
   </style>
</head>

<body>
<div class="sidebar">
    <h2>CHOOSE PANEL</h2>
    <button class="nav-btn" id="record-nav-btn" onclick="show('record')">
    Access Patient Record
    <span id="notif-badge" class="badge">0</span>
</button>
<button class="nav-btn" onclick="show('my-schedule')">View My Schedule</button> 
<button class="nav-btn" onclick="show('profile')">Update Personal Profile</button>
    <button class="nav-btn logout-btn" onclick="logout()">Logout</button>
</div>

<div class="main-content">
    <div class="card" id="display-area">
        <h1>Welcome, Dr. <?php echo htmlspecialchars($current_user['full_name']); ?></h1>
        <p>Please select an action from the left sidebar to manage patients.</p>
    </div>
</div>

<script>
    const currentStaffId = "<?php echo $_SESSION['staff_id']; ?>";
    function renderDemographics(data) {
        if(!data) return "";
        return `
            <div style="background:#e7f3f3; padding:15px; border:1px solid darkcyan; border-radius:8px; margin: 20px 0;">
                <h3 style="margin-top:0; color:darkcyan;">INDIVIDUAL FOLDER (Demographic Data)</h3>
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px; font-size: 14px;">
                    <p><strong>Name:</strong> ${data.full_name}</p>
                    <p><strong>MRN:</strong> ${data.mrn}</p>
                    <p><strong>Age:</strong> ${data.age} | <strong>Gender:</strong> ${data.gender}</p>
                    <p><strong>Region:</strong> ${data.region} | <strong>Wereda:</strong> ${data.wereda}</p>
                    <p><strong>Phone:</strong> ${data.phone}</p>
                    <p><strong>Registration Date:</strong> ${data.reg_date}</p>
                </div>
                <p><strong>Reason for Visit:</strong> ${data.initial_complaint}</p>
            </div>`;
    }

    async function saveToDB(action, data) {
    const patientId = data.id;
    const area = document.getElementById('display-area');
    const formData = new FormData();
    formData.append('action', action);
    formData.append('patient_id', patientId);

    // a. Validation: Lab እና Prescription ምርጫ ካልተደረገ እንዲያቆም
    if (action === 'Lab Request') {
        const labTech = area.querySelector('select[name="assigned_to_lab_technician"]');
        if (!labTech || labTech.value === "") { 
            alert("Lab Technician not selected! Please choose a technician."); 
            return; 
        }
    }
    
    if (action === 'Prescription') {
        const pharmacist = area.querySelector('select[name="assigned_pharmacist"]');
        if (!pharmacist || pharmacist.value === "") { 
            alert("Pharmacist not selected! Please choose a pharmacist."); 
            return; 
        }
    }

    if (action === 'Reassignment') {
        const selectField = document.getElementById('re_assign_to_staff_');
        if (!selectField || selectField.value === "") { alert("Staff not selected"); return; }
    }

    if (action === 'Appointment') {
        const dtInput = area.querySelector('input[type="datetime-local"]');
        if (dtInput && dtInput.value && new Date(dtInput.value) < new Date()) { 
            alert("The date is already past! Please select a future date."); return; 
        }
    }

    // ሁሉንም input values መሰብሰብ
    const inputs = area.querySelectorAll('input, textarea, select');
    // በ saveToDB function ውስጥ inputs.forEach ከሚለው በላይ ጨምረው
        inputs.forEach(el => {
            let key = el.name || el.id;
            if (!key) return;

            if (el.type === 'checkbox') {
                // ቼክቦክስ ከተመረጠ '1' እንዲልክ
                if (el.checked) formData.append(key, '1');
            } else if (el.type === 'radio') {
                if (el.checked) formData.append('condition', el.value);
            } else {
                formData.append(key, el.value);
            }
        });
    inputs.forEach(el => {
        let key = el.name || el.id;
        if (!key) return;
        if (el.type === 'checkbox') { if (el.checked) formData.append(key, '1'); } 
        else if (el.type === 'radio') { if (el.checked) formData.append('condition', el.value); } 
        else { formData.append(key, el.value); }
    });

    try {
        const res = await fetch('save_clinical.php', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.status === 'success') { 
            alert(action + " Processed Successfully!"); 

            // 1. የሞላኸው ዳታ ከፎርሙ ላይ እንዲጠፋ (Reset) ማድረግ
            const inputsToClear = area.querySelectorAll('input, textarea, select');
            inputsToClear.forEach(el => {
                if (el.type === 'checkbox' || el.type === 'radio') {
                    el.checked = false;
                } else {
                    el.value = '';
                }
            });

            // 2. በዛው ፓነል ላይ እንዲቆይ ማድረግ (ወደ Welcome እንዳይሄድ)
            // Reassignment ወይም Discharge ከሆነ ታካሚው ስለሚጠፋ ገጹ Refresh መሆኑ አይቀርም
            if (action === 'Reassignment' || action === 'Discharge Summary') {
                location.reload(); 
            } else {
                // ለሌሎች ስራዎች ግን በዛው ፓነል ላይ እንዲቆይ show function ን ድጋሚ እንጠራዋለን
                show(action, patientId); 
            }

        } else { 
            alert("Error: " + result.error); 
        }
    } catch (e) { alert("Connection error: Ensure save_clinical.php is reachable."); }
}

   async function pickAction(patientId, scheduledTime) {
    if(!patientId) return;
    
    // ሰዓቱ ካልደረሰ ለመከልከል (ያንተ ሎጅክ)
    const now = new Date();
    const scheduled = new Date(scheduledTime);
    if (now < scheduled) { 
        alert("This ID is scheduled for " + scheduledTime + ". Access denied until time reached."); 
        return; 
    }

    try {
        const res = await fetch(`get_full_history.php?mrn=${patientId}`);
        const data = await res.json();
        
        // አዲስ የላብ ውጤት ካለ በቀይ ቁጥር ያሳያል
        let unread = data.unread_labs || 0;
        let labBadge = (unread > 0) ? `<span class="lab-badge-inline">${unread}</span>` : "";

        const output = document.getElementById('record-output');
        output.innerHTML = renderDemographics(data.demographics) + `<div class="option-menu">
                <h3>Select Action for ID: ${patientId}</h3>
                <button onclick="show('previous_record', '${patientId}')">Access Previous Record</button>
                <button onclick="show('update', '${patientId}')">Update Information</button>
                <button onclick="show('Note', '${patientId}')">Progress Note</button>
                <button onclick="show('Order', '${patientId}')">Order Sheet</button>
                <button onclick="show('lab', '${patientId}')">Request Lab ${labBadge}</button>
                <button onclick="show('meds', '${patientId}')">Prescribe Medication</button>
                <button onclick="show('appt', '${patientId}')">Set Appointment</button>
                <button onclick="show('follow_up', '${patientId}')">Follow Up</button>
                <button onclick="show('reassign', '${patientId}')">Re-Assign</button>
                <button onclick="show('Discharge', '${patientId}')">Discharge Summary</button>
            </div>`;
    } catch (e) {
        console.error("Error loading patient data:", e);
        alert("Error loading patient history. Please check if get_full_history.php is working.");
    }
}

async function show(action, patientId = '') {
    if (action === 'record') {
        await fetch('clear_notif.php?type=patient');
        checkNotifications();
    }
    if (action === 'lab' && patientId !== '') {
        await fetch(`clear_notif.php?type=lab&mrn=${patientId}`);
        checkNotifications();
    }

    const navButtons = document.querySelectorAll('.nav-btn');
    navButtons.forEach(btn => btn.classList.remove('active'));
    navButtons.forEach(btn => {
        const btnClick = btn.getAttribute('onclick');
        
        if (action !== 'profile' && btnClick && btnClick.includes("'record'")) {
            btn.classList.add('active');
        } 
        else if (action === 'profile' && btnClick && btnClick.includes("'profile'")) {
            btn.classList.add('active');
        }
    });
        const area = document.getElementById('display-area');
        const idHeader = (patientId) ? `<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;"><div class="id-label-display">Medical Record Number: ${patientId}</div><button class="action-btn back-btn" onclick="show('record', '${patientId}')">⬅ Back to Options</button></div>` : "";
        let demoHTML = "";
        if(patientId) {
            const res = await fetch(`get_full_history.php?mrn=${patientId}`);
            const data = await res.json();
            demoHTML = renderDemographics(data.demographics);
        }

        if(action === 'record') {
            const now = new Date();
            const direct = <?php echo json_encode($direct_list); ?>; 
            const scheduled = <?php echo json_encode($scheduled_list); ?>; 
            
            // COLOR RESTORED: Incoming IDs are Darkcyan
            let dHTML = direct.map(p => `<button onclick="pickAction('${p.id}', '${p.time}')" style="background:darkcyan; color:white; padding:5px 10px; border-radius:15px; margin-right:5px; font-size:0.8em; border:none; cursor:pointer;">${p.id}</button>`).join('');
            
            // COLOR Logic: Scheduled are Orange if future, Darkcyan if reached
            let sHTML = scheduled.map(p => {
                let color = (now >= new Date(p.time)) ? 'darkcyan' : 'orange';
                return `<button onclick="pickAction('${p.id}', '${p.time}')" style="background:${color}; color:white; padding:5px 10px; border-radius:15px; margin-right:5px; font-size:0.8em; border:none; cursor:pointer;">${p.id}</button>`;
            }).join('');
            area.innerHTML = `<h1>Access Patient Record</h1><div style="display:flex; gap:20px;"><div style="flex:1; background:#e7f3f3; padding:15px; border-radius:8px; margin-bottom:20px;"><h4 style="margin-top:0; color:#006666;">Incoming IDs:</h4><div>${dHTML || 'None'}</div></div><div style="flex:1; background:#fff3e0; padding:15px; border-radius:8px; margin-bottom:20px;"><h4 style="margin-top:0; color:#e67e22;">Scheduled IDs:</h4><div>${sHTML || 'None'}</div></div></div><p style="font-weight: bold; color: #555;">Select the above Medical Record Numbers to treat them:</p><div id="record-output"></div>`;
            if(patientId) pickAction(patientId, '2000-01-01');
        }
        /////////////////////////////////////////////////////////////////////////////////////
       else if(action === 'previous_record') {
    try {
        const res = await fetch(`get_full_history.php?mrn=${patientId}`);
        const data = await res.json();
        let finalHTML = ""; 
        
        if (data && data.history && data.history.length > 0) {
            // 1. Follow Up (እንደነበረው ይቀጥላል)
            let followUps = data.history.filter(h => h.action_type === 'Follow Up');
            followUps.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
            let batches = {};
            followUps.forEach(h => { let ts = h.created_at; if (!batches[ts]) batches[ts] = []; batches[ts].push(h); });
            let followUpTablesHTML = "";
            let currentEpisode = [];
            let sortedTimestamps = Object.keys(batches).sort((a, b) => new Date(a) - new Date(b));
            sortedTimestamps.forEach(ts => {
                let batchItems = batches[ts];
                let statusOfThisBatch = batchItems[0].status;
                currentEpisode = currentEpisode.concat(batchItems);
                if (statusOfThisBatch !== 'Ongoing' && statusOfThisBatch !== '' && statusOfThisBatch !== null) {
                    followUpTablesHTML = renderFollowUpTable(currentEpisode) + followUpTablesHTML;
                    currentEpisode = [];
                }
            });
            if (currentEpisode.length > 0) followUpTablesHTML = renderFollowUpTable(currentEpisode) + followUpTablesHTML;

            // 2. ሌሎች መረጃዎች (A4 Sheet)
            let otherRecordsHTML = "";
            const otherRecords = data.history.filter(h => h.action_type !== 'Follow Up');
            otherRecords.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

            otherRecords.forEach(h => {
                otherRecordsHTML += renderAsA4Sheet(h, data.demographics);
            });

            finalHTML = followUpTablesHTML + otherRecordsHTML;
        } else {
            finalHTML = "<p>No medical history found.</p>";
        }
        area.innerHTML = `<h1>Full Medical History</h1>${idHeader}${finalHTML}`;
    } catch (error) { console.error(error); }
}
///////////////////////////////////////////////////////////////////////////////////
        else if(action === 'update') {
            area.innerHTML = `<h1>Update Patient Information</h1>${idHeader} ${demoHTML}
                <div style="display:flex; gap:10px;"><div class="form-group"><label>Ward:</label><input type="text" name="ward"></div><div class="form-group"><label>Bed Number:</label><input type="text" name="bed_number"></div><div class="form-group"><label>Department:</label><input type="Text" name="department"></div></div>
                <div class="form-group"><label>CC(Chief Complaint):</label><textarea name="cc_chief_complaint_" rows="4"></textarea></div><div class="form-group"><label>HPI(History of Present Illness):</label><textarea name="hpi_history_of_present_illness_" rows="4"></textarea></div><div class="form-group"><label>P/E(Physical Examination):</label><textarea name="p_e_physical_examination_" rows="4"></textarea></div><div class="form-group"><label>Assessiment:</label><textarea name="assessiment_" rows="4"></textarea></div><div class="form-group"><label>Plane:</label><textarea name="plane_" rows="4"></textarea></div>
                <button class="action-btn" onclick="saveToDB('Update Info', {id: '${patientId}'})">Save Changes</button>`;
        }
        else if(action === 'Note') {
            area.innerHTML = `<h1>Progress Note</h1>${idHeader} ${demoHTML}
                <div style="display:flex; gap:10px;"><div class="form-group"><label>Ward:</label><input type="text" name="ward"></div><div class="form-group"><label>Bed Number:</label><input type="text" name="bed_number"></div></div>
                <div class="form-group"><label>Current Problem List:</label><textarea name="current_problem_list_" rows="4"></textarea></div><div class="form-group"><label>Current Management Summary:</label><textarea name="current_management_summary_" rows="4"></textarea></div><div class="form-group"><label>Update In History</label><textarea name="update_in_history" rows="4"></textarea></div><div class="form-group"><label>Update in Physical Examination:</label><textarea name="update_in_physical_examination_" rows="4"></textarea></div><div class="form-group"><label>Current Assesment:</label><textarea name="current_assesment_" rows="4"></textarea></div><div class="form-group"><label>Suggested Plan:</label><textarea name="suggested_plan_" rows="4"></textarea></div>
                <button class="action-btn" onclick="saveToDB('Progress Note', {id: '${patientId}'})">Save Changes</button>`;
        }
        else if(action === 'Order') {
            area.innerHTML = `<h1>Order Sheet</h1>${idHeader} ${demoHTML}
            <div style="display:flex; gap:10px;"><div class="form-group"><label>Ward:</label><input type="text" name="ward"></div><div class="form-group"><label>Bed Number:</label><input type="text" name="bed_number"></div></div>
                <div class="form-container">
                    <table>
                        <thead>
                            <tr>
                                <th class="col-label">Category</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="col-label">Diagnosis</td>
                                <td><textarea name="diagnosis_text"></textarea></td>
                            </tr>
                            <tr>
                                <td class="col-label">Condition</td>
                                <td><textarea name="condition_text"></textarea></td>
                            </tr>
                            <tr>
                                <td class="col-label">Vital Sign</td>
                                <td><textarea name="vital_signs"></textarea></td>
                            </tr>
                            <tr>
                                <td class="col-label">Nursing Care</td>
                                <td><textarea name="nursing_care"></textarea></td>
                            </tr>
                            <tr>
                                <td class="col-label">Diet</td>
                                <td><textarea name="diet"></textarea></td>
                            </tr>
                            <tr>
                                <td class="col-label">Investigation</td>
                                <td><textarea name="investigation"></textarea></td>
                            </tr>
                            <tr>
                                <td class="col-label">Management</td>
                                <td><textarea name="management_text"></textarea></td>
                            </tr>
                        </tbody>
                    </table>
                <div class="submit-section"><button onclick="saveToDB('Order Sheet', {id: '${patientId}'})">Save Record</button></div></div>`;
        }
        else if(action === 'lab') {
    area.innerHTML = `<h1>Request Lab Test</h1>${idHeader} ${demoHTML}
        <div class="form-group"><label>Select Test Type</label><select name="select_test_type" onchange="checkLabOther(this)"><option>Blood Count (CBC)</option><option>Urinalysis</option><option>X-Ray</option><option value="Other">Other</option></select> </div>
        <div class="form-group" id="lab-other-input-div" style="display:none;"><label>Please Specify Other Test:</label><input type="text" name="please_specify_other_test_" placeholder="Enter test name..."></div>
        <div class="form-group"><label>Assign to Lab Technician:</label><select name="assigned_to_lab_technician" id="assigned_lab_tech"><option value="">Loading...</option></select></div>
        <button class="action-btn" onclick="saveToDB('Lab Request', {id: '${patientId}'})">Send Request</button>

        <hr style="margin: 25px 0; border-color: #ccc;">
        <h3 style="color: darkcyan;">📋 Lab Results for This Patient</h3>
        <div id="lab-results-container" style="margin-top:10px;">
            <p style="color:#888;">Loading results...</p>
        </div>`;

    // Load lab technicians dropdown
    area.querySelector('select[name="assigned_to_lab_technician"]').id = "lab_tech_select";
filterStaffByArea('lab', '', 'lab_tech_select');

    // Load lab results for this patient
    fetch(`get_lab_results.php?mrn=${patientId}`)
        .then(res => res.json())
        .then(results => {
    const container = document.getElementById('lab-results-container');
    if (!results || results.length === 0) {
        container.innerHTML = `<p style="color:#999; font-style:italic;">No lab results found in the last 12 hours.</p>`;
        return;
    }
    
    // 6 Columns: Type | Result | Image | Notes | Status | Date
    let html = `
    <table style="width:100%; border-collapse:collapse; border:2px solid darkcyan;">
        <thead>
            <tr style="background:#f0f7f7; color:darkcyan;">
                <th style="padding:10px; border:1px solid #ccc;">Test Type</th>
                <th style="padding:10px; border:1px solid #ccc;">Result Text</th>
                <th style="padding:10px; border:1px solid #ccc;">X-Ray/Image</th>
                <th style="padding:10px; border:1px solid #ccc;">Technician Notes</th>
                <th style="padding:10px; border:1px solid #ccc;">Status</th>
                <th style="padding:10px; border:1px solid #ccc;">Date</th>
            </tr>
        </thead>
        <tbody>`;

    // በ show('lab') ውስጥ ይሄን ፈልገህ ተካው
results.forEach(r => {
    const isDone = r.status === 'Completed';
    const statusColor = isDone ? 'green' : 'orange';
    
    let imgLink = '-';
    if (isDone && r.result_image) {
        // 'startZoom' የሚለውን ፋንክሽን እዚህ እንጠራዋለን
        imgLink = `<button class="action-btn" style="padding:4px 8px; font-size:11px; background:darkcyan;" 
                    onclick="startZoom('${r.result_image}')">🖼️ View Image</button>`;
    }

    html += `<tr style="border-bottom:1px solid #eee;">
        <td style="padding:10px; border:1px solid #ddd; font-weight:bold; font-size:13px;">${r.test_type}</td>
        <td style="padding:10px; border:1px solid #ddd; text-align:center; font-size:13px;">${r.result || '-'}</td>
        <td style="padding:10px; border:1px solid #ddd; text-align:center;">${imgLink}</td>
        
        <!-- ማስተካከያ፡ Technician Notes ጥቁር እና Bold ያልሆነ -->
        <td style="padding:10px; border:1px solid #ddd; color:black !important; font-weight:normal !important; font-size:13px;">${r.notes || '-'}</td>
        
        <td style="padding:10px; border:1px solid #ddd; text-align:center; color:${statusColor}; font-weight:bold; font-size:13px;">${isDone ? 'Completed' : 'Pending'}</td>
        <td style="padding:10px; border:1px solid #ddd; font-size:11px; text-align:center;">${r.created_at}</td>
    </tr>`;
});
    
    html += `</tbody></table>
    <!-- ምስል ማሳያ Modal -->
    <div id="imageViewer">
        <img id="fullImage" src="">
        <br><button class="close-viewer" onclick="closeImageViewer()">⬅ Back to Results</button>
    </div>`;
    
    container.innerHTML = html;
})
        .catch(() => {
            document.getElementById('lab-results-container').innerHTML = `<p style="color:red;">Failed to load results. Make sure get_lab_results.php exists.</p>`;
        });
}
        else if(action === 'meds') {
    area.innerHTML = `<h1>Prescribe Medication</h1>${idHeader} ${demoHTML}
        <div class="form-group"><label>Medication Name</label><input type="text" name="medication_name" placeholder="e.g. Paracetamol"></div>
        <div class="form-group"><label>Dosage Instruction</label><input type="text" name="dosage_instruction" placeholder="e.g. 500mg, twice a day"></div>
        <div class="form-group"><label>Assign to Pharmacist:</label><select name="assigned_pharmacist" id="pharm_select"><option value="">Loading...</option></select></div>
        <button class="action-btn" onclick="saveToDB('Prescription', {id: '${patientId}'})">Send to Pharmacist</button>

        <hr style="margin: 30px 0; border-color: #ccc;">
        <h3 style="color: darkcyan;">💊 Pharmacy Request Status (Last 24 Hours)</h3>
        <div id="prescription-status-container">
            <p style="color:#888;">Loading status...</p>
        </div>`;

    // 1. ፋርማሲስት መምረጫውን ሎድ አድርግ
    filterStaffByArea('pharmacist', '', 'pharm_select');

    // 2. የትዕዛዙን ሁኔታ (Status) ሎድ አድርግ
    fetch(`get_prescription_status.php?mrn=${patientId}`)
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('prescription-status-container');
            if(!data || data.length === 0) {
                container.innerHTML = "<p style='color:#999; font-style:italic;'>No medication requests found for this patient today.</p>";
                return;
            }
            let html = `<table style="width:100%; border-collapse:collapse; margin-top:10px; border:1px solid #ddd;">
                <tr style="background:#f2f2f2;">
                    <th style="padding:10px; border:1px solid #ddd;">Medication</th>
                    <th style="padding:10px; border:1px solid #ddd;">Pharmacist</th>
                    <th style="padding:10px; border:1px solid #ddd;">Status</th>
                    <th style="padding:10px; border:1px solid #ddd;">Time</th>
                </tr>`;
            data.forEach(r => {
                const color = (r.status === 'Pending') ? 'orange' : 'green';
                const icon = (r.status === 'Pending') ? '⏳' : '✅';
                html += `<tr>
                    <td style="padding:10px; border:1px solid #ddd;"><b>${r.medication_name}</b><br><small>${r.dosage_instruction}</small></td>
                    <td style="padding:10px; border:1px solid #ddd;">${r.pharmacist_name || 'N/A'}</td>
                    <td style="padding:10px; border:1px solid #ddd; color:${color}; font-weight:bold;">${icon} ${r.status}</td>
                    <td style="padding:10px; border:1px solid #ddd; font-size:11px;">${r.created_at}</td>
                </tr>`;
            });
            html += `</table>`;
            container.innerHTML = html;
        });
}
        else if(action === 'appt') {
            area.innerHTML = `<h1>Set Appointment</h1>${idHeader} ${demoHTML} 
                <div class="form-group"><label>Appointment Date</label><input type="datetime-local" name="appointment_date"></div>
                <button class="action-btn" onclick="saveToDB('Appointment', {id: '${patientId}'})">Set Appointment</button>`;
        }
        // በ show(action, patientId) ውስጥ የ follow_up የሚለውን ክፍል በዚህ ይተኩት
else if(action === 'follow_up') {
    area.innerHTML = `<h1>Follow up Patients</h1>${idHeader} ${demoHTML}
        <div style="display:flex; gap:10px; margin-bottom:20px;">
            <div class="form-group"><label>Ward:</label><input type="text" name="ward"></div>
            <div class="form-group"><label>Bed Number:</label><input type="text" name="bed_number"></div>
        </div>
        <div id="php-med-sheet-v1">${generateMedSheetHTML()}</div>
        <div class="form-group">
            <label>Condition Status:</label>
            <select name="condition_status_">
                <option value="Ongoing" selected>-- Keep Ongoing --</option>
                <option value="Improving">Improving</option>
                <option value="Stable">Stable</option>
                <option value="Critical">Critical</option>
                <option value="Discharge Ready">Discharge Ready</option>
            </select>
        </div>
        <button class="action-btn" onclick="saveToDB('Follow Up', {id: '${patientId}'})">Update Status</button>`;
}
        else if(action === 'reassign') {
    area.innerHTML = `<h1>Re-Assign Patient</h1>${idHeader} ${demoHTML}
        <div style="background:#f0f7f7; padding:15px; border-radius:8px; border:1px solid darkcyan;">
            <div class="form-group">
                <label>1. Select Main Category:</label>
                <select id="main_cat_re" onchange="updateSubAreas('main_cat_re', 'sub_cat_re', 'sub_wrapper_re')">
                    <option value="">-- Select Category --</option>
                    <option>Surgical</option><option>Medical</option><option>Pediatric</option><option>GYM</option>
                </select>
            </div>
            <div class="form-group" id="sub_wrapper_re" style="display:none;">
                <label>2. Select Specific Area:</label>
                <select id="sub_cat_re" onchange="filterStaffByArea('medical', 'sub_cat_re', 're_assign_to_staff_')"></select>
            </div>
            <div class="form-group">
                <label>3. Target Staff (Doctors/Nurses on Shift):</label>
                <select name="re_assign_to_staff_" id="re_assign_to_staff_" onchange="checkIfReferral(this, '${patientId}')">
                    <option value="">Select location first...</option>
                </select>
            </div>
            <div id="reassign-action-btn">
                <button class="action-btn" onclick="saveToDB('Reassignment', {id: '${patientId}'})">Process Re-assignment</button>
            </div>
            <!-- Referral option always available at the bottom -->
            <hr>
            <button class="action-btn" style="background:#607d8b;" onclick="checkIfReferral({value:'REFERRAL_EXTERNAL'}, '${patientId}')">Refer to Other Hospital</button>
        </div>
        <div id="referral-form-container"></div>`;
}
        else if(action === 'Discharge') {
            area.innerHTML = `<h1>Discharge Summary</h1>${idHeader} ${demoHTML}
                <div style="display:flex; gap:10px;"><div class="form-group"><label>Ward:</label><input type="text" name="ward"></div><div class="form-group"><label>Bed Number:</label><input type="text" name="bed_number"></div></div>
                <div class="form-group"><label>Brief History:</label><textarea name="brief_history_" rows="4"></textarea></div><div class="form-group"><label>Physical Examination:</label><textarea name="physical_examination_" rows="4"></textarea></div><div class="form-group"><label>Lab. Investigation:</label><textarea name="lab__investigation_" rows="4"></textarea></div><div class="form-group"><label>Final Diagnosis:</label><textarea name="final_diagnosis_" rows="4"></textarea></div><div class="form-group"><label>Course of Treatment:</label><textarea name="course_of_treatment_" rows="4"></textarea></div><div class="form-group"><label>Plan of Discharge:</label><textarea name="plan_of_discharge_" rows="4"></textarea></div>
                <div class="condition-container"><strong>Condition of Discharge:</strong> <label><input type="radio" name="condition" value="Cured"> Cured</label>&nbsp;<label><input type="radio" name="condition" value="Improved"> Improved</label>&nbsp;<label><input type="radio" name="condition" value="Same"> Same</label>&nbsp;<label><input type="radio" name="condition" value="Dead"> Dead</label></div><br>
                <button class="action-btn" onclick="saveToDB('Discharge Summary', {id: '${patientId}'})">Save Changes</button>`;
        }
        else if(action === 'my-schedule') {
    area.innerHTML = `<h1>My Duty Schedule</h1><hr><div id="schedule-output"><p>Loading schedule...</p></div>`;
    
    // የዶክተሩን ስም በመጠቀም ሪኩዌስት እንልካለን
    const docName = "<?php echo $current_user['full_name']; ?>"; 
    
    fetch(`get_my_schedule.php?name=${encodeURIComponent(docName)}`)
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
<h3> Change Password</h3>    
<hr>
        
        <div class="form-group">
            <label>Enter Current Password</label>
            <div class="password-wrapper">
                <input type="password" id="curr-pass">
                <span class="toggle-password" onclick="togglePass('curr-pass', this)">👁️</span>
            </div>
        </div>
        
        <div class="form-group">
            <label>Enter New Password</label>
            <div class="password-wrapper">
                <input type="password" id="new-pass">
                <span class="toggle-password" onclick="togglePass('new-pass', this)">👁️</span>
            </div>
        </div>
        
        <div class="form-group">
            <label>Confirm New Password</label>
            <div class="password-wrapper">
                <input type="password" id="confirm-pass">
                <span class="toggle-password" onclick="togglePass('confirm-pass', this)">👁️</span>
            </div>
        </div>
        
        <button class="action-btn" onclick="updateProfileLogic()">Update Profile & Password</button>
        <p id="profile-msg"></p>`;
}
    }

   function renderFollowUpTable(group) {
    if (group.length === 0) return "";
    let first = group[0];
    let last = group[group.length - 1]; // የቅርብ ጊዜው ስታተስ እንዲወጣ
    
    let displayDate = first.created_at ? first.created_at : "N/A";
    let displayWard = first.ward ? first.ward : "Not Set";
    let displayBed = first.bed_number ? first.bed_number : "Not Set";

    let daysHeader = "";
    for (let i = 1; i <= 30; i++) daysHeader += `<th style="border:1px solid black; width:22px; font-size:9px; background:#f2f2f2;">${i}</th>`;

    let rowsHTML = group.map(h => {
        let checked = h.days_checked ? h.days_checked.split(',') : [];
        let cells = "";
        for (let d = 1; d <= 30; d++) { 
            cells += `<td style="border:1px solid black; text-align:center; height:20px; font-size:12px;">${checked.includes(d.toString()) ? '✔' : ''}</td>`; 
        }
        return `<tr><td style="border:1px solid black; padding:4px; font-weight:bold; font-size:11px;">${h.item_description}</td><td style="border:1px solid black; text-align:center; font-size:11px;">${h.hrs}</td>${cells}</tr>`;
    }).join('');

    return `
        <div style="background:white; border:2px solid darkcyan; padding:15px; margin-bottom:20px; overflow-x:auto; border-radius:8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <!-- ርዕሱ መጀመሪያ እንዲሆን -->
            <div style="text-align:center; margin-bottom:10px; font-weight:bold; color:#006666; font-size:16px; text-decoration: underline;">
                MEDICATION FOLLOW-UP SHEET
            </div>
            
            <!-- መረጃዎቹ ከርዕሱ በታች -->
            <div style="background: #f0fafa; padding: 10px; border: 1px solid darkcyan; margin-bottom: 10px; display:flex; justify-content:space-between; font-weight:bold; color:darkcyan; font-size:13px;">
                <span>📅 Date: ${displayDate}</span>
                <span>🏥 Ward: ${displayWard}</span>
                <span>🛏️ Bed No: ${displayBed}</span>
            </div>

            <table style="width:100%; border-collapse:collapse; font-size:10px; border:2px solid black;">
                <thead><tr style="background:#eee;"><th style="border:1px solid black; padding:5px; text-align:left;">Item Description</th><th style="border:1px solid black; width:40px;">Hrs</th>${daysHeader}</tr></thead>
                <tbody>${rowsHTML}</tbody>
            </table>

            <!-- ስታተስ መጨረሻ ላይ -->
            <div style="margin-top:10px; padding:8px; background:${last.status === 'Ongoing' ? '#e7f3f3' : '#fbe9e7'}; border-left: 5px solid ${last.status === 'Ongoing' ? 'blue' : 'red'}; font-weight:bold; border-radius:0 4px 4px 0;">
                CURRENT STATUS: <span style="color:${last.status === 'Ongoing' ? 'blue' : 'red'}; font-size:14px;">${last.status}</span>
            </div>
        </div>`;
}

    function generateMedSheetHTML() {
        var html = '<table class="ms-table"><thead><tr class="ms-header-bg"><th class="ms-th ms-col-sn">S/N</th><th class="ms-th ms-col-desc">Item Description</th><th class="ms-th ms-col-hrs">Hrs</th>';
        for (var i = 1; i <= 30; i++) html += '<th class="ms-th ms-col-day">' + i + '</th>';
        html += '</tr></thead><tbody>';
        for (var r = 1; r <= 5; r++) { 
            html += '<tr><td class="ms-td">' + r + '</td><td class="ms-td"><input type="text" class="ms-input" name="item_' + r + '"></td><td class="ms-td"><input type="text" class="ms-input" name="hrs_' + r + '"></td>';
            for (var d = 1; d <= 30; d++) html += '<td class="ms-td"><input type="checkbox" class="ms-check" name="ch_' + r + '_d' + d + '"></td>';
            html += '</tr>';
        }
        return html + '</tbody></table>';
    }

    function checkLabOther(el) { document.getElementById('lab-other-input-div').style.display = (el.value === "Other") ? "block" : "none"; }
    
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
    
    function logout() { if(confirm("Are you sure?")) { window.location.href = "index.php"; } }

    async function checkIfReferral(select, patientId) {
    const container = document.getElementById('referral-form-container');
    const actionBtn = document.getElementById('reassign-action-btn');
    
    if (select.value === "REFERRAL_EXTERNAL") {
        // የሪአሳይን በተኑን ደብቅ
        actionBtn.style.display = "none";
        
        // የታካሚውን መረጃ አምጣ
        const res = await fetch(`get_full_history.php?mrn=${patientId}`);
        const data = await res.json();
        const p = data.demographics; // የታካሚው መረጃ
        
        // ዶክተሩን (የአሁኑን ተጠቃሚ) ስም ከ PHP እናምጣ
        const doctorName = "<?php echo htmlspecialchars($current_user['full_name']); ?>";
        const profession = "Doctor"; // ወይም እንደ ፍላጎትህ

        // አንተ የሰጠኸኝን ፎርም እዚህ ጋር ዳታውን አስገብተን እናሳየዋለን
        container.innerHTML = renderReferralFormHTML(p, doctorName, profession);
    } else {
        container.innerHTML = "";
        actionBtn.style.display = "block";
    }
}

function renderReferralFormHTML(p, doctorName, profession) {
    const today = new Date().toISOString().split('T')[0];

    return `
    <div id="referral-outer-container" style="display: flex; justify-content: center; gap: 20px; align-items: flex-end; margin-top: 20px; padding-bottom: 50px;">
        
        <div id="printable-referral-area" style="width: 210mm; min-height: 297mm; background: white; padding: 60px; box-shadow: 0 0 10px rgba(0,0,0,0.2); color: black; font-family: 'Times New Roman', Times, serif; box-sizing: border-box; border: 1px solid #ddd;">
            <style>
                @media print {
                    body * { visibility: hidden; }
                    #printable-referral-area, #printable-referral-area * { visibility: visible; }
                    #printable-referral-area { position: absolute; left: 0; top: 0; width: 210mm; height: 297mm; box-shadow: none; border: none; padding: 20mm; }
                    @page { size: A4; margin: 0; }
                }
                .value-display { border-bottom: 1px solid black; padding: 0 5px; display: inline-block; }
                .label-text { font-weight: bold; font-size: 14px; }
                .form-section-title { display: inline-block; border-bottom: 2px solid #333; margin-top: 25px; margin-bottom: 15px; font-weight: bold; padding-bottom: 2px; }
                textarea { width: 100%; border: none; border-bottom: 1px solid #ccc; height: 55px; font-family: inherit; font-size: 14px; resize: none; background: transparent; outline:none; }
                .input-line { border: none; border-bottom: 1px dotted black; font-family: inherit; font-size: 14px; padding: 0 5px; outline: none; background: transparent; }
                .row { display: flex; align-items: baseline; gap: 10px; margin-bottom: 12px; }
            </style>

            <h2 style="text-align: center; margin: 0; text-transform: uppercase;">Adigrat General Hospital</h2>   
            <h3 style="text-align: center; margin: 5px 0 30px 0; text-decoration: underline;">Patient Referral Form</h3>

            <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
                <div class="row">
                    <span class="label-text">Date:</span>
                    <span id="ref-date" class="value-display" style="min-width: 120px;">${today}</span>
                </div>
            </div>

            <div><h4 class="form-section-title">I. PATIENT INFORMATION</h4></div>
            <div style="display: flex; width: 100%;">
                <div class="row" style="width: 68%;">
                    <span class="label-text">Full Name:</span>
                    <span id="ref-name" class="value-display" style="flex: 1;">${p.full_name}</span>
                </div>
            </div>
            <div style="display: flex; width: 100%; gap: 30px;">
                <div class="row" style="flex: 1;"><span class="label-text">Age:</span><span id="ref-age" class="value-display" style="flex: 1;">${p.age}</span></div>
                <div class="row" style="flex: 1;"><span class="label-text">Gender:</span><span id="ref-gender" class="value-display" style="flex: 1;">${p.gender}</span></div>
                <div class="row" style="flex: 1;"><span class="label-text">Phone:</span><span id="ref-phone" class="value-display" style="flex: 1;">${p.phone}</span></div>
            </div>

            <div><h4 class="form-section-title">II. CLINICAL INFORMATION</h4></div>
            <div class="label-text">Chief Complaint:</div>
            <textarea id="ref-complaint" placeholder="Type here..."></textarea>
            <div class="label-text">History of Present Illness & Findings:</div>
            <textarea id="ref-hpi"></textarea>
            <div class="label-text">Provisional Diagnosis:</div>
            <textarea id="ref-diagnosis"></textarea>
            <div class="label-text">Treatment Given & Lab Results:</div>
            <textarea id="ref-treatment"></textarea>
            <div class="label-text">Reason for Referral:</div>
            <textarea id="ref-reason"></textarea>
            <div class="row">
                <span class="label-text">Receiving Hospital:</span>
                <input type="text" id="ref-hospital" class="input-line" style="flex: 1;">
            </div>

            <div><h4 class="form-section-title">III. REFERRING PROFESSIONAL</h4></div>
            <div class="row" style="max-width: 50%;"><span class="label-text">Name:</span><span id="ref-doc" class="value-display" style="flex: 1;">${doctorName}</span></div>
            <div class="row" style="max-width: 50%;"><span class="label-text">Profession:</span><span id="ref-prof" class="value-display" style="flex: 1;">${profession}</span></div>

            <div style="display: flex; gap: 50px; margin-top: 50px; align-items: baseline;">
                <div><span class="label-text">Signature:</span> ___________________</div>
                <div><span class="label-text">Stamp</span></div>
            </div>
        </div>

        <!-- Action Sidebar -->
        <div id="referral-action-sidebar" style="margin-bottom: 60px; display: flex; flex-direction: column; gap: 10px;">
            <button id="save-ref-btn" onclick="saveReferralToDB('${p.mrn}')" style="background: darkcyan; color: white; padding: 15px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold;">
                💾 Save Referral
            </button>
            <button id="print-ref-btn" onclick="window.print()" style="display: none; background: #2e8b57; color: white; padding: 15px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold;">
                🖨️ Print Referral
            </button>
        </div>
    </div>`;
}

async function saveReferralToDB(mrn) {
    const formData = new FormData();
    formData.append('mrn', mrn);
    formData.append('date', document.getElementById('ref-date').innerText);
    formData.append('name', document.getElementById('ref-name').innerText);
    formData.append('age', document.getElementById('ref-age').innerText);
    formData.append('gender', document.getElementById('ref-gender').innerText);
    formData.append('phone', document.getElementById('ref-phone').innerText);
    formData.append('complaint', document.getElementById('ref-complaint').value);
    formData.append('hpi', document.getElementById('ref-hpi').value);
    formData.append('diagnosis', document.getElementById('ref-diagnosis').value);
    formData.append('treatment', document.getElementById('ref-treatment').value);
    formData.append('reason', document.getElementById('ref-reason').value);
    formData.append('hospital', document.getElementById('ref-hospital').value);
    formData.append('doc_name', document.getElementById('ref-doc').innerText);
    formData.append('profession', document.getElementById('ref-prof').innerText);

    try {
        const res = await fetch('save_referral.php', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.status === 'success') {
            alert("Referral Successfully Saved!");
            // Save ቁልፉን ደብቆ Print ቁልፉን ማሳየት
            document.getElementById('save-ref-btn').style.display = 'none';
            document.getElementById('print-ref-btn').style.display = 'flex';
        } else {
            alert("Error: " + result.message);
        }
    } catch (e) {
        alert("Connection error!");
    }
}
//---------------------------------------------------------
function renderAsA4Sheet(h, p) {
    let content = "";
    const title = h.action_type.toUpperCase();
    if (h.action_type === 'Order Sheet') {
        content = `
            <table style="width:100%; border-collapse:collapse; border:2px solid black; margin-top:10px;">
                <tr style="background:#f0f0f0;"><th style="border:1px solid black; padding:10px; width:180px; text-align:left;">Category</th><th style="border:1px solid black; padding:10px; text-align:left;">Details</th></tr>
                <tr><td style="border:1px solid black; padding:10px; font-weight:bold;">DIAGNOSIS</td><td style="border:1px solid black; padding:10px;">${h.diagnosis || '-'}</td></tr>
                <tr><td style="border:1px solid black; padding:10px; font-weight:bold;">CONDITION</td><td style="border:1px solid black; padding:10px;">${h.patient_condition || '-'}</td></tr>
                <tr><td style="border:1px solid black; padding:10px; font-weight:bold;">VITAL SIGNS</td><td style="border:1px solid black; padding:10px;">${h.vital_signs || '-'}</td></tr>
                <tr><td style="border:1px solid black; padding:10px; font-weight:bold;">NURSING CARE</td><td style="border:1px solid black; padding:10px;">${h.nursing_care || '-'}</td></tr>
                <tr><td style="border:1px solid black; padding:10px; font-weight:bold;">DIET</td><td style="border:1px solid black; padding:10px;">${h.diet || '-'}</td></tr>
                <tr><td style="border:1px solid black; padding:10px; font-weight:bold;">INVESTIGATION</td><td style="border:1px solid black; padding:10px;">${h.investigation || '-'}</td></tr>
                <tr><td style="border:1px solid black; padding:10px; font-weight:bold;">MANAGEMENT</td><td style="border:1px solid black; padding:10px;">${h.management || '-'}</td></tr>
            </table>`;
    }
    else {
        Object.entries(h).forEach(([k, v]) => {
            if (!['id','patient_id','mrn','staff_id','created_at','action_type','row_index','days_checked','ward','bed_number','status','record_date','is_read'].includes(k) && v) {
                
                let displayValue = v;
                
                // Check if this field is an image path
                if (k === 'result_image') {
                    displayValue = `<button class="action-btn" style="padding:8px 15px; background:darkcyan;" 
                                    onclick="startZoom('${v}')">🖼️ View Lab Image / X-Ray</button>`;
                }

                content += `
                    <div class="sheet-row">
                        <span class="sheet-label">${k.replace(/_/g, ' ')}</span>
                        <div class="sheet-value">${displayValue}</div>
                    </div>`;
            }
        });
    }

    return `
        <div class="printable-sheet">
            <div class="sheet-header">
                <h1 style="margin:0; font-size:24px;">ADIGRAT GENERAL HOSPITAL</h1>
                <h2 style="margin:10px 0; text-decoration:underline; font-size:20px;">${title}</h2>
            </div>

            <div class="patient-info-box">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div><strong>Patient Name:</strong> ${p.full_name}</div>
                    <div><strong>Medical Record No (MRN):</strong> ${p.mrn}</div>
                    <div><strong>Age:</strong> ${p.age}</div>
                    <div><strong>Gender:</strong> ${p.gender}</div>
                    <div><strong>Ward:</strong> ${h.ward || 'N/A'}</div>
                    <div><strong>Bed Number:</strong> ${h.bed || 'N/A'}</div>
                    <div><strong>Date:</strong> ${h.created_at || h.record_date || '-'}</div>
                </div>
            </div>

            <div class="sheet-body">
                ${content}
            </div>
        </div>`;
}
//------------------------------------------------------
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

const areaMapping = {
    "Surgical": ["Ward", "Operation", "OPD", "Other"],
    "Medical": ["ART(HR)", "Ward", "OPD", "Other"],
    "Pediatric": ["Micu", "Ward", "OPD", "Other"],
    "GYM": ["MCC (Mother & Child Care)", "Delivery Room", "Postnatal Care", "Other"]
};

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
    } else { wrapper.style.display = 'none'; }
}

async function filterStaffByArea(roleType, areaId, targetSelectId) {
    const area = areaId ? document.getElementById(areaId).value : "";
    const select = document.getElementById(targetSelectId);
    select.innerHTML = '<option value="">Searching...</option>';
    
    let url = `get_scheduled_staff.php?role=${roleType}&area=${encodeURIComponent(area)}`;
    const res = await fetch(url);
    const data = await res.json();

    select.innerHTML = '<option value="">-- Select Available Staff --</option>';
    if(data.length === 0) {
        select.innerHTML = '<option value="">❌ No staff scheduled for this shift</option>';
    } else {
        data.forEach(s => {
            // ማስተካከያ፡ የራሱ ID ከሆነ ዝለለው (ወደ ዝርዝሩ አታስገባው)
            if(s.staff_id === currentStaffId) return;

            select.innerHTML += `<option value="${s.staff_id}">${s.full_name} (${s.role}) ${s.room ? '- Rm:'+s.room : ''}</option>`;
        });
    }
}

// የቆዩትን (viewFullImage, doZoom, closeImageViewer) በሙሉ አጥፋና ይሄን ብቻ ተካ
let myZoomScale = 1;

function startZoom(src) {
    myZoomScale = 1; 
    let viewer = document.getElementById('labZoomOverlay');
    
    // ማሳያው ከሌለ አንድ ጊዜ ብቻ መፍጠር
    if (!viewer) {
        viewer = document.createElement('div');
        viewer.id = 'labZoomOverlay';
        viewer.innerHTML = `
            <div class="zoom-tools">
                <button class="zoom-btn-item" onclick="zoomChange(0.2)">➕ Zoom In</button>
                <button class="zoom-btn-item" onclick="zoomChange(-0.2)">➖ Zoom Out</button>
                <button class="zoom-btn-item" onclick="zoomChange(0)" style="background:orange;">Reset</button>
                <button class="zoom-btn-item" onclick="endZoom()" style="background:red; color:white; border:none;">❌ Back to Results</button>
            </div>
            <div style="padding-bottom:50px;">
                <img id="zoomTargetImg">
            </div>`;
        document.body.appendChild(viewer);
    }
    
    const img = document.getElementById('zoomTargetImg');
    img.src = src;
    img.style.transform = "scale(1)";
    viewer.style.display = 'block';
    document.body.style.overflow = 'hidden'; 
}

function zoomChange(amt) {
    const img = document.getElementById('zoomTargetImg');
    if (amt === 0) myZoomScale = 1;
    else myZoomScale += amt;
    
    if (myZoomScale < 0.2) myZoomScale = 0.2; // በጣም እንዳያንስ መገደብ
    img.style.transform = `scale(${myZoomScale})`;
}

function endZoom() {
    document.getElementById('labZoomOverlay').style.display = 'none';
    document.body.style.overflow = 'auto'; 
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
            if (rxIndex !== -1) {
                prescriptions[rxIndex].status = "Dispensed";
            }
            
            // 2. እዛው ገጽ ላይ እንዲቆይ 'Ordered' ወይም 'dispense' ፋንክሽንን ድጋሚ ጥራ
            // rxId ካለ ወደ dispense ገጽ እንዲመለስ፣ ከሌለ ወደ Ordered ዝርዝር እንዲሄድ
            show('Ordered'); 
            
        } else {
            alert("Error: " + result);
        }
    } catch (e) {
        alert("Connection error. Could not update status.");
    }
}

// 1. የኖቲፊኬሽን ቁጥሩን ከሰርቨር አምጥቶ የሚያሳይ ፋንክሽን
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
    } catch (e) { console.error("Notification failed to load. Check get_notif_count.php"); }
}

// ገጹ ሲከፈት እንዲጀምር እና በየ 10 ሰከንዱ እንዲያድስ
setInterval(checkNotifications, 10000);
window.onload = checkNotifications;
</script>
</body>
</html>