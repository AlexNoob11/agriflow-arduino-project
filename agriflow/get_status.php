<?php
header('Content-Type: application/json');
include 'db_connect.php';

try {
    // 1. Get Control States
    $status = $pdo->query("SELECT mode, pump_status, selected_plant FROM device_controls WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    
    // 2. Get Latest Sensor Readings
    $latestSensor = $pdo->query("SELECT moisture, temp, ph FROM sensor_logs ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    
    // 3. Get Water Usage
    // We calculate it: (Total seconds today * Flow Rate) / 60
    // Example: 0.033 liters per second (approx 2L per minute)
    $flowRatePerSecond = 0.033; 

    // Change the waterUsage query to this:
    $waterUsage = $pdo->query("
        SELECT SUM(liters_used) as total 
        FROM pump_logs 
        WHERE DATE(start_time) = CURDATE()
    ")->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'mode' => $status['mode'] ?? 'manual',
        'pump_status' => (int)($status['pump_status'] ?? 0),
        'selected_plant' => $status['selected_plant'] ?? 'general',
        'current_moisture' => $latestSensor ? (float)$latestSensor['moisture'] : 0,
        'today_water_liters' => round((float)($waterUsage['total'] ?? 0), 2)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>