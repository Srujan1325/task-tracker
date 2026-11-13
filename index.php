<?php
require_once 'db.php';
require_once 'auth.php';

// LEVEL 3: Require login
requireLogin();

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$username = $_SESSION['username'];
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // LEVEL 3: Validate CSRF token on all POST requests
    if (!validateCSRFToken()) {
        header("Location: index.php?msg=Invalid request. Please try again.&type=error");
        exit;
    }

    if ($_POST['action'] === 'add') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'Pending';

        // Backend validation
        if ($title === '') {
            $errors[] = 'Title is required.';
        }
        if (strlen($title) > 255) {
            $errors[] = 'Title must be less than 255 characters.';
        }
        if (!in_array($status, ['Pending', 'In Progress', 'Completed'])) {
            $errors[] = 'Invalid status selected.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $title, $description, $status]);
            header("Location: index.php?msg=Task added successfully&type=success");
            exit;
        }
    }

    if ($_POST['action'] === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'Pending';

        // LEVEL 3: Authorization — verify task belongs to this user
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        if (!$stmt->fetch()) {
            header("Location: index.php?msg=Unauthorized: You cannot edit this task.&type=error");
            exit;
        }

        // Backend validation
        if ($title === '') {
            $errors[] = 'Title is required.';
        }
        if (strlen($title) > 255) {
            $errors[] = 'Title must be less than 255 characters.';
        }
        if (!in_array($status, ['Pending', 'In Progress', 'Completed'])) {
            $errors[] = 'Invalid status selected.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("UPDATE tasks SET title = ?, description = ?, status = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$title, $description, $status, $id, $userId]);
            header("Location: index.php?msg=Task updated successfully&type=success");
            exit;
        }
    }

    if ($_POST['action'] === 'status') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if (!in_array($status, ['Pending', 'In Progress', 'Completed'])) {
            header("Location: index.php?msg=Invalid status&type=error");
            exit;
        }

        // LEVEL 3: Authorization — verify task belongs to this user
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        if (!$stmt->fetch()) {
            header("Location: index.php?msg=Unauthorized: You cannot change this task.&type=error");
            exit;
        }

        $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$status, $id, $userId]);
        header("Location: index.php?msg=Status updated to $status&type=success");
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        // LEVEL 3: Authorization — verify task belongs to this user
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        if (!$stmt->fetch()) {
            header("Location: index.php?msg=Unauthorized: You cannot delete this task.&type=error");
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        header("Location: index.php?msg=Task deleted successfully&type=success");
        exit;
    }
}

// Search and Filter — only show current user's tasks (admin sees all)
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? '';

if ($userRole === 'admin') {
    $sql = "SELECT tasks.*, users.username AS owner FROM tasks JOIN users ON tasks.user_id = users.id WHERE 1=1";
} else {
    $sql = "SELECT * FROM tasks WHERE user_id = ?";
}
$params = [];

if ($userRole !== 'admin') {
    $params[] = $userId;
}

if ($search !== '') {
    $sql .= " AND title LIKE ?";
    $params[] = "%$search%";
}

