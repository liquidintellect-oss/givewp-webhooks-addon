<?php
/**
 * Fired when the plugin is uninstalled (deleted) via the WP admin Plugins screen.
 *
 * Removes plugin options and the delivery log table. Cron events are already cleared
 * on deactivation (see Activation::deactivate()).
 *
 * @package GiveWebhooks
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'give_webhooks_settings' );

$table_name = $wpdb->prefix . 'give_webhook_logs';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.NamingConventions.ValidVariableName.InterpolatedVariableNotSnakeCase -- table name isn't user input; uninstall.php intentionally drops the plugin's own table.
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
