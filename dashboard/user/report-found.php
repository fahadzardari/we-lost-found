<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $category = sanitizeInput($_POST['category']);
    $location = sanitizeInput($_POST['location']);
    $date_found = sanitizeInput($_POST['date_found']);
    $keywords = sanitizeInput($_POST['keywords']);
    
    // Validate form data
    if (empty($title) || empty($description) || empty($category) || empty($location) || empty($date_found)) {
        $error = 'Please fill in all required fields';
    } elseif (empty($_FILES['image']['name'])) {
        $error = 'Image is required for found items to help with identification';
    } else {
        try {
            // Insert found item into database
            $stmt = $conn->prepare("
                INSERT INTO items (user_id, type, title, description, category, location, date_lost_found, keywords) 
                VALUES (?, 'found', ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'], 
                $title, 
                $description, 
                $category, 
                $location, 
                $date_found,
                $keywords
            ]);
            
            $itemId = $conn->lastInsertId();
            
            // Upload image (required for found items)
            $image = uploadImage($_FILES['image']);
            if ($image) {
                $stmt = $conn->prepare("
                    INSERT INTO item_images (item_id, image_path) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$itemId, $image]);
                
                // Find potential matches
                findMatches($itemId, 'found');
                
                $success = 'Your found item has been reported successfully';
            } else {
                $error = 'Error uploading image. Please try again.';
                
                // Remove the item since image upload failed
                $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
                $stmt->execute([$itemId]);
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Include header
include_once '../../includes/header.php';
?>

<div class="bg-white shadow-md rounded-lg p-6 max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Report Found Item</h1>
    
    <?php if (!empty($error)): ?>
        <?php echo showError($error); ?>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <?php echo showSuccess($success); ?>
        <div class="mt-4 text-center">
            <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded">
                Back to Dashboard
            </a>
        </div>
    <?php else: ?>
    
    <form action="" method="post" enctype="multipart/form-data" class="space-y-4">
        <div>
            <label for="title" class="block text-sm font-medium text-gray-700">Item Name/Title*</label>
            <input type="text" id="title" name="title" required 
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
        </div>
        
        <div>
            <label for="category" class="block text-sm font-medium text-gray-700">Category*</label>
            <select id="category" name="category" required 
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select a category</option>
                <option value="Electronics">Electronics</option>
                <option value="Clothing">Clothing</option>
                <option value="Books">Books</option>
                <option value="Jewelry">Jewelry</option>
                <option value="Identification">Identification</option>
                <option value="Keys">Keys</option>
                <option value="Bags">Bags</option>
                <option value="Other">Other</option>
            </select>
        </div>
        
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700">Description*</label>
            <textarea id="description" name="description" rows="4" required 
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                placeholder="Color, brand, distinguishing features, etc."></textarea>
            <p class="mt-1 text-sm text-gray-500">Be descriptive, but avoid including unique identifying details that only the owner would know.</p>
        </div>
        
        <div>
            <label for="location" class="block text-sm font-medium text-gray-700">Found Location*</label>
            <input type="text" id="location" name="location" required 
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
        </div>
        
        <div>
            <label for="date_found" class="block text-sm font-medium text-gray-700">Date Found*</label>
            <input type="date" id="date_found" name="date_found" required 
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                max="<?php echo date('Y-m-d'); ?>">
        </div>
        
        <div>
            <label for="keywords" class="block text-sm font-medium text-gray-700">Keywords</label>
            <input type="text" id="keywords" name="keywords" 
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                placeholder="Comma separated keywords to help match the owner">
        </div>
        
        <div>
            <label for="image" class="block text-sm font-medium text-gray-700">Image*</label>
            <input type="file" id="image" name="image" accept="image/*" required 
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            <p class="mt-1 text-sm text-gray-500">Please upload a clear image of the found item. This is required for verification.</p>
        </div>
        
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mt-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">
                        <strong>Important:</strong> Do not include unique identifying features in your public description that only the owner would know. These details should be kept private to verify the true owner.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="flex justify-between items-center pt-4">
            <a href="index.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
            </a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded">
                Submit Report
            </button>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
    // Set max date to today
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('date_found').setAttribute('max', today);
    });
</script>

<?php include_once '../../includes/footer.php'; ?>