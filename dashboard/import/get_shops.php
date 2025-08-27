<?php
    require_once '../../config/config.php';

    header('Content-Type: application/json');

    try{
        $stmt = $pdo->query("SELECT id, name, shop_url FROM shops ORDER BY name ASC");
        $shops = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'shops' => $shops
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch shops'
        ]);
    }
?>