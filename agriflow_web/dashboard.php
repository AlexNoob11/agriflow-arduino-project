<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'db_connect.php'; // ensure this file defines $pdo

try {
    // 1. Get Latest Sensor Data + Previous reading for trend analysis
    $latestStmt = $pdo->query("SELECT * FROM sensor_logs ORDER BY timestamp DESC LIMIT 2");
    $readings = $latestStmt->fetchAll();
    
    $latest = $readings[0] ?? ['moisture' => 0, 'temp' => 0, 'ph' => 0, 'timestamp' => date('Y-m-d H:i:s')];
    $previous = $readings[1] ?? $latest;

    // Calculate Trends
    $moistureDiff = $latest['moisture'] - $previous['moisture'];
    $tempDiff = $latest['temp'] - $previous['temp'];

    // 2. Get Device Status
    $controlStmt = $pdo->query("SELECT * FROM device_controls WHERE id = 1");
    $controls = $controlStmt->fetch();
    $mode = $controls['mode'] ?? 'manual';
    $pump = $controls['pump_status'] ?? 0;

    // 3. Chart Data (Last 15 readings for better resolution)
    $chartStmt = $pdo->query("SELECT moisture, temp, ph, timestamp FROM sensor_logs ORDER BY timestamp DESC LIMIT 15");
    $chartRows = array_reverse($chartStmt->fetchAll());

    $chartLabels = [];
    $moistureData = [];
    $tempData = [];
    foreach ($chartRows as $row) {
        $chartLabels[] = date("H:i", strtotime($row['timestamp']));
        $moistureData[] = $row['moisture'];
        $tempData[] = $row['temp'];
    }

} catch (Exception $e) {
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agriflow | Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --glass: rgba(255, 255, 255, 0.8);
            --shadow: 0 8px 30px rgba(0,0,0,0.05);
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            border: 1px solid #f0f0f0;
        }

        .stat-card .trend {
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .trend.up { color: #2ecc71; }
        .trend.down { color: #e74c3c; }

        .control-banner {
            background: linear-gradient(135deg, #1a5d1a 0%, #2e7d32 100%);
            color: white;
            padding: 30px;
            border-radius: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 20px rgba(46, 125, 50, 0.2);
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            box-shadow: 0 0 10px currentColor;
        }

        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 24px;
            box-shadow: var(--shadow);
            margin-top: 25px;
        }

        .badge-mode {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(5px);
            padding: 6px 16px;
            border-radius: 50px;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>

    <?php include('sidebar/sidebar.php'); ?>

    <main style="padding: 30px;">
        <header style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <h1 style="font-size: 1.8rem; font-weight: 800;">Operational Overview</h1>
                <p style="color: #666;">Monitoring <strong>Node 001</strong> — Binalbagan Sector</p>
            </div>
            <div style="text-align: right;">
                <span class="status-indicator" style="background: #2ecc71; color: #2ecc71;"></span>
                <small style="color: #666; font-weight: 600;">SYSTEM LIVE</small>
            </div>
        </header>

        <section class="control-banner">
            <div>
                <div class="badge-mode">System Mode: <?php echo $mode; ?></div>
                <h2 style="font-size: 2rem; margin: 15px 0 5px 0;">
                    Pump is <?php echo ($pump == 1) ? 'Active' : 'Idle'; ?>
                </h2>
                <p style="opacity: 0.8;">Irrigation logic based on current moisture levels.</p>
            </div>
            <a href="motor_control.php" style="background: white; color: #1a5d1a; padding: 15px 30px; border-radius: 12px; font-weight: 800; text-decoration: none; transition: 0.3s;">
                Control Center
            </a>
        </section>

        <div class="dashboard-grid">
            <div class="stat-card">
                <p style="color: #666; font-size: 0.9rem; font-weight: 600;">Soil Moisture</p>
                <div style="display: flex; align-items: baseline; gap: 10px; margin: 10px 0;">
                    <span style="font-size: 2.5rem; font-weight: 800;"><?php echo round($latest['moisture'], 1); ?>%</span>
                    <span class="trend <?php echo ($moistureDiff >= 0) ? 'up' : 'down'; ?>">
                        <?php echo ($moistureDiff >= 0) ? '▲' : '▼'; ?> <?php echo abs(round($moistureDiff, 1)); ?>%
                    </span>
                </div>
                <div style="width: 100%; background: #eee; height: 8px; border-radius: 10px;">
                    <div style="width: <?php echo $latest['moisture']; ?>%; background: #2e7d32; height: 100%; border-radius: 10px;"></div>
                </div>
            </div>

            <div class="stat-card">
                <p style="color: #666; font-size: 0.9rem; font-weight: 600;">Soil Temperature</p>
                <div style="display: flex; align-items: baseline; gap: 10px; margin: 10px 0;">
                    <span style="font-size: 2.5rem; font-weight: 800; color: #f39c12;"><?php echo round($latest['temp'], 1); ?>°C</span>
                    <span class="trend <?php echo ($tempDiff <= 0) ? 'up' : 'down'; ?>" style="color: #999;">
                        Stable
                    </span>
                </div>
                <p style="font-size: 0.8rem; color: #999;">Optimal: 20°C - 30°C</p>
            </div>

            <div class="stat-card">
                <p style="color: #666; font-size: 0.9rem; font-weight: 600;">Soil Acidity (pH)</p>
                <div style="display: flex; align-items: baseline; gap: 10px; margin: 10px 0;">
                    <span style="font-size: 2.5rem; font-weight: 800; color: #9b59b6;"><?php echo round($latest['ph'], 1); ?></span>
                </div>
                <p style="font-size: 0.8rem; color: <?php echo ($latest['ph'] < 6 || $latest['ph'] > 7.5) ? '#e74c3c' : '#2ecc71'; ?>; font-weight: 700;">
                    <?php echo ($latest['ph'] < 6) ? 'Too Acidic' : (($latest['ph'] > 7.5) ? 'Too Alkaline' : 'Neutral Range'); ?>
                </p>
            </div>
        </div>

        <div class="chart-container">
            <h3 style="margin-bottom: 25px;">Moisture Dynamics (Live)</h3>
            <div style="height: 350px;">
                <canvas id="mainChart"></canvas>
            </div>
        </div>
    </main>

    <script>
        const ctx = document.getElementById('mainChart').getContext('2d');
        
        // Gradient for a professional look
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(46, 125, 50, 0.3)');
        gradient.addColorStop(1, 'rgba(46, 125, 50, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: 'Moisture %',
                    data: <?php echo json_encode($moistureData); ?>,
                    borderColor: '#2e7d32',
                    borderWidth: 3,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#2e7d32',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { 
                        grid: { color: '#f0f0f0' },
                        beginAtZero: true,
                        max: 100,
                        ticks: { callback: value => value + '%' }
                    },
                    x: { grid: { display: false } }
                }
            }
        });

        // Auto-refresh every 30 seconds for "live" feel
        setTimeout(() => { location.reload(); }, 30000);
    </script>
</body>
</html>
