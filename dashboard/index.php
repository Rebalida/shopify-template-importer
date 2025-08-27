<?php
    session_start();
        if (!isset($_SESSION['user'])) {
            header("Location: ../auth/login.php");
            exit();
    }

    $rows = [];
    $header = [];
    $fileName = null;
    $totalRows = 0;
    $previewLimit = 20;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
        $uploadDir = __DIR__ . '/../storage/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . "_" . basename($_FILES['csv_file']['name']);
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $filePath)) {
            if (($handle = fopen($filePath, "r")) !== false) {
                $header = fgetcsv($handle, 1000, ",");
                $count = 0;
                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                    if ($count < $previewLimit) {
                        $rows[] = $data;
                    }
                    $count++;
                    $totalRows++;
                }
                fclose($handle);
            }
        } else {
            $error = "File upload failed.";
        }
    }
?>
<?php include '../templates/header.php'; ?>

    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Upload Card -->
        <div class="bg-white shadow-lg rounded-2xl p-8 mb-8">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-semibold text-gray-900 mb-2">Upload CSV File</h2>
                <p class="text-gray-600">Select a CSV file to preview and convert to Shopify format</p>
            </div>

            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="max-w-md mx-auto">
                    <div class="mb-6">
                        <label for="csv_file" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-xl cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors duration-200">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-8 h-8 mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                <p class="text-xs text-gray-500">CSV files only</p>
                            </div>
                            <input id="csv_file" name="csv_file" type="file" accept=".csv" required class="hidden">
                        </label>
                    </div>
                    
                    <button type="submit" class="w-full px-8 py-4 bg-gradient-to-r from-primary-600 to-primary-700 text-white font-semibold rounded-xl hover:from-primary-700 hover:to-primary-900 transform hover:scale-105 transition-all duration-200 shadow-lg">
                        
                        <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        Upload & Preview CSV
                    </button>
                </div>
            </form>

            <?php if (isset($error)): ?>
                <div class="mt-6 max-w-md mx-auto">
                    <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg">
                        <div class="flex">
                            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="ml-3 text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Preview Section -->
        <?php if (!empty($rows)): ?>
        <div class="fade-in">
            <!-- File Info Card -->
            <div class="bg-white shadow-lg rounded-2xl p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">File Uploaded Successfully</h3>
                            <p class="text-sm text-gray-600">Filename: <span class="font-medium"><?php echo htmlspecialchars($fileName); ?></span></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-primary-600"><?php echo number_format($totalRows); ?></div>
                        <div class="text-sm text-gray-500">Total Rows</div>
                    </div>
                </div>
            </div>

            <!-- Preview Table Card -->
            <div class="bg-white shadow-lg rounded-2xl overflow-hidden">
                <div class="bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-semibold text-white">Data Preview</h3>
                        <div class="text-primary-100 text-sm">
                            Showing <?php echo count($rows); ?> of <?php echo number_format($totalRows); ?> rows
                            <?php if ($totalRows > $previewLimit): ?>
                                <span class="bg-primary-500 px-2 py-1 rounded-full text-xs ml-2">Limited Preview</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <div class="overflow-x-auto table-scroll" style="max-height: 600px;">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-gray-100">
                                <tr>
                                    <th class="bg-gray-200 px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider border-r border-gray-300 w-12">#</th>
                                    <?php foreach ($header as $index => $col): ?>
                                        <th class="bg-gray-200 px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider <?php echo $index < count($header) - 1 ? 'border-r border-gray-300' : ''; ?> min-w-32">
                                            <?php echo htmlspecialchars($col); ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($rows as $rowIndex => $row): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-4 py-3 text-xs text-gray-500 border-r border-gray-200 bg-gray-50 font-medium">
                                        <?php echo $rowIndex + 1; ?>
                                    </td>
                                    <?php foreach ($row as $cellIndex => $cell): ?>
                                        <td class="px-4 py-3 text-sm text-gray-900 <?php echo $cellIndex < count($row) - 1 ? 'border-r border-gray-200' : ''; ?> max-w-48">
                                            <div class="truncate" title="<?php echo htmlspecialchars($cell); ?>">
                                                <?php echo !empty($cell) ? htmlspecialchars($cell) : '<span class="text-gray-400 italic">empty</span>'; ?>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($totalRows > $previewLimit): ?>
                    <div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-sm text-blue-700">
                                <strong>Note:</strong> Only the first <?php echo $previewLimit; ?> rows are shown in this preview. 
                                All <?php echo number_format($totalRows); ?> rows will be processed during conversion or import.
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-8 flex flex-col sm:flex-row justify-center gap-4">
                <a href="convert/run.php?file=<?php echo urlencode($fileName); ?>"
                    class="flex items-center justify-center px-8 py-4 bg-gradient-to-r from-green-600 to-green-700 text-white font-semibold rounded-xl hover:from-green-700 hover:to-green-800 transform hover:scale-105 transition-all duration-200 shadow-lg">
                    <i class="fa-solid fa-file-csv mr-2"></i>
                    Convert to Shopify CSV
                </a>

                
                <button type="button"
                    onclick="startImport('<?php echo urlencode($fileName); ?>')"
                    class="flex items-center justify-center px-8 py-4 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white font-semibold rounded-xl hover:from-indigo-700 hover:to-indigo-800 transform hover:scale-105 transition-all duration-200 shadow-lg">
                    Direct Import to Shopify
                </button>

            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Import Modal -->
    <div id="importModal" class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 text-center">
            <h3 class="text-xl font-semibold mb-4">Importing Products</h3>
            <div id="importLoader" class="flex flex-col items-center">
            <svg class="animate-spin h-10 w-10 text-blue-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
            <p class="text-gray-600">Please wait, importing products...</p>
            </div>
            <div id="importResults" class="hidden">
            <div class="text-green-600 font-semibold mb-3">Import Finished!</div>
            <ul id="importList" class="text-left text-sm space-y-2 max-h-60 overflow-y-auto"></ul>
            <button onclick="closeModal()" class="mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Close</button>
            </div>
        </div>
    </div>

    <script>
        // File upload handling
        const fileInput = document.getElementById('csv_file');
        const uploadForm = document.getElementById('uploadForm');
        
        fileInput.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                const label = e.target.closest('label');
                const textElement = label.querySelector('p');
                textElement.innerHTML = `<span class="font-semibold text-primary-600">${fileName}</span> selected`;
            }
        });

        // Drag and drop functionality
        const dropZone = document.querySelector('label[for="csv_file"]');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropZone.classList.add('border-primary-500', 'bg-primary-50');
        }

        function unhighlight(e) {
            dropZone.classList.remove('border-primary-500', 'bg-primary-50');
        }

        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                fileInput.files = files;
                const fileName = files[0].name;
                const textElement = dropZone.querySelector('p');
                textElement.innerHTML = `<span class="font-semibold text-primary-600">${fileName}</span> selected`;
            }
        }
    </script>

    <script>
        function startImport(fileName) {
            // Show modal
            document.getElementById('importModal').classList.remove('hidden');
            document.getElementById('importLoader').classList.remove('hidden');
            document.getElementById('importResults').classList.add('hidden');

            fetch(`import/run.php?file=${fileName}`)
            .then(async response => {
                const contentType = response.headers.get('content-type');
                const text = await response.text();

                // Debug logging
                console.log('Response status:', response.status);
                console.log('Content-Type:', contentType);
                console.log('Raw response:', text);

                // Check if response is actually JSON
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error(`Invalid content type: ${contentType}. Raw response: ${text}`);
                }

                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error(`Failed to parse JSON: ${e.message}. Raw response: ${text}`);
                }
            })
            .then(data => {
                document.getElementById('importLoader').classList.add('hidden');
                document.getElementById('importResults').classList.remove('hidden');

                const list = document.getElementById('importList');
                list.innerHTML = "";

                if (data.results) {
                    data.results.forEach(r => {
                        const li = document.createElement('li');
                        li.classList.add("flex", "items-center", "space-x-2", "p-2");
                        
                        const icon = document.createElement('i');
                        if (r.status === "success") {
                            icon.className = "fa-solid fa-circle-check text-green-600";
                            li.classList.add("bg-green-50");
                        } else {
                            icon.className = "fa-solid fa-circle-xmark text-red-600";
                            li.classList.add("bg-red-50");
                        }

                        const text = document.createElement('span');
                        text.textContent = r.title;
                        
                        // Add error details if present
                        if (r.error) {
                            const errorText = document.createElement('span');
                            errorText.textContent = ` - Error: ${r.error}`;
                            errorText.classList.add("text-red-600", "ml-2");
                            text.appendChild(errorText);
                        }

                        li.appendChild(icon);
                        li.appendChild(text);
                        list.appendChild(li);
                    });
                }
            })
            .catch(err => {
                document.getElementById('importLoader').classList.add('hidden');
                document.getElementById('importResults').classList.remove('hidden');
                
                const list = document.getElementById('importList');
                list.innerHTML = `
                    <li class='flex items-center space-x-2 p-4 bg-red-50 text-red-600 rounded'>
                        <i class='fa-solid fa-triangle-exclamation'></i>
                        <div class='flex flex-col'>
                            <span class='font-semibold'>Import Error:</span>
                            <span class='text-sm whitespace-pre-wrap'>${err.message}</span>
                        </div>
                    </li>`;
            });
        }

        function closeModal() {
            document.getElementById('importModal').classList.add('hidden');
        }
    </script>

</body>
</html>