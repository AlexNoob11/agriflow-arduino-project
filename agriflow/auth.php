<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db   = "agriflow_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];
    $role   = $_POST['role']; // 'admin' or 'user'
    $email  = $_POST['email'];
    $password = $_POST['password'];

    // Determine which table to use
    $table = ($role === 'admin') ? 'admins' : 'users';

    if ($action == "register") {
        $fullname = $_POST['fullname'];
        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
        
        // Use Prepared Statements for security
        $stmt = $conn->prepare("INSERT INTO $table (fullname, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $fullname, $email, $hashed_pass);
        
        if ($stmt->execute()) {
            header("Location: index.php?status=registered");
            exit();
        } else {
            header("Location: index.php?status=error");
            exit();
        }
        $stmt->close();
    } 
    
    else if ($action == "login") {
        $stmt = $conn->prepare("SELECT * FROM $table WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                // Set session variables
                $_SESSION['user_id']   = $row['id'];
                $_SESSION['user_name'] = $row['fullname'];
                $_SESSION['role']      = $role; // Store role to check permissions later
                
                // Redirect based on role
                if ($role === 'admin') {
                    header("Location: admin/admin-dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            } else {
                header("Location: index.php?status=wrongpassword");
                exit();
            }
        } else {
            header("Location: index.php?status=usernotfound");
            exit();
        }
        $stmt->close();
    }
}
$conn->close();
?>