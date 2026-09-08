<?php
session_start();
include 'db_connect.php';

// Security check: Only logged-in users can save profiles
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Collect and sanitize input
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $plant_key = strtolower(trim($_POST['plant_key'])); // Convert to lowercase (e.g. "Tomato" -> "tomato")
    $plant_name = trim($_POST['plant_name']);
    $low = (float)$_POST['low'];
    $high = (float)$_POST['high'];

    // Replace spaces with underscores in the key to ensure it's a valid slug
    $plant_key = str_replace(' ', '_', $plant_key);

    try {
        if ($id) {
            // 2. UPDATE existing profile
            $sql = "UPDATE plant_profiles 
                    SET plant_key = ?, plant_name = ?, low_threshold = ?, high_threshold = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$plant_key, $plant_name, $low, $high, $id]);
        } else {
            // 3. INSERT new profile
            $sql = "INSERT INTO plant_profiles (plant_key, plant_name, low_threshold, high_threshold) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$plant_key, $plant_name, $low, $high]);
        }

        // Redirect back to threshold page with a success message
        header("Location: threshold_settings.php?status=success");
        exit();

    } catch (PDOException $e) {
        // If there is an error (like a duplicate plant_key)
        die("Error saving to database: " . $e->getMessage());
    }
} else {
    // If someone tries to access this file directly without POST
    header("Location: threshold_settings.php");
    exit();
}