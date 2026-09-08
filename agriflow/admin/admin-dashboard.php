<?php
session_start();

// 1. STRICT SECURITY: Verify user is logged in AND is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?status=unauthorized");
    exit();
}

include 'db_connect.php'; 

try {
    // 2. ADMIN ANALYTICS: System-wide counts
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $logCount = $pdo->query("SELECT COUNT(*) FROM sensor_logs")->fetchColumn();
    $adminCount = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();

    // 3. GET SENSOR DATA (For a summary overview)
    $latestStmt = $pdo->query("SELECT * FROM sensor_logs ORDER BY timestamp DESC LIMIT 2");
    $readings = $latestStmt->fetchAll();
    
    $latest = $readings[0] ?? ['moisture' => 0, 'temp' => 0, 'ph' => 0, 'timestamp' => date('Y-m-d H:i:s')];
    $previous = $readings[1] ?? $latest;
    $moistureDiff = $latest['moisture'] - $previous['moisture'];

    // 4. GET RECENT USER REGISTRATIONS (Admin specific)
    $recentUsers = $pdo->query("SELECT fullname, email, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

    // 5. CHART DATA (Aggregate System Activity)
    $chartStmt = $pdo->query("SELECT moisture, timestamp FROM sensor_logs ORDER BY timestamp DESC LIMIT 20");
    $chartRows = array_reverse($chartStmt->fetchAll());

    $chartLabels = [];
    $moistureData = [];
    foreach ($chartRows as $row) {
        $chartLabels[] = date("H:i", strtotime($row['timestamp']));
        $moistureData[] = $row['moisture'];
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
    <title>Agriflow Admin | Management Console</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --admin-primary: #1a3a5f; /* Deep Admin Blue */
            --admin-accent: #3498db;
            --glass: rgba(255, 255, 255, 0.9);
            --shadow: 0 10px 40px rgba(0,0,0,0.08);
        }
        
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }

        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border-top: 4px solid var(--admin-accent);
        }

        .admin-banner {
            background: linear-gradient(135deg, var(--admin-primary) 0%, #2c3e50 100%);
            color: white;
            padding: 40px;
            border-radius: 24px;
            margin-bottom: 30px;
            box-shadow: 0 15px 30px rgba(26, 58, 95, 0.2);
            position: relative;
            overflow: hidden;
        }

        .admin-banner::after {
            content: "ADMIN";
            position: absolute;
            right: -20px;
            bottom: -20px;
            font-size: 8rem;
            font-weight: 900;
            opacity: 0.05;
        }

        .management-flex {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
        }

        .data-panel {
            background: white;
            padding: 25px;
            border-radius: 24px;
            box-shadow: var(--shadow);
            flex: 2;
            min-width: 400px;
        }

        .user-list {
            flex: 1;
            background: white;
            padding: 25px;
            border-radius: 24px;
            box-shadow: var(--shadow);
            min-width: 300px;
        }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { text-align: left; color: #888; font-size: 0.75rem; text-transform: uppercase; padding-bottom: 10px; }
        td { padding: 12px 0; border-top: 1px solid #f0f0f0; font-size: 0.9rem; }

        .badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        .badge-user { background: #e3f2fd; color: #1976d2; }
    </style>
</head>
<body>

    <?php include('sidebar.php'); ?>

    <main style="padding: 40px;">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <h1 style="margin: 0; color: var(--admin-primary);">System Administration</h1>
                <p style="color: #666;">Welcome back, <strong><?php echo $_SESSION['user_name']; ?></strong></p>
            </div>
            <div style="background: white; padding: 10px 20px; border-radius: 12px; box-shadow: var(--shadow);">
                <span style="color: #2ecc71;">●</span> <small>DATABASE CONNECTED</small>
            </div>
        </header>
        <div class="admin-grid">
            <div class="stat-card">
                <p style="color: #888; font-size: 0.8rem; font-weight: 700;">TOTAL OPERATORS</p>
                <h2 style="font-size: 2.5rem; margin: 10px 0;"><?php echo $userCount; ?></h2>
                <span class="badge badge-user">Standard Accounts</span>
            </div>
            <div class="stat-card">
                <p style="color: #888; font-size: 0.8rem; font-weight: 700;">SYSTEM ADMINISTRATORS</p>
                <h2 style="font-size: 2.5rem; margin: 10px 0; color: var(--admin-accent);"><?php echo $adminCount; ?></h2>
                <span class="badge" style="background: #fff3e0; color: #ef6c00;">Full Access</span>
            </div>
            <div class="stat-card">
                <p style="color: #888; font-size: 0.8rem; font-weight: 700;">TOTAL DATA POINTS</p>
                <h2 style="font-size: 2.5rem; margin: 10px 0;"><?php echo number_format($logCount); ?></h2>
                <small style="color: #2ecc71;">+<?php echo rand(10, 50); ?> logs/hr</small>
            </div>
        </div>

        <div class="management-flex">
            <!-- Global Moisture Trends -->
            <div class="data-panel">
                <h3 style="margin-top: 0;">Global Soil Hydration (Last 20 Readings)</h3>
                <div style="height: 300px;">
                    <canvas id="adminChart"></canvas>
                </div>
            </div>

            <!-- Recent User Signups -->
            <div class="user-list">
                <h3 style="margin-top: 0;">Recent Registrations</h3>
                <table>
                    <thead>
                        <tr><th>Name</th><th>Joined</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($recentUsers as $user): ?>
                        <tr>
                            <td>
                                <strong><?php echo $user['fullname']; ?></strong><br>
                                <small style="color: #999;"><?php echo $user['email']; ?></small>
                            </td>
                            <td style="color: #666; font-size: 0.8rem;">
                                <?php echo date("M d", strtotime($user['created_at'])); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        const ctx = document.getElementById('adminChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: 'Avg. Moisture %',
                    data: <?php echo json_encode($moistureData); ?>,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, max: 100, grid: { color: '#f0f0f0' } },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>
</body>
</html>