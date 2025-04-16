<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$itemId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';
$item = null;
$images = [];

// Process claim submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_claim'])) {
    if (!isLoggedIn()) {
        $error = 'You must be logged in to submit a claim';
    } else {
        $claimDetails = sanitizeInput($_POST['claim_details']);
        $contactInfo = sanitizeInput($_POST['contact_info']);
        
        if (empty($claimDetails)) {
            $error = 'Please provide details to support your claim';
        } else {
            try {
                // Insert claim into database
                $stmt = $conn->prepare("
                    INSERT INTO claims (item_id, user_id, claim_details, contact_info, status, created_at)
                    VALUES (?, ?, ?, ?, 'pending', NOW())
                ");
                
                $stmt->execute([
                    $itemId,
                    $_SESSION['user_id'],
                    $claimDetails,
                    $contactInfo
                ]);
                
                $success = 'Your claim has been submitted successfully and is pending admin review';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get item details
if ($itemId > 0) {
    try {
        // Get item details with user info
        $stmt = $conn->prepare("
            SELECT i.*, u.name as user_name, u.email as user_email
            FROM items i
            JOIN users u ON i.user_id = u.id
            WHERE i.id = ? AND i.status != 'spam'
        ");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            // Get item images
            $stmt = $conn->prepare("SELECT image_path FROM item_images WHERE item_id = ?");
            $stmt->execute([$itemId]);
            $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Check if user has already claimed this item
            $userHasClaimed = false;
            if (isLoggedIn()) {
                $stmt = $conn->prepare("
                    SELECT COUNT(*) FROM claims 
                    WHERE item_id = ? AND user_id = ?
                ");
                $stmt->execute([$itemId, $_SESSION['user_id']]);
                $userHasClaimed = ($stmt->fetchColumn() > 0);
            }
            
            // Get similar items (fuzzy matching)
            $oppositeType = $item['type'] == 'lost' ? 'found' : 'lost';
            $stmt = $conn->prepare("
                SELECT i.*, 
                       (SELECT image_path FROM item_images WHERE item_id = i.id LIMIT 1) as image,
                       (
                           -- Category match
                           (CASE WHEN i.category = ? THEN 25 ELSE 0 END) +
                           -- Location match
                           (CASE WHEN i.location = ? THEN 25 ELSE 0 END) +
                           -- Date proximity (within 7 days)
                           (CASE 
                              WHEN ABS(DATEDIFF(i.date_lost_found, ?)) <= 7 THEN 25 
                              WHEN ABS(DATEDIFF(i.date_lost_found, ?)) <= 14 THEN 15
                              ELSE 0 
                            END) +
                           -- Title/Description similarity
                           (CASE WHEN i.title LIKE ? OR i.description LIKE ? THEN 25 ELSE 0 END)
                       ) as match_percentage
                FROM items i
                WHERE i.type = ? AND i.id != ? AND i.status = 'active'
                HAVING match_percentage > 0
                ORDER BY match_percentage DESC
                LIMIT 3
            ");
            
            $stmt->execute([
                $item['category'],
                $item['location'],
                $item['date_lost_found'],
                $item['date_lost_found'],
                '%' . $item['title'] . '%',
                '%' . $item['title'] . '%',
                $oppositeType,
                $itemId
            ]);
            
            $similarItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Include header
include_once 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <?php if (!empty($error)): ?>
        <?php echo showError($error); ?>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <?php echo showSuccess($success); ?>
    <?php endif; ?>
    
    <?php if ($item): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <!-- Item Header Section -->
            <div class="bg-gray-50 border-b p-4">
                <div class="flex justify-between items-center flex-wrap">
                    <div class="flex items-center">
                        <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold mr-4
                            <?php echo $item['type'] == 'lost' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                            <?php echo ucfirst($item['type']); ?>
                        </span>
                        <h1 class="text-2xl font-bold"><?php echo $item['title']; ?></h1>
                    </div>
                    <div class="text-gray-500 text-sm mt-2 md:mt-0">
                        Posted on <?php echo date('F j, Y', strtotime($item['created_at'])); ?>
                    </div>
                </div>
            </div>
            
            <!-- Item Content -->
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Left Column: Images -->
                    <div class="md:col-span-2">
                        <?php if (!empty($images)): ?>
                            <!-- Image Gallery -->
                            <div class="bg-gray-100 p-2 rounded-lg mb-4">
                                <div class="grid grid-cols-1 gap-2">
                                    <?php foreach ($images as $index => $imagePath): ?>
                                        <div class="rounded-lg overflow-hidden">
                                            <img src="<?php echo UPLOAD_URL . $imagePath; ?>" alt="Item Image <?php echo $index+1; ?>"
                                                 class="w-full object-contain max-h-96">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="bg-gray-100 p-8 rounded-lg mb-4 flex items-center justify-center">
                                <div class="text-gray-400 text-center">
                                    <i class="fas fa-image text-5xl mb-2"></i>
                                    <p>No images available</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Description -->
                        <div class="mb-6">
                            <h2 class="text-xl font-bold mb-2">Description</h2>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-700 whitespace-pre-line"><?php echo $item['description']; ?></p>
                            </div>
                        </div>
                        
                        <!-- Status and Actions -->
                        <div>
                            <?php if ($item['status'] == 'resolved'): ?>
                                <div class="bg-blue-50 text-blue-700 p-4 rounded-lg mb-4 flex items-center">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    <span>This item has been resolved and returned to its owner.</span>
                                </div>
                            <?php else: ?>
                                <!-- Submit Claim Section (only for opposite type and logged in users) -->
                                <?php 
                                $canClaim = isLoggedIn() && 
                                           (($item['type'] == 'lost' && $_SESSION['user_id'] != $item['user_id']) || 
                                            ($item['type'] == 'found' && $item['user_id'] != $_SESSION['user_id']));
                                ?>
                                
                                <?php if ($canClaim && !$userHasClaimed): ?>
                                    <div class="bg-blue-50 p-4 rounded-lg mb-4">
                                        <h3 class="font-bold text-lg mb-2">
                                            <?php echo $item['type'] == 'found' ? 'Claim This Item' : 'I Found This Item'; ?>
                                        </h3>
                                        <form method="POST" action="">
                                            <div class="mb-4">
                                                <label for="claim_details" class="block text-sm font-medium text-gray-700 mb-1">
                                                    Provide details to support your claim
                                                </label>
                                                <textarea id="claim_details" name="claim_details" rows="4"
                                                          class="w-full px-3 py-2 border rounded-md focus:ring focus:ring-blue-300"
                                                          placeholder="Describe specific details about the item that only the owner would know" required></textarea>
                                            </div>
                                            <div class="mb-4">
                                                <label for="contact_info" class="block text-sm font-medium text-gray-700 mb-1">
                                                    Additional contact information (optional)
                                                </label>
                                                <input type="text" id="contact_info" name="contact_info"
                                                       class="w-full px-3 py-2 border rounded-md focus:ring focus:ring-blue-300"
                                                       placeholder="Phone number or alternate email">
                                            </div>
                                            <button type="submit" name="submit_claim" 
                                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                                                Submit Claim
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($userHasClaimed): ?>
                                    <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-4 flex items-center">
                                        <i class="fas fa-check-circle mr-2"></i>
                                        <span>You have already submitted a claim for this item. An administrator will review your claim.</span>
                                    </div>
                                <?php elseif (!isLoggedIn()): ?>
                                    <div class="bg-yellow-50 text-yellow-700 p-4 rounded-lg mb-4 flex items-center">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        <span>Please <a href="<?php echo BASE_URL; ?>/auth/login.php" class="underline">log in</a> to submit a claim for this item.</span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Right Column: Details -->
                    <div>
                        <!-- Item Details -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-6">
                            <h2 class="text-xl font-bold mb-4">Details</h2>
                            <div class="space-y-3">
                                <div class="flex items-start">
                                    <div class="w-8 text-gray-500"><i class="fas fa-tag"></i></div>
                                    <div>
                                        <p class="text-sm text-gray-500">Category</p>
                                        <p><?php echo $item['category']; ?></p>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <div class="w-8 text-gray-500"><i class="fas fa-map-marker-alt"></i></div>
                                    <div>
                                        <p class="text-sm text-gray-500">Location</p>
                                        <p><?php echo $item['location']; ?></p>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <div class="w-8 text-gray-500"><i class="fas fa-calendar-alt"></i></div>
                                    <div>
                                        <p class="text-sm text-gray-500">
                                            <?php echo $item['type'] == 'lost' ? 'Date Lost' : 'Date Found'; ?>
                                        </p>
                                        <p><?php echo date('F j, Y', strtotime($item['date_lost_found'])); ?></p>
                                    </div>
                                </div>
                                <?php if (!empty($item['color'])): ?>
                                    <div class="flex items-start">
                                        <div class="w-8 text-gray-500"><i class="fas fa-palette"></i></div>
                                        <div>
                                            <p class="text-sm text-gray-500">Color</p>
                                            <p><?php echo $item['color']; ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($item['brand'])): ?>
                                    <div class="flex items-start">
                                        <div class="w-8 text-gray-500"><i class="fas fa-trademark"></i></div>
                                        <div>
                                            <p class="text-sm text-gray-500">Brand</p>
                                            <p><?php echo $item['brand']; ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Report Button -->
                        <div class="mb-6">
                            <a href="#" class="block text-center text-red-600 hover:text-red-700">
                                <i class="fas fa-flag mr-1"></i> Report this item
                            </a>
                        </div>
                        
                        <!-- Similar Items -->
                        <?php if (!empty($similarItems)): ?>
                            <div>
                                <h2 class="text-xl font-bold mb-4">Similar Items</h2>
                                <div class="space-y-4">
                                    <?php foreach ($similarItems as $similarItem): ?>
                                        <div class="border rounded-lg overflow-hidden">
                                            <div class="h-24 bg-gray-200">
                                                <?php if ($similarItem['image']): ?>
                                                    <img src="<?php echo UPLOAD_URL . $similarItem['image']; ?>" 
                                                         alt="<?php echo $similarItem['title']; ?>"
                                                         class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <div class="w-full h-full flex items-center justify-center text-gray-500">
                                                        <i class="fas fa-image"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="p-3">
                                                <div class="flex justify-between items-center mb-1">
                                                    <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold 
                                                        <?php echo $similarItem['type'] == 'lost' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                                        <?php echo ucfirst($similarItem['type']); ?>
                                                    </span>
                                                    <span class="text-xs text-blue-600 font-semibold">
                                                        Match: <?php echo round($similarItem['match_percentage'] / 100 * 100); ?>%
                                                    </span>
                                                </div>
                                                <h3 class="font-medium text-sm mb-1 truncate"><?php echo $similarItem['title']; ?></h3>
                                                <a href="item-details.php?id=<?php echo $similarItem['id']; ?>" 
                                                   class="text-blue-600 text-xs hover:underline">
                                                    View details →
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <div class="text-gray-400 mb-4">
                <i class="fas fa-search text-5xl"></i>
            </div>
            <h2 class="text-2xl font-bold mb-2">Item Not Found</h2>
            <p class="text-gray-600 mb-6">The item you are looking for does not exist or has been removed.</p>
            <div class="flex justify-center">
                <a href="<?php echo BASE_URL; ?>/" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md mr-2">
                    Return Home
                </a>
                <a href="<?php echo BASE_URL; ?>/search.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-md">
                    Search Items
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include_once 'includes/footer.php'; ?>