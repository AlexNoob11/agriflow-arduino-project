<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'db_connect.php';

$message = "";
$error = "";

// 1. Load User Data
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user) {
        die("User not found.");
    }
}

// 2. Handle Update Logic
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $new_password = $_POST['password'];

    try {
        if (!empty($new_password)) {
            // Update with password
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, password = ? WHERE id = ?");
            $updateStmt->execute([$fullname, $email, $hashed, $id]);
        } else {
            // Update without changing password
            $updateStmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ? WHERE id = ?");
            $updateStmt->execute([$fullname, $email, $id]);
        }
        header("Location: manage-users.php?status=updated");
        exit();
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User | Agriflow Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-card {
            background: white;
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 1rem;
        }
        .btn-save {
            background: var(--admin-blue, #1565c0);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            cursor: pointer;
            width: 100%;
            font-weight: 600;
        }
        .hint { font-size: 0.8rem; color: #888; margin-top: 5px; }
    </style>
</head>
<body>
    <?php include('sidebar.php'); ?>
    <main>
        <div class="form-card">
            <h2>Edit User Profile</h2>
            <p style="color: #666; margin-bottom: 25px;">Modify account details for #<?php echo $user['id']; ?></p>

            <?php if($error): ?> <div style="color: red; margin-bottom: 15px;"><?php echo $error; ?></div> <?php endif; ?>

            <form action="edit-user.php" method="POST">
                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="password" placeholder="Leave blank to keep current">
                    <p class="hint">Only fill this out if you want to reset their password.</p>
                </div>

                <button type="submit" class="btn-save">Update User Data</button>
                <a href="manage-users.php" style="display: block; text-align: center; margin-top: 15px; color: #888; text-decoration: none;">Cancel</a>
            </form>
        </div>
    </main>
</body>
</html>