<?php
require_once "variable.php";
app_start_session();
$conn = app_db();
$roles = ["siswa", "guru", "petugas_sapras", "admin"];
$formError = "";
$reporterName = "";
$facility = "";
$location = "";
$title = "";
$description = "";
$photoData = null;
$photoMime = null;
$bootstrapResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tblusers");
$bootstrapRequired = $bootstrapResult && (int) mysqli_fetch_assoc($bootstrapResult)["total"] === 0;
$canBootstrap = $bootstrapRequired && in_array($_SERVER["REMOTE_ADDR"] ?? "", ["127.0.0.1", "::1"], true);
$user = app_current_user($conn);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = is_string($_POST["action"] ?? null) ? $_POST["action"] : "";

  if (!app_csrf_valid()) {
    $formError = "Sesi formulir berakhir. Muat ulang halaman lalu coba lagi.";
  } elseif ($action === "logout" && $user) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
      $cookie = session_get_cookie_params();
      setcookie(session_name(), "", time() - 42000, $cookie["path"], $cookie["domain"], $cookie["secure"], $cookie["httponly"]);
    }
    session_destroy();
    header("Location: index.php");
    exit();
  } elseif ($action === "bootstrap_admin" && $canBootstrap) {
    $lock = mysqli_query($conn, "SELECT GET_LOCK('ruang_sapras_initial_admin', 5) AS acquired");
    $acquired = $lock && (int) mysqli_fetch_assoc($lock)["acquired"] === 1;
    if ($acquired) {
      $bootstrapCheck = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tblusers");
      $bootstrapRequired = $bootstrapCheck && (int) mysqli_fetch_assoc($bootstrapCheck)["total"] === 0;
    }
    $name = trim((string) ($_POST["nama"] ?? ""));
    $username = trim((string) ($_POST["username"] ?? ""));
    $password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";

    if (!$acquired) {
      $formError = "Inisialisasi admin sedang digunakan. Muat ulang halaman.";
    } elseif (!$bootstrapRequired) {
      $formError = "Admin pertama sudah dibuat. Silakan masuk.";
    } elseif ($name === "" || strlen($name) > 400 || !preg_match("/^[A-Za-z0-9._-]{3,50}$/", $username) || strlen($password) < 12) {
      $formError = "Isi nama, username 3-50 karakter, dan password minimal 12 karakter.";
    } else {
      $passwordHash = password_hash($password, PASSWORD_DEFAULT);
      $statement = mysqli_prepare($conn, "INSERT INTO tblusers (nama, username, password_hash, role) VALUES (?, ?, ?, 'admin')");
      if ($statement) {
        mysqli_stmt_bind_param($statement, "sss", $name, $username, $passwordHash);
        if (mysqli_stmt_execute($statement)) {
          session_regenerate_id(true);
          $_SESSION["user_id"] = mysqli_insert_id($conn);
          mysqli_stmt_close($statement);
          mysqli_query($conn, "SELECT RELEASE_LOCK('ruang_sapras_initial_admin')");
          header("Location: index.php");
          exit();
        }
        mysqli_stmt_close($statement);
      }
      $formError = "Admin tidak dapat dibuat. Pastikan username belum digunakan.";
    }

    if ($acquired) {
      mysqli_query($conn, "SELECT RELEASE_LOCK('ruang_sapras_initial_admin')");
    }
  } elseif ($action === "login" && !$user) {
    $username = trim((string) ($_POST["username"] ?? ""));
    $password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";
    $statement = mysqli_prepare($conn, "SELECT id, password_hash FROM tblusers WHERE username = ? AND aktif = 1");
    if ($statement) {
      mysqli_stmt_bind_param($statement, "s", $username);
      mysqli_stmt_execute($statement);
      $account = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
      mysqli_stmt_close($statement);

      if ($account && password_verify($password, $account["password_hash"])) {
        session_regenerate_id(true);
        $_SESSION["user_id"] = (int) $account["id"];
        app_csrf_token();
        header("Location: index.php");
        exit();
      }
    }
    $formError = "Username atau password tidak sesuai.";
  } elseif ($action === "create_user" && $user && $user["role"] === "admin") {
    $name = trim((string) ($_POST["nama"] ?? ""));
    $username = trim((string) ($_POST["username"] ?? ""));
    $role = is_string($_POST["role"] ?? null) ? $_POST["role"] : "";
    $password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";

    if ($name === "" || strlen($name) > 400 || !preg_match("/^[A-Za-z0-9._-]{3,50}$/", $username) || !in_array($role, $roles, true) || strlen($password) < 12) {
      $formError = "Periksa nama, username, role, dan password minimal 12 karakter.";
    } else {
      $passwordHash = password_hash($password, PASSWORD_DEFAULT);
      $statement = mysqli_prepare($conn, "INSERT INTO tblusers (nama, username, password_hash, role) VALUES (?, ?, ?, ?)");
      if ($statement) {
        mysqli_stmt_bind_param($statement, "ssss", $name, $username, $passwordHash, $role);
        if (!mysqli_stmt_execute($statement)) {
          $formError = "Akun tidak dapat dibuat. Pastikan username belum digunakan.";
        }
        mysqli_stmt_close($statement);
      } else {
        $formError = "Akun tidak dapat dibuat.";
      }
    }
  } elseif ($action === "submit_report" && $user && in_array($user["role"], ["siswa", "guru"], true)) {
    $facility = trim((string) ($_POST["fasilitas"] ?? ""));
    $location = trim((string) ($_POST["lokasi"] ?? ""));
    $title = trim((string) ($_POST["judul"] ?? ""));
    $description = trim((string) ($_POST["deskripsi"] ?? ""));
    $reporterName = $user["nama"];
    $file = $_FILES["foto"] ?? null;

    if ($facility === "" || $location === "" || $title === "" || $description === "") {
      $formError = "Lengkapi fasilitas, lokasi, judul, dan deskripsi laporan.";
    } elseif (strlen($facility) > 400 || strlen($location) > 600 || strlen($title) > 600 || strlen($description) > 20000) {
      $formError = "Ada kolom yang melebihi batas panjang.";
    } elseif ($file && $file["error"] !== UPLOAD_ERR_NO_FILE) {
      if ($file["error"] !== UPLOAD_ERR_OK || $file["size"] > 2 * 1024 * 1024 || !is_uploaded_file($file["tmp_name"])) {
        $formError = "Foto gagal diunggah atau melebihi batas 2 MB.";
      } else {
        $imageInfo = @getimagesize($file["tmp_name"]);
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $fileInfo->file($file["tmp_name"]);
        if (!$imageInfo || !in_array($detectedMime, ["image/jpeg", "image/png"], true) || $imageInfo["mime"] !== $detectedMime) {
          $formError = "Foto harus berupa gambar JPG, JPEG, atau PNG yang valid.";
        } else {
          $photoData = file_get_contents($file["tmp_name"]);
          $photoMime = $detectedMime;
          if ($photoData === false) {
            $formError = "Foto gagal dibaca. Silakan pilih file lain.";
          }
        }
      }
    }

    if ($formError === "") {
      $userId = (int) $user["id"];
      $statement = mysqli_prepare($conn, "INSERT INTO tblaporan_sapras (reporter_user_id, nama_pelapor, fasilitas, lokasi, judul, deskripsi, foto, foto_mime) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      if ($statement) {
        mysqli_stmt_bind_param($statement, "isssssss", $userId, $reporterName, $facility, $location, $title, $description, $photoData, $photoMime);
        if (mysqli_stmt_execute($statement)) {
          mysqli_stmt_close($statement);
          $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
          header("Location: pageview.php?laporan=terkirim");
          exit();
        }
        mysqli_stmt_close($statement);
      }
      $formError = "Laporan belum dapat disimpan. Silakan coba kembali.";
    }
  } else {
    http_response_code(403);
    $formError = "Aksi ini tidak diizinkan untuk akun Anda.";
  }
}

