<?php
require 'connect.php';
$stmt = $pdo->query("DESCRIBE bill_meters");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
