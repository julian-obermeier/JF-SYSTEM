CREATE TABLE IF NOT EXISTS tenant_roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY tenant_role_name_unique (tenant_id, name),
    CONSTRAINT tenant_roles_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_key VARCHAR(100) NOT NULL,
    PRIMARY KEY (role_id, permission_key),
    CONSTRAINT tenant_role_permissions_role_fk FOREIGN KEY (role_id) REFERENCES tenant_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_user_roles (
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (tenant_id, user_id),
    CONSTRAINT tenant_user_roles_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT tenant_user_roles_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT tenant_user_roles_role_fk FOREIGN KEY (role_id) REFERENCES tenant_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_modules (
    tenant_id BIGINT UNSIGNED NOT NULL,
    module_key VARCHAR(100) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    config_json TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id, module_key),
    CONSTRAINT tenant_modules_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_dashboard_widgets (
    tenant_id BIGINT UNSIGNED NOT NULL,
    widget_key VARCHAR(100) NOT NULL,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id, widget_key),
    CONSTRAINT tenant_widgets_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_privacy_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    member_id BIGINT UNSIGNED NULL,
    request_type ENUM('export','anonymize','delete') NOT NULL,
    status_name ENUM('pending','approved','processing','completed','rejected') NOT NULL DEFAULT 'pending',
    requested_by BIGINT UNSIGNED NULL,
    due_at DATE NULL,
    notes_text VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    KEY tenant_privacy_status_idx (tenant_id, status_name, due_at),
    CONSTRAINT tenant_privacy_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT tenant_privacy_member_fk FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
    CONSTRAINT tenant_privacy_user_fk FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_support_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operator_user_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    reason_text VARCHAR(500) NOT NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    ip_address VARCHAR(45) NULL,
    KEY tenant_support_active_idx (operator_user_id, ended_at),
    CONSTRAINT tenant_support_operator_fk FOREIGN KEY (operator_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT tenant_support_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE saas_tenant_settings ADD COLUMN dashboard_title VARCHAR(180) NULL AFTER support_email;
ALTER TABLE saas_tenant_settings ADD COLUMN readonly_override TINYINT(1) NOT NULL DEFAULT 0 AFTER maintenance_mode;
ALTER TABLE saas_backups ADD COLUMN backup_type ENUM('manual','automatic','pre_restore') NOT NULL DEFAULT 'manual' AFTER file_size;
ALTER TABLE saas_backups ADD COLUMN restore_requested_at DATETIME NULL AFTER completed_at;