$user = app_current_user($conn);
$bootstrapResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tblusers");
$bootstrapRequired = $bootstrapResult && (int) mysqli_fetch_assoc($bootstrapResult)["total"] === 0;
$canBootstrap = $bootstrapRequired && in_array($_SERVER["REMOTE_ADDR"] ?? "", ["127.0.0.1", "::1"], true);
$reportTotal = 0;
$statusCounts = ["Pending" => 0, "Diproses" => 0, "Selesai" => 0];
$activeUsers = 0;
$recentReports = [];
$studentRows = [];
$accountRows = [];

if ($user) {
  if (in_array($user["role"], ["siswa", "guru"], true)) {
    $statement = mysqli_prepare($conn, "SELECT status, COUNT(*) AS total FROM tblaporan_sapras WHERE reporter_user_id = ? GROUP BY status");
    mysqli_stmt_bind_param($statement, "i", $user["id"]);
    mysqli_stmt_execute($statement);
    $statusResult = mysqli_stmt_get_result($statement);
    while ($statusRow = mysqli_fetch_assoc($statusResult)) {
      $statusCounts[$statusRow["status"]] = (int) $statusRow["total"];
    }
    mysqli_stmt_close($statement);
    $statement = mysqli_prepare($conn, "SELECT id, judul, fasilitas, lokasi, nama_pelapor, status, created_at, foto_mime FROM tblaporan_sapras WHERE reporter_user_id = ? ORDER BY created_at DESC LIMIT 5");
    mysqli_stmt_bind_param($statement, "i", $user["id"]);
  } else {
    $statusResult = mysqli_query($conn, "SELECT status, COUNT(*) AS total FROM tblaporan_sapras GROUP BY status");
    while ($statusRow = mysqli_fetch_assoc($statusResult)) {
      $statusCounts[$statusRow["status"]] = (int) $statusRow["total"];
    }
    if ($user["role"] === "admin") {
      $activeUserResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tblusers WHERE aktif = 1");
      $activeUsers = (int) mysqli_fetch_assoc($activeUserResult)["total"];
    }
    $statement = mysqli_prepare($conn, "SELECT id, judul, fasilitas, lokasi, nama_pelapor, status, created_at, foto_mime FROM tblaporan_sapras ORDER BY created_at DESC LIMIT 5");
  }
  $reportTotal = array_sum($statusCounts);
  mysqli_stmt_execute($statement);
  $recentResult = mysqli_stmt_get_result($statement);
  while ($recent = mysqli_fetch_assoc($recentResult)) {
    $recentReports[] = $recent;
  }
  mysqli_stmt_close($statement);

  if ($user["role"] === "admin") {
    $studentResult = mysqli_query($conn, "SELECT id, nama, kelas FROM tbsiswa ORDER BY nama");
    while ($student = mysqli_fetch_assoc($studentResult)) {
      $studentRows[] = $student;
    }
    $accountResult = mysqli_query($conn, "SELECT id, nama, username, role, aktif FROM tblusers ORDER BY nama");
    while ($account = mysqli_fetch_assoc($accountResult)) {
      $accountRows[] = $account;
    }
  }
}
$csrfToken = app_csrf_token();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#800000">
  <title>Laporan Sapras Sekolah | Ruang Sapras</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root{--maroon:#800000;--ink:#262321;--muted:#77716e;--line:#e8e3e0;--paper:#fff;--canvas:#f5f3f1;--radius:8px;--blue:#245b99;--green:#246b3b;--yellow:#805500}*{box-sizing:border-box}body{min-height:100vh;margin:0;background:var(--canvas);color:var(--ink);font-family:'DM Sans','Segoe UI',sans-serif;-webkit-font-smoothing:antialiased}.app-navbar,.app-navbar .container-xl{min-height:68px;background:var(--maroon);color:var(--paper)}.brand-lockup{display:inline-flex;align-items:center;gap:11px;color:var(--paper);font-weight:700;text-decoration:none}.brand-mark{display:grid;width:34px;height:34px;place-items:center;border:1px solid #ffffff77;border-radius:7px;font-size:.78rem}.navbar-tools{display:flex;align-items:center;gap:12px}.navbar-user{font-size:.88rem}.navbar-link{display:inline-flex;min-height:38px;align-items:center;padding:0 13px;border:1px solid #ffffff66;border-radius:6px;color:var(--paper);font-size:.9rem;font-weight:600;text-decoration:none}.navbar-link:hover{background:#ffffff1f;color:var(--paper)}.page-wrap{max-width:1080px;padding-top:42px;padding-bottom:56px}.page-heading{margin-bottom:28px}.eyebrow{margin-bottom:8px;color:var(--maroon);font-size:.76rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase}.page-title{margin:0;font-size:clamp(1.65rem,3vw,2.1rem);font-weight:700}.page-subtitle{max-width:620px;margin:9px 0 0;color:var(--muted);line-height:1.65}.content-panel{border:1px solid var(--line);border-radius:var(--radius);background:var(--paper);box-shadow:0 8px 24px #2f1f190a}.form-panel{max-width:760px;padding:clamp(22px,4vw,36px)}.panel-heading{margin-bottom:24px}.panel-heading h2{margin:0;font-size:1.2rem;font-weight:700}.panel-heading p{margin:7px 0 0;color:var(--muted);font-size:.92rem}.form-label{margin-bottom:7px;font-size:.9rem;font-weight:600}.form-control{min-height:46px;border-color:#dcd5d1;border-radius:6px;color:var(--ink)}.form-control:focus{border-color:var(--maroon);box-shadow:0 0 0 .2rem #8000001f}.btn-maroon{min-height:44px;padding:10px 17px;border:1px solid var(--maroon);border-radius:6px;background:var(--maroon);color:var(--paper);font-weight:600}.btn-maroon:hover{border-color:#650000;background:#650000;color:var(--paper)}.back-link{color:var(--maroon);font-size:.9rem;font-weight:600;text-decoration:none}.back-link:hover{color:#650000;text-decoration:underline}.report-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}.report-grid .wide-field{grid-column:1/-1}.form-error{border-radius:6px}.section-kicker{color:var(--maroon);font-size:.78rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.dashboard-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:24px}.summary-panel{padding:18px 20px}.summary-label{color:var(--muted);font-size:.84rem}.summary-value{margin-top:3px;color:var(--maroon);font-size:1.55rem;font-weight:700}.stack-gap{display:grid;gap:20px}.split-grid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(280px,.9fr);gap:20px}.wide-panel{padding:22px}.panel-table{margin:0;vertical-align:middle}.panel-table th{color:var(--muted);font-size:.76rem;text-transform:uppercase}.panel-table td,.panel-table th{padding:12px}.report-entry{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:13px 0;border-bottom:1px solid var(--line)}.report-entry:last-child{border-bottom:0}.report-entry small{display:block;margin-top:4px;color:var(--muted)}.status-badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:.78rem;font-weight:700;white-space:nowrap}.status-pending{background:#fff2cc;color:var(--yellow)}.status-processing{background:#e4efff;color:var(--blue)}.status-done{background:#e4f4e9;color:var(--green)}.thumb{width:48px;height:48px;flex:0 0 48px;object-fit:cover;border:1px solid var(--line);border-radius:5px}.auth-wrap{max-width:560px}.auth-panel{padding:clamp(22px,4vw,34px)}.role-label{color:var(--muted);font-size:.83rem}.admin-section{margin-top:24px}@media(max-width:767.98px){.dashboard-grid{grid-template-columns:1fr 1fr}.split-grid{grid-template-columns:1fr}.navbar-user{display:none}}@media(max-width:575.98px){.page-wrap{padding-top:30px}.report-grid{grid-template-columns:1fr}.report-grid .wide-field{grid-column:auto}.dashboard-grid{grid-template-columns:1fr 1fr}}
  </style>
  <style>
    .school-logo{display:block;width:104px;height:104px;object-fit:contain;background:#fff;border-radius:16px;padding:9px}
    .school-logo-small{width:38px;height:38px;object-fit:contain;background:#fff;border-radius:7px;padding:3px}
    .school-lockup{display:flex;align-items:center;gap:10px}
    .school-lockup span{display:block}
    .school-name{font-size:.93rem;font-weight:700}
    .app-name{margin-top:2px;color:#ffffffc9;font-size:.75rem}
    .login-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(340px,.82fr);max-width:1000px;min-height:470px;overflow:hidden;border-radius:12px;background:#fff;box-shadow:0 18px 48px #30161317}
    .identity-panel{display:flex;flex-direction:column;align-items:flex-start;justify-content:space-between;padding:clamp(28px,5vw,54px);background:linear-gradient(145deg,#800000 0%,#650000 100%);color:#fff}
    .identity-copy{max-width:430px;margin-top:34px}
    .identity-copy .eyebrow{color:#ffffffb8}
    .identity-copy h1{max-width:420px;color:#fff;font-size:clamp(1.8rem,3vw,2.55rem);line-height:1.12}
    .identity-copy p{max-width:390px;margin-top:15px;color:#ffffffd1;line-height:1.7}
    .identity-school{margin-top:24px;color:#fff;font-size:.9rem;font-weight:600}
    .auth-panel{align-self:center;width:100%;padding:clamp(25px,4vw,42px)}
    .auth-panel .form-control{background:#fcfbfa}
    .auth-heading{margin-bottom:27px}
    .auth-heading h2{margin:0;font-size:1.45rem;font-weight:700}
    .auth-heading p{margin:8px 0 0;color:var(--muted);line-height:1.5}
    .school-copyright{margin:19px 0 0;color:var(--muted);font-size:.78rem;text-align:center}
    .dashboard-greeting{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;margin-bottom:24px}
    .dashboard-greeting .page-subtitle{margin-bottom:0}
    .role-chip{display:inline-flex;align-items:center;padding:7px 11px;border:1px solid #e5d1d1;border-radius:999px;background:#fbf4f4;color:var(--maroon);font-size:.8rem;font-weight:700;white-space:nowrap}
    .dashboard-grid{grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:22px}
    .summary-panel{min-height:104px;border-left:3px solid var(--maroon);transition:transform .16s ease,box-shadow .16s ease}
    .summary-panel:hover{transform:translateY(-2px);box-shadow:0 12px 28px #2f1f1910}
    .summary-value{font-size:1.7rem}
    .report-cta{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:24px;padding:22px 24px;border:1px solid #e8d5d5;border-radius:9px;background:#fffafa}
    .report-cta h2{margin:0;font-size:1.12rem;font-weight:700}
    .report-cta p{margin:5px 0 0;color:var(--muted);font-size:.9rem}
    .btn-report{min-height:44px;padding:10px 17px;border:1px solid var(--maroon);border-radius:7px;background:var(--maroon);color:#fff;font-weight:700;text-decoration:none;white-space:nowrap}
    .btn-report:hover{background:#650000;color:#fff}
    .recent-panel{padding:22px 24px}
    @media(max-width:767.98px){.login-layout{grid-template-columns:1fr;max-width:560px}.identity-panel{min-height:300px;padding:28px}.identity-copy{margin-top:24px}.identity-copy h1{font-size:1.9rem}.auth-panel{padding:28px}.dashboard-greeting{align-items:flex-start}.role-chip{margin-top:6px}}
    @media(max-width:575.98px){.identity-panel{min-height:275px}.school-logo{width:82px;height:82px}.login-layout{border-radius:10px}.report-cta{align-items:flex-start;flex-direction:column;padding:18px}.report-cta .btn-report{width:100%;text-align:center}.dashboard-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.summary-panel{min-height:92px;padding:15px}}
  </style>
  <style>
    @media(max-width:767.98px){.split-grid{width:100%;grid-template-columns:minmax(0,1fr)}.split-grid>*{width:100%;min-width:0;max-width:100%}.wide-panel{min-width:0}}
    @media(max-width:575.98px){.report-entry{flex-wrap:wrap}.report-entry>div{min-width:0;max-width:100%;overflow-wrap:anywhere}.navbar-tools{gap:5px}.navbar-link{padding-right:9px;padding-left:9px}.brand-lockup{gap:6px}.school-name{font-size:.78rem}.app-name{font-size:.67rem}}
  </style>
</head>
<body>
  <nav class="app-navbar">
    <div class="container-xl d-flex align-items-center justify-content-between">
      <a class="brand-lockup" href="index.php">
        <img class="school-logo-small" src="img/smk-negeri-1-probolinggo.webp" alt="Logo SMK Negeri 1 Probolinggo">
        <span class="school-lockup"><span><span class="school-name">SMK Negeri 1 Probolinggo</span><span class="app-name">Laporan Sapras Sekolah</span></span></span>
      </a>
      <?php if ($user) { ?>
        <div class="navbar-tools">
          <span class="navbar-user"><?php echo app_escape($user["nama"]); ?> · <?php echo app_escape(ucwords(str_replace("_", " ", $user["role"]))); ?></span>
          <a class="navbar-link" href="pageview.php">Laporan</a>
          <form method="post" action="index.php" class="m-0">
            <input type="hidden" name="csrf_token" value="<?php echo app_escape($csrfToken); ?>">
            <input type="hidden" name="action" value="logout">
            <button class="navbar-link" type="submit">Keluar</button>
          </form>
        </div>
      <?php } ?>
    </div>
  </nav>

  <main class="container-xl page-wrap">
    <?php if ($formError !== "") { ?>
      <div class="alert alert-danger" role="alert"><?php echo app_escape($formError); ?></div>
    <?php } ?>

    <?php if ($canBootstrap) { ?>
      <section class="login-layout">
        <div class="identity-panel"><img class="school-logo" src="img/smk-negeri-1-probolinggo.webp" alt="Logo SMK Negeri 1 Probolinggo"><div class="identity-copy"><p class="eyebrow">Sistem sekolah</p><h1>Laporan Sapras Sekolah</h1><p>Sistem Pelaporan Sarana dan Prasarana Sekolah</p><div class="identity-school">SMK Negeri 1 Probolinggo</div></div><div class="app-name">© <?php echo date("Y"); ?> SMK Negeri 1 Probolinggo</div></div>
        <div class="auth-panel"><div class="auth-heading"><h2>Penyiapan awal</h2><p>Buat akun admin pertama untuk mengelola akun siswa, guru, dan petugas.</p></div><form method="post" action="index.php"><input type="hidden" name="csrf_token" value="<?php echo app_escape($csrfToken); ?>"><input type="hidden" name="action" value="bootstrap_admin"><div class="mb-3"><label class="form-label" for="bootstrap-name">Nama admin</label><input class="form-control" id="bootstrap-name" name="nama" maxlength="100" required></div><div class="mb-3"><label class="form-label" for="bootstrap-user">Username</label><input class="form-control" id="bootstrap-user" name="username" minlength="3" maxlength="50" autocomplete="username" required></div><div class="mb-4"><label class="form-label" for="bootstrap-password">Password</label><input class="form-control" id="bootstrap-password" type="password" name="password" minlength="12" autocomplete="new-password" required><div class="role-label mt-1">Gunakan minimal 12 karakter.</div></div><button class="btn btn-maroon w-100" type="submit">Buat admin dan masuk</button></form></div>
      </section>
    <?php } elseif ($bootstrapRequired) { ?>
      <header class="page-heading"><p class="eyebrow">Penyiapan awal</p><h1 class="page-title">Penyiapan admin perlu akses lokal</h1><p class="page-subtitle">Buka aplikasi melalui localhost pada server untuk membuat akun admin pertama.</p></header>
    <?php } elseif (!$user) { ?>
      <section class="login-layout">
        <div class="identity-panel"><img class="school-logo" src="img/smk-negeri-1-probolinggo.webp" alt="Logo SMK Negeri 1 Probolinggo"><div class="identity-copy"><p class="eyebrow">Sistem sekolah</p><h1>Laporan Sapras Sekolah</h1><p>Sistem Pelaporan Sarana dan Prasarana Sekolah. Laporkan kerusakan dan ikuti proses penanganannya.</p><div class="identity-school">SMK Negeri 1 Probolinggo</div></div><div class="app-name">© <?php echo date("Y"); ?> SMK Negeri 1 Probolinggo</div></div>
        <div class="auth-panel"><div class="auth-heading"><h2>Selamat datang</h2><p>Masuk untuk membuat atau menangani laporan fasilitas sekolah.</p></div><form method="post" action="index.php"><input type="hidden" name="csrf_token" value="<?php echo app_escape($csrfToken); ?>"><input type="hidden" name="action" value="login"><div class="mb-3"><label class="form-label" for="login-user">Username</label><input class="form-control" id="login-user" name="username" autocomplete="username" required></div><div class="mb-4"><label class="form-label" for="login-password">Password</label><input class="form-control" id="login-password" type="password" name="password" autocomplete="current-password" required></div><button class="btn btn-maroon w-100" type="submit">Masuk</button></form></div>
      </section>
    <?php } else { ?>
      <header class="dashboard-greeting"><div><p class="eyebrow">SMK Negeri 1 Probolinggo · Ruang Sapras</p><h1 class="page-title">Selamat datang, <?php echo app_escape($user["nama"]); ?></h1><p class="page-subtitle">Apa yang ingin kamu lakukan hari ini?</p></div><span class="role-chip"><?php echo app_escape(ucwords(str_replace("_", " ", $user["role"]))); ?></span></header>

      <div class="dashboard-grid">
        <div class="content-panel summary-panel"><div class="summary-label"><?php echo in_array($user["role"], ["siswa", "guru"], true) ? "Laporan saya" : "Total laporan"; ?></div><div class="summary-value"><?php echo $reportTotal; ?></div></div>
        <?php foreach ($statusCounts as $statusName => $statusTotal) { ?><div class="content-panel summary-panel"><div class="summary-label"><?php echo app_escape($statusName); ?></div><div class="summary-value"><?php echo $statusTotal; ?></div></div><?php } ?>
        <?php if ($user["role"] === "admin") { ?><div class="content-panel summary-panel"><div class="summary-label">Akun aktif</div><div class="summary-value"><?php echo $activeUsers; ?></div></div><?php } ?>
      </div>

      <?php if (in_array($user["role"], ["siswa", "guru"], true)) { ?>
        <section class="report-cta"><div><h2>Laporkan kerusakan</h2><p>Sertakan foto bukti agar petugas lebih mudah menindaklanjuti.</p></div><a class="btn-report" href="#form-laporan">+ Buat Laporan</a></section>
        <section class="content-panel form-panel mb-4" id="form-laporan" aria-labelledby="form-title">
          <div class="panel-heading"><p class="section-kicker">Buat laporan</p><h2 id="form-title">Laporkan Kerusakan</h2><p>Pelapor: <?php echo app_escape($user["nama"]); ?></p></div>
          <form action="index.php#form-laporan" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo app_escape($csrfToken); ?>"><input type="hidden" name="action" value="submit_report">
            <div class="report-grid">
              <div class="mb-3"><label class="form-label" for="fasilitas">Fasilitas yang bermasalah</label><input class="form-control" id="fasilitas" name="fasilitas" type="text" list="pilihan-fasilitas" maxlength="100" value="<?php echo app_escape($facility); ?>" placeholder="Pilih atau ketik fasilitas" required><datalist id="pilihan-fasilitas"><option value="Meja"><option value="Kursi"><option value="Pintu"><option value="Jendela"><option value="Lampu"><option value="Kipas/AC"><option value="Toilet"><option value="Proyektor"><option value="Komputer"><option value="Jaringan/Internet"><option value="Lainnya"></datalist></div>
              <div class="mb-3"><label class="form-label" for="lokasi">Lokasi fasilitas</label><input class="form-control" id="lokasi" name="lokasi" maxlength="150" value="<?php echo app_escape($location); ?>" placeholder="Contoh: Ruang 10 RPL" required></div>
              <div class="mb-3 wide-field"><label class="form-label" for="judul">Judul laporan</label><input class="form-control" id="judul" name="judul" maxlength="150" value="<?php echo app_escape($title); ?>" placeholder="Contoh: Kursi kelas 10 RPL rusak" required></div>
              <div class="mb-3 wide-field"><label class="form-label" for="deskripsi">Deskripsi kerusakan</label><textarea class="form-control" id="deskripsi" name="deskripsi" rows="5" maxlength="5000" placeholder="Jelaskan kondisi kerusakan..." required><?php echo app_escape($description); ?></textarea></div>
              <div class="mb-4 wide-field"><label class="form-label" for="foto">Foto bukti (JPG/PNG, maks. 2 MB)</label><input class="form-control" id="foto" name="foto" type="file" accept="image/jpeg,image/png"><div class="role-label mt-1">Jangan unggah foto yang memuat informasi pribadi sensitif.</div></div>
            </div>
            <button class="btn btn-maroon" type="submit">Kirim Laporan</button>
          </form>
        </section>
      <?php } ?>

      <section class="content-panel recent-panel mb-4" aria-labelledby="recent-title">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><h2 class="h5 mb-0" id="recent-title">Laporan terbaru</h2><a class="back-link" href="pageview.php">Lihat semua</a></div>
        <?php if ($recentReports) { foreach ($recentReports as $recent) { $recentStatusClass = $recent["status"] === "Selesai" ? "status-done" : ($recent["status"] === "Diproses" ? "status-processing" : "status-pending"); ?>
          <div class="report-entry">
            <div class="d-flex align-items-center gap-3">
              <?php if ($recent["foto_mime"]) { ?><img class="thumb" src="pageview.php?foto=<?php echo (int) $recent["id"]; ?>" alt="Foto bukti laporan"><?php } ?>
              <div><strong><?php echo app_escape($recent["judul"]); ?></strong><small><?php echo app_escape($recent["fasilitas"]); ?> · <?php echo app_escape(date("d/m/Y", strtotime($recent["created_at"]))); ?><?php if (in_array($user["role"], ["petugas_sapras", "admin"], true)) { ?> · <?php echo app_escape($recent["nama_pelapor"]); ?> · <?php echo app_escape($recent["lokasi"]); ?><?php } ?></small></div>
            </div>
            <span class="status-badge <?php echo $recentStatusClass; ?>"><?php echo app_escape($recent["status"]); ?></span>
          </div>
        <?php } } else { ?><p class="text-secondary mb-0">Belum ada laporan tercatat.</p><?php } ?>
      </section>

      <?php if ($user["role"] === "admin") { ?>
        <section class="split-grid admin-section" id="akun">
          <div class="content-panel wide-panel">
            <div class="panel-heading"><h2>Tambah akun</h2><p>Admin membuat akun dengan role yang sesuai.</p></div>
            <form method="post" action="index.php#akun">
              <input type="hidden" name="csrf_token" value="<?php echo app_escape($csrfToken); ?>"><input type="hidden" name="action" value="create_user">
              <div class="mb-3"><label class="form-label" for="account-name">Nama</label><input class="form-control" id="account-name" name="nama" maxlength="100" required></div>
              <div class="mb-3"><label class="form-label" for="account-user">Username</label><input class="form-control" id="account-user" name="username" minlength="3" maxlength="50" required></div>
              <div class="mb-3"><label class="form-label" for="account-role">Role</label><select class="form-select" id="account-role" name="role" required><option value="siswa">Siswa</option><option value="guru">Guru</option><option value="petugas_sapras">Petugas Sapras</option><option value="admin">Admin</option></select></div>
              <div class="mb-4"><label class="form-label" for="account-password">Password sementara</label><input class="form-control" id="account-password" type="password" name="password" minlength="12" autocomplete="new-password" required></div>
              <button class="btn btn-maroon" type="submit">Buat akun</button>
            </form>
          </div>
          <div class="content-panel wide-panel">
            <div class="panel-heading"><h2>Akun pengguna</h2><p>Akun dapat dinonaktifkan oleh admin.</p></div>
            <?php foreach ($accountRows as $account) { ?>
              <div class="report-entry"><div><strong><?php echo app_escape($account["nama"]); ?></strong><small><?php echo app_escape($account["username"]); ?> · <?php echo app_escape(ucwords(str_replace("_", " ", $account["role"]))); ?></small></div><form method="post" action="pageview.php" class="m-0"><input type="hidden" name="csrf_token" value="<?php echo app_escape($csrfToken); ?>"><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="user_id" value="<?php echo (int) $account["id"]; ?>"><button class="btn btn-sm <?php echo $account["aktif"] ? "btn-outline-danger" : "btn-outline-success"; ?>" type="submit" <?php echo (int) $account["id"] === (int) $user["id"] ? "disabled" : ""; ?>><?php echo $account["aktif"] ? "Nonaktifkan" : "Aktifkan"; ?></button></form></div>
            <?php } ?>
          </div>
        </section>

        <section class="content-panel wide-panel admin-section">
          <div class="panel-heading"><h2>Tambah data siswa</h2><p>CRUD siswa lama tetap menggunakan handler yang tersedia.</p></div>
          <form action="prosesinput.php" method="post" class="report-grid"><input type="hidden" name="csrf_token" value="<?php echo app_escape($csrfToken); ?>"><div class="mb-3"><label class="form-label" for="student-name">Nama siswa</label><input class="form-control" id="student-name" name="nama" required></div><div class="mb-3"><label class="form-label" for="student-class">Kelas</label><input class="form-control" id="student-class" name="kelas" required></div><div class="wide-field"><button class="btn btn-maroon" type="submit">Tambah siswa</button></div></form>
          <div class="table-responsive mt-4"><table class="table panel-table"><thead><tr><th>Nama</th><th>Kelas</th><th>Aksi</th></tr></thead><tbody><?php foreach ($studentRows as $student) { ?><tr><td><?php echo app_escape($student["nama"]); ?></td><td><?php echo app_escape($student["kelas"]); ?></td><td><a class="btn btn-sm btn-outline-secondary" href="update.php?id=<?php echo (int) $student["id"]; ?>">Ubah</a></td></tr><?php } ?></tbody></table></div>
        </section>
      <?php } ?>
    <?php } ?>
  </main>
</body>
</html>