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
    // Use UTC for consistent timezone handling
    $stmt = $pdo->prepare("SELECT id, email, reset_token_expires FROM users WHERE reset_token = ? AND reset_token_expires > UTC_TIMESTAMP()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        // Check if token exists but expired
        $stmt = $pdo->prepare("SELECT id, reset_token_expires FROM users WHERE reset_token = ?");
        $stmt->execute([$token]);
        $expiredUser = $stmt->fetch();
        
        if ($expiredUser) {
            $messageType = 'warning';
            $message = "This reset link has expired. Please request a new password reset link.";
            
            // Optional: Clean up expired token
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

    // Comprehensive password validation
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .password-requirements {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .password-requirements .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 0.25rem;
        }
        .password-requirements .requirement.valid {
            color: #198754;
        }
        .password-requirements .requirement.invalid {
            color: #dc3545;
        }
        .password-toggle {
            cursor: pointer;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="card shadow-lg p-4" style="max-width: 450px; width: 100%; border-radius: 15px;">
            <div class="text-center mb-4">
                <h3 class="mb-3">Reset Your Password</h3>
                <?php if ($user): ?>
                    <p class="text-muted">Enter a new password for your account.</p>
                    <?php if ($timeRemaining): ?>
                        <p class="text-warning small">
                            <i class="bi bi-clock"></i> <?php echo $timeRemaining; ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; // Allow HTML for error lists ?>
                    <?php if ($messageType !== 'success'): ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    <?php endif; ?>
                </div>
                <?php if ($messageType === 'success'): ?>
                    <div class="text-center mb-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Redirecting...</span>
                        </div>
                        <p class="mt-2 text-muted">Redirecting to login page...</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($user && $messageType !== 'success'): ?>
                <form method="POST" class="needs-validation" novalidate id="resetForm">
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" 
                                   name="password" 
                                   class="form-control form-control" 
                                   id="password" 
                                   required 
                                   minlength="8"
                                   autocomplete="new-password"
                                   placeholder="Enter new password">
                            <button class="btn btn-outline-secondary password-toggle" type="button" onclick="togglePassword('password')">
                                <i class="bi bi-eye" id="password-icon"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">
                            Password must meet all requirements below.
                        </div>
                    </div>

                    <div class="mb-3 password-requirements">
                        <small>Password must contain:</small>
                        <div class="requirement" id="req-length">
                            <i class="bi bi-x-circle me-1"></i> At least 8 characters
                        </div>
                        <div class="requirement" id="req-uppercase">
                            <i class="bi bi-x-circle me-1"></i> One uppercase letter
                        </div>
                        <div class="requirement" id="req-lowercase">
                            <i class="bi bi-x-circle me-1"></i> One lowercase letter
                        </div>
                        <div class="requirement" id="req-number">
                            <i class="bi bi-x-circle me-1"></i> One number
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" 
                                   name="confirm_password" 
                                   class="form-control form-control" 
                                   id="confirm_password" 
                                   required
                                   autocomplete="new-password"
                                   placeholder="Confirm new password">
                            <button class="btn btn-outline-secondary password-toggle" type="button" onclick="togglePassword('confirm_password')">
                                <i class="bi bi-eye" id="confirm_password-icon"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">
                            Please confirm your password.
                        </div>
                        <div id="password-match" class="mt-1 small"></div>
                    </div>

                    <div class="d-grid gap-2 mb-3">
                        <button type="submit" class="btn btn-primary btn">Update Password</button>
                    </div>
                </form>
            <?php elseif ($messageType === 'danger' || $messageType === 'warning'): ?>
                <div class="text-center mb-3">
                    <a href="forgot_password.php" class="btn btn-primary btn">Request New Reset Link</a>
                </div>
            <?php endif; ?>

            <div class="text-center">
                <a href="login.php" class="text-decoration-none">← Back to login</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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