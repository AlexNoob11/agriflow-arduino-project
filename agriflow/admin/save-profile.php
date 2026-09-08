<?php
session_start();

// 1. STRICT SECURITY: Verify user is logged in AND is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?status=unauthorized");
    exit();
}

// 2. Database Connection (Adjust path if necessary)
require_once '../db_connect.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 3. Collect and sanitize input
    // Note: We use the names from your previous form: 'low_threshold' and 'high_threshold'
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $plant_key = strtolower(trim($_POST['plant_key'])); 
    $plant_name = trim($_POST['plant_name']);
    $low = (float)$_POST['low_threshold']; 
    $high = (float)$_POST['high_threshold'];

    // Clean the plant key: Replace spaces/hyphens with underscores
    $plant_key = preg_replace('/[^a-z0-t1-9_]/', '', str_replace(' ', '_', $plant_key));

    try {
        if ($id) {
            // 4. UPDATE existing profile
            $sql = "UPDATE plant_profiles 
                    SET plant_key = ?, plant_name = ?, low_threshold = ?, high_threshold = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$plant_key, $plant_name, $low, $high, $id]);
            $status = "updated";
        } else {
            // 5. INSERT new profile
            $sql = "INSERT INTO plant_profiles (plant_key, plant_name, low_threshold, high_threshold) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$plant_key, $plant_name, $low, $high]);
            $status = "created";
        }

        // Redirect back to settings page
        header("Location: threshold-settings.php?status=" . $status);
        exit();

    } catch (PDOException $e) {
        // Log error and show user-friendly message
        error_log("Database Error: " . $e->getMessage());
        header("Location: threshold-settings.php?status=error&msg=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    // Direct access prevention
    header("Location: threshold-settings.php");
    exit();
}