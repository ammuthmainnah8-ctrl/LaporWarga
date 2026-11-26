
<?php

/**
 * =====================================================
 * LAPORWARGA - GENERATOR NOMOR TIKET
 * Backend 2
 *
 * Format: LPR-YYYYMMDD-NNNN
 * Contoh : LPR-20261123-0003
 *
 * Nomor urut berlanjut lintas tanggal.
 * =====================================================
 */

function generateTicketNumber(PDO $pdo): string
{
    // Gunakan zona waktu Indonesia Tengah
    $timezone = new DateTimeZone('Asia/Makassar');
    $tanggal = (new DateTimeImmutable('now', $timezone))
        ->format('Ymd');

    // Ambil dan kunci nomor urut terakhir
    // agar tidak digunakan bersamaan oleh transaksi lain.
    $stmt = $pdo->query(
        "SELECT last_number
         FROM ticket_sequence
         WHERE id = 1
         FOR UPDATE"
    );

    $lastNumber = $stmt->fetchColumn();

    if ($lastNumber === false) {
        throw new RuntimeException(
            'Data nomor urut tiket tidak ditemukan.'
        );
    }

    // Tambahkan nomor urut
    $nextNumber = (int) $lastNumber + 1;

    // Simpan nomor urut terbaru
    $update = $pdo->prepare(
        "UPDATE ticket_sequence
         SET last_number = :number
         WHERE id = 1"
    );

    $update->execute([
        ':number' => $nextNumber
    ]);

    // Format nomor tiket
    $nomor = str_pad(
        (string) $nextNumber,
        4,
        '0',
        STR_PAD_LEFT
    );

    return "LPR-{$tanggal}-{$nomor}";
}