if ($filter !== '' && in_array($filter, ['Pending', 'In Progress', 'Completed'])) {
    $sql .= " AND status = ?";
    $params[] = $filter;
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if editing
$editTask = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    // LEVEL 3: Only allow editing own tasks
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->execute([$editId, $userId]);
    $editTask = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editTask) {
        header("Location: index.php?msg=Unauthorized: You cannot edit this task.&type=error");
        exit;
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Tracker</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { text-align: center; margin-bottom: 5px; color: #333; }

        /* Navbar */
        .navbar { background: #343a40; color: #fff; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .navbar .user-info { font-size: 14px; }
        .navbar .user-info span { background: #007bff; padding: 2px 8px; border-radius: 10px; font-size: 12px; margin-left: 5px; }
        .navbar a { color: #fff; text-decoration: none; background: #dc3545; padding: 6px 14px; border-radius: 4px; font-size: 14px; }
        .navbar a:hover { background: #c82333; }

        /* Messages */
        .msg { padding: 10px 15px; margin-bottom: 15px; border-radius: 5px; }
        .msg.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Validation errors */
        .errors { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px 15px; margin-bottom: 15px; border-radius: 5px; }
        .errors ul { margin-left: 20px; }

        /* Form */
        .form-box { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .form-box h2 { margin-bottom: 15px; font-size: 18px; }
        .form-box label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px; }
        .form-box input[type="text"],
        .form-box textarea,
        .form-box select { width: 100%; padding: 8px 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        .form-box textarea { height: 80px; resize: vertical; }
        .btn { padding: 8px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; color: #fff; }
        .btn-add { background: #28a745; }
        .btn-edit { background: #007bff; }
        .btn-delete { background: #dc3545; }
        .btn-cancel { background: #6c757d; text-decoration: none; padding: 8px 20px; border-radius: 4px; color: #fff; font-size: 14px; display: inline-block; }

        /* Search & Filter Bar */
        .search-bar { background: #fff; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .search-bar input[type="text"] { flex: 1; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; min-width: 200px; }
        .search-bar select { padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        .search-bar button { padding: 8px 16px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .search-bar a { padding: 8px 16px; background: #6c757d; color: #fff; border-radius: 4px; text-decoration: none; font-size: 14px; }

        /* Table */
        .task-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .task-table th, .task-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        .task-table th { background: #343a40; color: #fff; }
        .task-table tr:hover { background: #f9f9f9; }
        .actions a, .actions button { margin-right: 5px; }
        .actions a { color: #007bff; text-decoration: none; }
        .no-tasks { text-align: center; padding: 20px; color: #999; background: #fff; border-radius: 8px; }

        /* Status dropdown in table */
        .status-form select { padding: 4px 8px; font-size: 12px; border: 1px solid #ccc; border-radius: 4px; }
    </style>
</head>
<body>
<div class="container">
    <!-- Navbar with user info and logout -->
    <div class="navbar">
        <div class="user-info">
            👤 <?php echo htmlspecialchars($username); ?>
            <span><?php echo htmlspecialchars($userRole); ?></span>
        </div>
        <a href="logout.php">Logout</a>
    </div>

    <h1>📋 Task Tracker</h1>
    <p style="text-align:center; color:#777; margin-bottom:20px; font-size:14px;">
        <?php if ($userRole === 'admin'): ?>
            Admin view — viewing all users' tasks
        <?php else: ?>
            Your personal tasks
        <?php endif; ?>
    </p>

    <!-- Flash Message from redirect -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="msg <?php echo htmlspecialchars($_GET['type'] ?? ''); ?>">
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- Validation Errors -->
    <?php if (!empty($errors)): ?>
        <div class="errors">
            <strong>Please fix the following:</strong>
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Add / Edit Form -->
    <div class="form-box">
        <?php if ($editTask): ?>
            <h2>Edit Task</h2>
            <form method="POST" action="index.php" onsubmit="return validateForm(this);">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?php echo $editTask['id']; ?>">
                <label>Title <span style="color:red;">*</span></label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($editTask['title']); ?>" required>
                <label>Description</label>
                <textarea name="description"><?php echo htmlspecialchars($editTask['description']); ?></textarea>
                <label>Status</label>
                <select name="status">
                    <option value="Pending" <?php echo $editTask['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="In Progress" <?php echo $editTask['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="Completed" <?php echo $editTask['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
                <button type="submit" class="btn btn-edit">Update Task</button>
                <a href="index.php" class="btn-cancel">Cancel</a>
            </form>
        <?php else: ?>
            <h2>Add New Task</h2>
            <form method="POST" action="index.php" onsubmit="return validateForm(this);">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="add">
                <label>Title <span style="color:red;">*</span></label>
                <input type="text" name="title" placeholder="Enter task title" required>
                <label>Description</label>
                <textarea name="description" placeholder="Enter task description"></textarea>
                <label>Status</label>
                <select name="status">
                    <option value="Pending">Pending</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Completed">Completed</option>
                </select>
                <button type="submit" class="btn btn-add">Add Task</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Search & Filter -->
    <div class="search-bar">
        <form method="GET" action="index.php" style="display:flex; gap:10px; width:100%; flex-wrap:wrap; align-items:center;">
            <input type="text" name="search" placeholder="🔍 Search by title..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="filter">
                <option value="">All Statuses</option>
                <option value="Pending" <?php echo $filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="In Progress" <?php echo $filter === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="Completed" <?php echo $filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
            </select>
            <button type="submit">Search</button>
            <a href="index.php">Clear</a>
        </form>
    </div>

    <!-- Task List -->
    <?php if (count($tasks) > 0): ?>
        <table class="task-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Status</th>
                    <?php if ($userRole === 'admin'): ?><th>Owner</th><?php endif; ?>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $index => $task): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($task['title']); ?></td>
                    <td><?php echo htmlspecialchars($task['description']); ?></td>
                    <td>
                        <?php if ($task['user_id'] == $userId): ?>
                        <!-- Can change status of own tasks only -->
                        <form method="POST" action="index.php" class="status-form" style="display:inline;">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                            <select name="status" onchange="this.form.submit();">
                                <option value="Pending" <?php echo $task['status'] === 'Pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                                <option value="In Progress" <?php echo $task['status'] === 'In Progress' ? 'selected' : ''; ?>>🔄 In Progress</option>
                                <option value="Completed" <?php echo $task['status'] === 'Completed' ? 'selected' : ''; ?>>✅ Completed</option>
                            </select>
                        </form>
                        <?php else: ?>
                            <?php echo htmlspecialchars($task['status']); ?>
                        <?php endif; ?>
                    </td>
                    <?php if ($userRole === 'admin'): ?>
                        <td><?php echo htmlspecialchars($task['owner'] ?? ''); ?></td>
                    <?php endif; ?>
                    <td><?php echo $task['created_at']; ?></td>
                    <td class="actions">
                        <?php if ($task['user_id'] == $userId): ?>
                            <a href="index.php?edit=<?php echo $task['id']; ?>">✏️ Edit</a>
                            <form method="POST" action="index.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                                <button type="submit" class="btn btn-delete" style="padding:2px 8px; font-size:12px;">🗑️ Delete</button>
                            </form>
                        <?php else: ?>
                            <em style="color:#999;">View only</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-tasks">
            <?php if ($search !== '' || $filter !== ''): ?>
                No tasks found matching your search/filter. <a href="index.php">Show all tasks</a>
            <?php else: ?>
                No tasks yet. Add your first task above!
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function validateForm(form) {
    var title = form.querySelector('[name="title"]').value.trim();
    if (title === '') {
        alert('Error: Title is required!');
        return false;
    }
    if (title.length > 255) {
        alert('Error: Title must be less than 255 characters.');
        return false;
    }
    return true;
}
</script>
</body>
</html>
