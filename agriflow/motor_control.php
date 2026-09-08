<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// FETCH ALL PROFILES FROM THE DATABASE
$stmt = $pdo->query("SELECT * FROM plant_profiles ORDER BY plant_name ASC");
$db_profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CREATE A JS-FRIENDLY ARRAY FOR THE FRONTEND
$js_library = [];
foreach ($db_profiles as $p) {
    $js_library[$p['plant_key']] = [
        'name' => $p['plant_name'],
        'low' => (float)$p['low_threshold'],
        'high' => (float)$p['high_threshold']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agriflow | Motor Control</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* [KEEP YOUR EXISTING CSS HERE] */
        :root {
            --power-on: #4caf50;
            --power-off: #f44336;
            --manual-color: #ff9800;
            --guard-color: #2196f3;
        }
        .control-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin-top: 1rem; }
        .control-card { background: white; padding: 2rem; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); display: flex; flex-direction: column; align-items: center; text-align: center; border: 1px solid #eee; }
        .status-indicator { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 8px; }
        .pulse { animation: pulse-animation 2s infinite; }
        @keyframes pulse-animation { 0% { box-shadow: 0 0 0 0px rgba(76, 175, 80, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(76, 175, 80, 0); } 100% { box-shadow: 0 0 0 0px rgba(76, 175, 80, 0); } }
        .switch { position: relative; display: inline-block; width: 60px; height: 34px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--guard-color); }
        input:checked + .slider:before { transform: translateX(26px); }
        .btn-manual-toggle { padding: 15px; font-weight: bold; border: none; border-radius: 50px; cursor: pointer; width: 100%; transition: 0.3s; }
        .btn-on { background: var(--power-on); color: white; }
        .btn-off { background: #eee; color: #666; }
        .disabled-overlay { opacity: 0.4; pointer-events: none; filter: grayscale(0.8); }
        .guard-active-box { background: #e3f2fd; color: #0d47a1; padding: 15px; border-radius: 12px; margin-top: 10px; width: 100%; border: 1px solid #bbdefb; }
    </style>
</head>
<body>

    <?php include('sidebar/sidebar.php'); ?>

    <main>
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h1>Motor Control</h1>
                <p id="systemSummary">System Status: Monitoring...</p>
            </div>
            <div id="connectionStatus">
                <span class="status-indicator" style="background: var(--power-on);"></span>
                <small>Hardware Live</small>
            </div>
        </header>

        <section class="control-grid">
            <div class="control-card">
                <div class="mode-label">PLANT PROFILE</div>
                
                <!-- DYNAMIC SELECT: Populated from Database -->
                <select id="plantSelector" onchange="updatePlant()" style="width: 100%; padding: 12px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #ddd; font-weight: bold;">
                    <?php foreach ($db_profiles as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['plant_key']); ?>">
                            <?php echo htmlspecialchars($p['plant_name']); ?> 
                            (<?php echo $p['low_threshold']; ?>% - <?php echo $p['high_threshold']; ?>%)
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="mode-label">CONTROL MODE</div>
                <h3 id="currentModeDisplay" style="margin: 5px 0;">MANUAL</h3>
                
                <div class="switch-container" style="display: flex; align-items: center; gap: 10px; margin: 15px 0;">
                    <span style="font-size: 0.8rem; color: #666;">MANUAL</span>
                    <label class="switch">
                        <input type="checkbox" id="modeToggle" onchange="toggleMode()">
                        <span class="slider"></span>
                    </label>
                    <span style="font-size: 0.8rem; color: var(--guard-color); font-weight: bold;">SMART GUARD</span>
                </div>
                <p style="font-size: 0.75rem; color: #999;">Smart Guard automatically triggers the pump if soil moisture drops too low.</p>
            </div>

            <div class="control-card" id="pumpControlCard">
                <div class="mode-label">PUMP ACTIVITY</div>
                <div style="background: #f8f9fa; padding: 10px; border-radius: 15px; width: 100%; margin-bottom: 15px;">
                    <span style="font-size: 0.8rem; color: #666;">Current Soil Moisture</span>
                    <div id="liveMoisture" style="font-size: 1.8rem; font-weight: bold; color: #333;">0.0%</div>
                </div>

                <div style="margin-bottom: 20px; display: flex; align-items: center; justify-content: center;">
                    <span id="pumpPulse" class="status-indicator"></span>
                    <strong id="pumpStatusText">LOADING...</strong>
                </div>

                <div id="usageInfo" style="margin-bottom: 15px; font-size: 0.9rem; color: #555;">
                    💧 Today: <b id="todayUsage">0.00</b> Liters
                </div>
                
                <div id="manualControls" style="width: 100%;">
                    <button id="pumpBtn" class="btn-manual-toggle btn-off" onclick="togglePump()">Turn Pump On</button>
                </div>

                <div id="guardMessage" style="display: none;" class="guard-active-box">
                    <p style="margin: 0; font-weight: bold;">🛡️ Dryness Protection Active</p>
                    <p id="guardDetailText" style="font-size: 0.8rem; margin: 5px 0 0 0;">Monitoring...</p>
                </div>
            </div>
        </section>
    </main>

<script>
    // DYNAMIC LIBRARY: Fetched from Database via PHP
    const plantLibrary = <?php echo json_encode($js_library); ?>;

    let currentPumpStatus = 0;

    async function updateDashboard() {
        try {
            const response = await fetch('get_status.php');
            const data = await response.json();

            document.getElementById('liveMoisture').innerText = data.current_moisture.toFixed(1) + "%";
            document.getElementById('todayUsage').innerText = data.today_water_liters.toFixed(2);

            const modeToggle = document.getElementById('modeToggle');
            const pumpStatusText = document.getElementById('pumpStatusText');
            const pumpBtn = document.getElementById('pumpBtn');
            const pumpPulse = document.getElementById('pumpPulse');
            const plantSelector = document.getElementById('plantSelector');
            const manualControls = document.getElementById('manualControls');
            const guardMessage = document.getElementById('guardMessage');

            currentPumpStatus = Number(data.pump_status || 0);
            const isGuardMode = (data.mode === 'auto');
            
            if (data.selected_plant) plantSelector.value = data.selected_plant;
            modeToggle.checked = isGuardMode;

            document.getElementById('currentModeDisplay').innerText = isGuardMode ? "SMART GUARD" : "MANUAL";
            
            if (currentPumpStatus === 1) {
                pumpStatusText.innerText = "PUMPING WATER";
                pumpPulse.classList.add('pulse');
                pumpPulse.style.background = "var(--power-on)";
                pumpBtn.className = "btn-manual-toggle btn-on";
                pumpBtn.innerText = "Stop Pump";
            } else {
                pumpStatusText.innerText = "PUMP STANDBY";
                pumpPulse.classList.remove('pulse');
                pumpPulse.style.background = "#ccc";
                pumpBtn.className = "btn-manual-toggle btn-off";
                pumpBtn.innerText = "Start Pump";
            }

            if (isGuardMode) {
                manualControls.classList.add('disabled-overlay');
                guardMessage.style.display = 'block';

                // Look up the profile in our dynamic library
                const profile = plantLibrary[plantSelector.value];
                const moisture = data.current_moisture;

                if (profile) {
                    let statusNote = (moisture <= profile.low) ? "<br><b>Triggering pump now...</b>" : "<br>Soil condition: Healthy";
                    if (moisture <= 0) statusNote = "<br><span style='color:red'>⚠️ SENSOR ERROR</span>";

                    document.getElementById('guardDetailText').innerHTML = 
                        `Protecting <b>${profile.name}</b><br>` +
                        `Range: ${profile.low}% - ${profile.high}%` + statusNote;
                }
            } else {
                manualControls.classList.remove('disabled-overlay');
                guardMessage.style.display = 'none';
            }
        } catch (e) { console.error("Update Error:", e); }
    }

    function togglePump() { sendCommand('pump', currentPumpStatus === 1 ? 0 : 1); }
    function toggleMode() { sendCommand('mode', document.getElementById('modeToggle').checked ? 'auto' : 'manual'); }
    function updatePlant() { sendCommand('plant', document.getElementById('plantSelector').value); }

    async function sendCommand(type, value) {
        const params = new URLSearchParams({ type, value });
        try {
            await fetch('update_controls.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            });
            updateDashboard();
        } catch (e) { console.error("Command Error:", e); }
    }

    setInterval(updateDashboard, 3000);
    updateDashboard();
</script>
</body>
</html>