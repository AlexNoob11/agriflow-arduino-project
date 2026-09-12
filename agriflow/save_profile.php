<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $plant_key = strtolower(trim($_POST['plant_key'])); // Convert to lowercase (e.g. "Tomato" -> "tomato")
    $plant_name = trim($_POST['plant_name']);
    $low = (float)$_POST['low'];
    $high = (float)$_POST['high'];

    $plant_key = str_replace(' ', '_', $plant_key);

    try {
        if ($id) {
            $sql = "UPDATE plant_profiles 
                    SET plant_key = ?, plant_name = ?, low_threshold = ?, high_threshold = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$plant_key, $plant_name, $low, $high, $id]);
        } else {
            $sql = "INSERT INTO plant_profiles (plant_key, plant_name, low_threshold, high_threshold) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$plant_key, $plant_name, $low, $high]);
        }

        header("Location: threshold_settings.php?status=success");
        exit();

    } catch (PDOException $e) {
        die("Error saving to database: " . $e->getMessage());
    }
} else {
    header("Location: threshold_settings.php");
    exit();
}
