<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

try {
    $stmt = $conn->query("
        SELECT i.*, u.name as user_name, 
               (SELECT image_path FROM item_images WHERE item_id = i.id LIMIT 1) as image
        FROM items i
        JOIN users u ON i.user_id = u.id
        WHERE i.status = 'active'
        AND NOT EXISTS (
            SELECT 1 FROM claims c 
            WHERE c.item_id = i.id AND c.status = 'approved'
        )
        ORDER BY i.created_at DESC
        LIMIT 6
    ");
    $recent_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_items = [];
}

include_once 'includes/header.php';
?>

<!-- Hero Section -->
<div class="bg-blue-600 text-white py-12 mb-8">
    <div class="container mx-auto px-4 text-center">
        <h1 class="text-4xl font-bold mb-4">Smart Lost & Found Portal</h1>
        <p class="text-xl mb-8">A simple way to report lost items or submit items you've found</p>
        <?php if (!isLoggedIn()): ?>
            <div class="flex flex-col md:flex-row gap-4 justify-center">
                <a href="<?php echo BASE_URL; ?>/auth/register.php"
                    class="bg-white text-blue-600 hover:bg-gray-100 px-6 py-3 rounded-lg font-medium">
                    Register Now
                </a>
                <a href="<?php echo BASE_URL; ?>/auth/login.php"
                    class="bg-transparent border-2 border-white hover:bg-white hover:text-blue-600 px-6 py-3 rounded-lg font-medium">
                    Login
                </a>
            </div>
        <?php else: ?>
            <div class="flex flex-col md:flex-row gap-4 justify-center">
                <a href="<?php echo BASE_URL; ?>/dashboard/user/report-lost.php"
                    class="bg-white text-blue-600 hover:bg-gray-100 px-6 py-3 rounded-lg font-medium">
                    Report Lost Item
                </a>
                <a href="<?php echo BASE_URL; ?>/dashboard/user/report-found.php"
                    class="bg-transparent border-2 border-white hover:bg-white hover:text-blue-600 px-6 py-3 rounded-lg font-medium">
                    Report Found Item
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- How It Works Section -->
<div class="container mx-auto px-4 py-8">
    <h2 class="text-3xl font-bold text-center mb-8">How It Works</h2>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="bg-white p-6 rounded-lg shadow-md text-center">
            <div
                class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-2xl mx-auto mb-4">
                <i class="fas fa-user-plus"></i>
            </div>
            <h3 class="text-xl font-bold mb-2">Register</h3>
            <p class="text-gray-600">Create an account to access all features of our portal</p>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md text-center">
            <div
                class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-2xl mx-auto mb-4">
                <i class="fas fa-file-alt"></i>
            </div>
            <h3 class="text-xl font-bold mb-2">Report</h3>
            <p class="text-gray-600">Report your lost item or an item you found</p>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md text-center">
            <div
                class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-2xl mx-auto mb-4">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <h3 class="text-xl font-bold mb-2">Match & Retrieve</h3>
            <p class="text-gray-600">Our system automatically matches lost and found items</p>
        </div>
    </div>
</div>

<!-- Recent Items Section -->
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-3xl font-bold">Recent Items</h2>
        <a href="<?php echo BASE_URL; ?>/search.php" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-search mr-1"></i> Search All Items
        </a>
    </div>

    <?php if (count($recent_items) > 0): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
            <?php foreach ($recent_items as $item): ?>
                <a href="<?php echo BASE_URL; ?>/item-details.php?id=<?php echo $item['id']; ?>" class="block">
                    <div class="bg-white rounded-lg overflow-hidden shadow-md hover:shadow-lg transition duration-200 h-full flex flex-col">
                        <div class="h-48 bg-gray-200">
                            <?php if ($item['image']): ?>
                                <img src="<?php echo UPLOAD_URL . $item['image']; ?>" alt="<?php echo $item['title']; ?>"
                                    class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-500">
                                    <i class="fas fa-image text-4xl"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-4 flex-grow flex flex-col">
                            <span
                                class="inline-block px-3 py-1 rounded-full text-sm font-semibold self-start
                                <?php echo $item['type'] == 'lost' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?> mb-2">
                                <?php echo ucfirst($item['type']); ?>
                            </span>
                            <h3 class="text-xl font-bold mb-2"><?php echo $item['title']; ?></h3>
                            <p class="text-gray-600 mb-2 flex-grow">
                                <?php echo substr($item['description'], 0, 100) . (strlen($item['description']) > 100 ? '...' : ''); ?>
                            </p>
                            <div class="flex justify-between text-sm text-gray-500 mt-2">
                                <span><i class="fas fa-map-marker-alt mr-1"></i> <?php echo $item['location']; ?></span>
                                <span><i class="far fa-calendar-alt mr-1"></i>
                                    <?php echo date('M d, Y', strtotime($item['date_lost_found'])); ?></span>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 border-t">
                            <span class="text-blue-600 font-medium">View Details <i class="fas fa-arrow-right ml-1"></i></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-8">
            <a href="<?php echo BASE_URL; ?>/search.php" 
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium">
                Search All Items
            </a>
        </div>
    <?php else: ?>
        <div class="text-center bg-gray-100 py-8 rounded-lg">
            <p class="text-gray-600">No items have been posted yet.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Benefits Section -->
<div class="bg-gray-100 py-12 mt-8">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold text-center mb-8">Why Use Our Portal?</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="flex items-start">
                <div class="flex-shrink-0 bg-blue-100 rounded-full p-3 mr-4">
                    <i class="fas fa-bolt text-blue-600"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold mb-2">Fast Matching Algorithm</h3>
                    <p class="text-gray-600">Our system automatically matches lost and found items based on
                        descriptions, locations, and dates.</p>
                </div>
            </div>

            <div class="flex items-start">
                <div class="flex-shrink-0 bg-blue-100 rounded-full p-3 mr-4">
                    <i class="fas fa-lock text-blue-600"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold mb-2">Secure Communication</h3>
                    <p class="text-gray-600">Contact information is only shared when a match is confirmed by both
                        parties.</p>
                </div>
            </div>

            <div class="flex items-start">
                <div class="flex-shrink-0 bg-blue-100 rounded-full p-3 mr-4">
                    <i class="fas fa-images text-blue-600"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold mb-2">Image Verification</h3>
                    <p class="text-gray-600">Upload images of found items to help owners identify their belongings.</p>
                </div>
            </div>

            <div class="flex items-start">
                <div class="flex-shrink-0 bg-blue-100 rounded-full p-3 mr-4">
                    <i class="fas fa-shield-alt text-blue-600"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold mb-2">Spam Prevention</h3>
                    <p class="text-gray-600">Our admin team monitors and removes fake or inappropriate reports.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>