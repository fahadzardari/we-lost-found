<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('auth/login.php');
}

$error = '';
$success = '';
$item = null;
$matches = [];
$item_images = [];

// Check if item ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('manage-reports.php');
}

$itemId = $_GET['id'];

// Process actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    try {
        if ($action == 'mark_spam') {
            $stmt = $conn->prepare("UPDATE items SET status = 'spam' WHERE id = ?");
            $stmt->execute([$itemId]);
            $success = 'Item has been marked as spam successfully';
        } elseif ($action == 'restore') {
            $stmt = $conn->prepare("UPDATE items SET status = 'active' WHERE id = ?");
            $stmt->execute([$itemId]);
            $success = 'Item has been restored successfully';
        } elseif ($action == 'resolve') {
            $stmt = $conn->prepare("UPDATE items SET status = 'resolved' WHERE id = ?");
            $stmt->execute([$itemId]);
            $success = 'Item has been marked as resolved successfully';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Get item details
try {
    $stmt = $conn->prepare("
        SELECT i.*, u.name as user_name, u.email as user_email
        FROM items i
        JOIN users u ON i.user_id = u.id
        WHERE i.id = ?
    ");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        redirect('manage-reports.php');
    }
    
    // Get item images
    $stmt = $conn->prepare("
        SELECT * FROM item_images WHERE item_id = ?
    ");
    $stmt->execute([$itemId]);
    $item_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get potential matches
    if ($item['type'] == 'lost') {
        $stmt = $conn->prepare("
            SELECT m.*, i.title, i.description, i.category, i.location, i.date_lost_found,
                   u.name as finder_name, u.email as finder_email,
                   (SELECT image_path FROM item_images WHERE item_id = i.id LIMIT 1) as image
            FROM matches m
            JOIN items i ON m.found_item_id = i.id
            JOIN users u ON i.user_id = u.id
            WHERE m.lost_item_id = ?
            ORDER BY m.match_percentage DESC
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT m.*, i.title, i.description, i.category, i.location, i.date_lost_found,
                   u.name as owner_name, u.email as owner_email,
                   (SELECT image_path FROM item_images WHERE item_id = i.id LIMIT 1) as image
            FROM matches m
            JOIN items i ON m.lost_item_id = i.id
            JOIN users u ON i.user_id = u.id
            WHERE m.found_item_id = ?
            ORDER BY m.match_percentage DESC
        ");
    }
    
    $stmt->execute([$itemId]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $item = null;
}

// Include header
include_once '../../includes/header.php';

// Include admin sidebar
include_once '../../includes/sidebar.php';
?>

<!-- Admin Item Detail Content -->
<div class="md:ml-64 p-4">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold">Item Details</h1>
            <p class="text-gray-600">View detailed information about this item and its potential matches.</p>
        </div>
        
        <div class="flex space-x-2">
            <a href="manage-reports.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                <i class="fas fa-arrow-left mr-1"></i> Back to Reports
            </a>
            
            <?php if ($item['status'] == 'active'): ?>
                <a href="?id=<?php echo $itemId; ?>&action=mark_spam" 
                   class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded"
                   onclick="return confirm('Are you sure you want to mark this item as spam?')">
                    <i class="fas fa-ban mr-1"></i> Mark as Spam
                </a>
            <?php elseif ($item['status'] == 'spam'): ?>
                <a href="?id=<?php echo $itemId; ?>&action=restore" 
                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded"
                   onclick="return confirm('Are you sure you want to restore this item?')">
                    <i class="fas fa-undo mr-1"></i> Restore Item
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($success)): ?>
        <?php echo showSuccess($success); ?>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <?php echo showError($error); ?>
    <?php endif; ?>
    
    <?php if ($item): ?>
        <!-- Item Details -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <!-- Item Header -->
            <div class="bg-gray-50 px-6 py-4 border-b">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold"><?php echo $item['title']; ?></h2>
                    <div class="flex items-center space-x-3">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold 
                            <?php echo $item['type'] == 'lost' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                            <i class="<?php echo $item['type'] == 'lost' ? 'fas fa-search' : 'fas fa-hand-holding'; ?> mr-1"></i>
                            <?php echo ucfirst($item['type']); ?>
                        </span>
                        
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold 
                            <?php 
                            if ($item['status'] == 'active') {
                                echo 'bg-green-100 text-green-800';
                            } elseif ($item['status'] == 'resolved') {
                                echo 'bg-blue-100 text-blue-800';
                            } elseif ($item['status'] == 'spam') {
                                echo 'bg-red-100 text-red-800';
                            } else {
                                echo 'bg-gray-100 text-gray-800';
                            }
                            ?>">
                            <?php echo ucfirst($item['status']); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="md:flex">
                <!-- Image Section -->
                <div class="md:w-1/3 p-4 bg-gray-100">
                    <?php if (count($item_images) > 0): ?>
                        <div class="h-64 overflow-hidden rounded">
                            <img src="<?php echo UPLOAD_URL . $item_images[0]['image_path']; ?>" alt="<?php echo $item['title']; ?>" class="w-full h-full object-cover">
                        </div>
                        
                        <?php if (count($item_images) > 1): ?>
                            <div class="mt-2 flex gap-2">
                                <?php foreach($item_images as $index => $image): ?>
                                    <?php if ($index > 0): ?>
                                        <div class="w-16 h-16 overflow-hidden rounded">
                                            <img src="<?php echo UPLOAD_URL . $image['image_path']; ?>" alt="Additional image" class="w-full h-full object-cover">
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="h-64 flex items-center justify-center bg-gray-200 rounded">
                            <span class="text-gray-500"><i class="fas fa-image text-4xl"></i></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Details Section -->
                <div class="md:w-2/3 p-6">
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Category</p>
                            <p class="mt-1"><?php echo $item['category']; ?></p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Location</p>
                            <p class="mt-1"><?php echo $item['location']; ?></p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Date <?php echo $item['type'] == 'lost' ? 'Lost' : 'Found'; ?></p>
                            <p class="mt-1"><?php echo date('M d, Y', strtotime($item['date_lost_found'])); ?></p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Reported On</p>
                            <p class="mt-1"><?php echo date('M d, Y', strtotime($item['created_at'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <p class="text-sm font-medium text-gray-500">Description</p>
                        <p class="mt-1 whitespace-pre-line"><?php echo nl2br($item['description']); ?></p>
                    </div>
                    
                    <?php if (!empty($item['keywords'])): ?>
                    <div class="mb-6">
                        <p class="text-sm font-medium text-gray-500">Keywords</p>
                        <div class="mt-1 flex flex-wrap gap-2">
                            <?php foreach(explode(',', $item['keywords']) as $keyword): ?>
                                <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full text-xs"><?php echo trim($keyword); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- User Information -->
                    <div class="mb-6 border-t pt-4 mt-6">
                        <p class="text-sm font-medium text-gray-500">Reported by</p>
                        <div class="mt-2 flex items-center">
                            <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-blue-600 font-bold">
                                    <?php echo strtoupper(substr($item['user_name'], 0, 1)); ?>
                                </span>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium"><?php echo $item['user_name']; ?></p>
                                <p class="text-sm text-gray-500"><?php echo $item['user_email']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($item['status'] == 'active'): ?>
                        <div class="flex space-x-2">
                            <a href="?id=<?php echo $itemId; ?>&action=resolve" 
                               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm"
                               onclick="return confirm('Are you sure you want to mark this item as resolved?')">
                                <i class="fas fa-check-circle mr-1"></i> Mark as Resolved
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Potential Matches -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="border-b px-6 py-4">
                <h2 class="text-xl font-bold">Potential Matches</h2>
            </div>
            
            <div class="p-6">
                <?php if (count($matches) > 0): ?>
                    <div class="grid grid-cols-1 gap-4">
                        <?php foreach($matches as $match): ?>
                            <div class="border rounded-lg overflow-hidden">
                                <div class="md:flex">
                                    <div class="md:w-1/4 bg-gray-100">
                                        <?php if ($match['image']): ?>
                                            <img src="<?php echo UPLOAD_URL . $match['image']; ?>" alt="<?php echo $match['title']; ?>" class="w-full h-48 object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-48 flex items-center justify-center bg-gray-200">
                                                <span class="text-gray-500"><i class="fas fa-image text-4xl"></i></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="md:w-3/4 p-4">
                                        <div class="flex justify-between items-start">
                                            <h3 class="text-lg font-bold"><?php echo $match['title']; ?></h3>
                                            <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-1 rounded-full">
                                                <?php echo round($match['match_percentage']); ?>% Match
                                            </span>
                                        </div>
                                        <p class="text-gray-600 my-2"><?php echo substr($match['description'], 0, 150); ?>...</p>
                                        <div class="grid grid-cols-2 gap-2 text-sm text-gray-600 mb-4">
                                            <div>
                                                <span class="font-medium">Category:</span> <?php echo $match['category']; ?>
                                            </div>
                                            <div>
                                                <span class="font-medium">Location:</span> <?php echo $match['location']; ?>
                                            </div>
                                            <div>
                                                <span class="font-medium">Date:</span> <?php echo date('M d, Y', strtotime($match['date_lost_found'])); ?>
                                            </div>
                                            <div>
                                                <span class="font-medium">
                                                    <?php echo $item['type'] == 'lost' ? 'Finder' : 'Owner'; ?>:
                                                </span> 
                                                <?php echo $item['type'] == 'lost' ? $match['finder_name'] : $match['owner_name']; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="flex justify-end">
                                            <a href="view-item.php?id=<?php echo $item['type'] == 'lost' ? $match['found_item_id'] : $match['lost_item_id']; ?>" 
                                               class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <p class="text-gray-500">No potential matches found for this item.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include_once '../../includes/footer.php'; ?>