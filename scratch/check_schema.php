<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=luxe_shop', 'root', '');
$st = $pdo->query('SHOW CREATE TABLE seller_delivery_options');
echo $st->fetchColumn(1);
