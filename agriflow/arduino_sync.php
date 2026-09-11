<?php
include 'db_connect.php';

// SENSOR INPUT
$m = isset($_POST['moisture']) ? (float)$_POST['moisture'] : null;
$t = isset($_POST['temp']) ? (float)$_POST['temp'] : 0;
$p = isset($_POST['ph']) ? (float)$_POST['ph'] : 0;

// LOG SENSOR DATA
if ($m !== null) {
    $stmt = $pdo->prepare("INSERT INTO sensor_logs (moisture, temp, ph) VALUES (?, ?, ?)");
    $stmt->execute([$m, $t, $p]);
}

// 1. GET CURRENT SYSTEM STATE
$controls = $pdo->query("SELECT * FROM device_controls WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

$mode = $controls['mode'];
$dbPump = (int)$controls['pump_status'];
$plantKey = $controls['selected_plant'] ?? 'general';
$autoLock = (int)$controls['auto_lock'];

$desiredPump = $dbPump;
// 2. FETCH DYNAMIC THRESHOLDS FROM DATABASE
// Instead of a hardcoded array, we query the settings you saved in your Threshold page

$stmtProfile = $pdo->prepare("SELECT low_threshold, high_threshold FROM plant_profiles WHERE plant_key = ?");
$stmtProfile->execute([$plantKey]);
$profile = $stmtProfile->fetch(PDO::FETCH_ASSOC);

// Fallback if the profile doesn't exist in the database
if (!$profile) {
    $profile = ['low_threshold' => 17.0, 'high_threshold' => 60.0];
}

// 3. AUTO MODE LOGIC (SMART GUARD)
if ($mode === 'auto' && $m !== null && $autoLock === 0) {

    // SAFETY CHECK: If moisture is 0 or less, stop the pump.
    if ($m <= 0) {
        $desiredPump = 0; 
    } 
    // TRIGGER: Use the 'low_threshold' from the DB
    elseif ($m <= (float)$profile['low_threshold']) {
        $desiredPump = 1;
    } 
    // STOP: Use the 'high_threshold' from the DB
    elseif ($m >= (float)$profile['high_threshold']) {
        $desiredPump = 0;
    }
}

// 4. UPDATE DATABASE & SYNC LOGS
if ($desiredPump !== $dbPump) {
    // Update the control state
    $stmtUpdate = $pdo->prepare("UPDATE device_controls SET pump_status = ? WHERE id = 1");
    $stmtUpdate->execute([$desiredPump]);

    // Handle Pump Logs & Liters
    if ($desiredPump == 1) {
        // Start Log
        $pdo->prepare("INSERT INTO pump_logs (start_time) VALUES (NOW())")->execute();
    } else {
        // Stop Log & Calculate Liters (duration_seconds * 0.033)
        $pdo->prepare("
            UPDATE pump_logs 
            SET end_time = NOW(), 
                duration_seconds = TIMESTAMPDIFF(SECOND, start_time, NOW()),
                liters_used = TIMESTAMPDIFF(SECOND, start_time, NOW()) * 0.033
            WHERE end_time IS NULL 
            ORDER BY id DESC LIMIT 1
        ")->execute();
    }
    $dbPump = $desiredPump;
}

// OUTPUT TO ESP (NodeMCU)
// Format: mode:pump_status (e.g., auto:1 or manual:0)
echo $mode . ":" . $dbPump;
exit();
?>
