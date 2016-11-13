<?php
	/**
	 * @package     Freemius Migration
	 * @copyright   Copyright (c) 2016, Freemius, Inc.
	 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
	 * @since       1.0.3
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( defined( 'DOING_CRON' ) ) {
		return;
	}

	require_once dirname( __FILE__ ) . '/fs-client-license-abstract.php';
	require_once dirname( __FILE__ ) . '/fs-edd-client-migration.php';

	class My_EDD_License_Key extends FS_Client_License_Abstract_v1 {
		/**
		 * @author   Vova Feldman (@svovaf)
		 * @since    1.0.3
		 *
		 * @return string
		 */
		function get() {
			return trim( get_option( 'edd_sample_license_key' ) );
		}

		/**
		 * @author   Vova Feldman (@svovaf)
		 * @since    1.0.3
		 *
		 * @param string $license_key
		 *
		 * @return bool True if successfully updated.
		 */
		function set( $license_key ) {
			return update_option( 'edd_sample_license_key', $license_key );
		}
	}

	new FS_EDD_Client_Migration_v1(
		my_freemius(),
		'https://your-edd-store.com',
		'1234',
		new My_EDD_License_Key()
	);