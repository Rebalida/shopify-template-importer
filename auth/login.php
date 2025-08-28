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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="card shadow-lg p-4" style="max-width: 400px; width: 100%; border-radius: 15px;">
            <h3 class="text-center mb-4">Sign In</h3>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" 
                           name="email" 
                           class="form-control" 
                           id="email" 
                           required 
                           autocomplete="email">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" 
                           name="password" 
                           class="form-control" 
                           id="password" 
                           required>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="remember" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Sign In</button>
                </div>
            </form>

            <p class="text-center mt-3">
                <a href="#" class="text-decoration-none">Forgot password?</a>
            </p>
        </div>
    </div>
</body>
</html>