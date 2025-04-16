<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('auth/login.php');
}

$error = '';
$success = '';

// Process claim actions (approve/reject)
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $claimId = $_GET['id'];
    $action = $_GET['action'];
    
    try {
        if ($action == 'approve') {
            // Start a transaction
            $conn->beginTransaction();
            
            // Get claim details including item_id
            $stmt = $conn->prepare("SELECT * FROM claims WHERE id = ?");
            $stmt->execute([$claimId]);
            $claim = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($claim) {
                // Update claim status to approved
                $stmt = $conn->prepare("UPDATE claims SET status = 'approved' WHERE id = ?");
                $stmt->execute([$claimId]);
                
                // Update item status to resolved
                $stmt = $conn->prepare("UPDATE items SET status = 'resolved' WHERE id = ?");
                $stmt->execute([$claim['item_id']]);
                
                // Reject other claims for this item
                $stmt = $conn->prepare("UPDATE claims SET status = 'rejected' WHERE item_id = ? AND id != ?");
                $stmt->execute([$claim['item_id'], $claimId]);
                
                $conn->commit();
                $success = 'Claim has been approved successfully and item marked as resolved';
            } else {
                $conn->rollBack();
                $error = 'Claim not found';
            }
        } elseif ($action == 'reject') {
            $stmt = $conn->prepare("UPDATE claims SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$claimId]);
            $success = 'Claim has been rejected successfully';
        } elseif ($action == 'delete') {
            $stmt = $conn->prepare("DELETE FROM claims WHERE id = ?");
            $stmt->execute([$claimId]);
            $success = 'Claim has been deleted successfully';
        }
    } catch (PDOException $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Get claims with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Search and filter functionality
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
    $type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
    
    $whereConditions = [];
    $params = [];
    
    if (!empty($search)) {
        $whereConditions[] = "(i.title LIKE ? OR c.claim_details LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($status)) {
        $whereConditions[] = "c.status = ?";
        $params[] = $status;
    }
    
    if (!empty($type)) {
        $whereConditions[] = "i.type = ?";
        $params[] = $type;
    }
    
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    }
    
    // Get total count
    $countQuery = "
        SELECT COUNT(*) 
        FROM claims c 
        JOIN items i ON c.item_id = i.id 
        JOIN users u ON c.user_id = u.id 
        $whereClause
    ";
    $stmt = $conn->prepare($countQuery);
    $stmt->execute($params);
    $totalClaims = $stmt->fetchColumn();
    $totalPages = ceil($totalClaims / $limit);
    
    // Get claims
    $query = "
        SELECT c.*, 
               i.title as item_title, 
               i.type as item_type, 
               i.category as item_category,
               i.status as item_status,
               u.name as user_name, 
               u.email as user_email,
               (SELECT image_path FROM item_images WHERE item_id = i.id LIMIT 1) as item_image
        FROM claims c
        JOIN items i ON c.item_id = i.id
        JOIN users u ON c.user_id = u.id
        $whereClause
        ORDER BY 
            CASE c.status 
                WHEN 'pending' THEN 1
                WHEN 'approved' THEN 2
                WHEN 'rejected' THEN 3
            END,
            c.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $claims = [];
    $totalPages = 0;
}

// Include header
include_once '../../includes/header.php';

// Include admin sidebar
include_once '../../includes/sidebar.php';
?>

<!-- Admin Claims Management Content -->
<div class="md:ml-64 pt-6 px-4 pb-4">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Manage Claims</h1>
        <p class="text-gray-600">Review, approve, or reject claims for lost and found items.</p>
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
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                           placeholder="Item title, claim details, or user">
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Claim Status</label>
                    <select id="status" name="status" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Item Type</label>
                    <select id="type" name="type" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="">All Types</option>
                        <option value="lost" <?php echo $type == 'lost' ? 'selected' : ''; ?>>Lost Items</option>
                        <option value="found" <?php echo $type == 'found' ? 'selected' : ''; ?>>Found Items</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end">
                <a href="manage-claims.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 mr-2">
                    Reset
                </a>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    <i class="fas fa-search mr-1"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>
    
    <!-- Claims Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Item
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Claim Details
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Submitted By
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date Submitted
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
                    <?php if (count($claims) > 0): ?>
                        <?php foreach ($claims as $claim): ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 mr-3">
                                            <?php if ($claim['item_image']): ?>
                                                <img class="h-10 w-10 rounded object-cover" src="<?php echo UPLOAD_URL . $claim['item_image']; ?>" alt="<?php echo $claim['item_title']; ?>">
                                            <?php else: ?>
                                                <div class="h-10 w-10 rounded bg-gray-200 flex items-center justify-center text-gray-500">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo $claim['item_title']; ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php echo $claim['item_type'] == 'lost' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                                    <?php echo ucfirst($claim['item_type']); ?>
                                                </span>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <a href="../view-item.php?id=<?php echo $claim['item_id']; ?>" class="text-blue-600 hover:underline" target="_blank">
                                                    View item details →
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <div class="max-h-20 overflow-y-auto">
                                            <?php echo nl2br(htmlspecialchars($claim['claim_details'])); ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($claim['contact_info'])): ?>
                                        <div class="text-xs text-gray-500 mt-1">
                                            Contact: <?php echo htmlspecialchars($claim['contact_info']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $claim['user_name']; ?></div>
                                    <div class="text-xs text-gray-500"><?php echo $claim['user_email']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($claim['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($claim['status'] == 'pending'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Pending
                                        </span>
                                    <?php elseif ($claim['status'] == 'approved'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Approved
                                        </span>
                                    <?php elseif ($claim['status'] == 'rejected'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Rejected
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <?php if ($claim['status'] == 'pending'): ?>
                                            <a href="?action=approve&id=<?php echo $claim['id']; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&type=<?php echo urlencode($type); ?>" 
                                               class="text-green-600 hover:text-green-900"
                                               title="Approve Claim"
                                               onclick="return confirm('Are you sure you want to approve this claim? This will mark the item as resolved and reject all other claims for this item.')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="?action=reject&id=<?php echo $claim['id']; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&type=<?php echo urlencode($type); ?>" 
                                               class="text-red-600 hover:text-red-900"
                                               title="Reject Claim"
                                               onclick="return confirm('Are you sure you want to reject this claim?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?action=delete&id=<?php echo $claim['id']; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&type=<?php echo urlencode($type); ?>" 
                                           class="text-red-600 hover:text-red-900"
                                           title="Delete Claim"
                                           onclick="return confirm('Are you sure you want to permanently delete this claim? This action cannot be undone!')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                No claims found matching your criteria
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
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&type=<?php echo urlencode($type); ?>" 
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
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&type=<?php echo urlencode($type); ?>" 
                       class="px-4 py-2 bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 <?php echo $i == $page ? 'bg-blue-50 text-blue-600 font-bold' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&type=<?php echo urlencode($type); ?>" 
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