<?php
/**
 * api/city-list.php
 * Endpoint PUBLIK read-only: daftar kota/kabupaten kanonik untuk mengisi <datalist> di
 * halaman custom brand yang pakai event-sdk.js (HTML statis, tidak bisa render PHP
 * langsung). Sumber data sama dengan includes/city_list.php supaya satu-satunya daftar
 * kanonik tidak terduplikasi/berbeda antara template PHP dan halaman custom.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$cities = indonesia_city_list();
sort($cities, SORT_STRING | SORT_FLAG_CASE);

echo json_encode(['success' => true, 'cities' => array_values($cities)]);
