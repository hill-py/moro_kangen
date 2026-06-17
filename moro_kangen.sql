-- ============================================================
-- DATABASE: moro_kangen
-- Sistem Kasir & Pemesanan UMKM Mie Ayam Bakso Moro Kangen
-- ============================================================

CREATE DATABASE IF NOT EXISTS moro_kangen
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE moro_kangen;

-- ------------------------------------------------------------
-- 1. USER
-- ------------------------------------------------------------
CREATE TABLE user (
  id_user     INT AUTO_INCREMENT PRIMARY KEY,
  username    VARCHAR(50)  NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,
  role        ENUM('karyawan','pemilik') NOT NULL DEFAULT 'karyawan',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2. KURSI
-- ------------------------------------------------------------
CREATE TABLE kursi (
  id_kursi    INT AUTO_INCREMENT PRIMARY KEY,
  nomor_kursi VARCHAR(10) NOT NULL UNIQUE,
  status      ENUM('kosong','terisi','nonaktif') NOT NULL DEFAULT 'kosong'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. MENU
-- ------------------------------------------------------------
CREATE TABLE menu (
  id_menu     INT AUTO_INCREMENT PRIMARY KEY,
  nama_menu   VARCHAR(100)   NOT NULL,
  kategori    VARCHAR(50)    NOT NULL,
  harga       DECIMAL(10,2)  NOT NULL,
  deskripsi   TEXT,
  status_menu ENUM('tersedia','habis','nonaktif') NOT NULL DEFAULT 'tersedia',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 4. PESANAN
-- ------------------------------------------------------------
CREATE TABLE pesanan (
  id_pesanan     INT AUTO_INCREMENT PRIMARY KEY,
  nama_pelanggan VARCHAR(100) NOT NULL,
  jenis_pesanan  ENUM('dine_in','take_away') NOT NULL,
  total_pesanan  DECIMAL(10,2) NOT NULL DEFAULT 0,
  status         ENUM('menunggu','dibayar','selesai') NOT NULL DEFAULT 'menunggu',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 5. DETAIL_KURSI
-- ------------------------------------------------------------
CREATE TABLE detail_kursi (
  id_detail_kursi INT AUTO_INCREMENT PRIMARY KEY,
  id_pesanan      INT NOT NULL,
  id_kursi        INT NOT NULL,
  CONSTRAINT fk_dk_pesanan FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan) ON DELETE CASCADE,
  CONSTRAINT fk_dk_kursi   FOREIGN KEY (id_kursi)   REFERENCES kursi(id_kursi)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 6. DETAIL_PESANAN
-- ------------------------------------------------------------
CREATE TABLE detail_pesanan (
  id_detail_pesanan INT AUTO_INCREMENT PRIMARY KEY,
  id_pesanan        INT           NOT NULL,
  id_menu           INT           NOT NULL,
  jumlah            INT           NOT NULL DEFAULT 1,
  catatan           VARCHAR(255),
  harga_satuan      DECIMAL(10,2) NOT NULL,
  subtotal          DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_dp_pesanan FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan) ON DELETE CASCADE,
  CONSTRAINT fk_dp_menu    FOREIGN KEY (id_menu)    REFERENCES menu(id_menu)       ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 7. PEMBAYARAN
-- ------------------------------------------------------------
CREATE TABLE pembayaran (
  id_pembayaran     INT AUTO_INCREMENT PRIMARY KEY,
  id_pesanan        INT NOT NULL UNIQUE,
  id_user           INT NOT NULL,
  metode_pembayaran ENUM('cash','qris') NOT NULL,
  status            ENUM('lunas','batal') NOT NULL DEFAULT 'lunas',
  tanggal_bayar     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_pesanan FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan) ON DELETE CASCADE,
  CONSTRAINT fk_pay_user    FOREIGN KEY (id_user)    REFERENCES user(id_user)       ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 8. PENGELUARAN
-- ------------------------------------------------------------
CREATE TABLE pengeluaran (
  id_pengeluaran INT AUTO_INCREMENT PRIMARY KEY,
  id_user        INT           NOT NULL,
  tanggal        DATE          NOT NULL,
  nominal        DECIMAL(10,2) NOT NULL,
  keterangan     VARCHAR(255)  NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pel_user FOREIGN KEY (id_user) REFERENCES user(id_user) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 9. STOK BAHAN BAKU
-- ============================================================
CREATE TABLE stock_bahan_baku (
  id_bahan INT(11) NOT NULL AUTO_INCREMENT,
  nama_bahan VARCHAR(100) NOT NULL,
  stok INT(11) NOT NULL DEFAULT 0,
  satuan VARCHAR(30) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_bahan),
  UNIQUE KEY unique_nama_bahan (nama_bahan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- DATA AWAL (SEEDING)
-- ============================================================

-- User default (password: admin123 -> bcrypt)
INSERT INTO user (username, password, role) VALUES
('admin',  '$2y$10$rjYnEukfsrSTfh/ta2aU/uHIRkKnAjLtAsKid59.dfbpurWg01Bma', 'karyawan'),
('pemilik','$2y$10$rjYnEukfsrSTfh/ta2aU/uHIRkKnAjLtAsKid59.dfbpurWg01Bma', 'pemilik');

-- Kursi awal
INSERT INTO kursi (nomor_kursi, status) VALUES
('A01','kosong'),
('A02','kosong'),
('A03','kosong'),
('A04','kosong'),
('A05','kosong'),
('A06','kosong'),
('A07','kosong'),
('A08','kosong'),
('A09','kosong'),
('A10','kosong'),

('B01','kosong'),
('B02','kosong'),
('B03','kosong'),
('B04','kosong'),
('B05','kosong'),
('B06','kosong'),
('B07','kosong'),
('B08','kosong'),
('B09','kosong'),
('B10','kosong');

-- Menu awal
INSERT INTO menu (nama_menu, kategori, harga, deskripsi, status_menu) VALUES
('Mie Ayam Biasa',   'Makanan',  10000, 'Mie ayam dengan topping ayam cincang', 'tersedia'),
('Mie Ayam Spesial', 'Makanan',  13000, 'Mie ayam dengan topping ayam + pangsit', 'tersedia'),
('Bakso Biasa',      'Makanan',     10000, 'Bakso sapi dengan kuah kaldu', 'tersedia'),
('Bakso Spesial',    'Makanan',     13000, 'Bakso sapi + tahu + pangsit', 'tersedia'),
('Sate Ayam',        'Makanan',      15000, 'Sate ayam dengan bumbu kacang', 'tersedia'),
('Sate Kambing',     'Makanan',      20000, 'Sate kambing dengan bumbu kecap', 'tersedia'),
('Sate Sapi',        'Makanan',      18000, 'Sate sapi dengan bumbu kacang', 'tersedia'),
('Gulai',            'Makanan',     15000, 'Gulai daging dengan kuah santan', 'tersedia'),
('Tongseng',         'Makanan',  16000, 'Tongseng daging dengan sayuran', 'tersedia'),
('Tengkleng',        'Makanan', 15000, 'Tengkleng tulang dengan kuah bening', 'tersedia'),
('Soto',             'Makanan',      12000, 'Soto ayam dengan kuah bening', 'tersedia'),
('Es Jeruk','Minuman',4000,'Es jeruk segar','tersedia'),
('Es Teh Kampul','Minuman',5000,'Es teh kampul khas Moro Kangen','tersedia'),
('Air Putih','Minuman',1000,'Air mineral','tersedia'),
('Es Teh','Minuman',3000,'Es teh manis','tersedia');

