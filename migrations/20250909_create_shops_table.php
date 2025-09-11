<?php
require_once __DIR__ . '/../config/config.php';

try {
    $sql = "
        CREATE TABLE IF NOT EXISTS shops (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            shop_url VARCHAR(255) NOT NULL,
            access_token VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY shop_url (shop_url)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($sql);
    echo "✅ Migration create_shops_table applied successfully!\n";
} catch (PDOException $e) {
    echo "❌ Migration create_shops_table failed: " . $e->getMessage() . "\n";
}
