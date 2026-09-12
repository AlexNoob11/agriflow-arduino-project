<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$host = "localhost";
$user = "root";
$pass = "";
$db   = "agriflow_db";
$conn = new mysqli($host, $user, $pass, $db);

// 1. Handle Form Update for Device Controls
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    $mode = $conn->real_escape_string($_POST['mode']);
    $plant = $conn->real_escape_string($_POST['selected_plant']);
    $auto_lock = isset($_POST['auto_lock']) ? 1 : 0;
    
    $conn->query("UPDATE device_controls SET mode='$mode', selected_plant='$plant', auto_lock=$auto_lock WHERE id=1");
    echo "<script>alert('Hardware Configuration Updated!');</script>";
}

// 2. Fetch Current Device Settings & Plant Profiles
$device = $conn->query("SELECT * FROM device_controls WHERE id=1")->fetch_assoc();
$plants_query = $conn->query("SELECT * FROM plant_profiles ORDER BY plant_name ASC");

// Cache array for active configuration review matching
$active_profile = [
    'name' => 'Generic Target',
    'low' => 30,
    'high' => 70
];
$plants_list = [];
while($p = $plants_query->fetch_assoc()) {
    $plants_list[] = $p;
    if ($device['selected_plant'] === $p['plant_key']) {
        $active_profile = [
            'name' => $p['plant_name'],
            'low' => $p['low_threshold'],
            'high' => $p['high_threshold']
        ];
    }
}

// 3. Filter Logic for Reports
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// 4. Fetch Stats based on Filter
$stats_q = $conn->prepare("SELECT AVG(moisture) as avg_m FROM sensor_logs WHERE DATE(timestamp) BETWEEN ? AND ?");
$stats_q->bind_param("ss", $start_date, $end_date);
$stats_q->execute();
$avg_moisture = $stats_q->get_result()->fetch_assoc()['avg_m'] ?? 0;

$water_q = $conn->prepare("SELECT SUM(liters_used) as total_l, COUNT(id) as trigger_count FROM pump_logs WHERE DATE(start_time) BETWEEN ? AND ?");
$water_q->bind_param("ss", $start_date, $end_date);
$water_q->execute();
$water_data = $water_q->get_result()->fetch_assoc();
$total_water = $water_data['total_l'] ?? 0;
$pump_events = $water_data['trigger_count'] ?? 0;

