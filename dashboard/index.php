<?php
    session_start();
    require_once '../auth/middleware.php';
    
    $headerStartRow = 9; // Headers start at row 9 (1-based)
    $headerStartColumn = 1; // Headers start at column A (1-based)
    
    checkAuth();

    $rows = [];
    $header = [];
    $fileName = null;
    $totalRows = 0;
    $previewLimit = 20;
    $templateType = null;

    // Define signature columns to identify template types
    $requiredGrsColumns = ['Reward Name', 'Brand', 'SKU'];
    $requiredMasterColumns = ['OLD CODES', 'Image', 'Supplier'];

    // Helper function to validate headers
    function validateHeader($header, $requiredColumns) {
        if (empty($header)) return false;
        return empty(array_diff($requiredColumns, $header));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && isset($_POST['template_type'])) {
        // Validate file type
        $uploadedFile = $_FILES['csv_file'];
        $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        $templateType = $_POST['template_type'];
        
        if ($fileExtension !== 'csv') {
            $error = "Please upload a CSV file only.";
        } elseif (!in_array($templateType, ['grs', 'master'])) {
            $error = "Invalid template type selected.";
        } else {
            $uploadDir = __DIR__ . '/../storage/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Add template type prefix to filename
            $fileName = $templateType . '_' . time() . "_" . basename($uploadedFile['name']);
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
                if (($handle = fopen($filePath, "r")) !== false) {
                    if ($templateType === 'master') {
                        // Master template processing
                        $currentRow = 1;
                        $headerFound = false;
                        
                        // Skip rows until we reach the header start row
                        while ($currentRow < $headerStartRow && ($data = fgetcsv($handle, 1000, ",")) !== false) {
                            $currentRow++;
                        }
                        
                        // Read header from the specified row
                        if ($currentRow == $headerStartRow && ($headerData = fgetcsv($handle, 1000, ",")) !== false) {
                            // Validate strictly the first N columns (A, B, C)
                            $headerSubset = array_map('trim', array_slice($headerData, 0, count($requiredMasterColumns)));

                            if ($headerSubset !== $requiredMasterColumns) {
                                $error = "File structure does not match the Master Template. "
                                    . "Row {$headerStartRow} must start with: " . implode(", ", $requiredMasterColumns);
                                $headerFound = false;
                            } else {
                                $headerFound = true;
                                $header = array_map('trim', $headerData);
                            }
                            $currentRow++;
                        }



                        if (!$headerFound) {
                            // Error is set either by validation failure or if header row wasn't found
                            if (!isset($error)) {
                                $error = "Could not find header at row {$headerStartRow} or file is empty.";
                            }
                            fclose($handle);
                        } else {
                            $dataRowCount = 0;
                            // Read data rows
                            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                                $rowData = array_slice($data, $headerStartColumn - 1, count($header));
                                if ($dataRowCount < $previewLimit) {
                                    $rows[] = $rowData;
                                }
                                $dataRowCount++;
                                $totalRows++;
                            }
                            fclose($handle);
                        }
                    } else {
                        // GRS template processing (standard CSV)
                        $headerData = fgetcsv($handle, 1000, ",");
                        if ($headerData) {
                            $header = array_map('trim', $headerData);

                            if (!validateHeader($header, $requiredGrsColumns)) {
                                $error = "File structure does not match the GRS Template. Please check the column headers.";
                                $header = []; 
                            } else {
                                $dataRowCount = 0;
                                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                                    if ($dataRowCount < $previewLimit) {
                                        $rows[] = $data;
                                    }
                                    $dataRowCount++;
                                    $totalRows++;
                                }
                            }
                        } else {
                            $error = "Could not read header from GRS template file or file is empty.";
                        }
                        fclose($handle);
                    }
                } else {
                    $error = "Could not open the uploaded file.";
                }
            } else {
                $error = "File upload failed.";
            }
        }
    }
?>

