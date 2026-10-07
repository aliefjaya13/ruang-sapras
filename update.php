<?php
require_once "variable.php";
app_start_session();
$conn = app_db();
app_require_roles($conn, ["admin"]);
$id = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit("ID siswa tidak valid.");
}
$statement = mysqli_prepare($conn, "SELECT id, nama, kelas FROM tbsiswa WHERE id = ?");
mysqli_stmt_bind_param($statement, "i", $id);
mysqli_stmt_execute($statement);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
mysqli_stmt_close($statement);
if (!$row) {
    http_response_code(404);
    exit("Data siswa tidak ditemukan.");
}
$csrfToken = app_csrf_token();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#800000">
    <title>Ubah Data Siswa | Ruang Sapras</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{min-height:100vh;margin:0;background:#f5f3f1;color:#262321;font-family:'Segoe UI',sans-serif}.app-navbar,.app-navbar .container-xl{min-height:68px;background:#800000;color:#fff}.brand-lockup{display:inline-flex;align-items:center;gap:11px;color:#fff;font-weight:700;text-decoration:none}.brand-mark{display:grid;width:34px;height:34px;place-items:center;border:1px solid #ffffff77;border-radius:7px;font-size:.78rem}.navbar-link{display:inline-flex;min-height:38px;align-items:center;padding:0 13px;border:1px solid #ffffff66;border-radius:6px;color:#fff;font-size:.9rem;font-weight:600;text-decoration:none}.page-wrap{max-width:1080px;padding-top:42px;padding-bottom:56px}.page-heading{margin-bottom:28px}.eyebrow{margin-bottom:8px;color:#800000;font-size:.76rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase}.page-title{margin:0;font-size:2rem;font-weight:700}.page-subtitle{margin:9px 0 0;color:#77716e;line-height:1.65}.content-panel{border:1px solid #e8e3e0;border-radius:8px;background:#fff;box-shadow:0 8px 24px #2f1f190a}.form-panel{max-width:700px;padding:clamp(22px,4vw,36px)}.panel-heading{margin-bottom:24px}.panel-heading h2{margin:0;font-size:1.2rem;font-weight:700}.form-label{font-size:.9rem;font-weight:600}.form-control{min-height:46px;border-color:#dcd5d1;border-radius:6px}.form-control:focus{border-color:#800000;box-shadow:0 0 0 .2rem #8000001f}.btn-maroon{min-height:44px;padding:10px 17px;border:1px solid #800000;border-radius:6px;background:#800000;color:#fff;font-weight:600}.back-link{color:#800000;font-size:.9rem;font-weight:600;text-decoration:none}@media(max-width:575.98px){.page-wrap{padding-top:30px}}
    </style>
</head>
<body>
    <nav class="app-navbar">
        <div class="container-xl d-flex align-items-center justify-content-between">
            <a class="brand-lockup" href="index.php">
                <span class="brand-mark" aria-hidden="true">RS</span>
                <span>Ruang Sapras</span>
            </a>
            <a class="navbar-link" href="pageview.php">Data siswa</a>
        </div>
    </nav>

    <main class="container-xl page-wrap">
        <header class="page-heading">
            <p class="eyebrow">Laporan Sapras Sekolah</p>
            <h1 class="page-title">Ubah data siswa</h1>
            <p class="page-subtitle">Perbarui nama atau kelas, lalu simpan perubahan.</p>
        </header>

        <section class="content-panel form-panel" aria-labelledby="form-title">
            <div class="panel-heading">
                <h2 id="form-title">Informasi siswa</h2>
            </div>
            <form action="prosesupdate.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo app_escape($csrfToken); ?>">
                <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                <div class="mb-3">
                    <label class="form-label" for="nama">Nama siswa</label>
                    <input class="form-control" id="nama" type="text" name="nama" value="<?php echo htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="kelas">Kelas</label>
                    <input class="form-control" id="kelas" type="text" name="kelas" value="<?php echo htmlspecialchars($row['kelas'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <button class="btn btn-maroon" type="submit">Simpan perubahan</button>
                    <a class="back-link" href="pageview.php">Kembali ke data</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>