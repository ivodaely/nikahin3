-- =========================================================
-- nikahin — MySQL schema
-- Compatible with MySQL 5.7+ / MariaDB 10.3+
-- =========================================================

CREATE DATABASE IF NOT EXISTS nikahin
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nikahin;

-- ---------------------------------------------------------
-- users
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email           VARCHAR(190) NOT NULL UNIQUE,
  phone_e164      VARCHAR(20)  NOT NULL UNIQUE,
  display_name    VARCHAR(120) DEFAULT NULL,
  status          ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
  is_admin        TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at   DATETIME DEFAULT NULL,
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- otp_codes (passwordless auth)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS otp_codes (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier      VARCHAR(190) NOT NULL,        -- email or phone (whichever submitted)
  code_hash       VARCHAR(255) NOT NULL,
  purpose         ENUM('register','login') NOT NULL,
  attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at      DATETIME NOT NULL,
  consumed_at     DATETIME DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_identifier (identifier),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- invitations
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS invitations (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  tier            ENUM('basic','ai') NOT NULL DEFAULT 'basic',
  status          ENUM('draft','pending_payment','paid','generating','ready_for_preview','published','flagged') NOT NULL DEFAULT 'draft',
  slug            VARCHAR(120) DEFAULT NULL UNIQUE,
  -- Couple identity (denormalized for fast listing)
  groom_name      VARCHAR(120) DEFAULT NULL,
  bride_name      VARCHAR(120) DEFAULT NULL,
  wedding_date    DATE DEFAULT NULL,
  -- Profile JSON (groom, bride, schedule, theme, gift accounts)
  profile_json    LONGTEXT DEFAULT NULL,
  -- Generated design spec (palette, typography, layout, copy)
  design_json     LONGTEXT DEFAULT NULL,
  cover_url       VARCHAR(500) DEFAULT NULL,
  -- Audit
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  published_at    DATETIME DEFAULT NULL,
  view_count      INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_inv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_status (status),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- invitation_assets (uploaded media + generated)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS invitation_assets (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invitation_id   INT UNSIGNED NOT NULL,
  type            ENUM('groom_photo','bride_photo','prewedding','reference','generated','cover') NOT NULL,
  url             VARCHAR(500) NOT NULL,
  position        TINYINT UNSIGNED DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_asset_inv FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE,
  INDEX idx_inv (invitation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- orders (kept even with skipped payment for traceability)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  invitation_id   INT UNSIGNED NOT NULL,
  amount          INT UNSIGNED NOT NULL DEFAULT 0,
  status          ENUM('pending_payment','paid','cancelled','refunded') NOT NULL DEFAULT 'pending_payment',
  gateway         VARCHAR(40) DEFAULT 'simulated',
  gateway_ref     VARCHAR(120) DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at         DATETIME DEFAULT NULL,
  CONSTRAINT fk_ord_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ord_inv  FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- generations (AI run log)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS generations (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invitation_id   INT UNSIGNED NOT NULL,
  status          ENUM('queued','running','succeeded','failed') NOT NULL DEFAULT 'queued',
  prompt_hash     CHAR(64) DEFAULT NULL,
  tokens_in       INT UNSIGNED DEFAULT 0,
  tokens_out      INT UNSIGNED DEFAULT 0,
  model_version   VARCHAR(80) DEFAULT NULL,
  error_message   TEXT DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at     DATETIME DEFAULT NULL,
  CONSTRAINT fk_gen_inv FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- guests
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS guests (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invitation_id   INT UNSIGNED NOT NULL,
  name            VARCHAR(160) NOT NULL,
  phone           VARCHAR(30) DEFAULT NULL,
  group_label     VARCHAR(80) DEFAULT NULL,
  link_token      VARCHAR(40) NOT NULL UNIQUE,
  rsvp_status     ENUM('pending','yes','no','maybe') NOT NULL DEFAULT 'pending',
  attendees       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  event_choice    ENUM('akad','resepsi','both') DEFAULT NULL,
  message         TEXT DEFAULT NULL,
  responded_at    DATETIME DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_guest_inv FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE,
  INDEX idx_inv (invitation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- guestbook (open wishes from anyone with the link)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS guestbook (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invitation_id   INT UNSIGNED NOT NULL,
  guest_id        INT UNSIGNED DEFAULT NULL,
  guest_name      VARCHAR(160) NOT NULL,
  message         VARCHAR(280) NOT NULL,
  hidden          TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gb_inv FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE,
  INDEX idx_inv (invitation_id, hidden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- gifts (digital angpau records)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS gifts (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invitation_id   INT UNSIGNED NOT NULL,
  guest_id        INT UNSIGNED DEFAULT NULL,
  guest_name      VARCHAR(160) NOT NULL,
  channel         VARCHAR(60) NOT NULL,
  amount          INT UNSIGNED DEFAULT NULL,
  receipt_url     VARCHAR(500) DEFAULT NULL,
  status          ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  confirmed_at    DATETIME DEFAULT NULL,
  CONSTRAINT fk_gift_inv FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- audit_log
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id        INT UNSIGNED DEFAULT NULL,
  action          VARCHAR(80) NOT NULL,
  target_type     VARCHAR(40) DEFAULT NULL,
  target_id       INT UNSIGNED DEFAULT NULL,
  payload         TEXT DEFAULT NULL,
  ip_address      VARCHAR(45) DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_actor (actor_id),
  INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Seed: a default admin (email login still requires OTP; this just flags admin)
-- Update the email below before running, or set is_admin=1 on your own user.
-- ---------------------------------------------------------
INSERT IGNORE INTO users (email, phone_e164, display_name, status, is_admin)
VALUES ('admin@nikahin.local', '+6281000000000', 'Admin', 'active', 1);
