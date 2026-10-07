<?php
require_once "variable.php";
app_start_session();
$conn = app_db();
app_require_roles($conn, ["admin"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !app_csrf_valid()) {
    http_response_code(400);
    exit("Permintaan tidak valid.");
}

$id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
$nama = trim((string) ($_POST["nama"] ?? ""));
$kelas = trim((string) ($_POST["kelas"] ?? ""));
if (!$id || $nama === "" || $kelas === "" || strlen($nama) > 400 || strlen($kelas) > 200) {
    http_response_code(400);
    exit("Data siswa tidak valid.");
}

$statement = mysqli_prepare($conn, "UPDATE tbsiswa SET nama = ?, kelas = ? WHERE id = ?");
mysqli_stmt_bind_param($statement, "ssi", $nama, $kelas, $id);
mysqli_stmt_execute($statement);
mysqli_stmt_close($statement);
header("Location: pageview.php");
exit();
?>