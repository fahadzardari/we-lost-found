<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('auth/login.php');
}

$error = '';
$success = '';

// Process actions (ban/unban)
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $userId = $_GET['id'];
    $action = $_GET['action'];
    
    // Prevent admin from banning themselves
    if ($userId == $_SESSION['user_id']) {
        $error = 'You cannot modify your own admin account!';
    } else {
        try {
            if ($action == 'ban') {
                $stmt = $conn->prepare("UPDATE users SET status = 'banned' WHERE id = ?");
                $stmt->execute([$userId]);
                $success = 'User has been banned successfully';
            } elseif ($action == 'unban') {
                $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $stmt->execute([$userId]);
                $success = 'User has been unbanned successfully';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get users with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Get total users count
    $stmt = $conn->query("SELECT COUNT(*) FROM users");
    $totalUsers = $stmt->fetchColumn();
    $totalPages = ceil($totalUsers / $limit);
    
    // Search functionality
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $searchCondition = '';
    $params = [];
    
    if (!empty($search)) {
        $searchCondition = "WHERE name LIKE ? OR email LIKE ?";
        $params = ["%$search%", "%$search%"];
    }
    
    // Filter by status
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
    if (!empty($status)) {
        $searchCondition = empty($searchCondition) ? "WHERE status = ?" : $searchCondition . " AND status = ?";
        $params[] = $status;
    }
    
    // Get users
    $query = "
        SELECT u.*, 
               (SELECT COUNT(*) FROM items WHERE user_id = u.id) as item_count
        FROM users u
        $searchCondition
        ORDER BY u.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $users = [];
    $totalPages = 0;
}

// Include header
include_once '../../includes/header.php';

// Include admin sidebar
include_once '../../includes/sidebar.php';
?>

<!-- Admin Users Management Content -->
<div class="md:ml-64 pt-6 px-4 pb-4 bg-green-500">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Manage Users</h1>
        <p class="text-gray-600">View and manage user accounts.</p>
    </div>
    
    <?php if (!empty($success)): ?>
        <?php echo showSuccess($success); ?>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <?php echo showError($error); ?>
    <?php endif; ?>
    
    <!-- Search and Filter -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <form method="GET" class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search Users</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                       placeholder="Name or email">
            </div>
            <div class="w-full md:w-40">
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <option value="">All</option>
                    <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="banned" <?php echo $status == 'banned' ? 'selected' : ''; ?>>Banned</option>
                </select>
            </div>
            <div class="self-end">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    <i class="fas fa-search mr-1"></i> Search
                </button>
            </div>
        </form>
    </div>
    
    <!-- Users Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            User
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Email
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Role
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Reports
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Joined
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span class="text-blue-600 font-bold">
                                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo $user['name']; ?>
                                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                    <span class="text-xs text-gray-500">(You)</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-sm text-gray-500">ID: <?php echo $user['id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $user['email']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $user['role'] == 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $user['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $user['item_count']; ?> items
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <?php if ($user['status'] == 'active'): ?>
                                            <a href="?action=ban&id=<?php echo $user['id']; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" 
                                               class="text-red-600 hover:text-red-900"
                                               onclick="return confirm('Are you sure you want to ban this user?')">
                                                Ban User
                                            </a>
                                        <?php else: ?>
                                            <a href="?action=unban&id=<?php echo $user['id']; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" 
                                               class="text-green-600 hover:text-green-900"
                                               onclick="return confirm('Are you sure you want to unban this user?')">
                                                Unban User
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">Admin Account</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                No users found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="flex justify-center">
            <nav class="inline-flex rounded-md shadow">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" 
                       class="px-4 py-2 bg-white text-gray-700 border border-gray-300 rounded-l-md hover:bg-gray-50">
                        Previous
                    </a>
                <?php else: ?>
                    <span class="px-4 py-2 bg-gray-100 text-gray-400 border border-gray-300 rounded-l-md">
                        Previous
                    </span>
                <?php endif; ?>
                
                <?php 
                $startPage = max(1, $page - 2);
                $endPage = min($startPage + 4, $totalPages);
                if ($endPage - $startPage < 4) {
                    $startPage = max(1, $endPage - 4);
                }
                
                for ($i = $startPage; $i <= $endPage; $i++): 
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" 
                       class="px-4 py-2 bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 <?php echo $i == $page ? 'bg-blue-50 text-blue-600 font-bold' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" 
                       class="px-4 py-2 bg-white text-gray-700 border border-gray-300 rounded-r-md hover:bg-gray-50">
                        Next
                    </a>
                <?php else: ?>
                    <span class="px-4 py-2 bg-gray-100 text-gray-400 border border-gray-300 rounded-r-md">
                        Next
                    </span>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php include_once '../../includes/footer.php'; ?>