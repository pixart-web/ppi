<?php
/**
 * Plugin Name:       PPI Core
 * Description:       Imóveis, localizações, pesquisa, leads, importação e SEO da PPI Estate Agents. Independente do tema: desativar ou trocar o tema não apaga dados.
 * Version:           1.0.0
 * Requires at least: 7.0
 * Requires PHP:      8.1
 * Author:            PIXART para PPI — Portuguese Property Investments
 * License:           GPL-2.0-or-later
 * Text Domain:       ppi-core
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

const PPI_CORE_VERSION = '1.0.0';
const PPI_DB_VERSION   = 3;
define( 'PPI_CORE_FILE', __FILE__ );
define( 'PPI_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'PPI_CORE_URL', plugin_dir_url( __FILE__ ) );

foreach ( array( 'fields', 'i18n', 'post-types', 'property', 'migrations', 'search', 'render', 'blocks', 'leads', 'mail', 'consent', 'seo', 'redirects', 'rest', 'settings', 'admin-property', 'admin-leads', 'importer', 'privacy', 'frontend' ) as $ppi_file ) {
	require_once PPI_CORE_DIR . "includes/{$ppi_file}.php";
}
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once PPI_CORE_DIR . 'includes/cli.php';
}

add_action( 'init', static function () {
	load_plugin_textdomain( 'ppi-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}, 1 );

register_activation_hook( __FILE__, static function () {
	PPI\Post_Types::register();
	PPI\Migrations::run();
	update_option( 'ppi_flush_rewrite', 1, false ); // depois de o Polylang registar os idiomas (ver abaixo)
} );

register_deactivation_hook( __FILE__, static function () {
	wp_clear_scheduled_hook( 'ppi_retry_notifications' );
	wp_clear_scheduled_hook( 'ppi_daily_maintenance' );
	flush_rewrite_rules();
} );

add_action( 'plugins_loaded', array( 'PPI\\Migrations', 'maybe_run' ) );

add_action( 'init', static function () {
	if ( get_option( 'ppi_flush_rewrite' ) ) {
		delete_option( 'ppi_flush_rewrite' );
		flush_rewrite_rules();
	}
}, 999 );
