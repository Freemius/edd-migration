<?php
	/**
	 * Plugin Name: Freemius for WC Migration
	 * Plugin URI:  http://freemius.com/
	 * Description: Server side endpoint to sync data between Freemius and WC.
	 * Version:     1.0.0
	 * Author:      Freemius
	 * Author URI:  http://freemius.com
	 * License: GPL2
	 *
	 * @requires
	 *  1. WC 2.5 or higher, assuming payments currency is USD and have no fees.
	 *  2. PHP 5.3 or higher [using spl_autoload_register()]
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	function fs_wc_migration_init() {
		if ( ! class_exists( 'WooCommerce_API_Manager' ) ) {
			deactivate_plugins( basename( __FILE__ ) );

			// Message error + allow back link.
			wp_die(
				__( 'Freemius for WCAM Migration plugin requires WooCommerce API Manager.',
					'fs-wc-migration' ),
				__( 'Error' ),
				array( 'back_link' => true )
			);
		}

		// Load config.
		require_once __DIR__ . '/start.php';

		// Load migration module.
		require_once WP_FSM__DIR_MIGRATION . '/wc/class-fs-wc-migration-endpoint.php';
	}

	// Get Freemius WC Migration running.
	add_action( 'plugins_loaded', 'fs_wc_migration_init' );

	function fs_wc_migration_auto_redirect() {
		if ( ! function_exists( 'is_network_admin' ) || ! is_network_admin() ) {
			if ( 'true' === get_option( 'fs_wc_migration_activated' ) ) {
				// Load config.
				require_once __DIR__ . '/start.php';

				update_option( 'fs_wc_migration_activated', null );

				if ( fs_redirect( add_query_arg( array(
					'post_type' => 'download',
					'page'      => 'fs-migration',
				), admin_url( 'edit.php', 'admin' ) ) ) ) {
					exit;
				}
			}
		}
	}

	add_action( 'admin_init', 'fs_wc_migration_auto_redirect' );

	function fs_migration_plugin_activation() {
		require_once __DIR__ . '/includes/class-fs-entity-mapper.php';

		// Create mapping table if not exist.
		FS_Entity_Mapper::create_table();

		// Hint the plugin that it was just activated.
		update_option( "fs_wc_migration_activated", 'true' );
	}

	register_activation_hook( __FILE__, 'fs_migration_plugin_activation' );