// 5. Fetch Chart Data based on Filter
$chart_labels = [];
$chart_data = [];
$usage_query = $conn->prepare("
    SELECT DATE(start_time) as day, SUM(liters_used) as total 
    FROM pump_logs 
    WHERE DATE(start_time) BETWEEN ? AND ?
    GROUP BY DATE(start_time) 
    ORDER BY day ASC
");
$usage_query->bind_param("ss", $start_date, $end_date);
$usage_query->execute();
$result = $usage_query->get_result();
while($row = $result->fetch_assoc()) {
    $chart_labels[] = date('M d', strtotime($row['day']));
    $chart_data[] = $row['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agriflow | System Settings</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-green: #2e7d32;
            --accent-blue: #1976d2;
            --border-color: #eef2f6;
            --bg-light: #fafbfc;
        }

        .settings-container { 
            display: grid; 
            grid-template-columns: 350px 1fr; 
            gap: 24px; 
            align-items: start; 
        }

        .card { 
            background: white; 
            border-radius: 16px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.04); 
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .settings-card { padding: 24px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { 
            display: block; 
            font-weight: 700; 
            margin-bottom: 8px; 
            color: #455a64; 
            font-size: 0.8rem; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group input, .form-group select { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #d1d9e0; 
            border-radius: 10px; 
            background: #fff; 
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }
        .form-group input:focus { border-color: var(--primary-green); outline: none; }
        
        .report-preview { padding: 32px; }
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .filter-bar { 
            display: flex; 
            gap: 12px; 
            align-items: flex-end; 
            background: var(--bg-light);
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .btn-update { 
            background: var(--primary-green); 
            color: white; 
            border: none; 
            padding: 14px; 
            border-radius: 10px; 
            cursor: pointer; 
            font-weight: 700; 
            width: 100%; 
            transition: transform 0.1s;
        }
        .btn-update:active { transform: scale(0.98); }

        .btn-print {
            display: flex;
            align-items: center;
            gap: 8px;
            background: white;
            border: 1px solid #d1d9e0;
            padding: 10px 18px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            color: #455a64;
        }

        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 16px; 
            margin-top: 24px;
        }
        .stat-item { 
            padding: 20px; 
            border-radius: 12px; 
            border: 1px solid var(--border-color); 
        }
        .stat-label { font-size: 0.7rem; color: #90a4ae; font-weight: 800; text-transform: uppercase; margin-bottom: 4px; }
        .stat-value { font-size: 1.8rem; font-weight: 800; }

        /* Summary Context Box Elements */
        .insights-summary-box {
            background: #fdfdfd;
            border: 1px solid #f0f4f1;
            border-left: 4px solid var(--primary-green);
            border-radius: 12px;
            padding: 18px;
            margin-top: 24px;
            font-size: 0.9rem;
            line-height: 1.5;
            color: #37474f;
        }

        @media print {
            .sidebar, .no-print, .filter-bar { display: none !important; }
            main { margin: 0 !important; padding: 0 !important; }
            .report-preview { border: none; box-shadow: none; width: 100%; }
            .settings-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <?php include('sidebar/sidebar.php'); ?>

    <main style="padding: 20px;">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h1>System Configuration</h1>
                <p style="color: #78909c;">Station ID: <strong>AGRI-01-Lolin</strong></p>
            </div>
            <button class="btn-print no-print" onclick="window.print()">
                <span>🖨️</span> Print Summary Profile
            </button>
        </header>

        <div class="settings-container">
            <div class="card settings-card no-print">
                <h3 style="margin-bottom: 20px; font-size: 1.1rem; color: #37474f;">Controller Settings</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Operation Mode</label>
                        <select name="mode">
                            <option value="manual" <?php if($device['mode'] == 'manual') echo 'selected'; ?>>Manual Override</option>
                            <option value="auto" <?php if($device['mode'] == 'auto') echo 'selected'; ?>>AI Automatic</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Active Plant Profile</label>
                        <select name="selected_plant">
                            <?php foreach($plants_list as $p): ?>
                                <option value="<?php echo $p['plant_key']; ?>" <?php if($device['selected_plant'] == $p['plant_key']) echo 'selected'; ?>>
                                    <?php echo $p['plant_name']; ?> 
                                    (<?php echo $p['low_threshold']; ?>% - <?php echo $p['high_threshold']; ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="background: #f8f9fa; padding: 12px; border-radius: 10px; border: 1px solid #eee;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" name="auto_lock" id="auto_lock" style="width: auto;" <?php if($device['auto_lock']) echo 'checked'; ?>>
                            <label for="auto_lock" style="margin:0; text-transform: none; color: #37474f; font-weight: 600;">Enable Auto-Lock Safety</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Server IP Binding</label>
                        <input type="text" value="192.168.1.8" readonly style="background: #f1f3f5; color: #90a4ae; border-style: dashed;">
                    </div>
                    
                    <button type="submit" name="update_settings" class="btn-update">Update Hardware Sync</button>
                </form>
            </div>

            <div class="card report-preview">
                <form method="GET" class="filter-bar no-print">
                    <div style="flex: 1;">
                        <label style="font-size: 0.7rem; font-weight: 800; color: #90a4ae;">START DATE</label>
                        <input type="date" name="start_date" value="<?php echo $start_date; ?>" style="width: 100%; border: 1px solid #d1d9e0; padding: 8px; border-radius: 8px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="font-size: 0.7rem; font-weight: 800; color: #90a4ae;">END DATE</label>
                        <input type="date" name="end_date" value="<?php echo $end_date; ?>" style="width: 100%; border: 1px solid #d1d9e0; padding: 8px; border-radius: 8px;">
                    </div>
                    <button type="submit" style="background: #37474f; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600;">Filter</button>
                </form>

                <div class="report-header">
                    <div>
                        <h2 style="color: #263238; margin:0;">Irrigation Metrics & Analysis</h2>
                        <p style="color: #90a4ae; font-size: 0.85rem; margin-top: 4px;"><?php echo date('M d, Y', strtotime($start_date)); ?> — <?php echo date('M d, Y', strtotime($end_date)); ?></p>
                    </div>
                    <div style="text-align: right;">
                        <span style="background: #e8f5e9; color: #2e7d32; padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; display: inline-block; margin-bottom: 6px;">Target Crop: <?php echo htmlspecialchars($active_profile['name']); ?></span>
                        <div style="font-size: 0.75rem; color: #78909c;">Optimal Band: <?php echo $active_profile['low']; ?>% - <?php echo $active_profile['high']; ?>%</div>
                    </div>
                </div>

                <div style="height: 280px; width: 100%;">
                    <canvas id="reportChart"></canvas>
                </div>

                <div class="stats-grid">
                    <div class="stat-item" style="background: #f1f8e9; border-color: #dcedc8;">
                        <div class="stat-label">Avg. Soil Moisture</div>
                        <div class="stat-value" style="color: #2e7d32;"><?php echo number_format($avg_moisture, 1); ?>%</div>
                    </div>
                    <div class="stat-item" style="background: #e3f2fd; border-color: #bbdefb;">
                        <div class="stat-label">Total Water Used</div>
                        <div class="stat-value" style="color: #1565c0;"><?php echo number_format($total_water, 1); ?> L</div>
                    </div>
                    <div class="stat-item" style="background: #fffde7; border-color: #fff9c4;">
                        <div class="stat-label">Irrigation Cycles</div>
                        <div class="stat-value" style="color: #f57f17;"><?php echo $pump_events; ?> Runs</div>
                    </div>
                </div>

                <div class="insights-summary-box" style="border-left-color: <?php echo ($avg_moisture < $active_profile['low']) ? '#d32f2f' : '#2e7d32'; ?>;">
                    <strong style="display: block; margin-bottom: 6px; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px; color: #263238;">📋 Analytics Interpretation Summary</strong>
                    <?php if ($avg_moisture == 0): ?>
                        No active log data available for this specified date parameter. Check ESP8266 power loops and serial connection continuity.
                    <?php else: ?>
                        During this operational period, the node handled <strong><?php echo $pump_events; ?> system activations</strong>, distributing a cumulative total of <strong><?php echo number_format($total_water, 1); ?> Liters</strong> of water. 
                        The field retained a mean soil index of <strong><?php echo number_format($avg_moisture, 1); ?>%</strong>. 
                        
                        <?php if ($avg_moisture < $active_profile['low']): ?>
                            <span style="color: #c62828; font-weight: 700;">Warning:</span> Average telemetry trends are resting below your <strong><?php echo $active_profile['low']; ?>%</strong> limit for <em><?php echo htmlspecialchars($active_profile['name']); ?></em>. Increase automatic running periods or trace the main line for pressure issues.
                        <?php elseif ($avg_moisture > $active_profile['high']): ?>
                            <span style="color: #e65100; font-weight: 700;">Notice:</span> Soil values indicate slight over-saturation above the <strong><?php echo $active_profile['high']; ?>%</strong> ceiling. Consider disabling manual overrides to limit the risk of root rot.
                        <?php else: ?>
                            <span style="color: #2e7d32; font-weight: 700;">Optimal Configuration Status:</span> Automated calibration routines are managing field moisture within the requested biological comfort window. No manual changes are required at this time.
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <footer style="margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--border-color); color: #b0bec5; font-size: 0.7rem; display: flex; justify-content: space-between;">
                    <span>System ID: NODE-V3-Lolin-<?php echo session_id(); ?></span>
                    <span>© 2026 Agriflow Smart Systems</span>
                </footer>
            </div>
        </div>
    </main>

    <script>
        const ctx = document.getElementById('reportChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Water Consumption (L)',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: 'rgba(46, 125, 50, 0.75)',
                    borderColor: 'rgba(46, 125, 50, 1)',
                    borderWidth: 1,
                    borderRadius: 6,
                    barThickness: 24
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        grid: { color: '#f5f7f8' },
                        ticks: { font: { size: 10 } }
                    },
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                }
            }
        });
    </script>
</body>
</html>
