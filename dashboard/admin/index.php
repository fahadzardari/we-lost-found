<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
  redirect('auth/login.php');
}

// Get statistics
try {
  // Count total users
  $stmt = $conn->query("SELECT COUNT(*) FROM users");
  $total_users = $stmt->fetchColumn();

  // Count active users
  $stmt = $conn->query("SELECT COUNT(*) FROM users WHERE status = 'active'");
  $active_users = $stmt->fetchColumn();

  // Count banned users
  $stmt = $conn->query("SELECT COUNT(*) FROM users WHERE status = 'banned'");
  $banned_users = $stmt->fetchColumn();

  // Count total items
  $stmt = $conn->query("SELECT COUNT(*) FROM items");
  $total_items = $stmt->fetchColumn();

  // Count lost items
  $stmt = $conn->query("SELECT COUNT(*) FROM items WHERE type = 'lost'");
  $lost_items = $stmt->fetchColumn();

  // Count found items
  $stmt = $conn->query("SELECT COUNT(*) FROM items WHERE type = 'found'");
  $found_items = $stmt->fetchColumn();

  // Count resolved items
  $stmt = $conn->query("SELECT COUNT(*) FROM items WHERE status = 'resolved'");
  $resolved_items = $stmt->fetchColumn();

  // Count potential matches
  $stmt = $conn->query("SELECT COUNT(*) FROM matches");
  $total_matches = $stmt->fetchColumn();

  // Count confirmed matches
  $stmt = $conn->query("SELECT COUNT(*) FROM matches WHERE status = 'confirmed'");
  $confirmed_matches = $stmt->fetchColumn();

  // Get recent items
  $stmt = $conn->query("
        SELECT i.*, u.name as user_name, 
               (SELECT image_path FROM item_images WHERE item_id = i.id LIMIT 1) as image
        FROM items i
        JOIN users u ON i.user_id = u.id
        ORDER BY i.created_at DESC
        LIMIT 5
    ");
  $recent_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
  // Handle any database errors
}

// Include header
include_once '../../includes/header.php';

// Include admin sidebar
include_once '../../includes/sidebar.php';
?>

<!-- Admin Dashboard Content -->
<h1 class="text-2xl font-bold">Admin Dashboard</h1>
<p class="text-gray-600 mb-6">Welcome to the administrative panel.</p>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="p-4 bg-blue-600 text-white">
      <h3 class="font-bold text-lg">Total Users</h3>
    </div>
    <div class="p-4">
      <p class="text-3xl font-bold"><?php echo $total_users; ?></p>
      <p class="text-sm text-gray-600">
        <span class="text-green-600"><?php echo $active_users; ?> active</span> -
        <span class="text-red-600"><?php echo $banned_users; ?> banned</span>
      </p>
    </div>
  </div>

  <div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="p-4 bg-red-600 text-white">
      <h3 class="font-bold text-lg">Lost Items</h3>
    </div>
    <div class="p-4">
      <p class="text-3xl font-bold"><?php echo $lost_items; ?></p>
      <p class="text-sm text-gray-600"><?php echo round(($lost_items / max(1, $total_items) * 100)); ?>% of all items
      </p>
    </div>
  </div>

  <div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="p-4 bg-green-600 text-white">
      <h3 class="font-bold text-lg">Found Items</h3>
    </div>
    <div class="p-4">
      <p class="text-3xl font-bold"><?php echo $found_items; ?></p>
      <p class="text-sm text-gray-600"><?php echo round(($found_items / max(1, $total_items) * 100)); ?>% of all items
      </p>
    </div>
  </div>

  <div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="p-4 bg-yellow-600 text-white">
      <h3 class="font-bold text-lg">Matches</h3>
    </div>
    <div class="p-4">
      <p class="text-3xl font-bold"><?php echo $total_matches; ?></p>
      <p class="text-sm text-gray-600"><?php echo $confirmed_matches; ?> confirmed matches</p>
    </div>
  </div>
</div>

<!-- Items Status Chart -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
  <div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="p-4 border-b">
      <h3 class="font-bold text-lg">Items Status</h3>
    </div>
    <div class="p-4">
      <div class="h-64">
        <canvas id="itemsChart"></canvas>
      </div>
    </div>
  </div>

  <div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="p-4 border-b">
      <h3 class="font-bold text-lg">Resolution Rate</h3>
    </div>
    <div class="p-4">
      <div class="h-64">
        <canvas id="resolutionChart"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Recent Items Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
  <div class="p-4 border-b">
    <h3 class="font-bold text-lg">Recent Items</h3>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full">
      <thead class="bg-gray-100">
        <tr>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-gray-200">
        <?php foreach ($recent_items as $item): ?>
          <tr>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="h-10 w-10 flex-shrink-0 mr-3">
                  <?php if ($item['image']): ?>
                    <img class="h-10 w-10 rounded object-cover" src="<?php echo UPLOAD_URL . $item['image']; ?>"
                      alt="<?php echo $item['title']; ?>">
                  <?php else: ?>
                    <div class="h-10 w-10 rounded bg-gray-200 flex items-center justify-center text-gray-500">
                      <i class="fas fa-image"></i>
                    </div>
                  <?php endif; ?>
                </div>
                <div>
                  <div class="text-sm font-medium text-gray-900"><?php echo $item['title']; ?></div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span
                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                  <?php echo $item['type'] == 'lost' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                <?php echo ucfirst($item['type']); ?>
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              <?php echo $item['category']; ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              <?php echo $item['user_name']; ?>
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
              <a href="view-item.php?id=<?php echo $item['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                View
              </a>
              <a href="manage-reports.php?action=mark_spam&id=<?php echo $item['id']; ?>"
                class="text-red-600 hover:text-red-900"
                onclick="return confirm('Are you sure you want to mark this item as spam?')">
                Mark as Spam
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="p-4 border-t text-right">
    <a href="manage-reports.php" class="text-blue-600 hover:underline">View all reports →</a>
  </div>
</div>

</div>
</main>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
<script>
  // Prepare chart data
  document.addEventListener('DOMContentLoaded', function () {
    // Items Status Chart
    const itemsCtx = document.getElementById('itemsChart').getContext('2d');
    const itemsChart = new Chart(itemsCtx, {
      type: 'doughnut',
      data: {
        labels: ['Lost', 'Found'],
        datasets: [{
          data: [<?php echo $lost_items; ?>, <?php echo $found_items; ?>],
          backgroundColor: [
            'rgba(239, 68, 68, 0.6)',
            'rgba(16, 185, 129, 0.6)'
          ],
          borderColor: [
            'rgba(239, 68, 68, 1)',
            'rgba(16, 185, 129, 1)'
          ],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom'
          }
        }
      }
    });

    // Resolution Rate Chart
    const resolutionCtx = document.getElementById('resolutionChart').getContext('2d');
    const resolutionChart = new Chart(resolutionCtx, {
      type: 'pie',
      data: {
        labels: ['Active', 'Resolved', 'Spam/Deleted'],
        datasets: [{
          data: [
            <?php echo $total_items - $resolved_items - ($total_items - $lost_items - $found_items); ?>,
            <?php echo $resolved_items; ?>,
            <?php echo $total_items - $lost_items - $found_items; ?>
          ],
          backgroundColor: [
            'rgba(59, 130, 246, 0.6)',
            'rgba(16, 185, 129, 0.6)',
            'rgba(107, 114, 128, 0.6)'
          ],
          borderColor: [
            'rgba(59, 130, 246, 1)',
            'rgba(16, 185, 129, 1)',
            'rgba(107, 114, 128, 1)'
          ],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom'
          }
        }
      }
    });
  });
</script>

<?php include_once '../../includes/footer.php'; ?>