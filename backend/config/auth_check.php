
<?php

/**
 * =====================================================
 * LAPORWARGA - AUTHENTICATION CHECK
 * Digunakan bersama oleh Backend 1 dan Backend 2
 * =====================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Memeriksa apakah pengguna sudah login.
 */
function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'Silakan login terlebih dahulu',
            'data' => null
        ]);

        exit;
    }
}

/**
 * Memeriksa hak akses berdasarkan role.
 */
function requireRole(string $role): void
{
    requireLogin();

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'Anda tidak memiliki hak akses',
            'data' => null
        ]);

        exit;
    }
}
