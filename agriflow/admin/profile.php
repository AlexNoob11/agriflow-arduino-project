<?php
session_start();

// 1. Security Gate
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'db_connect.php';

$admin_id = $_SESSION['user_id'];
$message = "";
$error = "";

// 2. Fetch current admin data
try {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// 3. Handle Profile Update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    try {
        if (!empty($new_pass)) {
            // If changing password, ensure they match
            if ($new_pass === $confirm_pass) {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE admins SET fullname = ?, email = ?, password = ? WHERE id = ?");
                $update->execute([$fullname, $email, $hashed, $admin_id]);
                $message = "Profile and password updated successfully.";
            } else {
                $error = "New passwords do not match.";
            }
        } else {
            // Update only name and email
            $update = $pdo->prepare("UPDATE admins SET fullname = ?, email = ? WHERE id = ?");
            $update->execute([$fullname, $email, $admin_id]);
            $message = "Profile updated successfully.";
        }
        
        // Refresh local data for the form
        $_SESSION['user_name'] = $fullname; 
    } catch (PDOException $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Agriflow Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-wrapper {
            max-width: 800px;
            margin: 0 auto;
        }

        .profile-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
            display: flex;
        }

        .profile-sidebar {
            background: var(--text); /* Deep Admin Blue */
            color: white;
            padding: 40px;
            width: 300px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .avatar-circle {
            width: 100px;
            height: 100px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            border: 4px solid rgba(255,255,255,0.2);
        }

        .profile-content {
            padding: 40px;
            flex: 1;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group { margin-bottom: 20px; }
        .full-width { grid-column: span 2; }

        label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.85rem; color: #666; }
        
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 10px;
            background: #fcfcfc;
        }

        .btn-update {
            background: var(--accent);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        .alert-error { background: #ffebee; color: #c62828; }

        @media (max-width: 768px) {
            .profile-card { flex-direction: column; }
            .profile-sidebar { width: 100%; padding: 30px; }
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
        }
    </style>
</head>
<body>

    <?php include('sidebar.php'); ?>

    <main>
        <div class="profile-wrapper">
            <header style="margin-bottom: 30px;">
                <h1 style="color: var(--text);">Account Settings</h1>
                <p style="color: #666;">Manage your administrative credentials and security.</p>
            </header>

            <?php if($message): ?> <div class="alert alert-success"><?php echo $message; ?></div> <?php endif; ?>
            <?php if($error): ?> <div class="alert alert-error"><?php echo $error; ?></div> <?php endif; ?>

            <div class="profile-card">
                <div class="profile-sidebar">
                    <div class="avatar-circle">
                        <?php echo strtoupper(substr($admin['fullname'], 0, 1)); ?>
                    </div>
                    <h3><?php echo htmlspecialchars($admin['fullname']); ?></h3>
                    <p style="opacity: 0.7; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px;">
                        <?php echo $admin['access_level']; ?>
                    </p>
                    <hr style="width: 50px; margin: 20px auto; opacity: 0.2;">
                    <small style="opacity: 0.6;">Member since<br><?php echo date("F Y", strtotime($admin['created_at'])); ?></small>
                </div>

                <div class="profile-content">
                    <form action="profile.php" method="POST">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Full Name</label>
                                <input type="text" name="fullname" value="<?php echo htmlspecialchars($admin['fullname']); ?>" required>
                            </div>
                            <div class="form-group full-width">
                                <label>Email Address</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                            </div>
                            
                            <div style="grid-column: span 2; margin: 10px 0; border-top: 1px solid #eee; padding-top: 20px;">
                                <h4 style="margin: 0 0 15px 0;">Security Update</h4>
                            </div>

                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" placeholder="Leave blank to keep current">
                            </div>
                            <div class="form-group">
                                <label>Confirm Password</label>
                                <input type="password" name="confirm_password" placeholder="Repeat new password">
                            </div>
                        </div>

                        <button type="submit" class="btn-update">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

</body>
</html>