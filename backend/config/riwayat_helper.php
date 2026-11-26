
<?php

/**
 * =====================================================
 * LAPORWARGA - RIWAYAT HELPER
 * Backend 2
 *
 * Fungsi pencatatan aktivitas pengaduan.
 * Riwayat bersifat append-only.
 * =====================================================
 */

function catatRiwayat(
    PDO $pdo,
    int $complaintId,
    int $userId,
    string $updateType,
    ?string $message = null,
    ?string $oldStatus = null,
    ?string $newStatus = null,
    ?string $documentationPhoto = null
): int {

    $allowedTypes = [
        'status',
        'response',
        'klarifikasi',
        'balasan',
        'pemeriksaan',
        'edit',
        'pembatalan'
    ];

    if (!in_array($updateType, $allowedTypes, true)) {
        throw new InvalidArgumentException(
            'Jenis riwayat tidak diizinkan.'
        );
    }

    $sql = "
        INSERT INTO complaint_updates (
            complaint_id,
            user_id,
            update_type,
            old_status,
            new_status,
            message,
            documentation_photo
        ) VALUES (
            :complaint_id,
            :user_id,
            :update_type,
            :old_status,
            :new_status,
            :message,
            :documentation_photo
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':complaint_id' => $complaintId,
        ':user_id' => $userId,
        ':update_type' => $updateType,
        ':old_status' => $oldStatus,
        ':new_status' => $newStatus,
        ':message' => $message,
        ':documentation_photo' => $documentationPhoto
    ]);

    return (int) $pdo->lastInsertId();
}
