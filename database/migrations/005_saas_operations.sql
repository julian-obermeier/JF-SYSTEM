CREATE TABLE IF NOT EXISTS saas_tenant_settings (
    tenant_id BIGINT UNSIGNED PRIMARY KEY,
    display_name VARCHAR(180) NULL,
    logo_path VARCHAR(255) NULL,
    primary_color CHAR(7) NOT NULL DEFAULT '#d71920',
    secondary_color CHAR(7) NOT NULL DEFAULT '#102b4e',
    custom_domain VARCHAR(190) NULL,
    subdomain VARCHAR(80) NULL UNIQUE,
    support_email VARCHAR(190) NULL,
    timezone VARCHAR(80) NOT NULL DEFAULT 'Europe/Berlin',
    maintenance_mode TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT saas_settings_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saas_registrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_name VARCHAR(180) NOT NULL,
    city VARCHAR(120) NULL,
    contact_email VARCHAR(190) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    plan_id BIGINT UNSIGNED NULL,
    verification_token CHAR(64) NOT NULL UNIQUE,
    status_name ENUM('pending','verified','approved','rejected','expired') NOT NULL DEFAULT 'pending',
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verified_at DATETIME NULL,
    approved_at DATETIME NULL,
    approved_by BIGINT UNSIGNED NULL,
    rejection_reason VARCHAR(500) NULL,
    KEY saas_registration_status_idx (status_name, requested_at),
    CONSTRAINT saas_registration_plan_fk FOREIGN KEY (plan_id) REFERENCES saas_plans(id) ON DELETE SET NULL,
    CONSTRAINT saas_registration_approver_fk FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saas_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    notification_type VARCHAR(80) NOT NULL,
    title VARCHAR(180) NOT NULL,
    message_text VARCHAR(1000) NOT NULL,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY saas_notification_user_idx (user_id, read_at, created_at),
    KEY saas_notification_tenant_idx (tenant_id, created_at),
    CONSTRAINT saas_notification_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT saas_notification_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saas_password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT saas_password_reset_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saas_backups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size BIGINT UNSIGNED NULL,
    status_name ENUM('queued','running','completed','failed','deleted') NOT NULL DEFAULT 'queued',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    CONSTRAINT saas_backup_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT saas_backup_user_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
