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
	 * Class FS_WC_Migration_Endpoint
	 * @property stdClass $order Data from $_order using magic getter
	 */
	class FS_WC_Migration_Endpoint extends FS_Migration_Endpoint_Abstract {

		/**
		 * @var FS_WC_Migration_Endpoint
		 */
		private static $_instance;

		/**
		 * @var stdClass Order data Example in /tests/order.txt
		 */
		private $_order;

		/**
		 * @param string $name Property to return
		 * @return mixed|null Value if property exists else null
		 */
		public function __get( $name ) {
			switch ( $name ) {
				case 'order':
					return $this->_order;
				break;
				default:
					return $this->get_param( $name );
			}

			return null;
		}

		public static function instance() {
			if ( ! isset( self::$_instance ) ) {
				self::$_instance = new FS_WC_Migration_Endpoint();
			}

			return self::$_instance;
		}

		private function __construct() {

			$this->init( WP_FS__NAMESPACE_WC );

			add_action( 'init', array( $this, 'maybe_test_full_migration' ) );
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
			$namespace = strtolower($this->_namespace);

			require_once WP_FSM__DIR_MIGRATION . '/class-fs-migration-abstract.php';
			require_once WP_FSM__DIR_MIGRATION . "/{$namespace}/class-fs-{$namespace}-migration.php";

			$migration = FS_WC_Migration::instance( $license_id );
			$migration->set_api( $this->get_api() );
			$migration->do_migrate_license();
		}

		/**
		 * Test full install's license migration.
		 *
		 * @author   Vova Feldman (@svovaf)
		 * @since    1.0.0
		 */
		public function maybe_test_full_migration() {
			if ( ! isset( $_GET['sfpfs-mig-test'] ) ) return;

			require_once WP_FSM__DIR_INCLUDES . '/class-fs-endpoint-exception.php';

			$url         = 'http://wp/sfpfs/usr';
			$email       = 'shramee.srivastav@gmail.com';
			$license_key = 'wc_order_583468387d63c_am_DKpGZTJBJDVH';

			$params = array(
				'license_key'      => $license_key,
				'site_url'         => $url,
				'url'              => $url,
				'plugin_version'   => '2.5.0',
				'is_premium'       => true,
				'site_uid'         => '19ec32f60ec07ef9fae025ce5ac6bb86', // FS site uid
				'site_name'        => 'GetFrappe',
				'wcam_site_id'     => 'rAnDoM12',
				'language'         => 'en-US',
				'charset'          => 'UTF-8',
				'platform_version' => '4.6.1',
				'php_version'      => '7.0.0',
				'module_title'     => 'Storefront Pro',
				//'email'            => $email,
				'is_active'        => true,
			);

			$this->maybe_process_api_request( $params );
		}

		#endregion

		#--------------------------------------------------------------------------------
		#region Local Data Getters
		#--------------------------------------------------------------------------------

		/**
		 * Return the WC plan ID.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param string $product_id
		 *
		 * @return string
		 */
		protected function get_local_paid_plan_id( $product_id ) {
			$product = wc_get_product( $product_id );
			return $product->get_parent() . ':' . $product_id;
		}

		/**
		 * Map WC download into FS object.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param WC_Product $local_module
		 *
		 * @return FS_Plugin
		 */
		protected function local_to_remote_module( $local_module ) {
			$module        = new FS_Plugin();
			$module->id    = $local_module->id;
			$module->slug  = $local_module->post_name;
			$module->title = $local_module->get_title();

			return $module;
		}

		/**
		 * Get all WC downloads.
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
				'post_type'      => 'product',
				'posts_per_page' => - 1,
			) );

			for ( $i = 0, $len = count( $downloads ); $i < $len; $i ++ ) {
				$downloads[ $i ] = wc_get_product( $downloads[ $i ]->ID );
			}

			return $downloads;
		}

		/**
		 * Load WC download by ID.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param string $local_module_id
		 *
		 * @return false|WC_Product
		 */
		protected function get_local_module_by_id( $local_module_id ) {
			return ( 'product' !== get_post_type( $local_module_id ) ) ? false :
				wc_get_product( $local_module_id );
		}

		/**
		 * WC Download slug (post_name).
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
		 * Return the instance of the WC download migration manager.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param mixed     $local_module
		 * @param FS_Plugin $module
		 *
		 * @return \FS_WC_Download_Migration
		 */
		protected function get_local_module_migration_manager( $local_module, FS_Plugin $module = null ) {
			require_once WP_FSM__DIR_MIGRATION . '/wc/class-fs-wc-download-migration.php';

			if ( is_null( $module ) ) {
				$module = $this->get_module_by_slug( $this->get_local_module_slug( $local_module ) );
			}

			return new FS_WC_Download_Migration(
				$this->get_developer(),
				$module,
				$local_module
			);
		}

		#endregion

		/**
		 * Migrate install's license and return FS account's data (user + install).
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return array
		 *
		 * @throws FS_Endpoint_Exception
		 */
		protected function migrate_install_license() {

			require_once WP_FSM__DIR_MIGRATION . '/class-fs-migration-abstract.php';
			require_once WP_FSM__DIR_MIGRATION . '/wc/class-fs-wc-migration.php';

			$migration = FS_WC_Migration::instance( $this );

			$migration->set_api( $this->get_api() );

			// Migrate customer, purchase/subscription, billing and license.
			$customer = $migration->do_migrate_license();

			// Migrate plugin installation.
			return $migration->do_migrate_install( $this->_request_data, $customer );
		}

		#--------------------------------------------------------------------------------
		#region API Request Params Validation
		#--------------------------------------------------------------------------------

		/**
		 * Validate request parameters.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @throws FS_Endpoint_Exception
		 */
		protected function validate_params() {
			// Software title ( which may not be same as product title ) is plugin identifier in WCAM
			$plugin_title = $this->get_param( 'module_title' );

			if ( ! $this->get_param( 'email' ) ) {
				$this->_request_data['email'] = $this->query_email_for_key();
			}

			// Get order
			$order_data = WCAM()->helpers->get_order_info_by_email_with_order_key(
				$this->get_param( 'email' ), $this->get_param( 'license_key' )
			);

			$order_data['product_id'] = empty( $order_data['variable_product_id'] ) ? 
				$order_data['parent_product_id'] : $order_data['variable_product_id'];

			// Before checking license with WC, make sure module is synced.
			if ( ! $order_data['product_id'] ){
				throw new FS_Endpoint_Exception(
					"You don't have permission to access plugin with identifier <code>{$plugin_title}</code>.", 'invalid_plugin_identifier',
					400
				);
			}

			$this->_order = (object) $order_data;
		}


		private function query_email_for_key() {
			global $wpdb;

			$sql = "
			SELECT user_email
			FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions
			WHERE order_key = %s
			";

			$args = explode( '_am_', $this->get_param('license_key') );

			// Returns an Object
			return $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
		}

		#endregion
	}

	/**
	 * The main function responsible for returning the FS_WC_Migration_Endpoint
	 * instance.
	 *
	 * Example: <?php $fs_migration_manager = fs_wc_migration_manager(); ?>
	 *
	 * @author Vova Feldman
	 * @since  1.0.0
	 *
	 * @return \FS_WC_Migration_Endpoint The one true FS_WC_Migration_Endpoint Instance
	 */
	function fs_wc_migration_manager() {
		return FS_WC_Migration_Endpoint::instance();
	}

	// Get Freemius WC Migration running.
	fs_wc_migration_manager();