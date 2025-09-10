<?php
    require_once '../config/config.php';
    require_once '../auth/middleware.php';
    checkAuth('admin');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $userId = $_POST['user_id'] ?? null;
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $role = $_POST['role'];
        $password = $_POST['password'] ?? '';

        $errors = [];

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address";
        }

        // Check if email already exists (for new users or different user)
        try {
            if ($userId) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $userId]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
            }
            if ($stmt->fetch()) {
                $errors[] = "Email address already exists";
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $errors[] = "Database error occurred";
        }

        // Validate names
        if (empty($firstName)) {
            $errors[] = "First name is required";
        }
        if (empty($lastName)) {
            $errors[] = "Last name is required";
        }

        // Validate role
        if (!in_array($role, ['admin', 'user'])) {
            $errors[] = "Invalid role selected";
        }

        // Password validation (only for new users or when password is provided)
        if (!$userId || !empty($password)) {
            if (strlen($password) < 8) {
                $errors[] = "Password must be at least 8 characters long";
            }
            if (!preg_match('/[A-Z]/', $password)) {
                $errors[] = "Password must contain at least one uppercase letter";
            }
            if (!preg_match('/[a-z]/', $password)) {
                $errors[] = "Password must contain at least one lowercase letter";
            }
            if (!preg_match('/[0-9]/', $password)) {
                $errors[] = "Password must contain at least one number";
            }
        }

        if (empty($errors)) {
            try {
                if ($userId) {
                    // Update existing user
                    if (!empty($password)) {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, password_hash = ? WHERE id = ?");
                        $stmt->execute([$firstName, $lastName, $email, $role, $passwordHash, $userId]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ? WHERE id = ?");
                        $stmt->execute([$firstName, $lastName, $email, $role, $userId]);
                    }
                } else {
                    // Create new user
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, role, password_hash, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())");
                    $stmt->execute([$firstName, $lastName, $email, $role, $passwordHash]);
                }
                
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success']);
                exit;
            } catch (PDOException $e) {
                error_log("User operation error: " . $e->getMessage());
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Failed to save user']);
                exit;
            }
        } else {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => implode(', ', $errors)]);
            exit;
        }
    }

    $editUser = null;
    if (isset($_GET['edit'])) {
        try {
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, role FROM users WHERE id = ?");
            $stmt->execute([$_GET['edit']]);
            $editUser = $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error fetching user: " . $e->getMessage());
        }
    }

    $users = $pdo->query("SELECT id, first_name, last_name, email, role, created_at, last_login FROM users ORDER BY created_at DESC")->fetchAll();
?>

