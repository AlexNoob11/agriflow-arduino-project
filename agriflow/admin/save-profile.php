<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?status=unauthorized");
    exit();
}

require_once '../db_connect.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $plant_key = strtolower(trim($_POST['plant_key'])); 
    $plant_name = trim($_POST['plant_name']);
    $low = (float)$_POST['low_threshold']; 
    $high = (float)$_POST['high_threshold'];

    $plant_key = preg_replace('/[^a-z0-t1-9_]/', '', str_replace(' ', '_', $plant_key));

    try {
        if ($id) {
            $sql = "UPDATE plant_profiles 
                    SET plant_key = ?, plant_name = ?, low_threshold = ?, high_threshold = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$plant_key, $plant_name, $low, $high, $id]);
            $status = "updated";
        } else {
            $sql = "INSERT INTO plant_profiles (plant_key, plant_name, low_threshold, high_threshold) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$plant_key, $plant_name, $low, $high]);
            $status = "created";
        }

        header("Location: threshold-settings.php?status=" . $status);
        exit();

    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        header("Location: threshold-settings.php?status=error&msg=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: threshold-settings.php");
    exit();
}
