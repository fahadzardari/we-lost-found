<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('auth/login.php');
}

$error = '';
$success = '';

// Process actions (mark as resolved, active, or delete)
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $itemId = $_GET['id'];
    $action = $_GET['action'];
    
    try {
        if ($action == 'mark_active') {
            $stmt = $conn->prepare("UPDATE items SET status = 'active' WHERE id = ?");
            $stmt->execute([$itemId]);
            $success = 'Item has been marked as active successfully';
        } elseif ($action == 'mark_resolved') {
            $stmt = $conn->prepare("UPDATE items SET status = 'resolved' WHERE id = ?");
            $stmt->execute([$itemId]);
            $success = 'Item has been marked as resolved successfully';
        } elseif ($action == 'mark_spam') {
            $stmt = $conn->prepare("UPDATE items SET status = 'spam' WHERE id = ?");
            $stmt->execute([$itemId]);
            $success = 'Item has been marked as spam successfully';
        } elseif ($action == 'delete') {
            // Delete images first
            $stmt = $conn->prepare("SELECT image_path FROM item_images WHERE item_id = ?");
            $stmt->execute([$itemId]);
            $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Delete actual image files
            foreach ($images as $image) {
                if (file_exists(UPLOAD_DIR . $image)) {
                    unlink(UPLOAD_DIR . $image);
                }
            }
            
            // Delete image records and item
            $stmt = $conn->prepare("DELETE FROM item_images WHERE item_id = ?");
            $stmt->execute([$itemId]);
            
            // Delete any claims for this item
            $stmt = $conn->prepare("DELETE FROM claims WHERE item_id = ?");
            $stmt->execute([$itemId]);
            
            // Delete the item
            $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
            $stmt->execute([$itemId]);
            
            $success = 'Item has been deleted successfully';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Get items with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Search and filter functionality
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
    $category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
    
    $whereConditions = [];
    $params = [];
    
    if (!empty($search)) {
        $whereConditions[] = "(i.title LIKE ? OR i.description LIKE ? OR i.location LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($type)) {
        $whereConditions[] = "i.type = ?";
        $params[] = $type;
    }
    
    if (!empty($status)) {
        $whereConditions[] = "i.status = ?";
        $params[] = $status;
    }
    
    if (!empty($category)) {
        $whereConditions[] = "i.category = ?";
        $params[] = $category;
    }
    
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) FROM items i $whereClause";
    $stmt = $conn->prepare($countQuery);
    $stmt->execute($params);
    $totalItems = $stmt->fetchColumn();
    $totalPages = ceil($totalItems / $limit);
    
    // Get items
    $query = "
        SELECT i.*, u.name as user_name, u.email as user_email,
               (SELECT image_path FROM item_images WHERE item_id = i.id LIMIT 1) as image,
               (SELECT COUNT(*) FROM claims WHERE item_id = i.id AND status = 'pending') as pending_claims
        FROM items i
        JOIN users u ON i.user_id = u.id
        $whereClause
        ORDER BY i.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get categories for filter
    $stmt = $conn->query("SELECT DISTINCT category FROM items ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $items = [];
    $totalPages = 0;
    $categories = [];
}

// Include header
include_once '../../includes/header.php';

// Include admin sidebar
include_once '../../includes/sidebar.php';
?>

<!-- Admin Items Management Content -->
<div class="md:ml-64 pt-6 px-4 pb-4">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Manage Items</h1>
        <p class="text-gray-600">View, filter, and manage all lost and found items.</p>
    </div>
    
    <?php if (!empty($success)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
            <p><?php echo $success; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p><?php echo $error; ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Search and Filter -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                           placeholder="Title, description or location">
                </div>
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select id="type" name="type" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="">All Types</option>
                        <option value="lost" <?php echo $type == 'lost' ? 'selected' : ''; ?>>Lost Items</option>
                        <option value="found" <?php echo $type == 'found' ? 'selected' : ''; ?>>Found Items</option>
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="resolved" <?php echo $status == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="spam" <?php echo $status == 'spam' ? 'selected' : ''; ?>>Spam</option>
                    </select>
                </div>
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select id="category" name="category" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>" <?php echo $category == $cat ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex justify-end">
                <a href="manage-items.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 mr-2">
                    Reset
                </a>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    <i class="fas fa-search mr-1"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>
    
    <!-- Items Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Item
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Type
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Category
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            User
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (count($items) > 0): ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 mr-3">
                                            <?php if ($item['image']): ?>
                                                <img class="h-10 w-10 rounded object-cover" src="<?php echo UPLOAD_URL . $item['image']; ?>" alt="<?php echo $item['title']; ?>">
                                            <?php else: ?>
                                                <div class="h-10 w-10 rounded bg-gray-200 flex items-center justify-center text-gray-500">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo $item['title']; ?></div>
                                            <div class="text-xs text-gray-500 truncate max-w-xs"><?php echo substr($item['description'], 0, 50) . (strlen($item['description']) > 50 ? '...' : ''); ?></div>
                                            <?php if ($item['pending_claims'] > 0): ?>
                                                <div class="text-xs text-blue-600 font-semibold mt-1">
                                                    <i class="fas fa-clipboard-list mr-1"></i> <?php echo $item['pending_claims']; ?> pending claim(s)
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $item['type'] == 'lost' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                        <?php echo ucfirst($item['type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $item['category']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $item['user_name']; ?></div>
                                    <div class="text-xs text-gray-500"><?php echo $item['user_email']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($item['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($item['status'] == 'active'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Active
                                        </span>
                                    <?php elseif ($item['status'] == 'resolved'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            Resolved
                                        </span>
                                    <?php elseif ($item['status'] == 'spam'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Spam
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            <?php echo ucfirst($item['status']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <a href="view-item.php?id=<?php echo $item['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900"
                                           title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($item['status'] != 'active'): ?>
                                            <a href="?action=mark_active&id=<?php echo $item['id']; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>" 
                                               class="text-green-600 hover:text-green-900"
                                               title="Mark as Active"
                                               onclick="return confirm('Are you sure you want to mark this item as active?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($item['status'] != 'resolved'): ?>
                                            <a href="?action=mark_resolved&id=<?php echo $item['id']; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>" 
                                               class="text-blue-600 hover:text-blue-900"
                                               title="Mark as Resolved"
                                               onclick="return confirm('Are you sure you want to mark this item as resolved?')">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($item['status'] != 'spam'): ?>
                                            <a href="?action=mark_spam&id=<?php echo $item['id']; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>" 
                                               class="text-red-600 hover:text-red-900"
                                               title="Mark as Spam"
                                               onclick="return confirm('Are you sure you want to mark this item as spam?')">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="?action=delete&id=<?php echo $item['id']; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>" 
                                           class="text-red-600 hover:text-red-900"
                                           title="Delete Item"
                                           onclick="return confirm('Are you sure you want to permanently delete this item? This action cannot be undone!')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                No items found matching your criteria
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
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>" 
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
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>" 
                       class="px-4 py-2 bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 <?php echo $i == $page ? 'bg-blue-50 text-blue-600 font-bold' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>" 
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