<?php
require_once "variable.php";
app_start_session();
$conn = app_db();
$user = app_require_login($conn);
$isStaff = in_array($user["role"], ["petugas_sapras", "admin"], true);
$isAdmin = $user["role"] === "admin";
$csrfToken = app_csrf_token();

if (isset($_GET["foto"])) {
    $photoId = filter_var($_GET["foto"], FILTER_VALIDATE_INT);
    $statement = $photoId ? mysqli_prepare($conn, "SELECT reporter_user_id, foto, foto_mime FROM tblaporan_sapras WHERE id = ?") : false;
    if ($statement) {
        mysqli_stmt_bind_param($statement, "i", $photoId);
        mysqli_stmt_execute($statement);
        $photo = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
        mysqli_stmt_close($statement);
    } else {
        $photo = null;
    }

    if (!$photo || !$photo["foto"] || !$photo["foto_mime"] || (!$isStaff && (int) $photo["reporter_user_id"] !== (int) $user["id"])) {
        http_response_code(404);
        exit("Foto tidak ditemukan.");
    }
    header("Content-Type: " . $photo["foto_mime"]);
    header("Content-Length: " . strlen($photo["foto"]));
    header("X-Content-Type-Options: nosniff");
    header("Cache-Control: private, no-store");
    echo $photo["foto"];
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!app_csrf_valid()) {
        http_response_code(400);
        exit("Token keamanan tidak valid.");
    }
    $action = is_string($_POST["action"] ?? null) ? $_POST["action"] : "";

    if ($action === "update_report" && $isStaff) {
        $reportId = filter_var($_POST["report_id"] ?? null, FILTER_VALIDATE_INT);
        $status = is_string($_POST["status"] ?? null) ? $_POST["status"] : "";
        $note = trim((string) ($_POST["catatan"] ?? ""));
        if (!$reportId || !in_array($status, ["Pending", "Diproses", "Selesai"], true) || strlen($note) > 20000) {
            http_response_code(400);
            exit("Status atau catatan tidak valid.");
        }
        $statement = mysqli_prepare($conn, "UPDATE tblaporan_sapras SET status = ?, catatan_tindak_lanjut = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        mysqli_stmt_bind_param($statement, "ssi", $status, $note, $reportId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        header("Location: pageview.php?perubahan=tersimpan#laporan");
        exit();
    }

    if ($action === "toggle_user" && $isAdmin) {
        $targetId = filter_var($_POST["user_id"] ?? null, FILTER_VALIDATE_INT);
        if ($targetId && $targetId !== (int) $user["id"]) {
            $statement = mysqli_prepare($conn, "UPDATE tblusers SET aktif = IF(aktif = 1, 0, 1) WHERE id = ?");
            mysqli_stmt_bind_param($statement, "i", $targetId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
        }
        header("Location: index.php#akun");
        exit();
    }

    if ($action === "delete_report" && $isAdmin) {
        $reportId = filter_var($_POST["report_id"] ?? null, FILTER_VALIDATE_INT);
        if ($reportId) {
            $statement = mysqli_prepare($conn, "DELETE FROM tblaporan_sapras WHERE id = ?");
            mysqli_stmt_bind_param($statement, "i", $reportId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
        }
        header("Location: pageview.php?perubahan=tersimpan#laporan");
        exit();
    }

    http_response_code(403);
    exit("Aksi ini tidak diizinkan.");
}

if ($isStaff) {
    $reportResult = mysqli_query($conn, "SELECT id, reporter_user_id, nama_pelapor, fasilitas, lokasi, judul, deskripsi, status, foto_mime, catatan_tindak_lanjut, created_at, updated_at FROM tblaporan_sapras ORDER BY created_at DESC, id DESC");
} else {
    $statement = mysqli_prepare($conn, "SELECT id, reporter_user_id, nama_pelapor, fasilitas, lokasi, judul, deskripsi, status, foto_mime, catatan_tindak_lanjut, created_at, updated_at FROM tblaporan_sapras WHERE reporter_user_id = ? ORDER BY created_at DESC, id DESC");
    mysqli_stmt_bind_param($statement, "i", $user["id"]);
    mysqli_stmt_execute($statement);
    $reportResult = mysqli_stmt_get_result($statement);
}
$reportTotal = $reportResult ? mysqli_num_rows($reportResult) : 0;
$studentRows = [];
if ($isAdmin) {
    $studentResult = mysqli_query($conn, "SELECT id, nama, kelas FROM tbsiswa ORDER BY nama");
    while ($student = mysqli_fetch_assoc($studentResult)) {
        $studentRows[] = $student;
    }
}
$roleName = ucwords(str_replace("_", " ", $user["role"]));
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#800000">
    <title>Laporan Sapras | Ruang Sapras</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{--maroon:#800000;--ink:#262321;--muted:#77716e;--line:#e8e3e0;--paper:#fff;--canvas:#f5f3f1}*{box-sizing:border-box}body{min-height:100vh;margin:0;background:var(--canvas);color:var(--ink);font-family:'DM Sans','Segoe UI',sans-serif;-webkit-font-smoothing:antialiased}.app-navbar,.app-navbar .container-xl{min-height:68px;background:var(--maroon);color:#fff}.brand-lockup{display:inline-flex;align-items:center;gap:10px;color:#fff;font-weight:700;text-decoration:none}.school-logo-small{width:38px;height:38px;object-fit:contain;background:#fff;border-radius:7px;padding:3px}.school-name{display:block;font-size:.9rem}.app-name{display:block;margin-top:2px;color:#ffffffc9;font-size:.74rem;font-weight:500}.nav-tools{display:flex;align-items:center;gap:12px}.navbar-link{display:inline-flex;min-height:38px;align-items:center;padding:0 13px;border:1px solid #ffffff66;border-radius:6px;background:transparent;color:#fff;font-size:.9rem;font-weight:600;text-decoration:none}.navbar-link:hover{background:#ffffff1f;color:#fff}.page-wrap{max-width:1120px;padding-top:38px;padding-bottom:56px}.eyebrow{margin-bottom:8px;color:var(--maroon);font-size:.76rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.page-title{margin:0;font-size:clamp(1.6rem,3vw,2rem);font-weight:700}.page-subtitle{margin:8px 0 24px;color:var(--muted)}.content-panel{overflow:hidden;border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:0 8px 24px #2f1f190a}.table-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:20px 22px;border-bottom:1px solid var(--line)}.table-toolbar h2{margin:0;font-size:1.1rem;font-weight:700}.table-toolbar p{margin:5px 0 0;color:var(--muted);font-size:.88rem}.count-pill{padding:7px 10px;border-radius:6px;background:#f7eded;color:var(--maroon);font-size:.84rem;font-weight:700}.data-table{margin:0;vertical-align:middle}.data-table th{padding:12px 15px;background:#faf9f8;color:var(--muted);font-size:.74rem;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap}.data-table td{padding:14px 15px;border-bottom-color:#f0ecea}.data-table tr:last-child td{border-bottom:0}.report-title{font-weight:700}.subtext{display:block;margin-top:4px;color:var(--muted);font-size:.84rem}.status-badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:.78rem;font-weight:700;white-space:nowrap}.status-pending{background:#fff2cc;color:#805500}.status-processing{background:#e4efff;color:#1d4f91}.status-done{background:#e4f4e9;color:#206337}.report-photo{display:block;width:64px;height:54px;object-fit:cover;border:1px solid var(--line);border-radius:5px}.detail-panel{min-width:270px;max-width:420px;padding:14px;border:1px solid var(--line);border-radius:6px;background:#fff}.detail-panel p{white-space:normal}.followup-form{min-width:220px}.followup-form textarea{min-height:64px}.empty-state{padding:44px 20px;color:var(--muted);text-align:center}.empty-state strong{display:block;margin-bottom:5px;color:var(--ink)}.student-panel{margin-top:28px}.logout-form{margin:0}.user-name{font-size:.88rem}.btn-maroon{border-color:var(--maroon);background:var(--maroon);color:#fff}.btn-maroon:hover{border-color:#650000;background:#650000;color:#fff}@media(max-width:767.98px){.user-name{display:none}.data-table{min-width:900px}}@media(max-width:575.98px){.page-wrap{padding-top:28px}.table-toolbar{padding:16px}.nav-tools{gap:7px}}
    </style>
</head>
<body>
    <nav class="app-navbar">
        <div class="container-xl d-flex align-items-center justify-content-between">
            <a class="brand-lockup" href="index.php"><img class="school-logo-small" src="img/smk-negeri-1-probolinggo.webp" alt="Logo SMK Negeri 1 Probolinggo"><span><span class="school-name">SMK Negeri 1 Probolinggo</span><span class="app-name">Laporan Sapras Sekolah</span></span></a>
            <div class="nav-tools"><span class="user-name"><?php echo app_escape($user["nama"]); ?> · <?php echo app_escape($roleName); ?></span><a class="navbar-link" href="index.php">Beranda</a><form class="logout-form" method="post" action="index.php"><input type="hidden" name="csrf_token" value="<?php echo app_escape($csrfToken); ?>"><input type="hidden" name="action" value="logout"><button class="navbar-link" type="submit">Keluar</button></form></div>
        </div>
    </nav>

    <main class="container-xl page-wrap">
        <p class="eyebrow">Laporan Sapras Sekolah · <?php echo app_escape($roleName); ?></p>
        <h1 class="page-title"><?php echo $isStaff ? "Penanganan laporan" : "Laporan saya"; ?></h1>
        <p class="page-subtitle"><?php echo $isStaff ? "Tinjau bukti kerusakan dan perbarui tindak lanjut." : "Lihat status serta detail laporan yang Anda buat."; ?></p>

        <?php if (isset($_GET["laporan"]) && $_GET["laporan"] === "terkirim") { ?><div class="alert alert-success" role="status">Laporan berhasil dikirim.</div><?php } ?>
        <?php if (isset($_GET["perubahan"]) && $_GET["perubahan"] === "tersimpan") { ?><div class="alert alert-success" role="status">Perubahan berhasil disimpan.</div><?php } ?>

        <section class="content-panel" id="laporan" aria-labelledby="report-list-title">
            <div class="table-toolbar"><div><h2 id="report-list-title">Daftar laporan</h2><p><?php echo $isStaff ? "Semua laporan sekolah" : "Laporan yang terhubung dengan akun Anda"; ?></p></div><span class="count-pill"><?php echo $reportTotal; ?> laporan</span></div>
            <?php if (!$reportResult) { ?>
                <div class="empty-state"><strong>Daftar belum dapat dimuat</strong>Periksa koneksi dan struktur database.</div>
            <?php } elseif ($reportTotal === 0) { ?>
                <div class="empty-state"><strong>Belum ada laporan</strong><?php echo $isStaff ? "Laporan baru akan muncul di sini." : "Buat laporan kerusakan untuk mulai memantau statusnya."; ?><?php if (!$isStaff) { ?><div class="mt-3"><a class="btn btn-maroon" href="index.php#form-laporan">Buat laporan</a></div><?php } ?></div>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="table data-table">
                        <thead><tr><th>Detail laporan</th><th>Lokasi</th><th>Pelapor</th><th>Tanggal</th><th>Status</th><?php if ($isStaff) { ?><th>Tindak lanjut</th><?php } ?><?php if ($isAdmin) { ?><th>Aksi</th><?php } ?></tr></thead>
                        <tbody>
                        <?php while ($report = mysqli_fetch_assoc($reportResult)) { $statusClass = $report["status"] === "Selesai" ? "status-done" : ($report["status"] === "Diproses" ? "status-processing" : "status-pending"); ?>
                            <tr>
                                <td>
                                    <div class="report-title"><?php echo app_escape($report["judul"]); ?></div><span class="subtext"><?php echo app_escape($report["fasilitas"]); ?></span><?php if ($report["foto_mime"]) { ?><a href="pageview.php?foto=<?php echo (int) $report["id"]; ?>" target="_blank" rel="noopener"><img class="report-photo mt-2" src="pageview.php?foto=<?php echo (int) $report["id"]; ?>" alt="Thumbnail foto bukti <?php echo app_escape($report["judul"]); ?>"></a><?php } ?>
                                    <details class="mt-2"><summary>Detail lengkap</summary><div class="detail-panel mt-2"><p class="mb-2"><?php echo nl2br(app_escape($report["deskripsi"])); ?></p><?php if ($report["foto_mime"]) { ?><strong class="d-block mb-2">Foto bukti</strong><a href="pageview.php?foto=<?php echo (int) $report["id"]; ?>" target="_blank" rel="noopener"><img class="report-photo" style="width:min(100%,360px);height:auto;max-height:300px;object-fit:contain" src="pageview.php?foto=<?php echo (int) $report["id"]; ?>" alt="Foto bukti <?php echo app_escape($report["judul"]); ?>"></a><?php } else { ?><p class="text-secondary mb-0">Tidak ada foto bukti.</p><?php } ?><?php if ($report["catatan_tindak_lanjut"]) { ?><p class="mt-3 mb-0"><strong>Catatan petugas:</strong><br><?php echo nl2br(app_escape($report["catatan_tindak_lanjut"])); ?></p><?php } ?></div></details>
                                </td>
                                <td><?php echo app_escape($report["lokasi"]); ?></td>
                                <td><?php echo app_escape($report["nama_pelapor"]); ?></td>
                                <td><?php echo app_escape(date("d/m/Y H:i", strtotime($report["created_at"]))); ?></td>
                                <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo app_escape($report["status"]); ?></span></td>
                                <?php if ($isStaff) { ?><td><form class="followup-form" method="post" action="pageview.php#laporan"><input type="hidden" name="csrf_token" value="<?php echo app_escape($csrfToken); ?>"><input type="hidden" name="action" value="update_report"><input type="hidden" name="report_id" value="<?php echo (int) $report["id"]; ?>"><label class="visually-hidden" for="status-<?php echo (int) $report["id"]; ?>">Status</label><select class="form-select form-select-sm mb-2" id="status-<?php echo (int) $report["id"]; ?>" name="status"><option<?php echo $report["status"] === "Pending" ? " selected" : ""; ?>>Pending</option><option<?php echo $report["status"] === "Diproses" ? " selected" : ""; ?>>Diproses</option><option<?php echo $report["status"] === "Selesai" ? " selected" : ""; ?>>Selesai</option></select><label class="visually-hidden" for="note-<?php echo (int) $report["id"]; ?>">Catatan tindak lanjut</label><textarea class="form-control form-control-sm mb-2" id="note-<?php echo (int) $report["id"]; ?>" name="catatan" maxlength="5000" placeholder="Catatan tindak lanjut"><?php echo app_escape($report["catatan_tindak_lanjut"] ?? ""); ?></textarea><button class="btn btn-sm btn-maroon" type="submit">Simpan</button></form></td><?php } ?>
                                <?php if ($isAdmin) { ?><td><form method="post" action="pageview.php" onsubmit="return confirm('Hapus laporan ini secara permanen?')"><input type="hidden" name="csrf_token" value="<?php echo app_escape($csrfToken); ?>"><input type="hidden" name="action" value="delete_report"><input type="hidden" name="report_id" value="<?php echo (int) $report["id"]; ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button></form></td><?php } ?>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>

        <?php if ($isAdmin) { ?>
            <section class="content-panel student-panel" aria-labelledby="students-title"><div class="table-toolbar"><div><h2 id="students-title">Data siswa</h2><p>CRUD siswa yang sudah tersedia.</p></div><span class="count-pill"><?php echo count($studentRows); ?> data</span></div><?php if ($studentRows) { ?><div class="table-responsive"><table class="table data-table"><thead><tr><th>Nama</th><th>Kelas</th><th>Aksi</th></tr></thead><tbody><?php foreach ($studentRows as $student) { ?><tr><td><?php echo app_escape($student["nama"]); ?></td><td><?php echo app_escape($student["kelas"]); ?></td><td><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-secondary" href="update.php?id=<?php echo (int) $student["id"]; ?>">Ubah</a><form method="post" action="hapus.php" onsubmit="return confirm('Hapus data siswa ini?')"><input type="hidden" name="csrf_token" value="<?php echo app_escape($csrfToken); ?>"><input type="hidden" name="id" value="<?php echo (int) $student["id"]; ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button></form></div></td></tr><?php } ?></tbody></table></div><?php } else { ?><div class="empty-state">Belum ada data siswa.</div><?php } ?></section>
        <?php } ?>
    </main>
</body>
</html>