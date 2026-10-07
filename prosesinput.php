<?php
require_once "variable.php";
app_start_session();
$conn = app_db();
app_require_roles($conn, ["admin"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !app_csrf_valid()) {
    http_response_code(400);
    exit("Permintaan tidak valid.");
}

$nama = trim((string) ($_POST["nama"] ?? ""));
$kelas = trim((string) ($_POST["kelas"] ?? ""));
if ($nama === "" || $kelas === "" || strlen($nama) > 400 || strlen($kelas) > 200) {
    http_response_code(400);
    exit("Nama dan kelas wajib diisi.");
}

$statement = mysqli_prepare($conn, "INSERT INTO tbsiswa (nama, kelas) VALUES (?, ?)");
mysqli_stmt_bind_param($statement, "ss", $nama, $kelas);
if (mysqli_stmt_execute($statement)) {
    mysqli_stmt_close($statement);
    header("Location: index.php");
    exit();
}

mysqli_stmt_close($statement);
http_response_code(500);
exit("Data siswa gagal disimpan.");
?>