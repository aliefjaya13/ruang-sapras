CREATE TABLE IF NOT EXISTS tbsiswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    kelas VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tblusers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('siswa', 'guru', 'petugas_sapras', 'admin') NOT NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tblaporan_sapras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_user_id INT NULL,
    nama_pelapor VARCHAR(100) NOT NULL,
    fasilitas VARCHAR(100) NOT NULL,
    lokasi VARCHAR(150) NOT NULL,
    judul VARCHAR(150) NOT NULL,
    deskripsi TEXT NOT NULL,
    status ENUM('Pending', 'Diproses', 'Selesai') NOT NULL DEFAULT 'Pending',
    foto MEDIUMBLOB NULL,
    foto_mime VARCHAR(20) NULL,
    catatan_tindak_lanjut TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @reporter_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblaporan_sapras' AND COLUMN_NAME = 'reporter_user_id'
);
SET @reporter_column_sql = IF(@reporter_column_exists = 0, 'ALTER TABLE tblaporan_sapras ADD COLUMN reporter_user_id INT NULL', 'SELECT 1');
PREPARE reporter_column_statement FROM @reporter_column_sql;
EXECUTE reporter_column_statement;
DEALLOCATE PREPARE reporter_column_statement;

SET @photo_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblaporan_sapras' AND COLUMN_NAME = 'foto'
);
SET @photo_column_sql = IF(@photo_column_exists = 0, 'ALTER TABLE tblaporan_sapras ADD COLUMN foto MEDIUMBLOB NULL', 'SELECT 1');
PREPARE photo_column_statement FROM @photo_column_sql;
EXECUTE photo_column_statement;
DEALLOCATE PREPARE photo_column_statement;

SET @photo_mime_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblaporan_sapras' AND COLUMN_NAME = 'foto_mime'
);
SET @photo_mime_column_sql = IF(@photo_mime_column_exists = 0, 'ALTER TABLE tblaporan_sapras ADD COLUMN foto_mime VARCHAR(20) NULL', 'SELECT 1');
PREPARE photo_mime_column_statement FROM @photo_mime_column_sql;
EXECUTE photo_mime_column_statement;
DEALLOCATE PREPARE photo_mime_column_statement;

SET @followup_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblaporan_sapras' AND COLUMN_NAME = 'catatan_tindak_lanjut'
);
SET @followup_column_sql = IF(@followup_column_exists = 0, 'ALTER TABLE tblaporan_sapras ADD COLUMN catatan_tindak_lanjut TEXT NULL', 'SELECT 1');
PREPARE followup_column_statement FROM @followup_column_sql;
EXECUTE followup_column_statement;
DEALLOCATE PREPARE followup_column_statement;

SET @reporter_fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tblaporan_sapras'
      AND CONSTRAINT_NAME = 'fk_laporan_reporter'
);
SET @reporter_fk_sql = IF(
    @reporter_fk_exists = 0,
    'ALTER TABLE tblaporan_sapras ADD CONSTRAINT fk_laporan_reporter FOREIGN KEY (reporter_user_id) REFERENCES tblusers(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE reporter_fk_statement FROM @reporter_fk_sql;
EXECUTE reporter_fk_statement;
DEALLOCATE PREPARE reporter_fk_statement;