-- ============================================================
--  LOST2FOUND - Barangay Lost & Found Management System
--  Database: MySQL (import via phpMyAdmin or run install.php)
-- ============================================================

CREATE DATABASE IF NOT EXISTS lost2found
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE lost2found;

-- ---------- USERS ----------
CREATE TABLE IF NOT EXISTS users (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  full_name      VARCHAR(100) NOT NULL,
  email          VARCHAR(100) NOT NULL UNIQUE,
  password       VARCHAR(255) NOT NULL,
  contact_number VARCHAR(20)  NOT NULL,
  address        VARCHAR(150) NOT NULL,
  role           ENUM('resident','admin') DEFAULT 'resident',
  status         ENUM('pending','active','inactive','rejected') DEFAULT 'pending',
  residency_proof VARCHAR(255) DEFAULT NULL,     -- photo of valid ID / barangay certificate (jpg/png/webp)
  review_remarks   VARCHAR(255) DEFAULT NULL,    -- reason written by the admin when a registration is rejected
  reviewed_by      INT DEFAULT NULL,             -- admin who approved/rejected the registration
  reviewed_at      DATETIME DEFAULT NULL,        -- when the registration was reviewed
  show_name      TINYINT(1) DEFAULT 1,          -- 1 = show real name, 0 = use anonymous_name
  anonymous_name VARCHAR(60) DEFAULT NULL UNIQUE, -- stable per-user alias shown to other residents
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- CATEGORIES ----------
CREATE TABLE IF NOT EXISTS categories (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(80) NOT NULL UNIQUE,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- LOCATIONS ----------
CREATE TABLE IF NOT EXISTS locations (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  location_name VARCHAR(100) NOT NULL UNIQUE,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- LOST ITEMS ----------
CREATE TABLE IF NOT EXISTS lost_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  category_id INT NOT NULL,
  location_id INT NOT NULL,
  item_name   VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  color       VARCHAR(40)  NOT NULL,
  brand       VARCHAR(60)  DEFAULT NULL,
  date_lost   DATE NOT NULL,
  photo       VARCHAR(255) DEFAULT NULL,
  status      ENUM('pending','approved','rejected','matched','claimed','closed')
              DEFAULT 'pending',
  reporter_privacy ENUM('name','anonymous') DEFAULT 'name',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (location_id) REFERENCES locations(id)
) ENGINE=InnoDB;

-- ---------- FOUND ITEMS ----------
CREATE TABLE IF NOT EXISTS found_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  category_id INT NOT NULL,
  location_id INT NOT NULL,
  item_name   VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  color       VARCHAR(40)  NOT NULL,
  brand       VARCHAR(60)  DEFAULT NULL,
  date_found  DATE NOT NULL,
  photo       VARCHAR(255) DEFAULT NULL,
  status      ENUM('pending','approved','rejected','matched','returned','closed')
              DEFAULT 'pending',
  reporter_privacy ENUM('name','anonymous') DEFAULT 'name',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (location_id) REFERENCES locations(id)
) ENGINE=InnoDB;

-- ---------- MATCHES (unique feature: weighted scoring) ----------
CREATE TABLE IF NOT EXISTS matches (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  lost_item_id  INT NOT NULL,
  found_item_id INT NOT NULL,
  match_score   INT NOT NULL,              -- 0-100
  status        ENUM('pending','confirmed','dismissed') DEFAULT 'pending',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pair (lost_item_id, found_item_id),
  FOREIGN KEY (lost_item_id)  REFERENCES lost_items(id)  ON DELETE CASCADE,
  FOREIGN KEY (found_item_id) REFERENCES found_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- CLAIM REQUESTS ----------
CREATE TABLE IF NOT EXISTS claim_requests (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  found_item_id INT NOT NULL,
  claimant_id   INT NOT NULL,
  reason        TEXT NOT NULL,
  proof         VARCHAR(255) DEFAULT NULL,
  status        ENUM('pending','approved','rejected') DEFAULT 'pending',
  reviewed_by   INT DEFAULT NULL,
  reviewed_at   DATETIME DEFAULT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (found_item_id) REFERENCES found_items(id) ON DELETE CASCADE,
  FOREIGN KEY (claimant_id)   REFERENCES users(id)       ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by)   REFERENCES users(id)       ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- NOTIFICATIONS ----------
CREATE TABLE IF NOT EXISTS notifications (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  message    VARCHAR(255) NOT NULL,
  type       ENUM('match','claim','status','system') DEFAULT 'system',
  link       VARCHAR(150) DEFAULT NULL,
  is_read    TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  SEED DATA
--  Demo login accounts (ready to use after import — no need to register).
--  Passwords are bcrypt hashes made with PHP password_hash().
--
--    Admin:    admin@lost2found.ph / Admin1234
--    Resident: mareiabygailluci.dost@gmail.com / Jeremiah29:11
--
--  Re-importing this file will NOT delete existing reports; it only
--  inserts/updates these two demo accounts and the default
--  categories/locations.
-- ============================================================

INSERT INTO categories (category_name) VALUES
  ('Electronics'),('Personal Items'),('Documents'),('Accessories'),
  ('Clothing'),('Keys'),('Bags'),('Others')
ON DUPLICATE KEY UPDATE category_name = category_name;

INSERT INTO locations (location_name) VALUES
  ('Barangay Hall'),('Barangay Covered Court'),('Health Center'),
  ('Purok 1'),('Purok 2'),('Purok 3'),('Chapel'),('Other')
ON DUPLICATE KEY UPDATE location_name = location_name;

-- ---------- DEMO ACCOUNTS (upsert by email, safe to re-import) ----------
INSERT INTO users (full_name, email, password, contact_number, address, role, status, anonymous_name) VALUES
  ('Barangay Admin', 'admin@lost2found.ph', '$2y$10$ugZmJ4714BYzL/9wBwbd6OGNJhm3kfWggGobD.Fdmd00nr9i99j1m', '09171234567', 'Purok 1', 'admin', 'active', 'Anonymous Admin 0001'),
  ('Marei Abygail Luci', 'mareiabygailluci.dost@gmail.com', '$2y$10$eq7SRUGyJU8Ti0UjjifSG.HmJ3/67fkyC4M6oFCLs2k2qObJCMdgq', '09171234568', 'Purok 2', 'resident', 'active', 'Anonymous Brave Owl 4812')
ON DUPLICATE KEY UPDATE
  password = VALUES(password),
  role     = VALUES(role),
  status   = VALUES(status);
