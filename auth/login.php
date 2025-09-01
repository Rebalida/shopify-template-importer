<?php
  session_start();
  require_once '../config/config.php';

  $errors = [];

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    try {
      $stmt = $pdo->prepare("SELECT id, email, password_hash, role FROM users WHERE email = ?");
      $stmt->execute([$email]);
      $user = $stmt->fetch();
      
      if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['last_activity'] = time();

        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);

        session_regenerate_id(true);

        header("Location: ../dashboard");
        exit();
      } else {
        $errors[] = "Invalid email or password";
        sleep(1);
      }
    } catch (PDOException $e) {
      $errors[] = "An error occurred. Please try again later";
      error_log("Login error: " . $e->getMessage());
    }
  }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Shopify Template Importer</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white shadow-lg rounded-2xl p-6 w-full max-w-md">
        <h3 class="text-xl font-semibold text-center mb-4">Sign In</h3>
        
        <?php if (!empty($errors)): ?>
            <div class="mb-4 p-3 rounded-md bg-red-100 text-red-700 border border-red-200">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4" novalidate>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                <input type="email" 
                       name="email" 
                       id="email" 
                       required 
                       autocomplete="email"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-300 focus:ring-opacity-50 p-3">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" 
                       name="password" 
                       id="password" 
                       required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-300 focus:ring-opacity-50 p-3">
            </div>

            <div class="flex items-center">
                <input type="checkbox" 
                       name="remember" 
                       id="remember"
                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-300 focus:ring-opacity-50">
                <label class="ml-2 block text-sm text-gray-700" for="remember">Remember me</label>
            </div>

            <button type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                Sign In
            </button>

            <div class="text-center mt-4">
              <a href="forgot_password.php" class="text-blue-600 hover:underline text-sm">Forgot password?</a>
              <span class="mx-2 text-gray-400">|</span>
              <a href="signup.php" class="text-blue-600 hover:underline text-sm">Create account</a>
          </div>
        </form>
    </div>
</body>
</html>