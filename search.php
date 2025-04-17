<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$query = isset($_GET['query']) ? sanitizeInput($_GET['query']) : '';
$type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$results = [];

try {
    $stmt = $conn->query("SELECT DISTINCT category FROM items ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}

if (!empty($query)) {
    try {
        $sql = "
            SELECT i.*, u.name as user_name, 
                   (SELECT image_path FROM item_images WHERE item_id = i.id LIMIT 1) as image,
                   (
                       -- Title match (higher weight)
                       (CASE WHEN i.title LIKE ? THEN 40 ELSE 0 END) +
                       (CASE WHEN i.title LIKE ? THEN 30 ELSE 0 END) +
                       (CASE WHEN i.title LIKE ? THEN 20 ELSE 0 END) +
                       
                       -- Description match
                       (CASE WHEN i.description LIKE ? THEN 20 ELSE 0 END) +
                       (CASE WHEN i.description LIKE ? THEN 15 ELSE 0 END) +
                       (CASE WHEN i.description LIKE ? THEN 10 ELSE 0 END) +
                       
                       -- Keywords match
                       (CASE WHEN i.keywords LIKE ? THEN 20 ELSE 0 END) +
                       (CASE WHEN i.keywords LIKE ? THEN 15 ELSE 0 END) +
                       
                       -- Category match
                       (CASE WHEN i.category LIKE ? THEN 15 ELSE 0 END) +
                       
                       -- Location match
                       (CASE WHEN i.location LIKE ? THEN 15 ELSE 0 END)
                   ) as match_percentage
            FROM items i
            JOIN users u ON i.user_id = u.id
            WHERE i.status = 'active'
        ";

        $params = [
            $query . '%',               // Title starts with query (highest weight)
            '%' . $query . '%',         // Title contains query
            '%' . implode('%', str_split($query)) . '%', // Fuzzy title match

            $query . '%',               // Description starts with
            '%' . $query . '%',         // Description contains
            '%' . implode('%', str_split($query)) . '%', // Fuzzy description match

            '%' . $query . '%',         // Keywords contain
            '%' . implode('%', str_split($query)) . '%', // Fuzzy keywords match

            '%' . $query . '%',         // Category match

            '%' . $query . '%'          // Location match
        ];

        if (!empty($type)) {
            $sql .= " AND i.type = ?";
            $params[] = $type;
        }

        if (!empty($category)) {
            $sql .= " AND i.category = ?";
            $params[] = $category;
        }

        $sql .= " HAVING match_percentage > 0 ORDER BY match_percentage DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $maxScore = 100; // Maximum possible score based on weights above
        foreach ($results as &$result) {
            $result['match_percentage'] = min(100, round(($result['match_percentage'] / $maxScore) * 100));
        }

    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Include header
include_once 'includes/header.php';
?>

<!-- Search Section -->
<div class="bg-blue-600 text-white py-8 mb-6">
    <div class="container mx-auto px-4">
        <h1 class="text-3xl font-bold mb-6">Search Lost & Found Items</h1>
        <form method="GET" action="search.php" class="bg-white p-4 rounded-lg shadow-md">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-2">
                    <label for="query" class="block text-gray-700 font-medium mb-1">Search</label>
                    <input type="text" id="query" name="query" value="<?php echo htmlspecialchars($query); ?>"
                        placeholder="Enter keywords, item name, description, etc."
                        class="w-full px-4 py-2 border rounded-lg text-black focus:ring focus:ring-blue-300">
                </div>
                <div>
                    <label for="type" class="block text-gray-700 font-medium mb-1">Type</label>
                    <select id="type" name="type"
                        class="w-full px-4 py-2 text-black border rounded-lg focus:ring focus:ring-blue-300">
                        <option value="">All Items</option>
                        <option value="lost" <?php echo $type === 'lost' ? 'selected' : ''; ?>>Lost Items</option>
                        <option value="found" <?php echo $type === 'found' ? 'selected' : ''; ?>>Found Items</option>
                    </select>
                </div>
                <div>
                    <label for="category" class="block text-gray-700 font-medium mb-1">Category</label>
                    <select id="category" name="category"
                        class="w-full px-4 text-black py-2 border rounded-lg focus:ring focus:ring-blue-300">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                <?php echo $cat; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-4 text-center">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium">
                    <i class="fas fa-search mr-2"></i> Search
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Search Results -->
<div class="container mx-auto px-4 py-6">
    <?php if ($query): ?>
        <h2 class="text-2xl font-bold mb-4">Search Results for "<?php echo htmlspecialchars($query); ?>"</h2>

        <?php if (count($results) > 0): ?>
            <p class="text-gray-600 mb-6">Found <?php echo count($results); ?> item(s) matching your search.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <?php foreach ($results as $item): ?>
                    <div class="bg-white rounded-lg overflow-hidden shadow-md border border-gray-200 flex flex-col">
                        <div class="h-48 bg-gray-200 relative">
                            <?php if ($item['image']): ?>
                                <img src="<?php echo UPLOAD_URL . $item['image']; ?>" alt="<?php echo $item['title']; ?>"
                                    class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-500">
                                    <i class="fas fa-image text-4xl"></i>
                                </div>
                            <?php endif; ?>

                            <!-- Match percentage badge -->
                            <div class="absolute top-2 right-2 bg-blue-600 text-white text-sm font-bold px-2 py-1 rounded-full">
                                <?php echo $item['match_percentage']; ?>% match
                            </div>
                        </div>
                        <div class="p-4 flex-grow">
                            <div class="flex justify-between items-start mb-2">
                                <span
                                    class="inline-block px-3 py-1 rounded-full text-sm font-semibold 
                                    <?php echo $item['type'] == 'lost' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                    <?php echo ucfirst($item['type']); ?>
                                </span>
                                <span class="text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($item['date_lost_found'])); ?>
                                </span>
                            </div>
                            <h3 class="text-xl font-bold mb-2"><?php echo $item['title']; ?></h3>
                            <p class="text-gray-600 mb-4">
                                <?php echo substr($item['description'], 0, 100) . (strlen($item['description']) > 100 ? '...' : ''); ?>
                            </p>
                            <div class="text-sm text-gray-500 mb-4">
                                <p><i class="fas fa-map-marker-alt mr-1"></i> <?php echo $item['location']; ?></p>
                                <p><i class="fas fa-tag mr-1"></i> <?php echo $item['category']; ?></p>
                            </div>
                        </div>
                        <div class="px-4 py-3 bg-gray-50 border-t text-center">
                            <a href="item-details.php?id=<?php echo $item['id']; ?>"
                                class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium">
                                View Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-4 rounded-lg mb-6">
                <p>No items found matching your search criteria. Please try using different keywords or filters.</p>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-bold mb-3">Search Tips:</h3>
                <ul class="list-disc pl-5 space-y-2 text-gray-600">
                    <li>Use shorter, simpler keywords</li>
                    <li>Check for spelling errors</li>
                    <li>Try searching by category only</li>
                    <li>Broaden your search by removing filters</li>
                </ul>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="bg-white p-6 rounded-lg shadow-md text-center">
            <h2 class="text-2xl font-bold mb-4">Start Your Search</h2>
            <p class="text-gray-600">Enter keywords above to search for lost or found items.</p>
        </div>
    <?php endif; ?>
</div>

<?php include_once 'includes/footer.php'; ?>