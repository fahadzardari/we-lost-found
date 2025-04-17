<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('auth/login.php');
}

// Get user's items
try {
    $stmt = $conn->prepare("
        SELECT i.*, 
               (SELECT image_path FROM item_images WHERE item_id = i.id LIMIT 1) as image,
               (SELECT COUNT(*) FROM matches 
                WHERE (lost_item_id = i.id OR found_item_id = i.id) 
                AND status = 'pending') as match_count
        FROM items i
        WHERE i.user_id = ?
        ORDER BY i.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get categories for filter
    $stmt = $conn->query("SELECT DISTINCT category FROM items ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $user_items = [];
    $categories = [];
}

// Include header
include_once '../../includes/header.php';
?>

<div class="bg-white shadow-md rounded-lg p-6 mb-6">
    <h1 class="text-2xl font-bold mb-6">Welcome, <?php echo $_SESSION['user_name']; ?></h1>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-blue-100 p-4 rounded-lg text-center">
            <h2 class="text-lg font-semibold mb-2">My Reports</h2>
            <p class="text-3xl font-bold text-blue-600"><?php echo count($user_items); ?></p>
        </div>
        <div class="bg-red-100 p-4 rounded-lg text-center">
            <h2 class="text-lg font-semibold mb-2">Lost Items</h2>
            <p class="text-3xl font-bold text-red-600">
                <?php
                $lostCount = 0;
                foreach ($user_items as $item) {
                    if ($item['type'] == 'lost')
                        $lostCount++;
                }
                echo $lostCount;
                ?>
            </p>
        </div>
        <div class="bg-green-100 p-4 rounded-lg text-center">
            <h2 class="text-lg font-semibold mb-2">Found Items</h2>
            <p class="text-3xl font-bold text-green-600">
                <?php
                $foundCount = 0;
                foreach ($user_items as $item) {
                    if ($item['type'] == 'found')
                        $foundCount++;
                }
                echo $foundCount;
                ?>
            </p>
        </div>
    </div>

    <div class="flex flex-col md:flex-row gap-4 mb-6">
        <a href="report-lost.php"
            class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg flex-1 text-center">
            <i class="fas fa-search mr-2"></i> Report Lost Item
        </a>
        <a href="report-found.php"
            class="bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg flex-1 text-center">
            <i class="fas fa-hand-holding mr-2"></i> Report Found Item
        </a>
    </div>
</div>

<!-- My Items Section -->
<div class="bg-white shadow-md rounded-lg p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold">My Lost & Found Items</h2>
        <div class="flex items-center space-x-4">
            <select id="type-filter" class="border rounded px-3 py-1">
                <option value="all">All Types</option>
                <option value="lost">Lost Only</option>
                <option value="found">Found Only</option>
            </select>
            <select id="category-filter" class="border rounded px-3 py-1">
                <option value="all">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category; ?>"><?php echo $category; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if (count($user_items) > 0): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" id="items-container">
            <?php foreach ($user_items as $item): ?>
                <div class="border rounded-lg overflow-hidden flex item-card" data-type="<?php echo $item['type']; ?>"
                    data-category="<?php echo $item['category']; ?>">
                    <div class="w-1/3 bg-gray-200">
                        <?php if ($item['image']): ?>
                            <img src="<?php echo UPLOAD_URL . $item['image']; ?>" alt="<?php echo $item['title']; ?>"
                                class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-gray-500">
                                <i class="fas fa-image text-4xl"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="w-2/3 p-4">
                        <div class="flex justify-between items-start">
                            <span
                                class="inline-block px-3 py-1 rounded-full text-sm font-semibold 
                                <?php echo $item['type'] == 'lost' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?> mb-2">
                                <?php echo ucfirst($item['type']); ?>
                            </span>
                            <?php if ($item['match_count'] > 0): ?>
                                <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2.5 py-0.5 rounded">
                                    <?php echo $item['match_count']; ?> potential
                                    <?php echo $item['match_count'] == 1 ? 'match' : 'matches'; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <h3 class="text-lg font-bold mb-1"><?php echo $item['title']; ?></h3>
                        <p class="text-gray-600 mb-2 text-sm">
                            <?php echo substr($item['description'], 0, 100) . (strlen($item['description']) > 100 ? '...' : ''); ?>
                        </p>
                        <div class="flex justify-between text-xs text-gray-500">
                            <span><i class="fas fa-tag mr-1"></i> <?php echo $item['category']; ?></span>
                            <span><i class="fas fa-map-marker-alt mr-1"></i> <?php echo $item['location']; ?></span>
                        </div>
                        <div class="mt-3 flex justify-between items-center">
                            <span class="text-xs text-gray-500">
                                <i class="far fa-calendar-alt mr-1"></i>
                                <?php echo date('M d, Y', strtotime($item['date_lost_found'])); ?>
                            </span>
                            <a href="item-details.php?id=<?php echo $item['id']; ?>"
                                class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                View Details
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center bg-gray-100 py-8 rounded-lg">
            <p class="text-gray-600 mb-4">You haven't reported any items yet.</p>
            <p>
                <a href="report-lost.php" class="text-blue-600 hover:underline mr-4">Report a Lost Item</a>
                <a href="report-found.php" class="text-blue-600 hover:underline">Report a Found Item</a>
            </p>
        </div>
    <?php endif; ?>
</div>

<script>
    // Simple filtering functionality
    document.getElementById('type-filter').addEventListener('change', filterItems);
    document.getElementById('category-filter').addEventListener('change', filterItems);

    function filterItems() {
        const typeFilter = document.getElementById('type-filter').value;
        const categoryFilter = document.getElementById('category-filter').value;

        const items = document.querySelectorAll('.item-card');

        items.forEach(item => {
            const itemType = item.dataset.type;
            const itemCategory = item.dataset.category;

            let showType = typeFilter === 'all' || itemType === typeFilter;
            let showCategory = categoryFilter === 'all' || itemCategory === categoryFilter;

            if (showType && showCategory) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }
</script>

<?php include_once '../../includes/footer.php'; ?>