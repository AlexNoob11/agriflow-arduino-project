<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'db_connect.php';

// 1. Handle Filter Logic
$filter = $_GET['filter'] ?? '24h';
$interval = "1 DAY"; // Default
$title_label = "Last 24 Hours";

if ($filter === '7d') {
    $interval = "7 DAY";
    $title_label = "Last 7 Days";
} elseif ($filter === '30d') {
    $interval = "30 DAY";
    $title_label = "Last 30 Days";
}

try {
    // 2. Fetch data for Chart (ASC for chronological order)
    $stmt = $pdo->prepare("SELECT moisture, temp, ph, timestamp FROM sensor_logs 
                           WHERE timestamp >= NOW() - INTERVAL $interval 
                           ORDER BY timestamp ASC LIMIT 100");
    $stmt->execute();
    $logs = $stmt->fetchAll();

    $chartLabels = [];
    $moistureData = [];
    $tempData = [];

    // Summary Metric Accumulators
    $moistureTotal = 0;
    $tempTotal = 0;
    $logCount = count($logs);

    foreach ($logs as $log) {
        $format = ($filter === '24h') ? "H:i" : "M d, H:i";
        $chartLabels[] = date($format, strtotime($log['timestamp']));
        $moistureData[] = $log['moisture'];
        $tempData[] = $log['temp'];

        $moistureTotal += $log['moisture'];
        $tempTotal += $log['temp'];
    }

    // Compute basic math diagnostics if data exists
    $avgMoisture = $logCount > 0 ? round($moistureTotal / $logCount, 1) : 0;
    $maxMoisture = $logCount > 0 ? max($moistureData) : 0;
    $minMoisture = $logCount > 0 ? min($moistureData) : 0;

    $avgTemp = $logCount > 0 ? round($tempTotal / $logCount, 1) : 0;
    $maxTemp = $logCount > 0 ? max($tempData) : 0;
    $minTemp = $logCount > 0 ? min($tempData) : 0;

    // 3. Fetch data for Table (Newest to Oldest)
    $stmtTable = $pdo->prepare("SELECT * FROM sensor_logs 
                                WHERE timestamp >= NOW() - INTERVAL $interval 
                                ORDER BY timestamp DESC LIMIT 50");
    $stmtTable->execute();
    $tableData = $stmtTable->fetchAll();

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agriflow | Data Visualization</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .viz-container { display: flex; flex-direction: column; gap: 2rem; }
        .chart-main-card { background: white; padding: 2rem; border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); border: 1px solid #f6f6f6; }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        
        /* Filter Control Layout */
        .filter-group { display: flex; gap: 10px; }
        .filter-btn { 
            text-decoration: none; padding: 8px 16px; border-radius: 8px; border: 1px solid #eee; 
            background: white; color: #666; font-size: 0.85rem; font-weight: 600; transition: 0.3s;
        }
        .filter-btn.active { background: #2e7d32; color: white; border-color: #2e7d32; }
        .filter-btn:hover:not(.active) { background: #f0f0f0; }

        /* KPI Quick Metrics Row */
        .summary-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1rem;
        }
        .stat-badge-box {
            background: #f9fbf9;
            padding: 1.2rem;
            border-radius: 16px;
            border-left: 4px solid #2e7d32;
        }
        .stat-badge-box.temp-box {
            background: #fffbf7;
            border-left-color: #f57c00;
        }
        .stat-meta-title { font-size: 0.75rem; color: #888; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-main-value { font-size: 1.8rem; font-weight: 800; margin: 5px 0; color: #333; }
        .stat-min-max { font-size: 0.8rem; color: #666; }

        /* Explanatory Analytics Callout Box */
        .insight-callout {
            background: #f4f6f9;
            border-radius: 16px;
            padding: 1.2rem;
            margin-top: 1.5rem;
            font-size: 0.88rem;
            line-height: 1.5;
            color: #455a64;
            border-left: 4px solid #78909c;
        }

        .history-table-card { background: white; padding: 1.5rem; border-radius: 24px; overflow-x: auto; border: 1px solid #f6f6f6; }
        table { width: 100%; border-collapse: collapse; min-width: 500px; }
        th { text-align: left; padding: 12px; color: #666; border-bottom: 2px solid #f0f0f0; font-size: 0.8rem; text-transform: uppercase; font-weight: 700; }
        td { padding: 15px 12px; border-bottom: 1px solid #f9f9f9; font-size: 0.9rem; }
        
        .status-badge { padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
        .status-optimal { background: #e8f5e9; color: #2e7d32; }
        .status-low { background: #fff3e0; color: #ef6c00; }
    </style>
</head>
<body>

    <?php include('sidebar/sidebar.php'); ?>

    <main style="padding: 20px;">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1>Data Visualization Center</h1>
                <p style="color: #666;">Historical crop telemetry dynamics for <b><?php echo $title_label; ?></b></p>
            </div>
            <div class="filter-group">
                <a href="?filter=24h" class="filter-btn <?php echo $filter == '24h' ? 'active' : ''; ?>">Last 24h</a>
                <a href="?filter=7d" class="filter-btn <?php echo $filter == '7d' ? 'active' : ''; ?>">7 Days</a>
                <a href="?filter=30d" class="filter-btn <?php echo $filter == '30d' ? 'active' : ''; ?>">30 Days</a>
            </div>
        </header>

        <div class="viz-container">
            
            <div class="summary-stats-grid">
                <div class="stat-badge-box">
                    <div class="stat-meta-title">Avg Soil Moisture</div>
                    <div class="stat-main-value"><?php echo $avgMoisture; ?>%</div>
                    <div class="stat-min-max">
                        High: <b><?php echo $maxMoisture; ?>%</b> &nbsp;|&nbsp; Low: <b><?php echo $minMoisture; ?>%</b>
                    </div>
                </div>
                <div class="stat-badge-box temp-box">
                    <div class="stat-meta-title">Avg Substrate Temp</div>
                    <div class="stat-main-value"><?php echo $avgTemp; ?>°C</div>
                    <div class="stat-min-max">
                        Peak: <b><?php echo $maxTemp; ?>°C</b> &nbsp;|&nbsp; Low: <b><?php echo $minTemp; ?>°C</b>
                    </div>
                </div>
            </div>

            <div class="chart-main-card">
                <div class="chart-header">
                    <div>
                        <h3 style="margin: 0 0 5px 0;">Moisture vs. Temperature Correlation</h3>
                        <p style="margin: 0; font-size: 0.85rem; color: #777;">Tracking real-time response to ambient temperatures and field irrigation events</p>
                    </div>
                    <div style="font-size: 0.8rem; color: #999; font-weight: 600;">📍 Source: `sensor_logs`</div>
                </div>
                
                <div style="height: 400px;">
                    <canvas id="trendChart"></canvas>
                </div>

                <div class="insight-callout">
                    💡 <b>Data Insight Summary:</b> 
                    This graph details how moisture drops as temperature rises. 
                    When soil temperatures spike near peak daylight hours, water levels decline due to **evapotranspiration**. 
                    <?php if ($minMoisture < 35): ?>
                        During this window, moisture dropped to a low of <b><?php echo $minMoisture; ?>%</b>. If this dip occurs frequently during high-heat periods, consider updating your irrigation schedules to run automated shifts earlier in the morning before thermal stress peaks.
                    <?php else: ?>
                        Your soil moisture remained steady above the 35% safety floor throughout this cycle, indicating healthy soil water retention.
                    <?php endif; ?>
                </div>
            </div>

            <div class="history-table-card">
                <h3 style="margin: 0 0 15px 0;">Detailed Log Audits (Latest 50 Entries)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Moisture</th>
                            <th>Temp</th>
                            <th>pH Level</th>
                            <th>Status Check</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tableData as $row): ?>
                        <tr>
                            <td><?php echo date("Y-m-d H:i", strtotime($row['timestamp'])); ?></td>
                            <td style="font-weight: bold; color: #2e7d32;"><?php echo $row['moisture']; ?>%</td>
                            <td><?php echo $row['temp']; ?>°C</td>
                            <td><span style="color: #9c27b0; font-weight: 600;"><?php echo $row['ph']; ?></span></td>
                            <td>
                                <?php if ($row['moisture'] < 35): ?>
                                    <span class="status-badge status-low">Low Moisture</span>
                                <?php else: ?>
                                    <span class="status-badge status-optimal">Optimal</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tableData)): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 30px; color: #999;">No field data logs returned inside this historical window.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        const ctx = document.getElementById('trendChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [
                    {
                        label: 'Moisture (%)',
                        data: <?php echo json_encode($moistureData); ?>,
                        borderColor: '#2e7d32',
                        backgroundColor: 'rgba(46, 125, 50, 0.06)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 3,
                        pointRadius: 2,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Temp (°C)',
                        data: <?php echo json_encode($tempData); ?>,
                        borderColor: '#f57c00',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [6, 4],
                        pointRadius: 2,
                        tension: 0.35,
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { boxWidth: 15, font: { weight: 'bold' } } }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Soil Moisture Level (%)', font: { weight: 'bold' } },
                        min: 0, max: 100,
                        grid: { color: '#f0f0f0' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'Temperature Data (°C)', font: { weight: 'bold' } }
                    }
                }
            }
        });
    </script>
</body>
</html>