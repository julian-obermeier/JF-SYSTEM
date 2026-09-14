CREATE TABLE IF NOT EXISTS saas_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_key VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    yearly_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    member_limit INT UNSIGNED NULL,
    user_limit INT UNSIGNED NULL,
    storage_limit_mb INT UNSIGNED NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saas_addons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    addon_key VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saas_plan_addons (
    plan_id BIGINT UNSIGNED NOT NULL,
    addon_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (plan_id, addon_id),
    CONSTRAINT saas_plan_addon_plan_fk FOREIGN KEY (plan_id) REFERENCES saas_plans(id) ON DELETE CASCADE,
    CONSTRAINT saas_plan_addon_addon_fk FOREIGN KEY (addon_id) REFERENCES saas_addons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saas_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    status_name ENUM('trial','active','past_due','paused','cancelled','expired') NOT NULL DEFAULT 'trial',
    billing_cycle ENUM('monthly','yearly','manual') NOT NULL DEFAULT 'manual',
    starts_at DATE NOT NULL,
    trial_ends_at DATE NULL,
    current_period_start DATE NOT NULL,
    current_period_end DATE NULL,
    cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
    external_customer_ref VARCHAR(190) NULL,
    external_subscription_ref VARCHAR(190) NULL,
    notes_text VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY saas_subscription_tenant_unique (tenant_id),
    KEY saas_subscription_plan_idx (plan_id),
    CONSTRAINT saas_subscription_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT saas_subscription_plan_fk FOREIGN KEY (plan_id) REFERENCES saas_plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saas_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NULL,
    invoice_number VARCHAR(60) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    status_name ENUM('draft','open','paid','void','overdue') NOT NULL DEFAULT 'open',
    issued_at DATE NOT NULL,
    due_at DATE NULL,
    paid_at DATE NULL,
    description_text VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY saas_invoice_tenant_idx (tenant_id, issued_at),
    CONSTRAINT saas_invoice_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT saas_invoice_subscription_fk FOREIGN KEY (subscription_id) REFERENCES saas_subscriptions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saas_entitlements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    entitlement_key VARCHAR(100) NOT NULL,
    value_text VARCHAR(255) NULL,
    source_name ENUM('plan','addon','manual') NOT NULL DEFAULT 'plan',
    expires_at DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY saas_entitlement_unique (tenant_id, entitlement_key),
    CONSTRAINT saas_entitlement_tenant_fk FOREIGN KEY (tenant_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
