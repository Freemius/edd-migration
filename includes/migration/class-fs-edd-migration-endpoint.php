<?php
	/**
	 * @package     Freemius Migration
	 * @copyright   Copyright (c) 2016, Freemius, Inc.
	 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
	 * @since       1.0.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	require_once WP_FSM__DIR_MIGRATION . '/class-fs-migration-endpoint-abstract.php';

	/**
	 * Class FS_EDD_Migration_Endpoint
	 */
	class FS_EDD_Migration_Endpoint extends FS_Migration_Endpoint_Abstract {

		/**
		 * @var FS_EDD_Migration_Endpoint
		 */
		private static $_instance;

		public static function instance() {
			if ( ! isset( self::$_instance ) ) {
				self::$_instance = new FS_EDD_Migration_Endpoint();
			}

			return self::$_instance;
		}

		private function __construct() {

			$this->init( WP_FS__NAMESPACE_EDD );

			if ( ! defined( 'DOING_AJAX' ) ) {
//				return false;

//				$this->migrate_license_by_id( 71336 );

				$url         = 'http://test9.freemius.com';
				$download_id = 25;
				$license_key = '74062bc8b9cc256823f8f08d0f8feedf';

				$params = array(
//					'fs_action'        => 'migrate_license',
					'license_key'      => $license_key,
					'item_id'          => $download_id,
					'url'              => $url,
					'site_url'         => $url,
					'plugin_version'   => '1.2.1',
					'site_uid'         => $this->get_anonymous_id( $url ),
					'site_name'        => 'Freemius Test',
					'platform_version' => get_bloginfo( 'version' ),
					'php_version'      => phpversion(),
					'language'         => get_bloginfo( 'language' ),
					'charset'          => get_bloginfo( 'charset' ),
					'is_premium'       => true,
					'is_active'        => true,
					'is_uninstalled'   => false,
				);

				$decoded = json_encode( $params );

				$x = 1;
//				$this->migrate_license_remote( $params );
			}
		}

		#--------------------------------------------------------------------------------
		#region Testing
		#--------------------------------------------------------------------------------

		/**
		 * Migrate license by ID.
		 *
		 * Note: This method is only for testing reasons.
		 *
		 * @author   Vova Feldman (@svovaf)
		 * @since    1.0.0
		 *
		 * @param int $license_id
		 *
		 * @throws Exception
		 */
		function migrate_license_by_id( $license_id ) {
			require_once WP_FSM__DIR_INCLUDES . '/migration/class-fs-migration-abstract.php';
			require_once WP_FSM__DIR_INCLUDES . '/migration/class-fs-edd-migration.php';

			$migration = FS_EDD_Migration::instance( $license_id );
			$migration->set_api( $this->get_api() );
			$migration->do_migrate_license();
		}


		#endregion

		#--------------------------------------------------------------------------------
		#region Local Data Getters
		#--------------------------------------------------------------------------------

		/**
		 * Map EDD download into FS object.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param mixed $local_module
		 *
		 * @return FS_Plugin
		 */
		protected function local_to_remote_module( $local_module ) {
			$module        = new FS_Plugin();
			$module->id    = $local_module->ID;
			$module->slug  = $local_module->post_name;
			$module->title = $local_module->post_title;

			return $module;
		}

		/**
		 * Get all EDD downloads.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return array
		 */
		protected function get_all_local_modules() {
			/**
			 * @var WP_Post[] $downloads
			 */
			$downloads = get_posts( array(
				'post_type'      => 'download',
				'posts_per_page' => - 1,
			) );

			for ( $i = 0, $len = count( $downloads ); $i < $len; $i ++ ) {
				$downloads[ $i ] = new EDD_Download( $downloads[ $i ]->ID );
			}

			return $downloads;
		}

		/**
		 * Load EDD download by ID.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param string $local_module_id
		 *
		 * @return false|\EDD_Download
		 */
		protected function get_local_module_by_id( $local_module_id ) {
			$download_post = WP_Post::get_instance( $local_module_id );

			return ( empty( $download_post ) || 'download' !== $download_post->post_type ) ?
				false :
				new EDD_Download( $local_module_id );
		}

		/**
		 * EDD Download slug (post_name).
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param mixed $local_module
		 *
		 * @return string
		 */
		protected function get_local_module_slug( $local_module ) {
			return $local_module->post_name;
		}

		/**
		 * Return the instance of the EDD download migration manager.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param mixed     $local_module
		 * @param FS_Plugin $module
		 *
		 * @return \FS_EDD_Download_Migration
		 */
		protected function get_local_module_migration_manager( $local_module, FS_Plugin $module = null ) {
			require_once WP_FSM__DIR_INCLUDES . '/migration/class-fs-edd-download-migration.php';

			if ( is_null( $module ) ) {
				$module = $this->get_module_by_slug( $this->get_local_module_slug( $local_module ) );
			}

			return new FS_EDD_Download_Migration(
				$this->get_developer(),
				$module,
				$local_module
			);
		}

		#endregion

		private function is_valid_license_request( $edd_license_state ) {
			switch ( $edd_license_state ) {
				/**
				 * Migrate expired license since all EDD licenses are not blocking.
				 */
				case 'expired':
					/**
					 * License not yet activated.
					 *
					 * This use-case should not happen since if the client triggered a migration
					 * request with a valid license key, it means that the license was activated
					 * at least once. Hence, 'inactive' isn't possible.
					 */
				case 'inactive':
					/**
					 * License was disabled, therefore, ...
					 *
					 * @todo what to do in that case?
					 */
				case 'disabled':
					/**
					 * Migrate license & site.
					 *
					 * Based on the EDD SL logic this result is trigger when it's a production
					 * site (not localhost), and the site license wasn't activated.
					 *
					 * It can happen for example when the user trying to activate a license
					 * that is already fully utilized.
					 *
					 * @todo what to do in that case?
					 */
				case 'site_inactive':
					/**
					 * Migrate license & site.
					 *
					 * License is valid and activated for the context site.
					 */
				case 'valid':
					$is_valid_request = true;
					break;
				case 'invalid':
				case 'invalid_item_id':
				case 'item_name_mismatch':
				default:
					$is_valid_request = false;
					break;
			}

			return $is_valid_request;
		}

		protected function migrate_license_remote( array $data ) {
			$edd_sl = edd_software_licensing();

			$item_id     = ! empty( $data['item_id'] ) ? absint( $data['item_id'] ) : false;
			$item_name   = ! empty( $data['item_name'] ) ? rawurldecode( $data['item_name'] ) : false;
			$license_key = ! empty( $data['license_key'] ) ? urldecode( $data['license_key'] ) : false;
			$url         = isset( $data['url'] ) ? urldecode( $data['url'] ) : '';

			if ( empty( $url ) ) {
				// Attempt to grab the URL from the user agent if no URL is specified
				$domain = array_map( 'trim', explode( ';', $_SERVER['HTTP_USER_AGENT'] ) );
				if ( 1 < count( $domain ) ) {
					$url = trim( $domain[1] );
				}
			}

			$args = array(
				'item_id'   => $item_id,
				'item_name' => $item_name,
				'key'       => $license_key,
				'url'       => $url,
			);

			$result = $edd_sl->check_license( $args );

			if ( $this->is_valid_license_request( $result ) ) {
				require_once WP_FSM__DIR_INCLUDES . '/migration/class-fs-migration-abstract.php';
				require_once WP_FSM__DIR_INCLUDES . '/migration/class-fs-edd-migration.php';

				$license_id = $edd_sl->get_license_by_key( $args['key'] );

				$migration = FS_EDD_Migration::instance( $license_id );
				$migration->set_api( $this->get_api() );

				// Migrate customer, purchase/subscription, billing and license.
				$customer = $migration->do_migrate_license();

				// Migrate plugin installation.
				$account = $migration->do_migrate_install( $data, $customer );

				$this->shoot_json_success(
					array_merge( $account, array(
						'license' => $result,
					) )
				);
			} else {
				$this->shoot_json_failure(
					'',
					array( 'license' => $result, )
				);
			}
		}

		#--------------------------------------------------------------------------------
		#region API Endpoint
		#--------------------------------------------------------------------------------

		// @todo Add validation.
		protected function validate_params( array $data ) {
			if ( false ) {
				$this->shoot_json_failure(
					'whatever error message'
				);
			}
		}


		#endregion API Endpoint
	}

	/**
	 * The main function responsible for returning the FS_EDD_Migration_Endpoint
	 * instance.
	 *
	 * Example: <?php $fs_migration_manager = fs_edd_migration_manager(); ?>
	 *
	 * @author Vova Feldman
	 * @since  1.0.0
	 *
	 * @return FS_EDD_Migration_Endpoint The one true Easy_Digital_Downloads Instance
	 */
	function fs_edd_migration_manager() {
		return FS_EDD_Migration_Endpoint::instance();
	}

	// Get Freemius EDD Migration running.
	fs_edd_migration_manager();