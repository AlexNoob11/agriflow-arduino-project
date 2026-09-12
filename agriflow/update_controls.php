<?php
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $value = $_POST['value'] ?? '';

    try {
        // mode toggle
        if ($type === 'mode') {
            $autoLock = ($value === 'auto') ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE device_controls SET mode = ?, auto_lock = ? WHERE id = 1");
            $stmt->execute([$value, $autoLock]);
        }

        // pump control
        elseif ($type === 'pump') {
            $newStatus = (int)$value;
            $current = $pdo->query("SELECT pump_status FROM device_controls WHERE id = 1")->fetchColumn();

            if ($newStatus != $current) {
                $stmt = $pdo->prepare("UPDATE device_controls SET pump_status = ? WHERE id = 1");
                $stmt->execute([$newStatus]);

                if ($newStatus == 1) {
                    $pdo->prepare("INSERT INTO pump_logs (start_time) VALUES (NOW())")->execute();
                } else {
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

        // plant profile
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
