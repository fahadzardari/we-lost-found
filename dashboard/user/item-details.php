<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$error = '';
$success = '';
$item = null;
$matches = [];
$item_images = [];

// Check if item ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('index.php');
}

$itemId = $_GET['id'];

// Mark item as resolved if requested
if (isset($_GET['action']) && $_GET['action'] == 'resolve') {
    try {
        $stmt = $conn->prepare("UPDATE items SET status = 'resolved' WHERE id = ? AND user_id = ?");
        $stmt->execute([$itemId, $_SESSION['user_id']]);
        
        $success = 'Item marked as resolved successfully';
    } catch (PDOException $e) {
        $error = 'Error updating item status';
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
    
    // Verify the item exists and belongs to this user
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item || $item['user_id'] != $_SESSION['user_id']) {
        redirect('index.php');
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
?>

<div class="max-w-5xl mx-auto">
    
    <?php if (!empty($success)): ?>
        <?php echo showSuccess($success); ?>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <?php echo showError($error); ?>
    <?php endif; ?>
    
    <?php if ($item): ?>
        <!-- Item Details -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
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
                    <div class="flex justify-between items-center mb-4">
                        <h1 class="text-2xl font-bold"><?php echo $item['title']; ?></h1>
                        <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold 
                            <?php echo $item['type'] == 'lost' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                            <?php echo ucfirst($item['type']); ?>
                        </span>
                    </div>
                    
                    <div class="mb-6 grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Category</p>
                            <p class="font-medium"><?php echo $item['category']; ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Location</p>
                            <p class="font-medium"><?php echo $item['location']; ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Date</p>
                            <p class="font-medium"><?php echo date('M d, Y', strtotime($item['date_lost_found'])); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Status</p>
                            <p class="font-medium">
                                <?php if ($item['status'] == 'active'): ?>
                                    <span class="text-green-600">Active</span>
                                <?php elseif ($item['status'] == 'resolved'): ?>
                                    <span class="text-blue-600">Resolved</span>
                                <?php else: ?>
                                    <span class="text-gray-600"><?php echo ucfirst($item['status']); ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <h3 class="font-bold text-lg mb-2">Description</h3>
                        <p class="text-gray-700"><?php echo nl2br($item['description']); ?></p>
                    </div>
                    
                    <?php if (!empty($item['keywords'])): ?>
                    <div class="mb-6">
                        <h3 class="font-bold text-lg mb-2">Keywords</h3>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach(explode(',', $item['keywords']) as $keyword): ?>
                                <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full text-sm"><?php echo trim($keyword); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between mt-6">
                        <a href="index.php" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                        </a>
                        <div>
                            <?php if ($item['status'] == 'active'): ?>
                                <a href="?id=<?php echo $itemId; ?>&action=resolve" 
                                   class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"
                                   onclick="return confirm('Are you sure you want to mark this item as resolved?')">
                                    Mark as Resolved
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
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
                                            <button type="button" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 contact-btn" 
                                                    data-email="<?php echo $item['type'] == 'lost' ? $match['finder_email'] : $match['owner_email']; ?>"
                                                    data-name="<?php echo $item['type'] == 'lost' ? $match['finder_name'] : $match['owner_name']; ?>">
                                                Contact <?php echo $item['type'] == 'lost' ? 'Finder' : 'Owner'; ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <p class="text-gray-500 mb-4">No potential matches found yet.</p>
                        <p class="text-sm text-gray-500">Our system will automatically notify you when a potential match is found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Contact Modal -->
        <div id="contactModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold" id="modalTitle">Contact Information</h3>
                    <button type="button" class="text-gray-500 hover:text-gray-700" id="closeModal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="mb-6">
                    <p class="mb-4">Please use the following contact information to get in touch:</p>
                    <div class="bg-gray-100 p-4 rounded">
                        <p><strong>Name:</strong> <span id="contactName"></span></p>
                        <p><strong>Email:</strong> <span id="contactEmail"></span></p>
                    </div>
                </div>
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 text-sm text-yellow-700 mb-4">
                    <p><strong>Important:</strong> When meeting to retrieve/return an item, please consider meeting in a public place for safety.</p>
                </div>
                <div class="text-right">
                    <button type="button" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400" id="closeModalBtn">
                        Close
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    // Contact modal functionality
    document.addEventListener('DOMContentLoaded', function() {
        const contactBtns = document.querySelectorAll('.contact-btn');
        const contactModal = document.getElementById('contactModal');
        const contactName = document.getElementById('contactName');
        const contactEmail = document.getElementById('contactEmail');
        const closeModal = document.getElementById('closeModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        
        contactBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                contactName.textContent = this.dataset.name;
                contactEmail.textContent = this.dataset.email;
                contactModal.classList.remove('hidden');
            });
        });
        
        const closeModalFunction = function() {
            contactModal.classList.add('hidden');
        };
        
        closeModal.addEventListener('click', closeModalFunction);
        closeModalBtn.addEventListener('click', closeModalFunction);
        
        contactModal.addEventListener('click', function(e) {
            if (e.target === contactModal) {
                closeModalFunction();
            }
        });
    });
</script>

<?php include_once '../../includes/footer.php'; ?>