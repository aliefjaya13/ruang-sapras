<!-- koneksi database -->
<?php

$dbHost = getenv("DB_HOST") ?: "localhost";
$dbUser = getenv("DB_USER") ?: "dev";
$dbPassword = getenv("DB_PASSWORD");
$dbName = getenv("DB_NAME") ?: "tbsiswa";

if ($dbPassword === false) {
  http_response_code(500);
  die("DB_PASSWORD belum dikonfigurasi.");
}

$conn = mysqli_connect($dbHost, $dbUser, $dbPassword, $dbName);
if (!$conn) {
  http_response_code(500);
  die("Koneksi database gagal.");
}
?>