<?php include '../templates/header.php'; ?>

    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Title and Button -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900">User Management</h2>
            <button onclick="openModal()" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200">
                <i class="fas fa-user-plus mr-2"></i>Add User
            </button>
        </div>

        <!-- User Table -->
         <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach($users as $user): ?>
                    <tr>

                        <!-- Name -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                            </div>
                        </td>
                        
                        <!-- Email -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-500">
                                <?= htmlspecialchars($user['email']) ?>
                            </div>
                        </td>

                        <!-- Role -->
                        <td class="px-6 py-4 whitespace-nowrap">
                           <span class="px-2 pb-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?= $user['role'] === 'admin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' ?>">
                                <?= ucfirst($user['role'])?>
                            </span>
                        </td>

                        <!-- Created At -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-500">
                                <?= date('M d, Y', strtotime($user['created_at'])) ?>
                            </div>
                        </td>

                        <!-- Last Login -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-500">
                                <?= $user['last_login'] ? date('M d, Y', strtotime($user['last_login'])) : 'Never' ?>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                            <button onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)" 
                                    class="text-indigo-600 hover:text-indigo-900">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <button onclick="deleteUser(<?= $user['id'] ?>)" class="text-red-600 hover:text-red-900">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
         </div>
    </div>

    <!-- Modal -->
    <div id="userModal" class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
            <div id="userForm" class="text-center">
                <h3 id="modalTitle" class="text-xl font-semibold text-gray-900 mb-6">Add New User</h3>
                <form id="userFormElement" class="space-y-4">
                    <input type="hidden" id="userId" name="user_id" value="">
                    
                    <!-- Name -->
                    <div class="grid grid-cols-2 gap-4">
                        <!-- First Name -->
                        <div class="text-left">
                            <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                            <input type="text" id="firstName" name="first_name" required class="w-full p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        </div>

                        <!-- Last Name -->
                        <div class="text-left">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                            <input type="text" id="lastName" name="last_name" required class="w-full p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="text-left">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" id="email" name="email" required class="w-full p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>

                    <!-- Role -->
                    <div class="text-left">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                        <select name="role" id="role" required class="w-full p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <!-- Password -->
                    <div class="text-left">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Password <span class="text-sm text-gray-500" id="passwordHint">(8+ chars, uppercase, lowercase, number)</span>
                        </label>
                        <div class="relative">
                            <input type="password" id="password" name="password" 
                                    class="w-full p-3 pr-10 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <button type="button" onclick="togglePassword()" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <i id="passwordIcon" class="fas fa-eye text-gray-400"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-8">
                        <button type="button" onclick="closeModal()" class="px-6 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200">
                            <span id="submitText">Create User</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-2xl shadonw-2xl w-full max-w-md p-6">
            <div class="text-center space-y-5">
                <h3 class="text-xl font-semibold text-gray-900 mb-4">Confirm Delete</h3>
                <p>Are you sure you want to delete this user? This action cannot be undone</p>
                <input type="hidden" id="deleteUserId">
                <div class="flex justify-end space-x-3">
                    <button onclick="closeDeleteModal()" class="px-6 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                        Cancel
                    </button>
                    <button onclick="confirmDelete()" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let isEditMode = false;

        function openModal() {
            document.getElementById('userModal').classList.remove('hidden');
            resetForm();
        }

        function closeModal() {
            document.getElementById('userModal').classList.add('hidden');
            resetForm();
        }

        function resetForm() {
            document.getElementById('userFormElement').reset();
            document.getElementById('userId').value = '';
            document.getElementById('modalTitle').textContent = 'Add New User';
            document.getElementById('submitText').textContent = 'Create User';
            document.getElementById('passwordHint').textContent = '(8+ chars, uppercase, lowercase, number)';
            document.getElementById('password').required = true;
            isEditMode = false;
        }

        function editUser(user) {
            isEditMode = true;
            document.getElementById('userModal').classList.remove('hidden');
            
            document.getElementById('userId').value = user.id;
            document.getElementById('firstName').value = user.first_name;
            document.getElementById('lastName').value = user.last_name;
            document.getElementById('email').value = user.email;
            document.getElementById('role').value = user.role;
            document.getElementById('password').value = '';
            document.getElementById('password').required = false;
            
            document.getElementById('modalTitle').textContent = 'Edit User';
            document.getElementById('submitText').textContent = 'Update User';
            document.getElementById('passwordHint').textContent = '(Leave blank to keep current password)';
        }

        function togglePassword() {
            const passwordField = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash text-gray-400';
            } else {
                passwordField.type = 'password';
                passwordIcon.className = 'fas fa-eye text-gray-400';
            }
        }

        document.getElementById('userFormElement').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    closeModal();
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to save user'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the user');
            });
        });

        function deleteUser(id) {
            document.getElementById('deleteUserId').value = id;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('deleteUserId').value = '';
        }

        function confirmDelete() {
            const id  = document.getElementById('deleteUserId').value;

            fetch(`delete_user.php?id=${id}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    closeDeleteModal();
                    window.location.reload();
                } else {
                    alert('Failed to delete user: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occured while deleting the user');
            });
        }

        // Auto-open edit modal if edit parameter is present
        <?php if ($editUser): ?>
            editUser(<?= json_encode($editUser) ?>);
        <?php endif; ?>
    </script>
    
</body>
</html>