<?php
session_start();
include 'db_connect.php'; // Ensure you have your DB connection

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch existing profiles from database
$stmt = $pdo->query("SELECT * FROM plant_profiles ORDER BY id DESC");
$profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate quick overview stats for the context panel
$totalProfiles = count($profiles);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agriflow | Threshold Settings</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .threshold-container { background: white; padding: 2rem; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th { text-align: left; padding: 12px; border-bottom: 2px solid #eee; color: #666; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 15px 12px; border-bottom: 1px solid #eee; font-size: 0.95rem; }
        
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; display: inline-block; }
        .badge-moisture-low { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .badge-moisture-high { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }

        /* Summary Panel CSS Layout */
        .info-panel-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 2rem;
        }
        .info-panel-card {
            background: #fff;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            border: 1px solid #f0f0f0;
            border-left: 4px solid #2ecc71;
        }
        .info-panel-card h3 { margin: 0 0 8px 0; font-size: 0.9rem; text-transform: uppercase; color: #888; }
        .info-panel-card p { margin: 0; font-size: 0.85rem; color: #666; line-height: 1.4; }
        .info-panel-card .stat-val { font-size: 1.8rem; font-weight: 800; color: #333; margin-bottom: 4px; display: block;}

        .modal-overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.5); display: none; justify-content: center; 
            align-items: center; z-index: 2000; backdrop-filter: blur(4px);
        }
        .modal-card { background: white; padding: 2rem; border-radius: 20px; width: 90%; max-width: 450px; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; }
        .form-group input, .form-group select { 
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box;
        }

        .btn-group { display: flex; gap: 10px; margin-top: 1.5rem; }
        .btn-save { background: #2ecc71; color: white; border: none; flex: 2; padding: 12px; border-radius: 10px; cursor: pointer; font-weight: 600; }
        .btn-cancel { background: #eee; color: #333; border: none; flex: 1; padding: 12px; border-radius: 10px; cursor: pointer; }
        .btn-action { background: #2e7d32; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: 0.2s; }
        .btn-action:hover { background: #1b5e20; }
    </style>
</head>
<body>

    <?php include('sidebar/sidebar.php'); ?>

    <main style="padding: 20px;">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <div>
                <h1>Control Thresholds</h1>
                <p style="color: #666;">Define specific dynamic moisture limits to govern localized MCU hardware automation routines.</p>
            </div>
            <button class="btn-action" onclick="openModal()">+ Add New Profile</button>
        </header>

        <div class="info-panel-grid">
            <div class="info-panel-card" style="border-left-color: #2e7d32;">
                <h3>Active Configurations</h3>
                <span class="stat-val"><?php echo $totalProfiles; ?> Crop Keys</span>
                <p>Total operational botanical configurations active within the local database instance registry.</p>
            </div>
            <div class="info-panel-card" style="border-left-color: #e53935;">
                <h3>Low Threshold (ON)</h3>
                <p>The <strong>Dehydration Floor</strong>. If sensor values fall below this point, automation loops immediately signal the field node relays to open water valves.</p>
            </div>
            <div class="info-panel-card" style="border-left-color: #1e88e5;">
                <h3>High Threshold (OFF)</h3>
                <p>The <strong>Saturation Ceiling</strong>. Once moisture values climb past this target, irrigation breaks off instantly to protect crops from root rot.</p>
            </div>
        </div>

        <section class="threshold-container">
            <h2 style="margin-top:0; font-size: 1.2rem; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px; color: #444;">Botanical Threshold Profiles</h2>
            <table>
                <thead>
                    <tr>
                        <th>Plant Key (Slug)</th>
                        <th>Display Name</th>
                        <th>Low Point (Irrigation ON)</th>
                        <th>High Point (Irrigation OFF)</th>
                        <th>Summary / Action Guide</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="thresholdTableBody">
                    <?php if (empty($profiles)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #999; padding: 30px;">No threshold configurations found. Click "+ Add New Profile" to begin.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($profiles as $p): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($p['plant_key']); ?></code></td>
                        <td><strong><?php echo htmlspecialchars($p['plant_name']); ?></strong></td>
                        <td><span class="badge badge-moisture-low">Below <?php echo round($p['low_threshold'], 1); ?>%</span></td>
                        <td><span class="badge badge-moisture-high">Above <?php echo round($p['high_threshold'], 1); ?>%</span></td>
                        <td style="font-size: 0.85rem; color: #666; max-width: 300px; line-height: 1.3;">
                            Maintains a tight moisture envelope of <strong><?php echo round($p['high_threshold'] - $p['low_threshold'], 1); ?>%</strong>. 
                            The pump fires below <?php echo round($p['low_threshold'], 1); ?>% to avoid crop stress and cuts off past <?php echo round($p['high_threshold'], 1); ?>% to save water.
                        </td>
                        <td>
                            <button onclick='editProfile(<?php echo json_encode($p); ?>)' style="background:none; border:none; color:#1e88e5; font-weight: 600; cursor:pointer; margin-right:10px;">Edit</button>
                            <button onclick="deleteProfile(<?php echo $p['id']; ?>)" style="background:none; border:none; color:#e53935; font-weight: 600; cursor:pointer;">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>

    <div class="modal-overlay" id="modalOverlay">
        <div class="modal-card">
            <h2 id="modalTitle" style="margin-top:0; color: #2e7d32;">Plant Profile</h2>
            <form id="thresholdForm" action="save_profile.php" method="POST">
                <input type="hidden" name="id" id="profileId">
                
                <div class="form-group">
                    <label>Plant Key (Matches PHP Code Library)</label>
                    <input type="text" name="plant_key" id="plantKey" placeholder="e.g. tomato" required>
                    <small style="color: #999; display: block; margin-top: 4px;">Must be lowercase with no spaces (e.g., leafy_greens, sugar_cane)</small>
                </div>

                <div class="form-group">
                    <label>Display Name</label>
                    <input type="text" name="plant_name" id="plantName" placeholder="e.g. Cherry Tomatoes" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Low Limit (ON %)</label>
                        <input type="number" step="0.1" name="low_threshold" id="lowThreshold" min="0" max="100" placeholder="30" required>
                    </div>
                    <div class="form-group">
                        <label>High Limit (OFF %)</label>
                        <input type="number" step="0.1" name="high_threshold" id="highThreshold" min="0" max="100" placeholder="65" required>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-save" style="background: #2e7d32;">Save Profile</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('modalOverlay');
        const form = document.getElementById('thresholdForm');

        function openModal() {
            document.getElementById('modalTitle').innerText = "Add New Plant Profile";
            document.getElementById('profileId').value = "";
            form.reset();
            modal.style.display = 'flex';
        }

        function closeModal() { modal.style.display = 'none'; }

        function editProfile(data) {
            document.getElementById('modalTitle').innerText = "Modify: " + data.plant_name;
            document.getElementById('profileId').value = data.id;
            document.getElementById('plantKey').value = data.plant_key;
            document.getElementById('plantName').value = data.plant_name;
            document.getElementById('lowThreshold').value = data.low_threshold;
            document.getElementById('highThreshold').value = data.high_threshold;
            modal.style.display = 'flex';
        }

        function deleteProfile(id) {
            if(confirm("Are you sure? This will remove these operational threshold parameters from the automated runtime environment completely.")) {
                window.location.href = "delete_profile.php?id=" + id;
            }
        }
    </script>
</body>
</html>