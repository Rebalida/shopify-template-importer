<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSV Importer - GRS Template</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            900: '#1e3a8a'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        .file-upload-input {
            position: absolute;
            left: -9999px;
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .table-scroll {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e0 #f7fafc;
        }
        .table-scroll::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        .table-scroll::-webkit-scrollbar-track {
            background: #f7fafc;
        }
        .table-scroll::-webkit-scrollbar-thumb {
            background-color: #cbd5e0;
            border-radius: 4px;
        }
        .nav-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            min-width: 200px;
            z-index: 50;
        }
        .nav-dropdown.active {
            display: block;
        }
        .nav-item {
            position: relative;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-6xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <!-- Logo and Title -->
                <div class="flex items-center space-x-3">
                    <a href="../dashboard/" class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <h1 class="text-2xl font-bold text-gray-900">GRS Template Importer</h1>
                    </a>
                </div>

                
                <!-- Navigation -->
                <nav class="flex items-center space-x-6">
                    <a href="../dashboard" class="text-gray-600 hover:text-primary-600 transition-colors duration-200">
                        <i class="fas fa-home mr-2"></i>Dashboard
                    </a>

                    <div class="nav-item">
                        <button onclick="toggleDropdown('userMenu')" class="flex items-center text-gray-600 hover:text-primary-600 transition-colors duration-200">
                            <i class="fas fa-user-circle mr-2"></i><?= ucfirst(strtolower(htmlspecialchars($_SESSION['role'] ?? 'Guest'))) ?>
                            <i class="fas fa-chevron-down ml-2 text-xs"></i>
                        </button>
                        <div id="userMenu" class="nav-dropdown">
                            <div class="py-2">
                                <div class="px-4 py-2 text-sm text-gray-500 border-b">
                                    Signed in as<br>
                                    <span class="font-medium text-gray-900"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></span>
                                </div>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <a href="../management/" class="block px-4 py-2 text-gray-700 hover:bg-primary-50 hover:text-primary-600">
                                    <i class="fas fa-users mr-2"></i>User Management
                                </a>
                                <a href="../settings" class="block px-4 py-2 text-gray-700 hover:bg-primary-50 hover:text-primary-600">
                                    <i class="fas fa-sliders mr-2"></i>Settings
                                </a>
                                <?php endif; ?>
                                <a href="../auth/logout.php" class="block px-4 py-2 text-gray-700 hover:bg-primary-50 hover:text-primary-600">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </div>

    <script>
        function toggleDropdown(menuId) {
            const menu = document.getElementById(menuId);
            const allDropdowns = document.querySelectorAll('.nav-dropdown');
            
            // Close all other dropdowns
            allDropdowns.forEach(dropdown => {
                if (dropdown.id !== menuId) {
                    dropdown.classList.remove('active');
                }
            });
            
            // Toggle current dropdown
            menu.classList.toggle('active');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.nav-item')) {
                document.querySelectorAll('.nav-dropdown').forEach(dropdown => {
                    dropdown.classList.remove('active');
                });
            }
        });
    </script>