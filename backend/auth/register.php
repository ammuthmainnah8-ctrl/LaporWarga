
<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';

// Fungsi respons JSON
function responseJSON(int $code, bool $success, string $message, $data = null): void
{
    http_response_code($code);

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);

    exit;
}

// Registrasi hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responseJSON(405, false, 'Method tidak diizinkan');
}

// Ambil data formulir
$name = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Validasi kolom wajib
if (
    $name === '' ||
    $username === '' ||
    $email === '' ||
    $password === '' ||
    $confirmPassword === ''
) {
    responseJSON(422, false, 'Semua kolom wajib harus diisi');
}

// Validasi nama
if (strlen($name) > 100) {
    responseJSON(422, false, 'Nama maksimal 100 karakter');
}

// Validasi username
if (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $username)) {
    responseJSON(
        422,
        false,
        'Username harus 3-50 karakter dan hanya boleh berisi huruf, angka, titik, atau garis bawah'
    );
}

// Validasi email
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
    responseJSON(422, false, 'Format email tidak valid');
}

// Validasi nomor telepon jika diisi
if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{8,20}$/', $phone)) {
    responseJSON(422, false, 'Format nomor telepon tidak valid');
}

// Validasi panjang password
if (strlen($password) < 8) {
    responseJSON(422, false, 'Password minimal 8 karakter');
}

// Validasi konfirmasi password
if ($password !== $confirmPassword) {
    responseJSON(422, false, 'Konfirmasi password tidak cocok');
}

try {
    // Periksa username dan email
    $check = $pdo->prepare("
        SELECT username, email
        FROM users
        WHERE username = :username
           OR email = :email
        LIMIT 1
    ");

    $check->execute([
        ':username' => $username,
        ':email' => $email
    ]);

    $existingUser = $check->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        if ($existingUser['username'] === $username) {
            responseJSON(409, false, 'Username sudah digunakan');
        }

        if (strcasecmp($existingUser['email'], $email) === 0) {
            responseJSON(409, false, 'Email sudah digunakan');
        }

        responseJSON(409, false, 'Username atau email sudah digunakan');
    }

    // Buat hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    if ($passwordHash === false) {
        throw new RuntimeException('Gagal membuat hash password');
    }

    // Simpan akun baru
    $stmt = $pdo->prepare("
        INSERT INTO users (
            name,
            username,
            email,
            phone,
            password,
            role
        ) VALUES (
            :name,
            :username,
            :email,
            :phone,
            :password,
            'warga'
        )
    ");

    $stmt->execute([
        ':name' => $name,
        ':username' => $username,
        ':email' => $email,
        ':phone' => $phone !== '' ? $phone : null,
        ':password' => $passwordHash
    ]);

    $newUserId = (int) $pdo->lastInsertId();

    responseJSON(201, true, 'Registrasi berhasil', [
        'user_id' => $newUserId,
        'name' => $name,
        'username' => $username,
        'email' => $email,
        'role' => 'warga'
    ]);

} catch (PDOException $e) {

    error_log($e->getMessage());

    // Antisipasi username/email yang digunakan
    // bersamaan oleh proses registrasi lain.
    if ($e->getCode() === '23000') {
        responseJSON(409, false, 'Username atau email sudah digunakan');
    }

    responseJSON(500, false, 'Terjadi kesalahan saat registrasi');

} catch (Throwable $e) {

    error_log($e->getMessage());

    responseJSON(500, false, 'Terjadi kesalahan saat registrasi');
}
