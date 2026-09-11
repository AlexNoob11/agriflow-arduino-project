<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$host = 'localhost';
$dbname = 'agriflow_db'; 
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Individual Deletion
    if (isset($_POST['delete_id'])) {
        $stmt = $pdo->prepare("DELETE FROM pump_logs WHERE id = :id");
        $stmt->execute(['id' => $_POST['delete_id']]);
        header("Location: pump_logs.php?status=deleted");
        exit();
    }

    // Bulk Deletion (Clear All)
    if (isset($_POST['delete_all'])) {
        $pdo->exec("DELETE FROM pump_logs");
        header("Location: pump_logs.php?status=cleared");
        exit();
    }

    // Setup Filtering Logic
    $filter_date = $_GET['filter_date'] ?? '';
    
    $query_str = "
        SELECT 
            pl.*, 
            dc.selected_plant,
            (SELECT moisture FROM sensor_logs WHERE timestamp <= pl.start_time ORDER BY timestamp DESC LIMIT 1) as start_moisture,
            (SELECT temp FROM sensor_logs WHERE timestamp <= pl.start_time ORDER BY timestamp DESC LIMIT 1) as start_temp,
            (SELECT ph FROM sensor_logs WHERE timestamp <= pl.start_time ORDER BY timestamp DESC LIMIT 1) as start_ph
        FROM pump_logs pl
        CROSS JOIN device_controls dc 
        WHERE dc.id = 1";

    if (!empty($filter_date)) {
        $query_str .= " AND DATE(pl.start_time) = :filter_date";
    }

    $query_str .= " ORDER BY pl.start_time DESC";
    
    $stmt = $pdo->prepare($query_str);
    if (!empty($filter_date)) {
        $stmt->bindParam(':filter_date', $filter_date);
    }
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Compute Real-time Meta Analytics
    $total_water = 0;
    $total_duration = 0;
    $total_entries = count($logs);
    
    foreach ($logs as $log) {
        $total_water += $log['liters_used'] ?? 0;
        $total_duration += $log['duration_seconds'] ?? 0;
    }
    
    $avg_duration = $total_entries > 0 ? round($total_duration / $total_entries, 1) : 0;

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agriflow | Pump & Sensor Logs</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Summary Cards Panel Design */
        .summary-metrics-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
            margin-bottom: 2rem;
        }
        .metric-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            border: 1px solid #f0f0f0;
        }
        .metric-card p { margin: 0; color: #888; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;}
        .metric-card h3 { margin: 8px 0 0 0; font-size: 1.8rem; font-weight: 800; color: #2c3e50; }
        .metric-card small { color: #555; font-size: 0.8rem; font-weight: 500; }

        .log-table-container { background: white; padding: 1.2rem; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow-x: auto; border: 1px solid #f0f0f0; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        th { background: #f8f9fa; padding: 15px; text-align: left; font-size: 0.85rem; color: #777; border-bottom: 2px solid #eee; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid #eee; font-size: 0.9rem; vertical-align: middle; }
        
        .sensor-pill {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: #f8f9fa;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.85rem;
            color: #444;
            border: 1px solid #eee;
        }
        .moisture-val { color: #1976d2; font-weight: 700; }
        .temp-val { color: #f57c00; font-weight: 600; }
        .ph-val { color: #7b1fa2; font-weight: 600; }
        .plant-badge { background: #e8f5e9; color: #2e7d32; padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;}

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            gap: 15px;
            flex-wrap: wrap;
        }
        .filter-group { display: flex; gap: 10px; align-items: center; }
        .input-field { padding: 10px; border: 1px solid #ddd; border-radius: 10px; font-family: inherit; outline: none; font-size: 0.9rem; }
        
        .btn { padding: 10px 20px; border-radius: 10px; cursor: pointer; border: none; font-weight: 700; transition: 0.2s; font-size: 0.85rem; }
        .btn-filter { background: #2e7d32; color: white; }
        .btn-filter:hover { background: #1b5e20; }
        .btn-delete-all { background: #fff5f5; color: #e53935; border: 1px solid #ffcdd2; }
        .btn-delete-all:hover { background: #ffebee; }
        
        .btn-action-del {
            background: none;
            border: none;
            color: #ff5252;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: 0.2s;
        }
        .btn-action-del:hover { background: #ffebee; color: #d32f2f; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: #888; }
    </style>
</head>
<body>

    <?php include('sidebar/sidebar.php'); ?>

    <main style="padding: 20px;">
        <header style="margin-bottom: 20px;">
            <h1>Pump & Sensor History</h1>
            <p style="color: #666;">Reviewing ambient field environment states matched against systemic watering cycles.</p>
        </header>

        <div class="summary-metrics-bar">
            <div class="metric-card">
                <p>Total Water Discharged</p>
                <h3><?php echo number_format($total_water, 2); ?> L</h3>
                <small style="color: #2e7d32; font-weight: 700;">Across <?php echo $total_entries; ?> cycle events</small>
            </div>
            <div class="metric-card">
                <p>Average Operational Runtime</p>
                <h3><?php echo $avg_duration; ?>s</h3>
                <small style="color: #666;">Per automated valve cycle</small>
            </div>
            <div class="metric-card">
                <p>Primary Crop Configuration</p>
                <h3>
                    <?php 
                        echo isset($logs[0]['selected_plant']) ? str_replace('_', ' ', htmlspecialchars($logs[0]['selected_plant'])) : 'None';
                    ?>
                </h3>
                <small style="color: #1976d2; font-weight: 700;">Active targeting baseline profile</small>
            </div>
        </div>

        <div class="toolbar">
            <form method="GET" class="filter-group">
                <input type="date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>" class="input-field">
                <button type="submit" class="btn btn-filter">Filter by Date</button>
                <?php if($filter_date): ?>
                    <a href="pump_logs.php" style="font-size: 0.85rem; color: #666; text-decoration: none; font-weight: 600; padding-left: 5px;">✕ Clear Filter</a>
                <?php endif; ?>
            </form>

            <form method="POST" onsubmit="return confirm('Are you absolutely sure you want to completely erase all historical records? This step cannot be undone.');">
                <button type="submit" name="delete_all" class="btn btn-delete-all">🗑️ Clear All Logs</button>
            </form>
        </div>

        <section class="log-table-container">
            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <p style="font-size: 1.1rem; font-weight: 600; margin-bottom: 5px;">No historical telemetry records found</p>
                    <p style="font-size: 0.85rem; margin: 0;">Try shifting dates or checking your ESP8266 remote relay transmissions.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Target Profile</th>
                            <th>Soil Telemetry (At Trigger)</th>
                            <th>Cycle Duration</th>
                            <th>Calculated Water Mass</th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <strong style="color: #2c3e50;"><?php echo date('M d, Y', strtotime($log['start_time'])); ?></strong><br>
                                <small style="color:#999; font-weight: 600;"><?php echo date('H:i:s', strtotime($log['start_time'])); ?></small>
                            </td>
                            <td>
                                <span class="plant-badge">
                                    <?php echo str_replace('_', ' ', htmlspecialchars($log['selected_plant'] ?? 'Unknown')); ?>
                                </span>
                            </td>
                            <td>
                                <div class="sensor-pill">
                                    <span class="moisture-val">💧 <?php echo number_format($log['start_moisture'] ?? 0, 1); ?>%</span>
                                    <span class="temp-val">🌡️ <?php echo number_format($log['start_temp'] ?? 0, 1); ?>°C</span>
                                    <span class="ph-val">🧪 <?php echo number_format($log['start_ph'] ?? 0, 1); ?> pH</span>
                                </div>
                            </td>
                            <td>
                                <strong style="color: #444;"><?php echo $log['duration_seconds']; ?> seconds</strong><br>
                                <small style="color: #999;">Solenoid open phase</small>
                            </td>
                            <td style="font-size: 1.05rem; font-weight: 800; color: #2e7d32;">
                                <?php echo number_format($log['liters_used'], 3); ?> L
                            </td>
                            <td style="text-align: center;">
                                <form method="POST" onsubmit="return confirm('Delete this specific row from history?');" style="display:inline;">
                                    <input type="hidden" name="delete_id" value="<?php echo $log['id']; ?>">
                                    <button type="submit" class="btn-action-del" title="Delete Log Entry">
                                        🗑️
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>

</body>
</html>
