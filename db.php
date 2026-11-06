<?php
$host = 'localhost';
$dbname = 'db_bismillah'; // Nama database kamu
$username = 'root';
$password = ''; // Di Laragon biasanya kosong

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}
?>