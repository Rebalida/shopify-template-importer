<?php
    require_once '../config/config.php';
    

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['name'];
        $shop_url = $_POST['shop_url'];
        $access_token = $_POST['access_token'];

        // Hash the access toke
        $hashed_token = password_hash($access_token, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO shops (name, shop_url, access_token) VALUES (?, ?, ?)");
            $stmt->execute([$name, $shop_url, $hashed_token]);
            
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success']);
            exit;
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to save shop credentials']);
            exit;
        }
    }
    

    $shops = $pdo->query("SELECT id, name, shop_url, created_at FROM shops ORDER BY created_at DESC")->fetchAll();
?>

<?php include  '../templates/header.php'; ?>

    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Title and Button -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Shop Settings</h2>
            <button onclick="openModal()" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200"><i class="fas fa-plus mr-2"></i>Add Shop</button>
        </div>

        <!-- List of Shops -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shop Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">URL</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Adden On</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($shops as $shop): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($shop['name']) ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-500">
                                <?= htmlspecialchars($shop['shop_url']) ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-500">
                                <?= date('M d, Y', strtotime($shop['created_at'])) ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button onclick="deleteShop(<?= $shop['id'] ?>)" class="text-red-600 hover:text-red-900">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal -->
    <div id="shopModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    Add New Shop
                </h3>
                <form id="shopForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Shop Name</label>
                        <input type="text" name="name" required class="p-2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Shop URL</label>
                        <input type="url" name="shop_url" required placeholder="https://your-store.myshopify.com" 
                                class="p-2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Access Token</label>
                        <input type="password" name="access_token" required 
                                class="p-2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeModal()" 
                                class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</button>
                        <button type="submit" 
                                class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('shopModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('shopModal').classList.add('hidden');
            document.getElementById('shopForm').reset();
        }

        document.getElementById('shopForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.reload();
                } else {
                    alert('Failed to save shop credentials');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the shop credentials');
            });
        });

        function deleteShop(id) {
            if (confirm('Are you sure you want to delete this shop?')) {
                fetch(`delete.php?id=${id}`, {
                    method: 'DELETE'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.location.reload();
                    } else {
                        alert('Failed to delete shop');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the shop');
                });
            }
        }
    </script>

</body>
</html>