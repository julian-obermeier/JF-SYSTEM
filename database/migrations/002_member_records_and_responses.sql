-- JF-SYSTEM.de Migration 002
-- Einmalig auf bestehenden Installationen ausführen oder /update/ verwenden.

ALTER TABLE members
    ADD COLUMN address_street VARCHAR(190) NULL AFTER phone,
    ADD COLUMN postal_code VARCHAR(20) NULL AFTER address_street,
    ADD COLUMN city VARCHAR(120) NULL AFTER postal_code,
    ADD COLUMN school_name VARCHAR(180) NULL AFTER city,
    ADD COLUMN shirt_size VARCHAR(20) NULL AFTER school_name,
    ADD COLUMN pants_size VARCHAR(20) NULL AFTER shirt_size,
    ADD COLUMN shoe_size VARCHAR(20) NULL AFTER pants_size,
    ADD COLUMN pickup_authorized TINYINT(1) NOT NULL DEFAULT 0 AFTER shoe_size;

ALTER TABLE events
    ADD COLUMN learning_goals TEXT NULL AFTER description_text,
    ADD COLUMN material_needed TEXT NULL AFTER learning_goals,
    ADD COLUMN max_participants SMALLINT UNSIGNED NULL AFTER material_needed,
    ADD COLUMN response_deadline DATETIME NULL AFTER max_participants,
    ADD COLUMN reminder_at DATETIME NULL AFTER response_deadline,
    ADD COLUMN recurrence_rule ENUM('none','weekly','biweekly','monthly') NOT NULL DEFAULT 'none' AFTER reminder_at,
    ADD COLUMN leader_id BIGINT UNSIGNED NULL AFTER status_name,
    ADD CONSTRAINT events_leader_fk FOREIGN KEY (leader_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE member_guardians (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    member_id BIGINT UNSIGNED NOT NULL,
    full_name VARCHAR(180) NOT NULL,
    relationship_name VARCHAR(80) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(50) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    is_emergency_contact TINYINT(1) NOT NULL DEFAULT 1,
    is_pickup_authorized TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY guardians_tenant_member_idx (tenant_id, member_id),
    CONSTRAINT guardians_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT guardians_member_fk FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE member_consents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    member_id BIGINT UNSIGNED NOT NULL,
    consent_type ENUM('privacy','photo','swimming','trip','pickup','medical','other') NOT NULL,
    title VARCHAR(180) NOT NULL,
    consent_status ENUM('open','granted','declined','expired','revoked') NOT NULL DEFAULT 'open',
    granted_at DATE NULL,
    expires_at DATE NULL,
    document_reference VARCHAR(255) NULL,
    note_text VARCHAR(500) NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY consents_tenant_member_idx (tenant_id, member_id),
    KEY consents_expiry_idx (tenant_id, expires_at),
    CONSTRAINT consents_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT consents_member_fk FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    CONSTRAINT consents_user_fk FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_responses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    member_id BIGINT UNSIGNED NOT NULL,
    response_status ENUM('yes','no','maybe','open') NOT NULL DEFAULT 'open',
    note_text VARCHAR(500) NULL,
    responded_at DATETIME NULL,
    recorded_by BIGINT UNSIGNED NULL,
    UNIQUE KEY responses_event_member_unique (event_id, member_id),
    KEY responses_tenant_idx (tenant_id),
    CONSTRAINT responses_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT responses_event_fk FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT responses_member_fk FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    CONSTRAINT responses_user_fk FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    event_type ENUM('practice','meeting','trip','competition','other') NOT NULL DEFAULT 'practice',
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 90,
    location_name VARCHAR(180) NULL,
    description_text TEXT NULL,
    learning_goals TEXT NULL,
    material_needed TEXT NULL,
    max_participants SMALLINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY templates_tenant_idx (tenant_id),
    CONSTRAINT templates_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT templates_user_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

