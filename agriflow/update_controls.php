<?php
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $value = $_POST['value'] ?? '';

    try {
        // --- 1. MODE TOGGLE ---
        if ($type === 'mode') {
            $autoLock = ($value === 'auto') ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE device_controls SET mode = ?, auto_lock = ? WHERE id = 1");
            $stmt->execute([$value, $autoLock]);
        }

        // --- 2. PUMP CONTROL (Manual Click) ---
        elseif ($type === 'pump') {
            $newStatus = (int)$value;
            $current = $pdo->query("SELECT pump_status FROM device_controls WHERE id = 1")->fetchColumn();

            if ($newStatus != $current) {
                $stmt = $pdo->prepare("UPDATE device_controls SET pump_status = ? WHERE id = 1");
                $stmt->execute([$newStatus]);

                if ($newStatus == 1) {
                    // Start Log
                    $pdo->prepare("INSERT INTO pump_logs (start_time) VALUES (NOW())")->execute();
                } else {
                    // Stop Log & Calculate Liters
                    $pdo->prepare("
                        UPDATE pump_logs 
                        SET end_time = NOW(), 
                            duration_seconds = TIMESTAMPDIFF(SECOND, start_time, NOW()),
                            liters_used = TIMESTAMPDIFF(SECOND, start_time, NOW()) * 0.033
                        WHERE end_time IS NULL 
                        ORDER BY id DESC LIMIT 1
                    ")->execute();
                }
            }
        }

        // --- 3. PLANT PROFILE ---
        elseif ($type === 'plant') {
            $stmt = $pdo->prepare("UPDATE device_controls SET selected_plant = ? WHERE id = 1");
            $stmt->execute([$value]);
        }

        echo "OK";

    } catch (Exception $e) {
        http_response_code(500);
        echo "Error: " . $e->getMessage();
    }
}
?>