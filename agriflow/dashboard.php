<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'db_connect.php';

try {
    // Get Latest Sensor Data + Previous reading for trend analysis
    $latestStmt = $pdo->query("SELECT * FROM sensor_logs ORDER BY timestamp DESC LIMIT 2");
    $readings = $latestStmt->fetchAll();
    
    $latest = $readings[0] ?? ['moisture' => 0, 'temp' => 0, 'ph' => 0, 'timestamp' => date('Y-m-d H:i:s')];
    $previous = $readings[1] ?? $latest;

    // Calculate Trends
    $moistureDiff = $latest['moisture'] - $previous['moisture'];
    $tempDiff = $latest['temp'] - $previous['temp'];

    // Get Device Status
    $controlStmt = $pdo->query("SELECT * FROM device_controls WHERE id = 1");
    $controls = $controlStmt->fetch();
    $mode = $controls['mode'] ?? 'manual';
    $pump = $controls['pump_status'] ?? 0;

    // Chart Data (Last 15 readings for better resolution)
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

    // Live Weather Forecast & Parameters Engine
    $searchLocation = isset($_GET['search_location']) ? trim($_GET['search_location']) : 'Himamaylan,PH';
    $temperatureUnit = isset($_GET['unit']) && $_GET['unit'] === 'F' ? 'F' : 'C';

    $apiKey = "968da3cb56d320a14e941bf4cf230256";
    
    // Choose API system units depending on toggle selection
    $apiUnits = ($temperatureUnit === 'F') ? 'imperial' : 'metric';

    // Baseline Hardcoded Fallbacks (In case API request fails or key is unactivated)
    $weatherCurrent = [
        'temp' => ($temperatureUnit === 'C') ? 29 : round((29 * 9/5) + 32),
        'desc' => 'Clear Sky (Fallback)',
        'icon' => '☀️',
        'feels_like' => ($temperatureUnit === 'C') ? 29 : round((29 * 9/5) + 32),
        'wind' => '4 m/s W',
        'humidity' => 76,
        'visibility' => '10km',
        'pressure' => '1027 hPa',
        'uv' => '0 Low',
        'dew_point' => ($temperatureUnit === 'C') ? '13°C' : round((13 * 9/5) + 32) . '°F'
    ];

    $forecastDays = [
        ['day' => 'Today', 'temp' => 29, 'desc' => 'clear sky', 'icon' => '☀️'],
        ['day' => 'Sun', 'temp' => 31, 'desc' => 'broken clouds', 'icon' => '⛅'],
        ['day' => 'Mon', 'temp' => 32, 'desc' => 'scattered clouds', 'icon' => '⛅'],
        ['day' => 'Tue', 'temp' => 32, 'desc' => 'overcast clouds', 'icon' => '☁️'],
        ['day' => 'Wed', 'temp' => 33, 'desc' => 'clear sky', 'icon' => '☀️'],
        ['day' => 'Thu', 'temp' => 34, 'desc' => 'overcast clouds', 'icon' => '☁️'],
        ['day' => 'Fri', 'temp' => 28, 'desc' => 'scattered clouds', 'icon' => '⛅'],
        ['day' => 'Sat', 'temp' => 31, 'desc' => 'overcast clouds', 'icon' => '☁️']
    ];

    // LIVE REQUEST BLOCK 1: Current Weather Data
    $currentUrl = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($searchLocation) . "&units=" . $apiUnits . "&appid=" . $apiKey;
    
    // Disable errors via context to avoid crashing layout if key drops offline
    $context = stream_context_create(['http' => ['ignore_errors' => true]]);
    $currentResponse = @file_get_contents($currentUrl, false, $context);
    
    if ($currentResponse) {
        $currentData = json_decode($currentResponse, true);
        if (isset($currentData['main'])) {
            $windUnit = ($temperatureUnit === 'F') ? 'mph' : 'm/s';
            
            // Map OpenWeather Map dynamic codes onto custom interface icons
            $iconMap = ['01' => '☀️', '02' => '⛅', '03' => '☁️', '04' => '☁️', '09' => '🌧️', '10' => '🌦️', '11' => '⛈️', '13' => '❄️', '50' => '🌫️'];
            $rawIcon = substr($currentData['weather'][0]['icon'] ?? '01', 0, 2);
            $uiIcon = $iconMap[$rawIcon] ?? '☀️';

            $weatherCurrent = [
                'temp' => round($currentData['main']['temp']),
                'desc' => $currentData['weather'][0]['description'] ?? 'Clear Sky',
                'icon' => $uiIcon,
                'feels_like' => round($currentData['main']['feels_like']),
                'wind' => round($currentData['wind']['speed']) . " " . $windUnit . " " . ($currentData['wind']['deg'] ?? ''),
                'humidity' => $currentData['main']['humidity'],
                'visibility' => (isset($currentData['visibility']) ? round($currentData['visibility'] / 1000, 1) . 'km' : 'N/A'),
                'pressure' => $currentData['main']['pressure'] . ' hPa',
                'uv' => 'N/A', // 3-hour basic api baseline excludes raw uv details
                'dew_point' => 'N/A'
            ];
        }
    }

    // LIVE REQUEST BLOCK 2: 5-Day Forecast Data (OpenWeather free tier defaults)
    $forecastUrl = "https://api.openweathermap.org/data/2.5/forecast?q=" . urlencode($searchLocation) . "&units=" . $apiUnits . "&appid=" . $apiKey;
    $forecastResponse = @file_get_contents($forecastUrl, false, $context);
    
    if ($forecastResponse) {
        $forecastData = json_decode($forecastResponse, true);
        if (isset($forecastData['list'])) {
            $processedForecast = [];
            $daysFound = [];
            
            foreach ($forecastData['list'] as $item) {
                $dateStr = $item['dt_txt'];
                $dayName = date('D', strtotime($dateStr));
                
                // Keep only one entry per day (midday at 12:00 targeted)
                if (!in_array($dayName, $daysFound) && (strpos($dateStr, '12:00:00') !== false || count($processedForecast) == 0)) {
                    $daysFound[] = $dayName;
                    
                    $rawIcon = substr($item['weather'][0]['icon'] ?? '01', 0, 2);
                    $uiIcon = $iconMap[$rawIcon] ?? '☀️';
                    
                    $processedForecast[] = [
                        'day' => (date('Y-m-d', strtotime($dateStr)) === date('Y-m-d')) ? 'Today' : $dayName,
                        'temp' => round($item['main']['temp']),
                        'desc' => $item['weather'][0]['description'],
                        'icon' => $uiIcon
                    ];
                }
                if(count($processedForecast) >= 5) break; // Limit to 5 days supported natively by standard tier APIs
            }
            if(!empty($processedForecast)) {
                $forecastDays = $processedForecast;
            }
        }
    }

    // Standard static Fahrenheit adjustment rule handler used as fallback logic security
    if (!isset($forecastData['list']) && $temperatureUnit === 'F') {
        foreach ($forecastDays as &$fd) {
            $fd['temp'] = round(($fd['temp'] * 9/5) + 32);
        }
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
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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
            display: flex;
            flex-direction: column;
            justify-content: space-between;
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
            margin-top: 25px;
            margin-bottom: 5px;
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
            width: fit-content;
        }

        .analytics-summary-box {
            background: #fafafa;
            border-radius: 12px;
            padding: 12px;
            margin-top: 16px;
            font-size: 0.82rem;
            line-height: 1.4;
            color: #555;
            text-align: left;
            border-left: 3px solid #ccc;
        }

        .weather-section {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 20px;
            margin-top: 15px;
        }
        @media (max-width: 992px) {
            .weather-section { grid-template-columns: 1fr; }
        }
        .weather-search-bar {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
        }
        .weather-search-bar input {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 0.9rem;
            outline: none;
        }
        .weather-search-bar button {
            background: #2e7d32;
            color: white;
            border: none;
            padding: 0 16px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
        }
        .unit-toggle {
            display: flex;
            gap: 4px;
            background: #f0f0f0;
            padding: 4px;
            border-radius: 8px;
        }
        .unit-toggle a {
            text-decoration: none;
            color: #333;
            padding: 4px 10px;
            font-size: 0.8rem;
            font-weight: 700;
            border-radius: 6px;
        }
        .unit-toggle a.active {
            background: white;
            color: #2e7d32;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .weather-param-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 15px;
            border-top: 1px solid #f0f0f0;
            padding-top: 15px;
        }
        .param-item {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 10px;
            font-size: 0.85rem;
            text-align: left;
        }
        .param-item span { display: block; color: #888; font-size: 0.75rem; margin-bottom: 2px;}
        .forecast-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f6f6f6;
        }
        .forecast-row:last-child { border-bottom: none; }

        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(8px);
            display: flex; justify-content: center; align-items: center; z-index: 9999;
        }
        .modal-content {
            background: white; padding: 40px; border-radius: 30px;
            max-width: 450px; width: 90%; text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: modalPop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes modalPop { from { opacity: 0; transform: scale(0.8); } to { opacity: 1; transform: scale(1); } }
        .modal-icon { font-size: 3rem; margin-bottom: 20px; animation: pulse 2s infinite; }
        .modal-actions { display: flex; flex-direction: column; gap: 12px; margin-top: 30px; }
        .btn-activate { background: #2e7d32; color: white; padding: 15px; border-radius: 12px; text-decoration: none; font-weight: 800; transition: 0.3s; display: block; }
        .btn-activate:hover { background: #1b5e20; transform: translateY(-2px); }
        .btn-dismiss { background: none; border: none; color: #999; font-weight: 600; cursor: pointer; }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
    </style>
</head>
<body>

    <?php include('sidebar/sidebar.php'); ?>

     <main style="padding: 20px;">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div style="text-align: left;">
                <h1>Operational Overview</h1>
                <p style="color: #666; margin: 4px 0 0 0;">Monitoring <strong>Node 001</strong> — Himamaylan Sector</p>
            </div>
            <div style="text-align: right; display: flex; align-items: center; gap: 15px;">
                <div class="unit-toggle">
                    <a href="?search_location=<?php echo urlencode($searchLocation); ?>&unit=C" class="<?php echo $temperatureUnit === 'C' ? 'active' : ''; ?>">°C</a>
                    <a href="?search_location=<?php echo urlencode($searchLocation); ?>&unit=F" class="<?php echo $temperatureUnit === 'F' ? 'active' : ''; ?>">°F</a>
                </div>
                <div style="display: flex; align-items: center;">
                    <span class="status-indicator" style="background: #2ecc71; color: #2ecc71;"></span>
                    <small style="color: #666; font-weight: 600;">SYSTEM LIVE</small>
                </div>
            </div>
        </header>

        <div class="weather-section">
            <div class="stat-card" style="text-align: left;">
                <form class="weather-search-bar" method="GET" action="">
                    <input type="text" name="search_location" placeholder="Search location..." value="<?php echo htmlspecialchars($searchLocation); ?>">
                    <input type="hidden" name="unit" value="<?php echo $temperatureUnit; ?>">
                    <button type="submit">Go</button>
                </form>

                <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 15px;">
                    <div>
                        <h3 style="margin:0; font-size: 1.1rem;">Current Conditions</h3>
                        <p style="margin: 2px 0; color: #666; font-size: 0.85rem;"><?php echo htmlspecialchars($searchLocation); ?></p>
                    </div>
                    <span style="font-size: 2.5rem;"><?php echo $weatherCurrent['icon']; ?></span>
                </div>

                <div style="margin: 15px 0 5px 0; display: flex; align-items: baseline; gap: 8px;">
                    <span style="font-size: 3rem; font-weight: 800; color: #2c3e50;"><?php echo $weatherCurrent['temp']; ?>°</span>
                    <span style="color: #7f8c8d; font-weight: 600; text-transform: capitalize;"><?php echo $weatherCurrent['desc']; ?></span>
                </div>
                <p style="font-size: 0.85rem; color: #666; margin: 0 0 15px 0;">Feels like <?php echo $weatherCurrent['feels_like']; ?>°</p>

                <div class="weather-param-grid">
                    <div class="param-item"><span>Wind</span><strong><?php echo $weatherCurrent['wind']; ?></strong></div>
                    <div class="param-item"><span>Humidity</span><strong><?php echo $weatherCurrent['humidity']; ?>%</strong></div>
                    <div class="param-item"><span>Visibility</span><strong><?php echo $weatherCurrent['visibility']; ?></strong></div>
                    <div class="param-item"><span>Pressure</span><strong><?php echo $weatherCurrent['pressure']; ?></strong></div>
                    <div class="param-item"><span>UV Index</span><strong><?php echo $weatherCurrent['uv']; ?></strong></div>
                    <div class="param-item"><span>Dew Point</span><strong><?php echo $weatherCurrent['dew_point']; ?></strong></div>
                </div>
            </div>

            <div class="stat-card" style="display: flex; flex-direction: column; justify-content: space-between; text-align: left;">
                <h3 style="margin: 0 0 15px 0; font-size: 1.1rem; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px;">Weather Forecast</h3>
                <div style="flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
                    <?php foreach ($forecastDays as $day): ?>
                        <div class="forecast-row">
                            <span style="width: 80px; font-weight: 700; color: #333;"><?php echo $day['day']; ?></span>
                            <span style="font-size: 1.3rem; width: 30px; text-align: center;"><?php echo $day['icon']; ?></span>
                            <span style="width: 50px; font-weight: 800; text-align: right; color: #2e7d32;"><?php echo $day['temp']; ?>°</span>
                            <span style="flex: 1; text-align: right; font-size: 0.85rem; color: #7f8c8d; text-transform: capitalize; padding-left: 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo $day['desc']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <section class="control-banner" style="text-align: left;">
            <div>
                <div class="badge-mode">System Mode: <?php echo $mode; ?></div>
                <h2 style="font-size: 2rem; margin: 15px 0 5px 0;">
                    Pump is <?php echo ($pump == 1) ? 'Active' : 'Idle'; ?>
                </h2>
                <p style="opacity: 0.8; margin: 0;">Irrigation logic based on current moisture levels.</p>
            </div>
            <a href="motor_control.php" style="background: white; color: #1a5d1a; padding: 15px 30px; border-radius: 12px; font-weight: 800; text-decoration: none; transition: 0.3s; white-space: nowrap;">
                Control Center
            </a>
        </section>

        <div class="dashboard-grid">
            
            <div class="stat-card" style="text-align: left;">
                <div>
                    <p style="color: #666; font-size: 0.9rem; font-weight: 600; margin: 0 0 10px 0;">Soil Moisture</p>
                    <div style="display: flex; align-items: baseline; gap: 10px; margin: 10px 0;">
                        <span style="font-size: 2.5rem; font-weight: 800; line-height: 1;"><?php echo round($latest['moisture'], 1); ?>%</span>
                        <span class="trend <?php echo ($moistureDiff >= 0) ? 'up' : 'down'; ?>">
                            <?php echo ($moistureDiff >= 0) ? '▲' : '▼'; ?> <?php echo abs(round($moistureDiff, 1)); ?>%
                        </span>
                    </div>
                    <div style="width: 100%; background: #eee; height: 8px; border-radius: 10px; margin-bottom: 5px;">
                        <div style="width: <?php echo $latest['moisture']; ?>%; background: #2e7d32; height: 100%; border-radius: 10px;"></div>
                    </div>
                </div>
                
                <div class="analytics-summary-box" style="border-left-color: <?php echo $latest['moisture'] < 30 ? '#e74c3c' : '#2ecc71'; ?>;">
                    <?php if($latest['moisture'] < 30): ?>
                        <strong>Deficit:</strong> Substrate hydration has dropped below the 30% safe threshold. Irrigation trigger required to minimize localized root-wilt variables.
                    <?php else: ?>
                        <strong>Stable:</strong> Content levels are holding well. Capillary absorption curves are keeping root structures healthy without over-saturating.
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card" style="text-align: left;">
                <div>
                    <p style="color: #666; font-size: 0.9rem; font-weight: 600; margin: 0 0 10px 0;">Soil Temperature</p>
                    <div style="display: flex; align-items: baseline; gap: 10px; margin: 10px 0;">
                        <span style="font-size: 2.5rem; font-weight: 800; color: #f39c12; line-height: 1;">
                            <?php 
                                $displayTemp = ($temperatureUnit === 'F') ? round(($latest['temp'] * 9/5) + 32) : round($latest['temp'], 1);
                                echo $displayTemp . '°' . $temperatureUnit;
                            ?>
                        </span>
                        <span class="trend <?php echo ($tempDiff <= 0) ? 'up' : 'down'; ?>" style="color: #999;">
                            <?php echo ($tempDiff == 0) ? 'Stable' : (($tempDiff > 0) ? '▲ Warm' : '▼ Cool'); ?>
                        </span>
                    </div>
                    <p style="font-size: 0.8rem; color: #999; margin: 0;">Optimal: 20°C - 30°C</p>
                </div>

                <div class="analytics-summary-box" style="border-left-color: #f39c12;">
                    <?php if($latest['temp'] > 30): ?>
                        <strong>Alert:</strong> Higher soil heat increases water loss from evaporation. Watch your morning moisture trends closely.
                    <?php else: ?>
                        <strong>Optimal:</strong> Thermal conditions support helpful underground bacteria and standard chemical nutrient intake profiles.
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card" style="text-align: left;">
                <div>
                    <p style="color: #666; font-size: 0.9rem; font-weight: 600; margin: 0 0 10px 0;">Soil Acidity (pH)</p>
                    <div style="display: flex; align-items: baseline; gap: 10px; margin: 10px 0;">
                        <span style="font-size: 2.5rem; font-weight: 800; color: #9b59b6; line-height: 1;"><?php echo round($latest['ph'], 1); ?></span>
                    </div>
                    <p style="font-size: 0.8rem; color: <?php echo ($latest['ph'] < 6 || $latest['ph'] > 7.5) ? '#e74c3c' : '#2ecc71'; ?>; font-weight: 700; margin: 0;">
                        <?php echo ($latest['ph'] < 6) ? 'Too Acidic' : (($latest['ph'] > 7.5) ? 'Too Alkaline' : 'Neutral Range'); ?>
                    </p>
                </div>

                <div class="analytics-summary-box" style="border-left-color: #9b59b6;">
                    <?php if($latest['ph'] < 6 || $latest['ph'] > 7.5): ?>
                        <strong>Imbalance:</strong> Out of optimal bands. Nutrient availability might slow down due to chemical locking. Plan buffering adjustments.
                    <?php else: ?>
                        <strong>Balanced:</strong> Soil chemical properties allow normal, direct absorption of standard organic fertilizers.
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="chart-container" style="text-align: left;">
            <h3 style="margin-bottom: 25px;">Moisture Dynamics (Live)</h3>
            <div style="height: 350px;">
                <canvas id="mainChart"></canvas>
            </div>
            
            <div class="analytics-summary-box" style="border-left-width: 4px; border-left-color: #2e7d32; margin-top: 20px; background: #fbfdfb; padding: 15px;">
                <strong>Graph Summary &amp; Trend Insights:</strong> 
                The 15-point running field line shows your field's water cycle details over time. 
                <?php if($moistureDiff > 0 && $pump == 1): ?>
                    A clear upward curve confirms the active irrigation pump is successfully soaking into the soil bed.
                <?php elseif($moistureDiff < -2): ?>
                    A downward slope indicates normal soil drying or root drinking. Ensure natural drying rates align safely with current regional solar temperatures.
                <?php else: ?>
                    The flat, steady line shows well-balanced soil moisture retention, giving the field a secure buffer against midday sun stress.
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="moistureModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-icon">⚠️</div>
            <h2>Irrigation Required</h2>
            <p>Soil moisture at <strong><?php echo round($latest['moisture'], 1); ?>%</strong> is below optimal limits.</p>
            <div class="modal-actions">
                <a href="motor_control.php" class="btn-activate">Activate Pump Now</a>
                <button onclick="closeMoistureModal()" class="btn-dismiss">Dismiss</button>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('mainChart').getContext('2d');
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

        // Dynamic auto-refresh logic: pauses execution sequence if user focuses search bar field
        const searchInput = document.querySelector('input[name="search_location"]');
        setTimeout(() => { 
            if(document.activeElement !== searchInput) {
                location.reload(); 
            }
        }, 30000);

        window.addEventListener('DOMContentLoaded', (event) => {
            const currentMoisture = <?php echo $latest['moisture']; ?>;
            const pumpStatus = <?php echo $pump; ?>;
            if (currentMoisture < 30 && pumpStatus == 0) {
                document.getElementById('moistureModal').style.display = 'flex';
            }
        });
        function closeMoistureModal() { document.getElementById('moistureModal').style.display = 'none'; }
    </script>
</body>
</html>