<?php include '../templates/header.php'; ?>

    <div class="max-w-6xl mx-auto px-4 py-8">
        <div class="bg-white shadow-lg rounded-2xl p-8 mb-8">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-semibold text-gray-900 mb-2">CSV Template Processor</h2>
                <p class="text-gray-600">Select a template type and upload your CSV file for conversion</p>
            </div>

            <div class="max-w-md mx-auto">
                <button onclick="openTemplateModal()" class="w-full px-8 py-4 bg-gradient-to-r from-primary-600 to-primary-700 text-white font-semibold rounded-xl hover:from-primary-700 hover:to-primary-900 transform hover:scale-105 transition-all duration-200 shadow-lg">
                    <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add a Template
                </button>
            </div>

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

        <?php if (!empty($rows) && $templateType): ?>
        <div class="fade-in">
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
                            <p class="text-sm text-gray-600">
                                Template: <span class="font-medium text-primary-600"><?php echo strtoupper($templateType); ?></span> | 
                                Filename: <span class="font-medium"><?php echo htmlspecialchars($fileName); ?></span>
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-primary-600"><?php echo number_format($totalRows); ?></div>
                        <div class="text-sm text-gray-500">Total Rows</div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-lg rounded-2xl overflow-hidden">
                <div class="bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-semibold text-white">Data Preview - <?php echo strtoupper($templateType); ?> Template</h3>
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

            <?php if (json_encode($rows) === false) {
                error_log("JSON encode error: " . json_last_error_msg());
            } ?>
            <script>
                const previewData = <?php echo json_encode($rows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
                const previewHeader = <?php echo json_encode($header, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
                const templateType = <?php echo json_encode($templateType); ?>;
            </script>

            <div class="mt-8 flex flex-col sm:flex-row justify-center gap-4">
                <?php if ($templateType === 'master'): ?>
                    <a onclick="validateAndConvert('run.php', '<?php echo urlencode($fileName); ?>')"
                        class="cursor-pointer flex items-center justify-center px-8 py-4 bg-gradient-to-r from-green-600 to-green-700 text-white font-semibold rounded-xl hover:from-green-700 hover:to-green-800 transform hover:scale-105 transition-all duration-200 shadow-lg">
                        <i class="fa-solid fa-file-csv mr-2"></i>
                        Convert to Shopify CSV
                    </a>
                    <a onclick="validateAndConvert('run_grs.php', '<?php echo urlencode($fileName); ?>')"
                        class="cursor-pointer flex items-center justify-center px-8 py-4 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold rounded-xl hover:from-blue-700 hover:to-blue-800 transform hover:scale-105 transition-all duration-200 shadow-lg">
                        <i class="fa-solid fa-file-csv mr-2"></i>
                        Convert to GRS CSV
                    </a>
                <?php elseif ($templateType === 'grs'): ?>
                    <a href="convert/run.php?file=<?php echo urlencode($fileName); ?>"
                        class="flex items-center justify-center px-8 py-4 bg-gradient-to-r from-green-600 to-green-700 text-white font-semibold rounded-xl hover:from-green-700 hover:to-green-800 transform hover:scale-105 transition-all duration-200 shadow-lg">
                        <i class="fa-solid fa-file-csv mr-2"></i>
                        Convert to Shopify CSV
                    </a>
                <?php endif; ?>

                <button type="button"
                    disabled
                    onclick="startImport('<?php echo urlencode($fileName); ?>')"
                    class="flex items-center justify-center px-8 py-4 bg-gradient-to-r from-gray-400 to-gray-500 text-white font-semibold rounded-xl cursor-not-allowed opacity-50">
                    Direct Import to Shopify
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div id="templateModal" class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6">
            <div class="text-center mb-6">
                <h3 class="text-2xl font-semibold text-gray-900 mb-2">Select Template Type</h3>
                <p class="text-gray-600">Choose the template type that matches your CSV file structure</p>
            </div>

            <form method="POST" enctype="multipart/form-data" id="templateForm">
                <input type="hidden" name="template_type" id="selectedTemplateType" value="">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="template-card border-2 border-gray-200 rounded-xl p-6 cursor-pointer hover:border-primary-500 transition-colors duration-200" data-template="grs">
                        <div class="text-center">
                            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <h4 class="text-lg font-semibold text-gray-900 mb-2">GRS Template</h4>
                            <p class="text-sm text-gray-600 mb-4">Standard GRS CSV format with reward data structure</p>
                            <div class="text-xs text-gray-500 bg-gray-50 p-3 rounded-lg text-left">
                                <div class="font-medium mb-1">Expected columns include:</div>
                                <div>• Reward Name • Brand • SKU</div>
                                <div>• Product Cost • MSRP • Status</div>
                                <div>• Category Code • Image URL</div>
                            </div>
                        </div>
                    </div>

                    <div class="template-card border-2 border-gray-200 rounded-xl p-6 cursor-pointer hover:border-primary-500 transition-colors duration-200" data-template="master">
                        <div class="text-center">
                            <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </div>
                            <h4 class="text-lg font-semibold text-gray-900 mb-2">Master Template</h4>
                            <p class="text-sm text-gray-600 mb-4">Complex CSV format with headers starting at row 9</p>
                            <div class="text-xs text-gray-500 bg-gray-50 p-3 rounded-lg text-left">
                                <div class="font-medium mb-1">Expected columns include:</div>
                                <div>• Product Name • CODE • Brand</div>
                                <div>• Features • Specifications</div>
                                <div>• Trade ex GST • RRP • Weight</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="fileUploadSection" class="hidden">
                    <div class="mb-6">
                        <label for="csv_file" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-xl cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors duration-200">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-8 h-8 mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <p id="upload-text" class="mb-2 text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                <p class="text-xs text-gray-500">CSV files only</p>
                            </div>
                            <input id="csv_file" name="csv_file" type="file" accept=".csv" required class="hidden">
                        </label>
                    </div>

                    <div class="flex justify-center gap-4">
                        <button type="button" onclick="closeTemplateModal()" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit" class="px-8 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white font-semibold rounded-xl hover:from-primary-700 hover:to-primary-900 transition-all duration-200">
                            Upload & Preview
                        </button>
                    </div>
                </div>

                <div id="templateButtons" class="flex justify-center gap-4">
                    <button type="button" onclick="closeTemplateModal()" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="button" id="selectTemplateBtn" onclick="selectTemplate()" disabled class="px-8 py-3 bg-gray-400 text-white font-semibold rounded-xl cursor-not-allowed">
                        Select Template
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="importModal" class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 text-center">

            <div id="shopSelection" class="mb-6">
                <h3 class="text-xl font-semibold mb-4">Select Shop for Import</h3>
                <div id="shopCards" class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                        
                </div>
            </div>
            
            <div id="importProgress" class="hidden text-center">
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
                    
                </div>
            </div>

            <button onclick="closeModal()" class="mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Close</button>
        </div>
    </div>

    <div id="warningModal" class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6">
            <div class="text-center mb-6">
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Warning!</h3>
                <p class="text-gray-600 text-sm leading-relaxed">
                    Some values in the 'Image' column do not look like valid URLs. 
                    Only valid URLs will be processed correctly.
                </p>
                <p class="text-gray-600 text-sm leading-relaxed mt-2">
                    Do you want to proceed with the conversion anyway?
                </p>
            </div>

            <div class="flex justify-center gap-4">
                <button type="button" onclick="closeWarningModal()" 
                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-colors duration-200">
                    Cancel
                </button>
                <button type="button" id="proceedWarningBtn" onclick="proceedWithConversion()" 
                    class="px-8 py-3 bg-gradient-to-r from-yellow-600 to-yellow-700 text-white font-semibold rounded-xl hover:from-yellow-700 hover:to-yellow-800 transition-all duration-200">
                    Proceed Anyway
                </button>
            </div>
        </div>
    </div>

    <script>
        let pendingConversion = null;

        function validateAndConvert(scriptName, fileName) {
            // Only run this check for the master template
            if (templateType !== 'master') {
                window.location.href = `convert/${scriptName}?file=${fileName}`;
                return;
            }

            const imageColumnIndex = previewHeader.indexOf('Image');
            
            // If there's no "Image" column, proceed without checking
            if (imageColumnIndex === -1) {
                window.location.href = `convert/${scriptName}?file=${fileName}`;
                return;
            }

            let hasInvalidUrl = false;
            for (const row of previewData) {
                const imageUrl = row[imageColumnIndex];
                // Check if the cell has a value but doesn't start with http:// or https://
                if (imageUrl && !imageUrl.startsWith('http://') && !imageUrl.startsWith('https://')) {
                    hasInvalidUrl = true;
                    break; 
                }
            }

            if (hasInvalidUrl) {
                // Store the conversion details for later use
                pendingConversion = { scriptName, fileName };
                // Show custom warning modal
                document.getElementById('warningModal').classList.remove('hidden');
            } else {
                window.location.href = `convert/${scriptName}?file=${fileName}`;
            }
        }

        function closeWarningModal() {
            document.getElementById('warningModal').classList.add('hidden');
            pendingConversion = null;
        }

        function proceedWithConversion() {
            if (pendingConversion) {
                window.location.href = `convert/${pendingConversion.scriptName}?file=${pendingConversion.fileName}`;
            }
            closeWarningModal();
        }

        let selectedTemplate = null;

        function openTemplateModal() {
            document.getElementById('templateForm').reset();
            document.getElementById('templateModal').classList.remove('hidden');
            selectedTemplate = null;
            updateTemplateSelection();
            document.getElementById('fileUploadSection').classList.add('hidden');
            document.getElementById('templateButtons').classList.remove('hidden');
        }

        function closeTemplateModal() {
            document.getElementById('templateModal').classList.add('hidden');
            selectedTemplate = null;
            updateTemplateSelection();
            // Reset form
            document.getElementById('templateForm').reset();
            // Reset upload text to default
            updateUploadText();
        }

        // Template card selection
        document.querySelectorAll('.template-card').forEach(card => {
            card.addEventListener('click', function() {
                const fileUploadSection = document.getElementById('fileUploadSection');
                if (!fileUploadSection.classList.contains('hidden')) {
                    fileUploadSection.classList.add('hidden');
                    document.getElementById('templateButtons').classList.remove('hidden');
                    document.getElementById('templateForm').reset();
                }

                // Remove previous selections
                document.querySelectorAll('.template-card').forEach(c => {
                    c.classList.remove('border-primary-500', 'bg-primary-50');
                    c.classList.add('border-gray-200');
                });
                
                // Select current card
                this.classList.remove('border-gray-200');
                this.classList.add('border-primary-500', 'bg-primary-50');
                
                selectedTemplate = this.dataset.template;
                
                // Update the hidden input value immediately on click
                document.getElementById('selectedTemplateType').value = selectedTemplate;
                
                // Update upload text based on selected template
                updateUploadText();
                
                updateTemplateSelection();
            });
        });

        function updateUploadText() {
            const uploadTextElement = document.getElementById('upload-text');
            
            if (selectedTemplate) {
                const templateName = selectedTemplate.toUpperCase();
                uploadTextElement.innerHTML = `<span class="font-semibold">Click to upload ${templateName} CSV</span> or drag and drop`;
            } else {
                // Reset to default text
                uploadTextElement.innerHTML = '<span class="font-semibold">Click to upload</span> or drag and drop';
            }
        }


        function updateTemplateSelection() {
            const selectBtn = document.getElementById('selectTemplateBtn');
            if (selectedTemplate) {
                selectBtn.disabled = false;
                selectBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                selectBtn.classList.add('bg-gradient-to-r', 'from-primary-600', 'to-primary-700', 'hover:from-primary-700', 'hover:to-primary-900');
            } else {
                selectBtn.disabled = true;
                selectBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                selectBtn.classList.remove('bg-gradient-to-r', 'from-primary-600', 'to-primary-700', 'hover:from-primary-700', 'hover:to-primary-900');
            }
        }

        function selectTemplate() {
            if (!selectedTemplate) return;
            
            document.getElementById('templateButtons').classList.add('hidden');
            document.getElementById('fileUploadSection').classList.remove('hidden');
            
            // Update upload text when file upload section is shown
            updateUploadText();
        }

        // File upload handling
        const fileInput = document.getElementById('csv_file');
        
        fileInput.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            const uploadTextElement = document.getElementById('upload-text');
            
            if (fileName) {
                // Validate file extension on client side
                const fileExtension = fileName.split('.').pop().toLowerCase();
                if (fileExtension !== 'csv') {
                    alert('Please select a CSV file only.');
                    e.target.value = '';
                    // Reset to template-specific text
                    updateUploadText();
                    return;
                }
                
                uploadTextElement.innerHTML = `<span class="font-semibold text-primary-600">${fileName}</span> selected`;
            } else {
                // Reset to template-specific text if no file selected
                updateUploadText();
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
            const uploadTextElement = document.getElementById('upload-text');
            
            if (files.length > 0) {
                const file = files[0];
                const fileExtension = file.name.split('.').pop().toLowerCase();
                
                if (fileExtension !== 'csv') {
                    alert('Please drop a CSV file only.');
                    // Reset to template-specific text
                    updateUploadText();
                    return;
                }
                
                fileInput.files = files;
                const fileName = file.name;
                uploadTextElement.innerHTML = `<span class="font-semibold text-primary-600">${fileName}</span> selected`;
            }
        }


        function startImport(fileName) {
            // Show modal
            document.getElementById('importModal').classList.remove('hidden');
            document.getElementById('shopSelection').classList.remove('hidden');
            document.getElementById('importProgress').classList.add('hidden');
            
            // Fetch and display shops
            fetch('import/get_shops.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.success || !data.shops.length) {
                        throw new Error('No shops available. Please add a shop in Settings first.');
                    }
                    
                    const shopCards = document.getElementById('shopCards');
                    shopCards.innerHTML = data.shops.map(shop => `
                        <div onclick="selectShopAndImport('${fileName}', ${shop.id})" 
                            class="p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors duration-200">
                            <div class="font-medium text-gray-900">${shop.name}</div>
                            <div class="text-sm text-gray-500">${shop.shop_url}</div>
                        </div>
                    `).join('');
                })
                .catch(error => {
                    document.getElementById('shopCards').innerHTML = `
                        <div class="col-span-2 p-4 bg-red-50 text-red-600 rounded-lg">
                            <div class="font-medium">Error</div>
                            <div class="text-sm">${error.message}</div>
                        </div>`;
                });
        }

        function selectShopAndImport(fileName, shopId) {
            // Hide shop selection and show progress
            document.getElementById('shopSelection').classList.add('hidden');
            document.getElementById('importProgress').classList.remove('hidden');
            document.getElementById('importLoader').classList.remove('hidden');
            document.getElementById('importResults').classList.add('hidden');

            // Call import endpoint with shop ID
            fetch(`import/run.php?file=${fileName}&shop_id=${shopId}`)
                .then(async response => {
                    const contentType = response.headers.get('content-type');
                    const text = await response.text();

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