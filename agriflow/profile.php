<?php
session_start();
include('db_connect.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

try {
    $stmt = $pdo->prepare("SELECT fullname, email, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // Protect against deleted or missing database rows
    if (!$user) {
        session_destroy();
        header("Location: index.php");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET fullname = ?, email = ?, password = ? WHERE id = ?";
            $params = [$fullname, $email, $hashed_password, $user_id];
        } else {
            $update_sql = "UPDATE users SET fullname = ?, email = ? WHERE id = ?";
            $params = [$fullname, $email, $user_id];
        }

        $update_stmt = $pdo->prepare($update_sql);
        if ($update_stmt->execute($params)) {
            $message = "<div class='alert success'>Success! Your profile has been updated.</div>";
            $user['fullname'] = $fullname;
            $user['email'] = $email;
        }
    }
} catch (PDOException $e) {
    $message = ($e->getCode() == 23000) 
               ? "<div class='alert error'>This email address is already registered.</div>" 
               : "<div class='alert error'>An unexpected error occurred.</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings | Agriflow</title>
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary-bg: #f4f7f6;
            --card-bg: #ffffff;
            --accent-color: #27ae60;
            --text-main: #2c3e50;
            --text-muted: #7f8c8d;
            --border-color: #edf2f7;
        }

        body { background-color: var(--primary-bg); color: var(--text-main); font-family: 'Inter', sans-serif; }

        .profile-wrapper {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Profile Header Section */
        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }

        .avatar-circle {
            width: 80px;
            height: 80px;
            background: var(--accent-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            text-transform: uppercase;
        }

        .header-info h1 { margin: 0; font-size: 1.5rem; }
        .header-info p { margin: 5px 0 0; color: var(--text-muted); font-size: 0.9rem; }

        /* Form Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            border: 1px solid var(--border-color);
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group { margin-bottom: 1.2rem; }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #dcdfe6;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
            box-sizing: border-box;
        }

        .form-group input:focus {
            border-color: var(--accent-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1);
        }

        .btn-save {
            grid-column: span 2;
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s, background 0.3s;
            margin-top: 10px;
        }

        .btn-save:hover { background: #219150; transform: translateY(-1px); }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.9rem;
            text-align: center;
        }
        .success { background: #e6fcf5; color: #0ca678; border: 1px solid #c3fae8; }
        .error { background: #fff5f5; color: #fa5252; border: 1px solid #ffe3e3; }

        @media (max-width: 768px) {
            .settings-grid { grid-template-columns: 1fr; }
            .btn-save { grid-column: span 1; }
        }
    </style>
</head>
<body>

    <?php include('sidebar/sidebar.php'); ?>

    <main>
        <div class="profile-wrapper">
            <?php echo $message; ?>

            <div class="profile-header">
                <div class="avatar-circle">
                    <?php echo htmlspecialchars(substr($user['fullname'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="header-info">
                    <h1><?php echo htmlspecialchars($user['fullname']); ?></h1>
                    <p>Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>

            <form method="POST" action="profile.php" class="settings-grid">
                <div class="card">
                    <div class="card-title">📝 Personal Information</div>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="card">
                    <div class="card-title">🔒 Account Security</div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="password" placeholder="••••••••">
                    </div>
                    <p style="font-size: 0.8rem; color: var(--text-muted);">
                        Leave the password field blank if you do not wish to change it. Use a strong password for better security.
                    </p>
                </div>

                <button type="submit" class="btn-save">Update Profile Settings</button>
            </form>
        </div>
    </main>

</body>
</html>
