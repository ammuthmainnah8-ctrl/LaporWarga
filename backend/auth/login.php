
<?php

/**
 * =====================================================
 * LAPORWARGA - LOGIN
 * Backend 2
 *
 * Method : POST
 * Table  : users
 * =====================================================
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_check.php';

// Hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Metode request harus POST',
        'data' => null
    ]);

    exit;
}

// Mengambil data login
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validasi input
if ($username === '' || $password === '') {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Username dan password wajib diisi',
        'data' => null
    ]);

    exit;
}

try {

    // Mencari pengguna berdasarkan username
    $stmt = $pdo->prepare("
        SELECT
            user_id,
            name,
            username,
            password,
            role
        FROM users
        WHERE username = :username
        LIMIT 1
    ");

    $stmt->execute([
        ':username' => $username
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Memeriksa akun dan password
    if (
        !$user ||
        !password_verify($password, $user['password'])
    ) {
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'Username atau password salah',
            'data' => null
        ]);

        exit;
    }

    // Memastikan role pengguna valid
    if (!in_array($user['role'], ['warga', 'petugas'], true)) {
        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'Role pengguna tidak valid',
            'data' => null
        ]);

        exit;
    }

    // Mengamankan session setelah login
    session_regenerate_id(true);

    // Menyimpan informasi pengguna ke session
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];

    // Respons berhasil
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'message' => 'Login berhasil',
        'data' => [
            'user_id' => (int) $user['user_id'],
            'name' => $user['name'],
            'username' => $user['username'],
            'role' => $user['role']
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan pada server',
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
