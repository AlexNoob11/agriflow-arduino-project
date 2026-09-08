<?php
session_start();

// Security: Only admins should be able to manually create new users from here
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'db_connect.php';

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($fullname) && !empty($email) && !empty($password)) {
        // 1. Hash the password (CRITICAL for security)
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            // 2. Prepare the SQL Statement
            $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password) VALUES (?, ?, ?)");
            
            if ($stmt->execute([$fullname, $email, $hashedPassword])) {
                $message = "User account created successfully!";
            }
        } catch (PDOException $e) {
            // Check for duplicate email (Unique Key violation)
            if ($e->getCode() == 23000) {
                $error = "Error: This email address is already registered.";
            } else {
                $error = "System Error: " . $e->getMessage();
            }
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agriflow Admin | Add User</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-container {
            background: white;
            max-width: 500px;
            margin: 40px auto;
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        }

        .form-group { margin-bottom: 20px; }
        
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: #1a3a5f; 
            font-size: 0.9rem;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 1rem;
            transition: border 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #1565c0;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        .btn-submit {
            background: #1565c0;
            color: white;
            border: none;
            width: 100%;
            padding: 15px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-submit:hover { background: #0d47a1; }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <?php include('sidebar.php'); ?>

    <main>
        <div class="form-container">
            <h2 style="color: #1a3a5f; margin-bottom: 10px;">Create New Account</h2>
            <p style="color: #666; margin-bottom: 30px;">Add a new operator to the Agriflow system.</p>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="fullname" placeholder="Enter operator's name" required>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="example@agriflow.com" required>
                </div>

                <div class="form-group">
                    <label>Temporary Password</label>
                    <input type="password" name="password" placeholder="Create a strong password" required>
                </div>

                <button type="submit" class="btn-submit">Register User</button>
            </form>

            <a href="manage-users.php" class="back-link">← Back to User Directory</a>
        </div>
    </main>

</body>
</html>