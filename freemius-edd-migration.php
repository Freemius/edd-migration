<?php
	/**
	 * Plugin Name: Freemius for EDD Migration
	 * Plugin URI:  http://freemius.com/
	 * Description: Server side endpoint to sync data between Freemius and EDD.
	 * Version:     1.0.0
	 * Author:      Freemius
	 * Author URI:  http://freemius.com
	 * License: GPL2
	 *
	 * @requires
	 *  1. EDD 2.5 or higher, assuming payments currency is USD and have no fees.
	 *  2. PHP 5.3 or higher [using spl_autoload_register()]
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	function fs_edd_migration_init() {
		if ( ! class_exists( 'Easy_Digital_Downloads' ) ||
		     ! class_exists( 'EDD_Software_Licensing' )
		) {
			deactivate_plugins( basename( __FILE__ ) );

			// Message error + allow back link.
			wp_die(
				__( 'Freemius for EDD Migration plugin requires Easy Digital Downloads and its Software Licensing extension to be active.', 'fs-edd-migration' ),
				__( 'Error' ),
				array( 'back_link' => true )
			);
		}

		// Load config.
		require_once __DIR__ . '/start.php';

		// Load migration module.
		require_once WP_FSM__DIR_MIGRATION . '/class-fs-edd-migration-endpoint.php';
	}

	// Get Freemius EDD Migration running.
	add_action( 'plugins_loaded', 'fs_edd_migration_init' );

	function fs_migration_plugin_activation() {
		require_once __DIR__ . '/includes/class-fs-entity-mapper.php';

		// Create mapping table if not exist.
		FS_Entity_Mapper::create_table();
	}

	register_activation_hook( __FILE__ , 'fs_migration_plugin_activation' );
