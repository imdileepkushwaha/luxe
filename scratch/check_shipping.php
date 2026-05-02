<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=luxe_shop', 'root', '');
$st = $pdo->query('SELECT * FROM seller_delivery_options');
print_r($st->fetchAll(PDO::FETCH_ASSOC));
$st2 = $pdo->query('SELECT * FROM seller_shipping_settings');
print_r($st2->fetchAll(PDO::FETCH_ASSOC));
