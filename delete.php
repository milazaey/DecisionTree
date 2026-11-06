<?php
require 'db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // 1. Ambil path file dulu sebelum dihapus datanya
    $stmt = $conn->prepare("SELECT path_file FROM history WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $fileToDelete = $row['path_file'];

        // 2. Hapus File Fisik di folder 'uploads' (Biar server gak penuh)
        if (file_exists($fileToDelete)) {
            unlink($fileToDelete);
        }

        // 3. Hapus Data dari Database
        $delStmt = $conn->prepare("DELETE FROM history WHERE id = ?");
        $delStmt->execute([$id]);
    }
}

// Kembali ke halaman utama
header("Location: index.php");
exit;
?>