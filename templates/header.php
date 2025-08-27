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
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-6xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <a href="../dashboard"><h1 class="text-2xl font-bold text-gray-900">GRS Template Importer</h1></a>
                </div>
                <div class="text-gray-500 flex space-x-4">
                    <a href="../settings">Settings</a>
                    <a href="../auth/logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>