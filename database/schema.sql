CREATE TABLE organizations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    city VARCHAR(120) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(50) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','leader','staff','viewer') NOT NULL DEFAULT 'staff',
    is_superadmin TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY users_email_unique (email),
    KEY users_tenant_idx (tenant_id),
    CONSTRAINT users_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    birth_date DATE NULL,
    entry_date DATE NULL,
    member_type ENUM('youth','staff') NOT NULL DEFAULT 'youth',
    status_name ENUM('active','paused','left') NOT NULL DEFAULT 'active',
    email VARCHAR(190) NULL,
    phone VARCHAR(50) NULL,
    emergency_name VARCHAR(180) NULL,
    emergency_phone VARCHAR(50) NULL,
    medical_notes TEXT NULL,
    notes_text TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY members_tenant_idx (tenant_id),
    KEY members_name_idx (tenant_id, last_name, first_name),
    CONSTRAINT members_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    event_type ENUM('practice','meeting','trip','competition','other') NOT NULL DEFAULT 'practice',
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    location_name VARCHAR(180) NULL,
    description_text TEXT NULL,
    status_name ENUM('draft','published','cancelled','completed') NOT NULL DEFAULT 'published',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY events_tenant_start_idx (tenant_id, starts_at),
    CONSTRAINT events_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT events_creator_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    member_id BIGINT UNSIGNED NOT NULL,
    attendance_status ENUM('present','excused','absent','unknown') NOT NULL DEFAULT 'unknown',
    note_text VARCHAR(500) NULL,
    recorded_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY attendance_event_member_unique (event_id, member_id),
    KEY attendance_tenant_idx (tenant_id),
    CONSTRAINT attendance_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT attendance_event_fk FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT attendance_member_fk FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    CONSTRAINT attendance_user_fk FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE qualifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    category_name VARCHAR(120) NULL,
    description_text TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY qualifications_tenant_idx (tenant_id),
    CONSTRAINT qualifications_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE member_qualifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    member_id BIGINT UNSIGNED NOT NULL,
    qualification_id BIGINT UNSIGNED NOT NULL,
    achieved_at DATE NULL,
    expires_at DATE NULL,
    note_text VARCHAR(500) NULL,
    UNIQUE KEY member_qualification_unique (member_id, qualification_id),
    CONSTRAINT member_qual_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT member_qual_member_fk FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    CONSTRAINT member_qual_qualification_fk FOREIGN KEY (qualification_id) REFERENCES qualifications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    action_name VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    description_text VARCHAR(500) NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY audit_tenant_created_idx (tenant_id, created_at),
    CONSTRAINT audit_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT audit_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY login_attempts_lookup_idx (email, ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
