<?php
require_once 'config/config.php';

// Admin user details
$admin = [
    'email' => 'admin@example.com',
    'password' => 'Admin123!',
    'first_name' => 'Admin',
    'last_name' => 'User',
    'role' => 'admin'
];

try {
    // Check if admin already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$admin['email']]);
    
    if ($stmt->fetch()) {
        echo "Admin user already exists!\n";
        exit(1);
    }

    // Create admin user
    $passwordHash = password_hash($admin['password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (
            email, 
            password_hash, 
            first_name, 
            last_name, 
            role, 
            created_at
        ) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())
    ");

    $stmt->execute([
        $admin['email'],
        $passwordHash,
        $admin['first_name'],
        $admin['last_name'],
        $admin['role']
    ]);

    echo "Admin user created successfully!\n";
    echo "Email: {$admin['email']}\n";
    echo "Password: {$admin['password']}\n";
    echo "\nPlease change the password after first login.\n";

} catch (PDOException $e) {
    echo "Error creating admin user: " . $e->getMessage() . "\n";
    exit(1);
}