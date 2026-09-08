<?php
session_start();

// 1. STRICT SECURITY: Verify user is logged in AND is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?status=unauthorized");
    exit();
}

// FIX: Added the database connection include
require_once 'db_connect.php'; 

try {
    // Fetch existing profiles from database
    $stmt = $pdo->query("SELECT * FROM plant_profiles ORDER BY id DESC");
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agriflow | Threshold Settings</title>
    <!-- Updated path to CSS -->
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --admin-blue: #1565c0;
            --admin-bg: #f4f7f6;
        }

        body { background-color: var(--admin-bg); }

        .threshold-container { 
            background: white; 
            padding: 2rem; 
            border-radius: 20px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); 
        }

        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th { text-align: left; padding: 15px; border-bottom: 2px solid #f0f0f0; color: #888; font-size: 0.8rem; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        
        .badge { padding: 6px 12px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; display: inline-block; }
        .badge-moisture { background: #e3f2fd; color: #1565c0; }

        .modal-overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(21, 58, 95, 0.4); display: none; justify-content: center; 
            align-items: center; z-index: 2000; backdrop-filter: blur(4px);
        }
        .modal-card { background: white; padding: 2.5rem; border-radius: 24px; width: 90%; max-width: 500px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); }
        
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #1a3a5f; }
        .form-group input { 
            width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 10px; box-sizing: border-box; font-size: 1rem;
        }

        .btn-group { display: flex; gap: 12px; margin-top: 2rem; }
        .btn-save { background: var(--admin-blue); color: white; border: none; flex: 2; padding: 14px; border-radius: 12px; cursor: pointer; font-weight: 700; }
        .btn-cancel { background: #eee; color: #666; border: none; flex: 1; padding: 14px; border-radius: 12px; cursor: pointer; font-weight: 600; }
        
        .btn-add { background: var(--admin-blue); color: white; border: none; padding: 12px 24px; border-radius: 10px; cursor: pointer; font-weight: 700; }
        
        .edit-link { color: var(--admin-blue); text-decoration: none; font-weight: 600; margin-right: 15px; cursor: pointer; }
        .delete-link { color: #e74c3c; text-decoration: none; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>

    <?php include('sidebar.php'); ?>

    <main style="padding: 40px;">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h1 style="color: #1a3a5f; margin: 0;">Irrigation Thresholds</h1>
                <p style="color: #666;">Configure automated pump logic for different plant profiles.</p>
            </div>
            <button class="btn-add" onclick="openModal()">+ Add New Profile</button>
        </header>

        <section class="threshold-container">
            <table>
                <thead>
                    <tr>
                        <th>Plant Key</th>
                        <th>Profile Name</th>
                        <th>Turn ON Level</th>
                        <th>Turn OFF Level</th>
                        <th>Management</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($profiles) > 0): ?>
                        <?php foreach ($profiles as $p): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($p['plant_key']); ?></code></td>
                            <td><strong><?php echo htmlspecialchars($p['plant_name']); ?></strong></td>
                            <td><span class="badge badge-moisture">Below <?php echo $p['low_threshold']; ?>%</span></td>
                            <td><span class="badge badge-moisture" style="background: #e8f5e9; color: #2e7d32;">Above <?php echo $p['high_threshold']; ?>%</span></td>
                            <td>
                                <span class="edit-link" onclick='editProfile(<?php echo json_encode($p); ?>)'>Edit</span>
                                <span class="delete-link" onclick="deleteProfile(<?php echo $p['id']; ?>)">Delete</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding: 50px; color: #999;">No profiles found. Create one to begin.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>

    <!-- Modal remains the same but styled better -->
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal-card">
            <h2 id="modalTitle" style="margin-top:0; color: #1a3a5f;">Plant Profile</h2>
            <form id="thresholdForm" action="save-profile.php" method="POST">
                <input type="hidden" name="id" id="profileId">
                <div class="form-group">
                    <label>System Key (Slug)</label>
                    <input type="text" name="plant_key" id="plantKey" placeholder="e.g. sugar_cane" required>
                </div>
                <div class="form-group">
                    <label>Display Name</label>
                    <input type="text" name="plant_name" id="plantName" placeholder="e.g. Sugar Cane" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>ON Threshold (%)</label>
                        <input type="number" step="0.1" name="low_threshold" id="lowThreshold" required>
                    </div>
                    <div class="form-group">
                        <label>OFF Threshold (%)</label>
                        <input type="number" step="0.1" name="high_threshold" id="highThreshold" required>
                    </div>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Settings</button>
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
            document.getElementById('modalTitle').innerText = "Edit " + data.plant_name;
            document.getElementById('profileId').value = data.id;
            document.getElementById('plantKey').value = data.plant_key;
            document.getElementById('plantName').value = data.plant_name;
            document.getElementById('lowThreshold').value = data.low_threshold;
            document.getElementById('highThreshold').value = data.high_threshold;
            modal.style.display = 'flex';
        }

        function deleteProfile(id) {
            if(confirm("Confirm deletion of this irrigation profile?")) {
                window.location.href = "delete_profile.php?id=" + id;
            }
        }
    </script>
</body>
</html>