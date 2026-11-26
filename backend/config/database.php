
<?php

/**
 * ==========================================================
 * LAPORWARGA - DATABASE CONFIGURATION
 * Backend 2
 * Database : laporwarga
 * DBMS     : MySQL
 * Connection: PDO
 * ==========================================================
 */

// Zona waktu aplikasi
date_default_timezone_set('Asia/Makassar');

// Konfigurasi database
$host = 'localhost';
$dbname = 'laporwarga';
$username = 'root';
$password = ''; // Default Laragon biasanya kosong

try {

    // Membuat koneksi PDO
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {

    // Menghentikan proses jika koneksi gagal
    error_log($e->getMessage());

    http_response_code(500);

    die('Koneksi database gagal. Periksa konfigurasi database.');

}
