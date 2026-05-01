<?php
require_once __DIR__ . '/../functions.php';
requireLogin();
header('Content-Type: application/json');

$countries = allCountries();
$q = strtolower(trim($_GET['q'] ?? ''));

$results = [];
foreach ($countries as $code => $name) {
    if (empty($q) || str_contains(strtolower($name), $q) || str_contains(strtolower($code), $q)) {
        $results[] = ['id' => $code, 'text' => "$name ($code)"];
    }
}
echo json_encode($results);
