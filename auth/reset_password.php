<?php
session_start();
require_once '../config/config.php';

$message = '';
$messageType = '';
$token = $_GET['token'] ?? '';
$user = null;

// If token is not provided, redirect to forgot password page
if (empty($token)) {
    header('Location: forgot_password.php?error=missing_token');
    exit();
}

// Check if token is valid and not expired
try {
    $stmt = $pdo->prepare("SELECT id, email, reset_token_expires FROM users WHERE reset_token = ? AND reset_token_expires > UTC_TIMESTAMP()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $stmt = $pdo->prepare("SELECT id, reset_token_expires FROM users WHERE reset_token = ?");
        $stmt->execute([$token]);
        $expiredUser = $stmt->fetch();
        
        if ($expiredUser) {
            $messageType = 'warning';
            $message = "This reset link has expired. Please request a new password reset link.";
            
            $cleanupStmt = $pdo->prepare("UPDATE users SET reset_token = NULL, reset_token_expires = NULL WHERE reset_token = ?");
            $cleanupStmt->execute([$token]);
        } else {
            $messageType = 'danger';
            $message = "Invalid reset link. Please request a new password reset link.";
        }
    }
} catch (PDOException $e) {
    error_log("Token validation error: " . $e->getMessage());
    $messageType = 'danger';
    $message = "An error occurred while validating the reset link. Please try again later.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        try {
            // Update password and clear reset token
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
            $stmt->execute([$passwordHash, $user['id']]);

            $messageType = 'success';
            $message = "Your password has been reset successfully! You will be redirected to the login page in a few seconds.";
            
            // Log successful password reset
            error_log("Password reset successful for user ID: " . $user['id'] . " (Email: " . $user['email'] . ")");
            
            // Redirect to login page after 3 seconds
            header("refresh:3;url=login.php?message=password_reset_success");
            
        } catch (PDOException $e) {
            error_log("Password reset database error: " . $e->getMessage());
            $messageType = 'danger';
            $message = "An error occurred while updating your password. Please try again later.";
        }
    } else {
        $messageType = 'danger';
        $message = implode('<br>', $errors);
    }
}

