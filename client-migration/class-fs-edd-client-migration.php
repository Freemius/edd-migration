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

	if ( class_exists( 'FS_EDD_Client_Migration_v1' ) ) {
		return;
	}

	// Include abstract class.
	require_once dirname( __FILE__ ) . '/class-fs-client-migration-abstract.php';

	/**
	 * Class My_EDD_Freemius_Migration
	 */
	class FS_EDD_Client_Migration_v1 extends FS_Client_Migration_Abstract_v1 {
		/**
		 *
		 * @param Freemius                      $freemius
		 * @param string                        $edd_store_url        Your EDD store URL.
		 * @param int                           $edd_download_id      The context EDD download ID (from your store).
		 * @param FS_Client_License_Abstract_v1 $edd_license_accessor License accessor.
		 * @param bool                          $is_blocking          Special argument for testing. When false, will
		 *                                                            initiate the migration in the same HTTP request.
		 */
		public function __construct(
			Freemius $freemius,
			$edd_store_url,
			$edd_download_id,
			FS_Client_License_Abstract_v1 $edd_license_accessor,
			$is_blocking = false
		) {
			$this->init(
				'edd',
				$freemius,
				$edd_store_url,
				$edd_download_id,
				$edd_license_accessor,
				$is_blocking
			);
		}
	}
