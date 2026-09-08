<?php
session_start();

// 1. Security Gate
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?status=unauthorized");
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
    // 2. Fetch data for Chart (limited to avoid crashing if logs are frequent)
    // We order by ASC for the chart (Oldest to Newest)
    $stmt = $pdo->prepare("SELECT moisture, temp, ph, timestamp FROM sensor_logs 
                           WHERE timestamp >= NOW() - INTERVAL $interval 
                           ORDER BY timestamp ASC LIMIT 100");
    $stmt->execute();
    $logs = $stmt->fetchAll();

    $chartLabels = [];
    $moistureData = [];
    $tempData = [];

    foreach ($logs as $log) {
        // Format label based on filter
        $format = ($filter === '24h') ? "H:i" : "M d, H:i";
        $chartLabels[] = date($format, strtotime($log['timestamp']));
        $moistureData[] = $log['moisture'];
        $tempData[] = $log['temp'];
    }

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
        .chart-main-card { background: white; padding: 2rem; border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        
        /* Filter Buttons */
        .filter-group { display: flex; gap: 10px; }
        .filter-btn { 
            text-decoration: none; padding: 8px 16px; border-radius: 8px; border: 1px solid #eee; 
            background: white; color: #666; font-size: 0.85rem; font-weight: 600; transition: 0.3s;
        }
        .filter-btn.active { background: #2e7d32; color: white; border-color: #2e7d32; }
        .filter-btn:hover:not(.active) { background: #f0f0f0; }

        .history-table-card { background: white; padding: 1.5rem; border-radius: 24px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 500px; }
        th { text-align: left; padding: 12px; color: var(--secondary); border-bottom: 2px solid #f0f0f0; font-size: 0.8rem; text-transform: uppercase; }
        td { padding: 15px 12px; border-bottom: 1px solid #f9f9f9; font-size: 0.9rem; }
        
        .status-badge { padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: bold; }
        .status-optimal { background: #e8f5e9; color: #2e7d32; }
        .status-low { background: #fff3e0; color: #ef6c00; }
    </style>
</head>
<body>

    <?php include('sidebar.php'); ?>

    <main>
        <header>
            <div>
                <h1>Data Visualization</h1>
                <p style="color: var(--secondary);">Showing trends for <b><?php echo $title_label; ?></b></p>
            </div>
            <div class="filter-group">
                <a href="?filter=24h" class="filter-btn <?php echo $filter == '24h' ? 'active' : ''; ?>">Last 24h</a>
                <a href="?filter=7d" class="filter-btn <?php echo $filter == '7d' ? 'active' : ''; ?>">7 Days</a>
                <a href="?filter=30d" class="filter-btn <?php echo $filter == '30d' ? 'active' : ''; ?>">30 Days</a>
            </div>
        </header>

        <div class="viz-container">
            <div class="chart-main-card">
                <div class="chart-header">
                    <h3>Moisture vs. Temperature Correlation</h3>
                    <div style="font-size: 0.8rem; color: var(--secondary);">📍 Source: SQL Table `sensor_logs`</div>
                </div>
                <div style="height: 400px;">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <div class="history-table-card">
                <h3>Detailed Reading History</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Moisture</th>
                            <th>Temp</th>
                            <th>pH Level</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tableData as $row): ?>
                        <tr>
                            <td><?php echo date("Y-m-d H:i", strtotime($row['timestamp'])); ?></td>
                            <td style="font-weight: bold;"><?php echo $row['moisture']; ?>%</td>
                            <td><?php echo $row['temp']; ?>°C</td>
                            <td><?php echo $row['ph']; ?></td>
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
                            <tr><td colspan="5" style="text-align: center; padding: 20px;">No data found for this period.</td></tr>
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
                        backgroundColor: 'rgba(46, 125, 50, 0.1)',
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Temp (°C)',
                        data: <?php echo json_encode($tempData); ?>,
                        borderColor: '#f57c00',
                        borderDash: [5, 5],
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', align: 'end' }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Moisture %' },
                        min: 0, max: 100
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'Temp °C' }
                    }
                }
            }
        });
    </script>
</body>
</html>