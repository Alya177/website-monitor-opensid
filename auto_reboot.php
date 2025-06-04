<?php

// ====================================================================
// --- Konfigurasi Penting: HARAP SESUAIKAN DENGAN SETUP ANDA! ---
// ====================================================================

// URL yang akan diperiksa
// URL berbasis ssl https, jika menggunakan http ganti dengan http
// Lakukan pengujian setalah mengaplikasikan
$urlToMonitor = "https://domainanda.com";

$timeout = 60; // Timeout cURL dalam detik
$checkInterval = 1800; // Interval pengecekan dalam detik (30 menit) - UNTUK DI DALAM JAM OPERASIONAL
$maxAttempts = 2; // Jumlah percobaan gagal sebelum reboot (2 percobaan = 1 jam jika setiap 30 menit)

// Jam Mulai dan Selesai monitoring (format 24 jam)
$startHour = 8;  // 08:00 AM
$endHour = 23;    // 23:00 PM (alias hingga 23:59:59, efektif sampai 24:00)

// File log tempat penyimpanan log
$logFile = __DIR__ . '/auto_reboot_nohup.log';

// Durasi tidur saat di luar jam operasional (dalam detik)
$sleepOutsideOperatingHours = 1800; // 30 menit (1800 detik)

// ====================================================================
// --- Akhir Konfigurasi --- (Jangan mengubah kode di bawah ini)
// ====================================================================


/**
 * Fungsi tulis pesan log dengan tanggal dan jam
 */
function writeLog($message) {
    global $logFile;
    $date = date('Y-m-d H:i:s'); // tanggal dan jam
    $logMessage = "[$date] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    manageLogFile(); // Panggil fungsi untuk mengelola file log
}

/**
 * Fungsi untuk mengelola ukuran file log
 */
function manageLogFile() {
    global $logFile;
    $maxLines = 1000; // Maksimum jumlah baris dalam log
    $linesToRemove = 500; // Jumlah baris yang akan dihapus

    // Baca semua baris dari file log
    $lines = @file($logFile); // Gunakan @ untuk menekan peringatan jika file tidak ada
    if ($lines === false) {
        // File belum ada atau tidak bisa dibaca, tidak perlu resize
        return;
    }
    $lineCount = count($lines);

    // Jika jumlah baris melebihi maksimum, hapus baris yang paling lama
    if ($lineCount > $maxLines) {
        // Ambil baris yang tersisa setelah menghapus yang paling lama
        $lines = array_slice($lines, $linesToRemove);
        // Tulis kembali sisa baris ke file log
        file_put_contents($logFile, implode('', $lines));
    }
}

/**
 * Fungsi untuk memeriksa apakah waktu saat ini berada dalam rentang jam operasional
 * @param int $startHour Jam mulai (0-23)
 * @param int $endHour Jam selesai (0-23)
 * @return bool True jika waktu saat ini berada dalam rentang, False jika tidak
 */
function isOperatingHours(int $startHour, int $endHour): bool {
    $currentHour = (int)date('H'); // Dapatkan jam saat ini dalam format 24 jam (0-23)

    // Untuk rentang 08:00 s/d 23:59
    return ($currentHour >= $startHour && $currentHour <= $endHour);
}


// --- Main Logic ---

writeLog("Memulai script monitoring. Menunggu jam operasional (" . sprintf('%02d', $startHour) . ":00 - " . sprintf('%02d', ($endHour + 1) % 24) . ":00)...");

// Loop utama yang terus berjalan 24/7
while (true) { // <-- LOOP TAK TERBATAS
    // Periksa apakah ini jam operasional
    if (isOperatingHours($startHour, $endHour)) {
        // Jika di dalam jam operasional, lakukan monitoring
        writeLog("Masuk jam operasional. Memulai monitoring website: $urlToMonitor");

        $unreachableCount = 0; // Reset hitungan kegagalan untuk URL yang dimonitor

        // Loop monitoring selama masih dalam jam operasional
        while (isOperatingHours($startHour, $endHour) && $unreachableCount < $maxAttempts) {
            $ch = curl_init($urlToMonitor);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);

            curl_close($ch);

            if ($response === false) {
                writeLog("Gagal mengakses website ($urlToMonitor). cURL error: '$curlError' (Code: $curlErrno)");
                $unreachableCount++;
            } elseif ($httpCode >= 200 && $httpCode < 400) {
                $logMessage = "Website bisa diakses. HTTP code: $httpCode. Reset hitungan unreachable.";
                if ($httpCode >= 300 && $httpCode < 400) {
                     $logMessage .= " (Redirect terdeteksi).";
                }
                writeLog($logMessage);
                $unreachableCount = 0; // Reset hitungan jika berhasil diakses
            } else {
                $logMessage = "Website tidak dapat diakses. HTTP code: $httpCode untuk $urlToMonitor";
                if (!empty($response)) {
                    $responseSnippet = substr($response, 0, 500);
                    $responseSnippet = str_replace(["\n", "\r"], ' ', $responseSnippet);
                    $logMessage .= ". Response snippet: '" . trim($responseSnippet) . "...'";
                }
                writeLog($logMessage);
                $unreachableCount++;
            }

            // Cek apakah website tidak dapat diakses setelah semua percobaan (di dalam jam operasional)
            if ($unreachableCount >= $maxAttempts) {
                writeLog("Website $urlToMonitor tidak bisa diakses setelah $maxAttempts percobaan. Sistem akan melakukan reboot.");
                // exec('sudo reboot'); // Uncomment ini setelah Anda yakin
                writeLog("PERINGATAN: Perintah reboot dikomentari (exec('sudo reboot')). Aktifkan jika Anda ingin reboot otomatis.");
                // Jika reboot dipicu, loop monitoring ini akan berhenti. Script utama akan terus berjalan.
                break; // Keluar dari loop monitoring
            }

            // Tunggu interval pengecekan jika masih dalam jam operasional
            if (isOperatingHours($startHour, $endHour)) { // Periksa lagi sebelum tidur
                writeLog("Menunggu $checkInterval detik sebelum pengecekan berikutnya untuk $urlToMonitor...");
                sleep($checkInterval);
            }
        } // Akhir dari loop monitoring (saat jam operasional)

        writeLog("Keluar dari jam operasional atau kondisi reboot terpenuhi.");
        // Setelah keluar dari loop monitoring, script akan kembali ke loop while(true)
        // dan akan menunggu hingga jam operasional berikutnya.

    } else {
        // Jika di luar jam operasional, tunggu hingga jam operasional berikutnya
        writeLog("Di luar jam operasional. Tidur selama " . ($sleepOutsideOperatingHours / 60) . " menit sampai jam operasional tiba atau sampai sistem di-reboot.");
        sleep($sleepOutsideOperatingHours); // Tidur selama durasi yang ditentukan (30 menit)
    }
} // Akhir dari loop utama while(true)

?>
