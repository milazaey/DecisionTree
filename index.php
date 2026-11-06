<?php
require 'db.php';

// Ambil data history dari database
$stmt = $conn->query("SELECT * FROM history ORDER BY tanggal_upload DESC");
$historyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Decision Tree Generator</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <center>
            <h1>Decision Tree Generator</h1>
        </center>

        <div class="section">
            <h3>📂 Upload Dataset Baru</h3>
            <div class="node-box" style="text-align: center; background: #f8fafd; border: 2px dashed #1EAE98;">
                <p style="color: #666; margin-bottom: 15px;">Silakan upload file CSV. Pastikan kolom <b>Terakhir</b> adalah Target (Label).</p>

                <form action="result.php" method="post" enctype="multipart/form-data">
                    <input type="file" name="dataset" accept=".csv" required>
                    <button type="submit">✨ Proses Dataset</button>
                </form>
            </div>
        </div>

        <div class="section">
            <div class="header-viz">
                <h3>📜 Riwayat Analisis</h3>
            </div>

            <?php if (count($historyData) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th width="40%">Nama File</th>
                                <th width="25%">Tanggal Upload</th>
                                <th width="30%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1;
                            foreach ($historyData as $row): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td style="text-align: left; padding-left: 20px;">
                                        📄 <?= htmlspecialchars($row['nama_file']) ?>
                                    </td>
                                    <td><?= date('d M Y, H:i', strtotime($row['tanggal_upload'])) ?></td>
                                    <td>
                                        <a href="result.php?id=<?= $row['id'] ?>" class="btn-view" style="font-size: 12px; padding: 6px 15px;">
                                            👁️ Lihat
                                        </a>

                                        <a href="delete.php?id=<?= $row['id'] ?>"
                                            class="btn-delete"
                                            style="font-size: 12px; padding: 6px 15px;"
                                            onclick="return confirm('⚠️ Yakin ingin menghapus history ini? Data tidak bisa dikembalikan.')">
                                            🗑️ Hapus
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="node-box" style="text-align: center; color: #888;">
                    <p>Belum ada riwayat data. Yuk upload file pertamamu!</p>
                </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 50px; color: #999; font-size: 12px;">
            &copy; <?php echo date("Y"); ?> Decision Tree ID3 Project
        </div>
    </div>
</body>

</html>