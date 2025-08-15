<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function WC_UPI_ADV_install_activate() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $table_votes = $wpdb->prefix . 'wc_upi_votes';
    $sql_votes = "CREATE TABLE IF NOT EXISTS {$table_votes} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        order_id BIGINT UNSIGNED NULL,
        user_id BIGINT UNSIGNED NULL,
        ip VARCHAR(45) NULL,
        choice TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) {$charset};";

    $table_hooks = $wpdb->prefix . 'wc_upi_webhooks';
    $sql_hooks = "CREATE TABLE IF NOT EXISTS {$table_hooks} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        headers LONGTEXT NULL,
        body LONGTEXT NULL,
        ip VARCHAR(45) NULL,
        transaction_id VARCHAR(191) NULL,
        order_id BIGINT UNSIGNED NULL,
        status VARCHAR(60) NULL,
        verified TINYINT(1) DEFAULT 0
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_votes);
    dbDelta($sql_hooks);
}

function WC_UPI_ADV_install_uninstall() {
    global $wpdb;
    $table_votes = $wpdb->prefix . 'wc_upi_votes';
    $table_hooks = $wpdb->prefix . 'wc_upi_webhooks';
    $wpdb->query("DROP TABLE IF EXISTS {$table_votes}"); // optional: remove votes table
    $wpdb->query("DROP TABLE IF EXISTS {$table_hooks}"); // optional: remove webhooks log table
}
