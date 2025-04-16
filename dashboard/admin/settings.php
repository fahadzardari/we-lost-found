<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('auth/login.php');
}

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Basic Settings
    if (isset($_POST['update_basic_settings'])) {
        try {
            $site_name = sanitizeInput($_POST['site_name']);
            $site_description = sanitizeInput($_POST['site_description']);
            $admin_email = sanitizeInput($_POST['admin_email']);
            $items_per_page = (int)$_POST['items_per_page'];
            $enable_registration = isset($_POST['enable_registration']) ? 1 : 0;
            $require_email_verification = isset($_POST['require_email_verification']) ? 1 : 0;
            
            // Update settings in the database
            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'site_name'");
            $stmt->execute([$site_name]);
            
            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'site_description'");
            $stmt->execute([$site_description]);
            
            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'admin_email'");
            $stmt->execute([$admin_email]);
            
            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'items_per_page'");
            $stmt->execute([$items_per_page]);
            
            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'enable_registration'");
            $stmt->execute([$enable_registration]);
            
            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'require_email_verification'");
            $stmt->execute([$require_email_verification]);
            
            $success = 'Basic settings updated successfully';
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
    
    // Matching Settings
    elseif (isset($_POST['update_matching_settings'])) {
        try {
            $match_threshold = (int)$_POST['match_threshold'];
            $title_weight = (int)$_POST['title_weight'];
            $description_weight = (int)$_POST['description_weight'];
            $category_weight = (int)$_POST['category_weight'];
            $location_weight = (int)$_POST['location_weight'];
            $date_weight = (int)$_POST['date_weight'];
            
            // Update settings in the database
            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'match_threshold'");
            $stmt->execute([$match_threshold]);
            
            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'title_weight'");
            $stmt->execute([$title_weight]);
            
            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'description_weight'");
            $stmt->execute([$description_weight]);
            
            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'category_weight'");
            $stmt->execute([$category_weight]);
            
            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'location_weight'");
            $stmt->execute([$location_weight]);
            
            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'date_weight'");
            $stmt->execute([$date_weight]);
            
            $success = 'Matching settings updated successfully';
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
    
    // Categories Management
    elseif (isset($_POST['update_categories'])) {
        try {
            $categories = array_map('trim', explode(',', $_POST['categories']));
            $categories = array_filter($categories); // Remove empty values
            $categories = array_unique($categories); // Remove duplicates
            sort($categories); // Sort alphabetically
            
            $categories_str = implode(',', $categories);
            
            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'item_categories'");
            $stmt->execute([$categories_str]);
            
            $success = 'Categories updated successfully';
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get current settings
try {
    $stmt = $conn->query("SELECT * FROM settings");
    $settings = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['name']] = $row['value'];
    }
    
    // If some settings don't exist, set defaults
    if (!isset($settings['site_name'])) $settings['site_name'] = 'Lost & Found Portal';
    if (!isset($settings['site_description'])) $settings['site_description'] = 'A platform to report and find lost items';
    if (!isset($settings['admin_email'])) $settings['admin_email'] = 'admin@example.com';
    if (!isset($settings['items_per_page'])) $settings['items_per_page'] = 10;
    if (!isset($settings['enable_registration'])) $settings['enable_registration'] = 1;
    if (!isset($settings['require_email_verification'])) $settings['require_email_verification'] = 0;
    
    if (!isset($settings['match_threshold'])) $settings['match_threshold'] = 50;
    if (!isset($settings['title_weight'])) $settings['title_weight'] = 25;
    if (!isset($settings['description_weight'])) $settings['description_weight'] = 30;
    if (!isset($settings['category_weight'])) $settings['category_weight'] = 20;
    if (!isset($settings['location_weight'])) $settings['location_weight'] = 15;
    if (!isset($settings['date_weight'])) $settings['date_weight'] = 10;
    
    if (!isset($settings['item_categories'])) {
        $settings['item_categories'] = 'Electronics,Documents,Jewelry,Clothing,Accessories,Keys,Wallet,Phone,Bag,Other';
    }
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $settings = [];
}

// Include header
include_once '../../includes/header.php';

// Include admin sidebar
include_once '../../includes/sidebar.php';
?>

<!-- Admin Settings Content -->
<div class="md:ml-64 p-4">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">System Settings</h1>
        <p class="text-gray-600">Configure the Lost & Found Portal settings.</p>
    </div>
    
    <?php if (!empty($success)): ?>
        <?php echo showSuccess($success); ?>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <?php echo showError($error); ?>
    <?php endif; ?>
    
    <!-- Settings Tabs -->
    <div class="mb-6">
        <ul class="flex border-b">
            <li class="-mb-px">
                <a href="#basic" class="inline-block py-2 px-4 text-blue-600 font-medium" id="basic-tab">
                    Basic Settings
                </a>
            </li>
            <li class="ml-2">
                <a href="#matching" class="inline-block py-2 px-4 text-gray-600 hover:text-blue-600 font-medium" id="matching-tab">
                    Matching Algorithm
                </a>
            </li>
            <li class="ml-2">
                <a href="#categories" class="inline-block py-2 px-4 text-gray-600 hover:text-blue-600 font-medium" id="categories-tab">
                    Categories Management
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Basic Settings -->
    <div id="basic-content" class="settings-content">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="border-b px-6 py-4 bg-gray-50">
                <h2 class="text-lg font-medium">Basic Settings</h2>
                <p class="text-sm text-gray-500">Configure general portal settings</p>
            </div>
            <div class="p-6">
                <form method="POST" action="">
                    <div class="space-y-4">
                        <div>
                            <label for="site_name" class="block text-sm font-medium text-gray-700 mb-1">Site Name</label>
                            <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                        </div>
                        
                        <div>
                            <label for="site_description" class="block text-sm font-medium text-gray-700 mb-1">Site Description</label>
                            <textarea id="site_description" name="site_description" rows="2"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                        </div>
                        
                        <div>
                            <label for="admin_email" class="block text-sm font-medium text-gray-700 mb-1">Admin Email</label>
                            <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($settings['admin_email']); ?>"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                        </div>
                        
                        <div>
                            <label for="items_per_page" class="block text-sm font-medium text-gray-700 mb-1">Items Per Page</label>
                            <input type="number" id="items_per_page" name="items_per_page" value="<?php echo htmlspecialchars($settings['items_per_page']); ?>"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" 
                                min="5" max="50" required>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" id="enable_registration" name="enable_registration" value="1"
                                <?php echo $settings['enable_registration'] ? 'checked' : ''; ?>
                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <label for="enable_registration" class="ml-2 block text-sm font-medium text-gray-700">
                                Enable User Registration
                            </label>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" id="require_email_verification" name="require_email_verification" value="1"
                                <?php echo $settings['require_email_verification'] ? 'checked' : ''; ?>
                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <label for="require_email_verification" class="ml-2 block text-sm font-medium text-gray-700">
                                Require Email Verification
                            </label>
                        </div>
                        
                        <div class="pt-4">
                            <button type="submit" name="update_basic_settings" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                                Save Basic Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Matching Algorithm Settings -->
    <div id="matching-content" class="settings-content hidden">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="border-b px-6 py-4 bg-gray-50">
                <h2 class="text-lg font-medium">Matching Algorithm Settings</h2>
                <p class="text-sm text-gray-500">Configure how lost and found items are matched</p>
            </div>
            <div class="p-6">
                <form method="POST" action="">
                    <div class="space-y-6">
                        <div>
                            <label for="match_threshold" class="block text-sm font-medium text-gray-700 mb-1">Match Threshold (%)</label>
                            <input type="range" id="match_threshold" name="match_threshold" value="<?php echo htmlspecialchars($settings['match_threshold']); ?>"
                                class="w-full" min="10" max="90" step="5" oninput="document.getElementById('match_threshold_value').textContent = this.value + '%'">
                            <div class="text-right text-sm text-gray-600">
                                Current: <span id="match_threshold_value"><?php echo htmlspecialchars($settings['match_threshold']); ?>%</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Minimum percentage required to consider items as potential matches</p>
                        </div>
                        
                        <div class="border-t pt-4">
                            <h3 class="font-medium mb-4">Feature Weights</h3>
                            <p class="text-sm text-gray-600 mb-4">Define the importance of each factor in the matching algorithm. Total should equal 100%.</p>
                            
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label for="title_weight" class="block text-sm font-medium text-gray-700 mb-1">Title Weight</label>
                                    <input type="number" id="title_weight" name="title_weight" value="<?php echo htmlspecialchars($settings['title_weight']); ?>"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" 
                                        min="0" max="100" required onchange="updateTotalWeight()">
                                </div>
                                
                                <div>
                                    <label for="description_weight" class="block text-sm font-medium text-gray-700 mb-1">Description Weight</label>
                                    <input type="number" id="description_weight" name="description_weight" value="<?php echo htmlspecialchars($settings['description_weight']); ?>"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" 
                                        min="0" max="100" required onchange="updateTotalWeight()">
                                </div>
                                
                                <div>
                                    <label for="category_weight" class="block text-sm font-medium text-gray-700 mb-1">Category Weight</label>
                                    <input type="number" id="category_weight" name="category_weight" value="<?php echo htmlspecialchars($settings['category_weight']); ?>"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" 
                                        min="0" max="100" required onchange="updateTotalWeight()">
                                </div>
                                
                                <div>
                                    <label for="location_weight" class="block text-sm font-medium text-gray-700 mb-1">Location Weight</label>
                                    <input type="number" id="location_weight" name="location_weight" value="<?php echo htmlspecialchars($settings['location_weight']); ?>"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" 
                                        min="0" max="100" required onchange="updateTotalWeight()">
                                </div>
                                
                                <div>
                                    <label for="date_weight" class="block text-sm font-medium text-gray-700 mb-1">Date Weight</label>
                                    <input type="number" id="date_weight" name="date_weight" value="<?php echo htmlspecialchars($settings['date_weight']); ?>"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" 
                                        min="0" max="100" required onchange="updateTotalWeight()">
                                </div>
                                
                                <div class="flex items-end">
                                    <div class="text-sm border rounded-md px-3 py-2 bg-gray-50">
                                        Total Weight: <span id="total_weight" class="font-bold">100</span>%
                                        <div id="weight_warning" class="text-red-600 text-xs hidden">
                                            Total weight must equal 100%
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="pt-4">
                            <button type="submit" name="update_matching_settings" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded" id="save_matching_btn">
                                Save Matching Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Categories Management -->
    <div id="categories-content" class="settings-content hidden">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="border-b px-6 py-4 bg-gray-50">
                <h2 class="text-lg font-medium">Categories Management</h2>
                <p class="text-sm text-gray-500">Manage item categories for lost and found reports</p>
            </div>
            <div class="p-6">
                <form method="POST" action="">
                    <div class="space-y-4">
                        <div>
                            <label for="categories" class="block text-sm font-medium text-gray-700 mb-1">Categories</label>
                            <p class="text-xs text-gray-500 mb-2">Enter categories separated by commas</p>
                            <textarea id="categories" name="categories" rows="6"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"><?php echo htmlspecialchars($settings['item_categories']); ?></textarea>
                        </div>
                        
                        <div class="pt-4">
                            <button type="submit" name="update_categories" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                                Save Categories
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Preview Categories -->
                <div class="mt-6 pt-4 border-t">
                    <h3 class="text-lg font-medium mb-3">Current Categories</h3>
                    <div class="flex flex-wrap gap-2" id="categories_preview">
                        <?php foreach(explode(',', $settings['item_categories']) as $category): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-800">
                                <?php echo htmlspecialchars(trim($category)); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Tab functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabs = ['basic', 'matching', 'categories'];
    
    tabs.forEach(tabId => {
        const tab = document.getElementById(`${tabId}-tab`);
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            showTab(tabId);
        });
    });
    
    function showTab(tabId) {
        // Hide all tabs
        tabs.forEach(id => {
            document.getElementById(`${id}-content`).classList.add('hidden');
            document.getElementById(`${id}-tab`).classList.remove('text-blue-600', 'border-b-2', 'border-blue-600');
            document.getElementById(`${id}-tab`).classList.add('text-gray-600');
        });
        
        // Show selected tab
        document.getElementById(`${tabId}-content`).classList.remove('hidden');
        document.getElementById(`${tabId}-tab`).classList.add('text-blue-600', 'border-b-2', 'border-blue-600');
        document.getElementById(`${tabId}-tab`).classList.remove('text-gray-600');
    }
    
    // Update categories preview
    const categoriesInput = document.getElementById('categories');
    if (categoriesInput) {
        categoriesInput.addEventListener('input', function() {
            updateCategoriesPreview();
        });
    }
    
    function updateCategoriesPreview() {
        const categoriesValue = categoriesInput.value;
        const categoriesArray = categoriesValue.split(',').map(cat => cat.trim()).filter(cat => cat !== '');
        const previewContainer = document.getElementById('categories_preview');
        
        previewContainer.innerHTML = '';
        
        categoriesArray.forEach(category => {
            const span = document.createElement('span');
            span.className = 'inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-800';
            span.textContent = category;
            previewContainer.appendChild(span);
        });
    }
    
    // Calculate total weight
    updateTotalWeight();
});

function updateTotalWeight() {
    const titleWeight = parseInt(document.getElementById('title_weight').value) || 0;
    const descriptionWeight = parseInt(document.getElementById('description_weight').value) || 0;
    const categoryWeight = parseInt(document.getElementById('category_weight').value) || 0;
    const locationWeight = parseInt(document.getElementById('location_weight').value) || 0;
    const dateWeight = parseInt(document.getElementById('date_weight').value) || 0;
    
    const totalWeight = titleWeight + descriptionWeight + categoryWeight + locationWeight + dateWeight;
    const totalElement = document.getElementById('total_weight');
    const warningElement = document.getElementById('weight_warning');
    const saveButton = document.getElementById('save_matching_btn');
    
    totalElement.textContent = totalWeight;
    
    if (totalWeight !== 100) {
        totalElement.classList.add('text-red-600');
        warningElement.classList.remove('hidden');
        saveButton.disabled = true;
        saveButton.classList.add('opacity-50', 'cursor-not-allowed');
    } else {
        totalElement.classList.remove('text-red-600');
        warningElement.classList.add('hidden');
        saveButton.disabled = false;
        saveButton.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}
</script>

<?php include_once '../../includes/footer.php'; ?>