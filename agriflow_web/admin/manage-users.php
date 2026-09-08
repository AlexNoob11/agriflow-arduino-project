<?php
session_start();

// 1. Security Gate
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?status=unauthorized");
    exit();
}

include 'db_connect.php';

// 2. Handle User Deletion
if (isset($_GET['delete_id'])) {
    $deleteId = $_GET['delete_id'];
    // Prevent admin from deleting themselves
    if ($deleteId != $_SESSION['user_id']) {
        $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $deleteStmt->execute([$deleteId]);
        header("Location: manage-users.php?status=deleted");
        exit();
    }
}

// 3. Fetch Users (with optional search)
$search = $_GET['search'] ?? '';
try {
    if (!empty($search)) {
        $stmt = $pdo->prepare("SELECT id, fullname, email, created_at FROM users WHERE fullname LIKE ? OR email LIKE ? ORDER BY created_at DESC");
        $stmt->execute(["%$search%", "%$search%"]);
    } else {
        $stmt = $pdo->query("SELECT id, fullname, email, created_at FROM users ORDER BY created_at DESC");
    }
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agriflow Admin | Manage Users</title>
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --admin-blue: #1565c0;
            --danger: #e74c3c;
        }

        .user-container {
            background: white;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        }

        .search-bar {
            width: 100%;
            padding: 12px 20px;
            border: 1px solid #e0e6e0;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 1rem;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
        }

        .user-table th {
            text-align: left;
            padding: 15px;
            color: #888;
            font-size: 0.85rem;
            border-bottom: 2px solid #f4f7f6;
        }

        .user-table td {
            padding: 15px;
            border-bottom: 1px solid #f4f7f6;
            font-size: 0.95rem;
        }

        .user-table tr:hover {
            background-color: #f8fbff;
        }

        .action-btn {
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-delete {
            background: #fff5f5;
            color: var(--danger);
            border: 1px solid #ffdada;
        }

        .btn-delete:hover {
            background: var(--danger);
            color: white;
        }

        .status-msg {
            padding: 10px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: inline-block;
            font-size: 0.9rem;
        }
        .status-deleted { background: #fee2e2; color: #b91c1c; }
    </style>
</head>
<body>

    <?php include('sidebar.php'); ?>

    <main>
        <header>
            <div>
                <h1 style="color: var(--admin-blue); margin: 0;">User Directory</h1>
                <p style="color: #666;">View and manage registered operators.</p>
            </div>
            <a href="register.php" class="btn-action">+ Add New User</a>
        </header>

        <?php if (isset($_GET['status']) && $_GET['status'] == 'deleted'): ?>
            <div class="status-msg status-deleted">User account has been successfully removed.</div>
        <?php endif; ?>

        <div class="user-container">
            <form action="manage-users.php" method="GET">
                <input type="text" name="search" class="search-bar" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
            </form>

            <table class="user-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Email Address</th>
                        <th>Registration Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><span style="color: #999; font-weight: bold;">#<?php echo $user['id']; ?></span></td>
                            <td><strong><?php echo htmlspecialchars($user['fullname']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo date("M d, Y", strtotime($user['created_at'])); ?></td>
                            <td>
                                <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="action-btn btn-edit">Edit</a>
                                
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <a href="manage-users.php?delete_id=<?php echo $user['id']; ?>" 
                                    class="action-btn btn-delete" 
                                    onclick="return confirm('Delete user?')">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: #999;">No users found matching your search.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

</body>
</html>