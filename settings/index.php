<?php
    require_once '../config/config.php';
    

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['name'];
        $shop_url = $_POST['shop_url'];
        $access_token = $_POST['access_token'];

        // Hash the access token
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted_token = openssl_encrypt($access_token, 'AES-256-CBC', ENCRYPTION_KEY, 0, $iv);
        $token_data = base64_encode($encrypted_token . '::' . base64_encode($iv));

        try {
            $stmt = $pdo->prepare("INSERT INTO shops (name, shop_url, access_token) VALUES (?, ?, ?)");
            $stmt->execute([$name, $shop_url, $token_data]);
            
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
            <button onclick="openModal()" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200">
                <i class="fas fa-plus mr-2"></i>Add Shop
            </button>
        </div>

        <!-- List of Shops -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shop Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">URL</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Added On</th>
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
    <div id="shopModal" class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            
            <!-- Form View -->
            <div id="shopForm" class="text-center">
                <h3 class="text-xl font-semibold text-gray-900 mb-6">Add New Shop</h3>
                <form id="addShopForm" class="space-y-4">
                    <div class="text-left">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Shop Name</label>
                        <input type="text" name="name" required 
                               class="w-full p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>
                    <div class="text-left">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Shop URL</label>
                        <input type="url" name="shop_url" required placeholder="https://your-store.myshopify.com" 
                               class="w-full p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>
                    <div class="text-left">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Access Token</label>
                        <input type="password" name="access_token" required 
                               class="w-full p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>
                    <div class="flex justify-end space-x-3 mt-8">
                        <button type="button" onclick="closeModal()" 
                                class="px-6 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200">
                            Save Shop
                        </button>
                    </div>
                </form>
            </div>

            <!-- Validation Progress View (initially hidden) -->
            <div id="validationProgress" class="hidden text-center">
                <div id="validationLoader" class="flex flex-col items-center">
                    <svg class="animate-spin h-12 w-12 text-primary-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Validating Shop Credentials</h3>
                    <p class="text-gray-600">Please wait while we verify your Shopify access token...</p>
                </div>
                
                <div id="validationResults" class="hidden">
                    <div id="validationSuccess" class="hidden">
                        <div class="flex justify-center mb-4">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-check text-green-600 text-xl"></i>
                            </div>
                        </div>
                        <h3 class="text-lg font-semibold text-green-600 mb-2">Credentials Validated!</h3>
                        <p class="text-gray-600 mb-4">Shop credentials are valid. Saving to database...</p>
                    </div>
                    
                    <div id="validationError" class="hidden">
                        <div class="flex justify-center mb-4">
                            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-times text-red-600 text-xl"></i>
                            </div>
                        </div>
                        <h3 class="text-lg font-semibold text-red-600 mb-2">Validation Failed</h3>
                        <p id="errorMessage" class="text-gray-600 mb-6"></p>
                        <button onclick="showForm()" 
                                class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200">
                            Try Again
                        </button>
                    </div>
                </div>
            </div>

            <!-- Close button (shown for successful completion) -->
            <button id="closeButton" onclick="closeModal()" class="hidden mt-6 w-full px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200">
                Close
            </button>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('shopModal').classList.remove('hidden');
            showForm();
        }

        function closeModal() {
            document.getElementById('shopModal').classList.add('hidden');
            document.getElementById('addShopForm').reset();
            showForm(); // Reset to form view
        }

        function showForm() {
            document.getElementById('shopForm').classList.remove('hidden');
            document.getElementById('validationProgress').classList.add('hidden');
            document.getElementById('closeButton').classList.add('hidden');
        }

        function showValidationProgress() {
            document.getElementById('shopForm').classList.add('hidden');
            document.getElementById('validationProgress').classList.remove('hidden');
            document.getElementById('validationLoader').classList.remove('hidden');
            document.getElementById('validationResults').classList.add('hidden');
        }

        function showValidationSuccess() {
            document.getElementById('validationLoader').classList.add('hidden');
            document.getElementById('validationResults').classList.remove('hidden');
            document.getElementById('validationSuccess').classList.remove('hidden');
            document.getElementById('validationError').classList.add('hidden');
        }

        function showValidationError(message) {
            document.getElementById('validationLoader').classList.add('hidden');
            document.getElementById('validationResults').classList.remove('hidden');
            document.getElementById('validationSuccess').classList.add('hidden');
            document.getElementById('validationError').classList.remove('hidden');
            document.getElementById('errorMessage').textContent = message;
        }

        function showSuccess() {
            document.getElementById('validationLoader').classList.add('hidden');
            document.getElementById('validationResults').classList.add('hidden');
            document.getElementById('closeButton').classList.remove('hidden');
            
            // Show success message
            const progressDiv = document.getElementById('validationProgress');
            progressDiv.innerHTML = `
                <div class="flex flex-col items-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-check text-green-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-green-600 mb-2">Shop Added Successfully!</h3>
                    <p class="text-gray-600">Your shop credentials have been validated and saved.</p>
                </div>
            `;
        }

        document.getElementById('addShopForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            showValidationProgress();

            // First, validate the credentials
            fetch('validate_shop.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showValidationSuccess();
                    
                    // Wait a moment to show success message, then save to database
                    setTimeout(() => {
                        return fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                showSuccess();
                                // Auto-close and refresh after 2 seconds
                                setTimeout(() => {
                                    closeModal();
                                    window.location.reload();
                                }, 2000);
                            } else {
                                showValidationError('Failed to save shop credentials to database');
                            }
                        });
                    }, 1500);
                } else {
                    showValidationError(data.message || 'Failed to validate credentials');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showValidationError('An error occurred while processing your request');
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