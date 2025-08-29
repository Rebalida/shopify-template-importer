<?php 
    session_start();
    require_once '../config/config.php';
    require '../vendor/autoload.php';

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    $message = '';
    $messageType = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

        try {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = gmdate('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
                $stmt->execute([$token, $expires, $email]);

                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host = $_ENV['SMTP_HOST'];

                    if (!empty($_ENV['SMTP_USERNAME']) && !empty($_ENV['SMTP_PASSWORD'])) {
                        $mail->SMTPAuth = true;
                        $mail->Username = $_ENV['SMTP_USERNAME'];
                        $mail->Password = $_ENV['SMTP_PASSWORD'];
                    } else {
                        $mail->SMTPAuth = false;
                    }

                    // Handle encryption
                    $smtpSecure = strtolower($_ENV['SMTP_SECURE'] ?? '');
                    if ($smtpSecure === 'tls') {
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    } elseif ($smtpSecure === 'ssl') {
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    } else {
                        $mail->SMTPSecure = false;
                        $mail->SMTPAutoTLS = false; // Disable auto TLS for MailHog
                    }
                    
                    $mail->Port = $_ENV['SMTP_PORT'] ?? 587;

                    $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
                    $mail->addAddress($email);

                    // Message content
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $resetLink = "{$protocol}://{$_SERVER['HTTP_HOST']}/auth/reset_password.php?token=" . $token;

                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Request';
                    $mail->Body = <<<HTML
                        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                            <h2 style="color: #333; text-align: center;">Password Reset Request</h2>
                            <p>Hello,</p>
                            <p>You recently requested to reset your password for your account. Click the button below to proceed:</p>
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="{$resetLink}" 
                                   style="background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">
                                    Reset Password
                                </a>
                            </div>
                            <p><strong>Important:</strong></p>
                            <ul>
                                <li>This link will expire in <strong>1 hour</strong></li>
                                <li>If you didn't request this password reset, please ignore this email</li>
                                <li>For security reasons, do not share this link with anyone</li>
                            </ul>
                            <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                            <p style="word-break: break-all; color: #666;">{$resetLink}</p>
                            <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
                            <p style="color: #666; font-size: 12px; text-align: center;">
                                This email was sent by {$_ENV['MAIL_FROM_NAME']}
                            </p>
                        </div>
                    HTML;
                    
                    $mail->AltBody = "Password Reset Request\n\n" .
                                   "You recently requested to reset your password. " .
                                   "Please click the following link to reset your password:\n\n" .
                                   "{$resetLink}\n\n" .
                                   "This link will expire in 1 hour.\n" .
                                   "If you didn't request this, please ignore this email.";

                    $mail->send();
                    $messageType = 'success';
                    $message = "If an account exists with this email, you will receive password reset instrution within a few minutes.";

                    error_log("Password reset email sent successfully to: " . $email);
                } catch (Exception $e) {
                    error_log("Email sending failed: " . $mail->ErrorInfo);
                    $messageType = 'danger';
                    $message = "Failed to send reset instructions. Please try again later.";
                }

            } else {
                // Don't reveal if email exists or not
                $messageType = 'success';
                $message = "If an account exists with this email, you will receive password reset instructions within a few minutes.";
                sleep(1);
            }
        } catch (PDOException $e) {
            error_log("Password reset database error: " . $e->getMessage());
            $messageType = 'danger';
            $message = "An error occurred. Please try again later.";
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password - Shopify Template Importer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 min-h-screen flex items-center justify-center">
        <div class="bg-white shadow-lg rounded-2xl p-6 w-full max-w-md">
            
            <!-- Header -->
            <div class="text-center mb-6">
            <h3 class="text-xl font-semibold mb-2">Reset Password</h3>
            <p class="text-gray-500 text-sm">Enter your email address and we'll send you a link to reset your password.</p>
            </div>

            <!-- PHP Message -->
            <?php if ($message): ?>
            <div class="mb-4 p-3 rounded-md text-sm 
                        <?php echo $messageType === 'success' 
                                ? 'bg-green-100 text-green-700 border border-green-200' 
                                : 'bg-red-100 text-red-700 border border-red-200'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <!-- Show form if not success -->
            <?php if ($messageType !== 'success'): ?>
            <form method="POST" class="space-y-4" novalidate>
                <!-- Email Field -->
                <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                <input type="email" 
                        name="email"
                        id="email"
                        required
                        autocomplete="email"
                        placeholder="Enter your email address"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-300 focus:ring-opacity-50 p-3">
                <p class="text-red-500 text-xs mt-1 hidden">Please enter a valid email address.</p>
                </div>

                <!-- Submit Button -->
                <div>
                <button type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                    Send Reset Link
                </button>
                </div>
            </form>
            <?php else: ?>
            <!-- Success Message -->
            <div class="text-center mb-3">
                <div class="text-green-600 mb-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2l4-4m6 2a9 9 0 11-18 0a9 9 0 0118 0z"/>
                </svg>
                </div>
                <p class="text-gray-500 text-sm">Check your email for further instructions.</p>
            </div>
            <?php endif; ?>

            <!-- Back to Login -->
            <div class="text-center mt-4">
            <a href="login.php" class="text-blue-600 hover:underline text-sm">← Back to login</a>
            </div>
        </div>

        <!-- Form validation -->
        <script>
            (function () {
            'use strict'
            const forms = document.querySelectorAll('form')
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                    form.querySelector('p').classList.remove('hidden')
                }
                }, false)
            })
            })()
        </script>
    </body>
</html>
