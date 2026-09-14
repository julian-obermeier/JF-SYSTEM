SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE tenants (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(180) NOT NULL, slug VARCHAR(120) NOT NULL UNIQUE,
 city VARCHAR(120) NULL, primary_color CHAR(7) NOT NULL DEFAULT '#d91f2a', logo_path VARCHAR(255) NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, is_superadmin TINYINT(1) NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1, email_verified_at DATETIME NULL, last_login_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tenant_users (
 tenant_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, role_key VARCHAR(80) NOT NULL DEFAULT 'staff',
 is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(tenant_id,user_id), CONSTRAINT tu_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT tu_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
 role_key VARCHAR(80) NOT NULL, permission_key VARCHAR(120) NOT NULL, PRIMARY KEY(role_key,permission_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plans (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, plan_key VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL,
 member_limit INT UNSIGNED NULL, user_limit INT UNSIGNED NULL, storage_limit_mb INT UNSIGNED NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL UNIQUE, plan_id BIGINT UNSIGNED NOT NULL,
 status_name ENUM('trial','active','past_due','paused','cancelled','expired') NOT NULL DEFAULT 'trial',
 billing_cycle ENUM('monthly','yearly','manual') NOT NULL DEFAULT 'manual', trial_ends_at DATE NULL,
 current_period_end DATE NULL, grace_ends_at DATE NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT subscriptions_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT subscriptions_plan_fk FOREIGN KEY(plan_id) REFERENCES plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE members (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, first_name VARCHAR(100) NOT NULL,
 last_name VARCHAR(100) NOT NULL, birth_date DATE NULL, entry_date DATE NULL,
 member_type ENUM('youth','staff') NOT NULL DEFAULT 'youth', status_name ENUM('active','paused','left') NOT NULL DEFAULT 'active',
 email VARCHAR(190) NULL, phone VARCHAR(50) NULL, address_street VARCHAR(190) NULL, postal_code VARCHAR(20) NULL,
 city VARCHAR(120) NULL, emergency_name VARCHAR(180) NULL, emergency_phone VARCHAR(50) NULL,
 medical_notes TEXT NULL, notes_text TEXT NULL, created_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY members_tenant_name(tenant_id,last_name,first_name),
 CONSTRAINT members_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT members_creator_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE member_guardians (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, member_id BIGINT UNSIGNED NOT NULL,
 full_name VARCHAR(180) NOT NULL, relationship_name VARCHAR(80) NULL, email VARCHAR(190) NULL, phone VARCHAR(50) NULL,
 is_primary TINYINT(1) NOT NULL DEFAULT 0, is_emergency_contact TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT guardians_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT guardians_member_fk FOREIGN KEY(member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE member_consents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, member_id BIGINT UNSIGNED NOT NULL,
 consent_type VARCHAR(80) NOT NULL, title VARCHAR(180) NOT NULL,
 status_name ENUM('open','granted','declined','expired','revoked') NOT NULL DEFAULT 'open',
 granted_at DATE NULL, expires_at DATE NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT consents_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT consents_member_fk FOREIGN KEY(member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, title VARCHAR(180) NOT NULL,
 event_type ENUM('practice','meeting','trip','competition','other') NOT NULL DEFAULT 'practice', starts_at DATETIME NOT NULL,
 ends_at DATETIME NOT NULL, location_name VARCHAR(180) NULL, description_text TEXT NULL,
 status_name ENUM('draft','published','cancelled','completed') NOT NULL DEFAULT 'published', created_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY events_tenant_start(tenant_id,starts_at), CONSTRAINT events_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT events_creator_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_responses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, event_id BIGINT UNSIGNED NOT NULL,
 member_id BIGINT UNSIGNED NOT NULL, response_status ENUM('yes','no','maybe','open') NOT NULL DEFAULT 'open', responded_at DATETIME NULL,
 UNIQUE KEY responses_event_member(event_id,member_id),
 CONSTRAINT responses_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT responses_event_fk FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
 CONSTRAINT responses_member_fk FOREIGN KEY(member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NULL, user_id BIGINT UNSIGNED NULL,
 action_name VARCHAR(80) NOT NULL, entity_type VARCHAR(80) NOT NULL, entity_id BIGINT UNSIGNED NULL,
 description_text VARCHAR(500) NOT NULL, ip_address VARCHAR(45) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY audit_tenant_created(tenant_id,created_at),
 CONSTRAINT audit_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
 CONSTRAINT audit_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
