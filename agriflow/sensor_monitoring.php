<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'db_connect.php';

try {
    $stmt = $pdo->query("SELECT id, moisture, temp, ph, timestamp FROM sensor_logs ORDER BY timestamp DESC LIMIT 1");
    $data = $stmt->fetch();

    // Fallback values if database is empty
    $moisture = $data['moisture'] ?? 0;
    $temp     = $data['temp'] ?? 0;
    $ph       = $data['ph'] ?? 0;
    
    // Check if timestamp exists before formatting
    $last_update = isset($data['timestamp']) ? date("H:i:s", strtotime($data['timestamp'])) : "No Data";

    // Weather Forecast Engine Parameters
    $searchLocation = isset($_GET['search_location']) ? trim($_GET['search_location']) : 'Himamaylan,PH';
    $temperatureUnit = isset($_GET['unit']) && $_GET['unit'] === 'F' ? 'F' : 'C';

    $apiKey = "968da3cb56d320a14e941bf4cf230256";
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
        ['day' => 'Tue', 'temp' => 32, 'desc' => 'overcast clouds', 'icon' => '☁️']
    ];

    // LIVE REQUEST BLOCK 1: Current Weather Data
    $currentUrl = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($searchLocation) . "&units=" . $apiUnits . "&appid=" . $apiKey;
    
    $context = stream_context_create(['http' => ['ignore_errors' => true]]);
    $currentResponse = @file_get_contents($currentUrl, false, $context);
    
    // UI Icon Mapping Dictionary
    $iconMap = ['01' => '☀️', '02' => '⛅', '03' => '☁️', '04' => '☁️', '09' => '🌧️', '10' => '🌦️', '11' => '⛈️', '13' => '❄️', '50' => '🌫️'];

    if ($currentResponse) {
        $currentData = json_decode($currentResponse, true);
        if (isset($currentData['main'])) {
            $windUnit = ($temperatureUnit === 'F') ? 'mph' : 'm/s';
            $rawIcon = substr($currentData['weather'][0]['icon'] ?? '01', 0, 2);
            $uiIcon = $iconMap[$rawIcon] ?? '☀️';

            $weatherCurrent = [
                'temp' => round($currentData['main']['temp']),
                'desc' => $currentData['weather'][0]['description'] ?? 'Clear Sky',
                'icon' => $uiIcon,
                'feels_like' => round($currentData['main']['feels_like']),
                'wind' => round($currentData['wind']['speed']) . " " . $windUnit,
                'humidity' => $currentData['main']['humidity'],
                'visibility' => (isset($currentData['visibility']) ? round($currentData['visibility'] / 1000, 1) . 'km' : 'N/A'),
                'pressure' => $currentData['main']['pressure'] . ' hPa',
                'uv' => 'N/A', 
                'dew_point' => 'N/A'
            ];
        }
    }

    // LIVE REQUEST BLOCK 2: 5-Day Forecast Data 
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
                if(count($processedForecast) >= 4) break;
            }
            if(!empty($processedForecast)) {
                $forecastDays = $processedForecast;
            }
        }
    }

    if (!isset($forecastData['list']) && $temperatureUnit === 'F') {
        foreach ($forecastDays as &$fd) {
            $fd['temp'] = round(($fd['temp'] * 9/5) + 32);
        }
    }

} catch (Exception $e) {
    die("Error fetching sensor data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agriflow | Sensor Monitoring</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .monitoring-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .sensor-card {
            background: white;
            padding: 2rem;
            border-radius: 24px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.02);
            position: relative;
            overflow: hidden;
            border: 1px solid #f6f6f6;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .gauge-container {
            position: relative;
            width: 160px;
            height: 160px;
            margin: 0 auto 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .gauge-svg { transform: rotate(-90deg); width: 100%; height: 100%; }
        .gauge-bg { fill: none; stroke: #edf2ed; stroke-width: 10; }
        .gauge-fill { 
            fill: none; stroke-width: 10; 
            stroke-linecap: round; transition: stroke-dasharray 1s ease;
        }

        .gauge-value {
            position: absolute;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text, #333);
        }

        .sensor-label { font-size: 1.1rem; font-weight: 700; color: var(--secondary, #555); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;}
        .status-dot { height: 8px; width: 8px; background: #4caf50; border-radius: 50%; display: inline-block; margin-right: 5px; }
        
        .data-meta { 
            display: flex; justify-content: space-between; 
            margin-top: 1.2rem; padding-top: 1.2rem; border-top: 1px solid #f0f0f0;
            font-size: 0.85rem; color: #999;
        }

        .refresh-tag { font-size: 0.75rem; background: #f0f0f0; padding: 4px 10px; border-radius: 20px; font-weight: 600; }

        /* Integrated Analytical Summaries Typography */
        .sensor-summary-box {
            background: #fafafa;
            border-radius: 12px;
            padding: 10px 14px;
            margin-top: 12px;
            font-size: 0.85rem;
            line-height: 1.4;
            color: #666;
            text-align: left;
            border-left: 3px solid #ccc;
        }

        /* Weather Module CSS Configurations */
        .weather-section {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 20px;
            margin-top: 1.5rem;
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
    </style>
</head>
<body>

    <?php include('sidebar/sidebar.php'); ?>

    <main style="padding: 20px;">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <div>
                <h1>Sensor Monitoring</h1>
                <p style="color: var(--secondary, #666);">Live telemetry from ESP8266 Field Node</p>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <div class="unit-toggle">
                    <a href="?search_location=<?php echo urlencode($searchLocation); ?>&unit=C" class="<?php echo $temperatureUnit === 'C' ? 'active' : ''; ?>">°C</a>
                    <a href="?search_location=<?php echo urlencode($searchLocation); ?>&unit=F" class="<?php echo $temperatureUnit === 'F' ? 'active' : ''; ?>">°F</a>
                </div>
                <div class="refresh-tag">Last Synced: <?php echo $last_update; ?></div>
            </div>
        </header>

        <div class="weather-section">
            <div class="sensor-card" style="text-align: left;">
                <form class="weather-search-bar" method="GET" action="">
                    <input type="text" name="search_location" placeholder="Search location..." value="<?php echo htmlspecialchars($searchLocation); ?>">
                    <input type="hidden" name="unit" value="<?php echo $temperatureUnit; ?>">
                    <button type="submit">Go</button>
                </form>

                <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px;">
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
                </div>
            </div>

            <div class="sensor-card" style="display: flex; flex-direction: column; justify-content: space-between; text-align: left;">
                <h3 style="margin: 0 0 15px 0; font-size: 1.1rem; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px;">Short-term Atmospheric Outlook</h3>
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

        <div class="monitoring-grid">
            
            <div class="sensor-card">
                <div>
                    <div class="sensor-label">Soil Moisture</div>
                    <div class="gauge-container">
                        <svg class="gauge-svg" viewBox="0 0 100 100">
                            <circle class="gauge-bg" cx="50" cy="50" r="45"></circle>
                            <circle class="gauge-fill" cx="50" cy="50" r="45" 
                                    style="stroke: #4caf50; stroke-dasharray: <?php echo ($moisture / 100) * 283; ?>, 283;"></circle>
                        </svg>
                        <div class="gauge-value"><?php echo round($moisture, 1); ?>%</div>
                    </div>
                    <div style="color: <?php echo $moisture < 35 ? '#d32f2f' : '#2e7d32'; ?>; font-weight: 700; font-size: 1.05rem;">
                        <?php echo $moisture < 35 ? '⚠️ Needs Water' : '✅ Optimal Hydration'; ?>
                    </div>
                </div>

                <div class="sensor-summary-box" style="border-left-color: <?php echo $moisture < 35 ? '#d32f2f' : '#4caf50'; ?>;">
                    <?php if($moisture < 35): ?>
                        <strong>Critical alert:</strong> Moisture is beneath the 35% safety floor. Soil dryness risk is climbing; irrigation lines should run immediately to safeguard crop roots from drying out.
                    <?php else: ?>
                        <strong>Healthy status:</strong> Earth hydration is balanced well within optimal physiological retention parameters, mitigating excessive root saturation while maintaining transpiration.
                    <?php endif; ?>
                </div>

                <div class="data-meta">
                    <span>Range: 0-100%</span>
                    <span>Modbus RS485</span>
                </div>
            </div>

            <div class="sensor-card">
                <div>
                    <div class="sensor-label">Soil Temperature</div>
                    <div style="font-size: 4rem; margin: 1rem 0; font-weight: 800; color: #f57c00; line-height: 1;">
                        <?php echo round($temp, 1); ?><span style="font-size: 1.5rem; vertical-align: top; font-weight: 500;">°C</span>
                    </div>
                    <p style="color: <?php echo $temp > 30 ? '#e65100' : '#455a64'; ?>; font-weight: 700;">
                        <?php echo $temp > 30 ? 'High Thermal Stress' : 'Stable Temperature'; ?>
                    </p>
                </div>

                <div class="sensor-summary-box" style="border-left-color: <?php echo $temp > 30 ? '#f57c00' : '#cfd8dc'; ?>;">
                    <?php if($temp > 30): ?>
                        <strong>Thermal Warning:</strong> Elevated substrate energy can speed up organic fluid loss and strain roots. Monitor evapotranspiration curves closely if open sky solar trends remain high.
                    <?php else: ?>
                        <strong>Thermal Summary:</strong> Root zone conditions are stable. Subsurface ambient storage falls in healthy microbiological bands, fostering micro-nutrient breakdown.
                    <?php endif; ?>
                </div>

                <div class="data-meta">
                    <span>Range: -40 to 80°C</span>
                    <span>Modbus RS485</span>
                </div>
            </div>

            <div class="sensor-card">
                <div>
                    <div class="sensor-label">Soil pH Level</div>
                    <div class="gauge-container">
                        <svg class="gauge-svg" viewBox="0 0 100 100">
                            <circle class="gauge-bg" cx="50" cy="50" r="45" style="stroke: #f3e5f5;"></circle>
                            <circle class="gauge-fill" cx="50" cy="50" r="45" 
                                    style="stroke: #9c27b0; stroke-dasharray: <?php echo ($ph / 14) * 283; ?>, 283;"></circle>
                        </svg>
                        <div class="gauge-value" style="color: #9c27b0;"><?php echo round($ph, 1); ?></div>
                    </div>
                    <p style="font-weight: 700; color: #9c27b0;">
                        <?php 
                            if($ph < 6) echo "Acidic Soil";
                            elseif($ph > 7.5) echo "Alkaline Soil";
                            else echo "Neutral Soil";
                        ?>
                    </p>
                </div>

                <div class="sensor-summary-box" style="border-left-color: #9c27b0;">
                    <?php if($ph < 6): ?>
                        <strong>Acidity Summary:</strong> Current reading trends low. Acidic chemistry blockages might reduce nitrogen and phosphorus absorption profiles. Plan corrective lime supplements if required.
                    <?php elseif($ph > 7.5): ?>
                        <strong>Alkalinity Summary:</strong> High alkaline saturation profile verified. Micronutrients (Iron/Manganese) risk reduced solubility. Track buffering levels during next chemical feed.
                    <?php else: ?>
                        <strong>Optimal Chemistry:</strong> Excellent balanced chemistry profile. Soil matrix matches standard macro-fertilizer solubility requirements perfectly for efficient chemical transfer.
                    <?php endif; ?>
                </div>

                <div class="data-meta">
                    <span>Range: 0-14 pH</span>
                    <span>Modbus RS485</span>
                </div>
            </div>
        </div>

        <div class="sensor-card" style="margin-top: 2rem; text-align: left; padding: 1.5rem;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center;">
                    <span class="status-dot" style="background: <?php echo (time() - strtotime($data['timestamp'] ?? '') < 120) ? '#4caf50' : '#f44336'; ?>;"></span>
                    <b style="font-size: 0.9rem;">
                        Hardware Status: <?php echo (time() - strtotime($data['timestamp'] ?? '') < 120) ? 'Online' : 'Offline (Check Hardware)'; ?>
                    </b>
                </div>
                <span style="font-size: 0.8rem; color: #999;">Database ID: #<?php echo $data['id'] ?? '0'; ?></span>
            </div>
        </div>
    </main>

    <script>
        // Refresh the page every 30 seconds to fetch fresh sensor telemetry streams
        const searchInput = document.querySelector('input[name="search_location"]');
        setTimeout(function(){
            if(document.activeElement !== searchInput) {
                window.location.reload();
            }
        }, 30000);
    </script>

</body>
</html>