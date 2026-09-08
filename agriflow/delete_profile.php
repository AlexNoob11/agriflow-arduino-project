<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: threshold_settings.php");
    exit();
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("DELETE FROM plant_profiles WHERE id = ?");
$stmt->execute([$id]);

header("Location: thresholds.php?status=deleted");
exit();