// Calculate time remaining for token expiry (for display purposes)
$timeRemaining = '';
if ($user && isset($user['reset_token_expires'])) {
    $expiryTime = strtotime($user['reset_token_expires'] . ' UTC'); // Treat as UTC
    $currentTime = time();
    $remaining = $expiryTime - $currentTime;
    
    if ($remaining > 0) {
        $minutes = floor($remaining / 60);
        $seconds = $remaining % 60;
        if ($minutes > 0) {
            $timeRemaining = "This link expires in {$minutes} minute(s).";
        } else {
            $timeRemaining = "This link expires in {$seconds} second(s).";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - Shopify Template Importer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white shadow-lg rounded-2xl p-6 w-full max-w-md">
        <div class="text-center mb-6">
            <h3 class="text-xl font-semibold mb-2">Reset Your Password</h3>
            <?php if ($user): ?>
                <p class="text-gray-500">Enter a new password for your account.</p>
                <?php if ($timeRemaining): ?>
                    <p class="text-yellow-600 text-sm mt-2">
                        <i class="bi bi-clock"></i> <?php echo $timeRemaining; ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <?php if ($message): ?>
            <div class="mb-4 p-3 rounded-md <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : ($messageType === 'warning' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); ?>">
                <?php echo $message; ?>
            </div>
            <?php if ($messageType === 'success'): ?>
                <div class="text-center mb-4">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-blue-500 border-t-transparent"></div>
                    <p class="mt-2 text-gray-500">Redirecting to login page...</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($user && $messageType !== 'success'): ?>
            <form method="POST" class="space-y-4" novalidate id="resetForm">
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">New Password</label>
                    <div class="relative">
                        <input type="password" 
                               name="password" 
                               id="password" 
                               required 
                               minlength="8"
                               autocomplete="new-password"
                               placeholder="Enter new password"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-300 focus:ring-opacity-50 pr-10 p-3">
                        <button type="button" 
                                onclick="togglePassword('password')" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="bi bi-eye text-gray-500" id="password-icon"></i>
                        </button>
                    </div>
                </div>

                <div class="space-y-2 text-sm text-gray-600">
                    <p class="font-medium">Password must contain:</p>
                    <div id="req-length" class="flex items-center">
                        <i class="bi bi-x-circle mr-2"></i> At least 8 characters
                    </div>
                    <div id="req-uppercase" class="flex items-center">
                        <i class="bi bi-x-circle mr-2"></i> One uppercase letter
                    </div>
                    <div id="req-lowercase" class="flex items-center">
                        <i class="bi bi-x-circle mr-2"></i> One lowercase letter
                    </div>
                    <div id="req-number" class="flex items-center">
                        <i class="bi bi-x-circle mr-2"></i> One number
                    </div>
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                    <div class="relative">
                        <input type="password" 
                               name="confirm_password" 
                               id="confirm_password" 
                               required
                               autocomplete="new-password"
                               placeholder="Confirm new password"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-300 focus:ring-opacity-50 pr-10 p-3">
                        <button type="button" 
                                onclick="togglePassword('confirm_password')" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="bi bi-eye text-gray-500" id="confirm_password-icon"></i>
                        </button>
                    </div>
                    <div id="password-match" class="mt-1 text-sm"></div>
                </div>

                <button type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                    Update Password
                </button>
            </form>
        <?php elseif ($messageType === 'danger' || $messageType === 'warning'): ?>
            <div class="text-center">
                <a href="forgot_password.php" 
                   class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                    Request New Reset Link
                </a>
            </div>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="login.php" class="text-blue-600 hover:underline">← Back to login</a>
        </div>
    </div>
    <script>
        // Password visibility toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '-icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }

        // Real-time password validation
        document.getElementById('password')?.addEventListener('input', function() {
            const password = this.value;
            
            // Length requirement
            const lengthReq = document.getElementById('req-length');
            if (password.length >= 8) {
                lengthReq.className = 'requirement valid';
                lengthReq.innerHTML = '<i class="bi bi-check-circle me-1"></i> At least 8 characters';
            } else {
                lengthReq.className = 'requirement invalid';
                lengthReq.innerHTML = '<i class="bi bi-x-circle me-1"></i> At least 8 characters';
            }
            
            // Uppercase requirement
            const uppercaseReq = document.getElementById('req-uppercase');
            if (/[A-Z]/.test(password)) {
                uppercaseReq.className = 'requirement valid';
                uppercaseReq.innerHTML = '<i class="bi bi-check-circle me-1"></i> One uppercase letter';
            } else {
                uppercaseReq.className = 'requirement invalid';
                uppercaseReq.innerHTML = '<i class="bi bi-x-circle me-1"></i> One uppercase letter';
            }
            
            // Lowercase requirement
            const lowercaseReq = document.getElementById('req-lowercase');
            if (/[a-z]/.test(password)) {
                lowercaseReq.className = 'requirement valid';
                lowercaseReq.innerHTML = '<i class="bi bi-check-circle me-1"></i> One lowercase letter';
            } else {
                lowercaseReq.className = 'requirement invalid';
                lowercaseReq.innerHTML = '<i class="bi bi-x-circle me-1"></i> One lowercase letter';
            }
            
            // Number requirement
            const numberReq = document.getElementById('req-number');
            if (/[0-9]/.test(password)) {
                numberReq.className = 'requirement valid';
                numberReq.innerHTML = '<i class="bi bi-check-circle me-1"></i> One number';
            } else {
                numberReq.className = 'requirement invalid';
                numberReq.innerHTML = '<i class="bi bi-x-circle me-1"></i> One number';
            }
            
            // Check password match
            checkPasswordMatch();
        });

        // Password match validation
        document.getElementById('confirm_password')?.addEventListener('input', checkPasswordMatch);

        function checkPasswordMatch() {
            const password = document.getElementById('password')?.value;
            const confirmPassword = document.getElementById('confirm_password')?.value;
            const matchDiv = document.getElementById('password-match');
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    matchDiv.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Passwords match</span>';
                } else {
                    matchDiv.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> Passwords do not match</span>';
                }
            } else {
                matchDiv.innerHTML = '';
            }
        }

        // Form validation
        (function () {
            'use strict'
            const forms = document.querySelectorAll('.needs-validation')
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    
                    // Custom validation
                    let isValid = true;
                    
                    // Check password requirements
                    if (password.length < 8 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
                        isValid = false;
                    }
                    
                    // Check password match
                    if (password !== confirmPassword) {
                        isValid = false;
                    }
                    
                    if (!form.checkValidity() || !isValid) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>