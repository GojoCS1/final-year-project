<?php
include 'db.php';
$id = $_GET['id'];
$res = $conn->query("SELECT * FROM schedules WHERE id = $id");
$data = $res->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Staff Schedule</title>
    <style>
        body { font-family: Arial; background: #f4f4f4; padding: 20px; }
        .box { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 500px; margin: auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { background: darkcyan; color: white; border: none; padding: 12px; width: 100%; cursor: pointer; font-weight: bold; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="box">
        <h3 style="color:darkcyan;">Edit Schedule: <?php echo $data['staff_name_id']; ?></h3>
        <hr>
        <form action="update_schedule_process.php" method="POST" onsubmit="prepareEditArea()">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="area" id="final_area_edit">

            <div class="form-group">
                <label>Day of Week:</label>
                <select name="day">
                    <?php $days = ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"];
                    foreach($days as $d) echo "<option ".($data['shift_day']==$d?'selected':'').">$d</option>"; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Time Shift:</label>
                <select name="shift">
                    <option <?php if($data['shift_time']=='Morning') echo 'selected'; ?>>Morning (8AM-12PM)</option>
                    <option <?php if($data['shift_time']=='Afternoon') echo 'selected'; ?>>Afternoon (12PM-6PM)</option>
                    <option <?php if($data['shift_time']=='Night') echo 'selected'; ?>>Night (6PM-8AM)</option>
                </select>
            </div>

            <div style="background: #f0f7f7; padding: 15px; border-radius: 5px; border: 1px solid darkcyan;">
                <label>Main Category (Current: <?php echo $data['assigned_area']; ?>):</label>
                <select id="main_class_edit" onchange="updateEditSub()">
                    <option value="">-- Keep Current or Select New --</option>
                    <option value="Surgical">Surgical</option>
                    <option value="Medical">Medical</option>
                    <option value="Pediatric">Pediatric</option>
                    <option value="GYM">GYM</option>
                </select>

                <div id="sub_wrapper_edit" style="display:none; margin-top:10px;">
                    <label>Specific Area:</label>
                    <select id="sub_drop_edit"></select>
                </div>
            </div>

            <div class="form-group" style="margin-top:15px;">
                <label>Room Number:</label>
                <input type="text" name="room" id="room_edit" value="<?php echo $data['room']; ?>" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
            </div>

            <button type="submit" class="btn">Save Changes</button>
            <a href="staff_admin.php?task=all-schedules" style="display:block; text-align:center; margin-top:15px; color:gray; text-decoration:none;">Cancel</a>
        </form>
    </div>

<script>
    const mapping = {
        "Surgical": ["Ward", "Operation", "OPD", "Other"],
        "Medical": ["ART(HR)", "Ward", "OPD", "Other"],
        "Pediatric": ["Micu", "Ward", "OPD", "Other"],
        "GYM": ["MCC (Mother & Child Care)", "Delivery Room", "Postnatal Care", "Other"]
    };

    function updateEditSub() {
        const main = document.getElementById('main_class_edit').value;
        const subDrop = document.getElementById('sub_drop_edit');
        const wrapper = document.getElementById('sub_wrapper_edit');
        
        subDrop.innerHTML = '<option value="">-- Select Specific --</option>';
        if (main && mapping[main]) {
            wrapper.style.display = 'block';
            mapping[main].forEach(a => {
                let opt = document.createElement('option');
                opt.value = a; opt.textContent = a;
                subDrop.appendChild(opt);
            });
        } else {
            wrapper.style.display = 'none';
        }
    }

    function prepareEditArea() {
        const main = document.getElementById('main_class_edit').value;
        const sub = document.getElementById('sub_drop_edit').value;
        const current = "<?php echo $data['assigned_area']; ?>";
        
        let final = current; 
        if (main) {
            final = main + (sub ? " (" + sub + ")" : "");
        }
        document.getElementById('final_area_edit').value = final;
    }
</script>
</body>
</html>