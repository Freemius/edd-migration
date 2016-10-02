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

	/**
	 * Class FS_EDD_Migration_Endpoint
	 */
	class FS_EDD_Migration_Endpoint {

		/**
		 * @var FS_Developer
		 */
		private $_developer;

		/**
		 * @var FS_Option_Manager
		 */
		private $_options;

		/**
		 * @var FS_Api
		 */
		private $_api;

		/**
		 * @var FS_Entity_Mapper
		 */
		protected $_entity_mapper;

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
			add_action( 'init', array( $this, 'maybe_process_api_request' ) );

			// Reduce query load for EDD API calls
			add_action( 'after_setup_theme', array( $this, 'reduce_query_load' ) );

			add_action( 'admin_menu', array( &$this, '_add_submenu' ), 99999999 );

			add_action( 'admin_notices', array( $this, '_notices' ) );

			if ( ! class_exists( 'FS_Option_Manager' ) ) {
				require_once WP_FSM__DIR_INCLUDES . '/class-fs-option-manager.php';
			}

			if ( ! class_exists( 'FS_Entity_Mapper' ) ) {
				require_once WP_FSM__DIR_INCLUDES . '/class-fs-entity-mapper.php';
			}

			$this->_options = FS_Option_Manager::get_manager( 'migration_options', true );

			$this->_entity_mapper = FS_Entity_Mapper::instance( 'edd' );

			add_action( 'wp_ajax_fs_sync_module', array( &$this, '_sync_module_to_freemius_callback' ) );

			if ( ! defined( 'DOING_AJAX' ) ) {
				return false;

//				$this->migrate_license_by_id( 71336 );

				$url = 'http://test5.freemius.com';
				$product_name = 'Exit Intent Popups';
				$license_key = 'ae0316639f86681e4379b378234a8195';

				$this->migrate_license_remote( array(
					'fs_action'        => 'migrate_license',
					'license_key'      => $license_key,
					'item_name'        => $product_name,
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
				) );
			}
		}

		/**
		 * Generates Freemius unique anonymous site identifier.
		 *
		 * Note: This method is only for testing reasons.
		 *
		 * @param string $site_url
		 *
		 * @return string
		 */
		private function get_anonymous_id( $site_url = '' ) {
			$key = empty( $site_url ) ? get_site_url() : $site_url;

			// If localhost, assign microtime instead of domain.
			if ( WP_FS__IS_LOCALHOST ||
			     false !== strpos( $key, 'localhost' ) ||
			     false === strpos( $key, '.' )
			) {
				$key = microtime();
			}

			return md5( $key );
		}

		/**
		 * Get remote entity ID.
		 *
		 * @author   Vova Feldman (@svovaf)
		 * @since    1.0.0
		 *
		 * @param string $type
		 * @param string $local_id
		 *
		 * @return number|false
		 */
		public function get_remote_id( $type, $local_id ) {
			return $this->_entity_mapper->get_remote_id( $type, $local_id );
		}

		/**
		 * Link local entity to remote.
		 *
		 * @author   Vova Feldman (@svovaf)
		 * @since    1.0.0
		 *
		 * @param FS_Entity $entity
		 * @param string    $local_entity_id
		 *
		 * @return false|FS_Entity_Map
		 */
		protected function link_entity( FS_Entity $entity, $local_entity_id ) {
			return $this->_entity_mapper->link(
				$entity,
				$local_entity_id
			);
		}

		#--------------------------------------------------------------------------------
		#region Admin Settings
		#--------------------------------------------------------------------------------

		private $_notices = array();

		/**
		 * Render all admin notices added by webhook.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 */
		function _notices() {
			foreach ( $this->_notices as $notice ) {
				$this->load_template( 'admin-notice.php', $notice );
			}
		}

		/**
		 * Add Freemius submenu item.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 */
		function _add_submenu() {
			// Add Freemius submenu item.
			$hook = add_submenu_page(
				'edit.php?post_type=download',
				'Freemius',
				'Freemius',
				'manage_options',
				WP_FSM__SLUG,
				array( &$this, '_admin_settings' )
			);

			add_action( "load-$hook", array( &$this, '_save_settings' ) );
		}

		/**
		 * Renders Freemius settings page.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 */
		function _admin_settings() {
			$params = array( 'webhook' => $this );
			$this->load_template( 'settings.php', $params );
		}

		private function load_template( $path, $params ) {
			$VARS = &$params;
			require WP_FSM__DIR_TEMPLATES . '/' . trim( $path, '/' );
		}

		/**
		 * Lazy load of developer.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return FS_Developer
		 */
		function get_developer() {
			if ( ! isset( $this->_developer ) ) {
				$this->_developer = $this->_options->get_option( 'developer', new FS_Developer() );
			}

			return $this->_developer;
		}

		/**
		 * Lazy load of the developer's API.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return FS_Api
		 */
		private function get_api() {
			if ( ! isset( $this->_api ) ) {
				if ( ! class_exists( 'FS_Api' ) ) {
					require_once WP_FSM__DIR_INCLUDES . '/class-fs-api.php';
				}

				$developer = $this->get_developer();

				$this->_api = FS_Api::instance(
					WP_FSM__SLUG,
					$developer->get_type(),
					$developer->id,
					$developer->public_key,
					false,
					$developer->secret_key
				);
			}

			return $this->_api;
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param number $id
		 * @param string $public_key
		 * @param string $secret_key
		 */
		private function update_developer( $id, $public_key, $secret_key ) {
			// Override info.
			$this->get_developer();
			$this->_developer->id         = $id;
			$this->_developer->public_key = $public_key;
			$this->_developer->secret_key = $secret_key;

			// Test credentials.
			$fs_api    = $this->get_api();
			$developer = $fs_api->get( '/' );

			if ( ! is_object( $developer ) || isset( $developer->error ) ) {
				// Request failed, bad credentials.
				$this->_notices[] = array(
					'message' => __fs( 'bad-credentials' ),
					'title'   => __fs( 'oops' ) . '...',
					'type'    => 'error',
				);
			} else {
				$this->_notices[] = array(
					'message' => __fs( 'credentials-validate' ),
					'title'   => sprintf( __fs( 'congrats-x' ), $developer->first ) . '!',
					'type'    => 'success',
				);

				$this->_developer = new FS_Developer( $developer );

				// Store new details.
				$this->_options->set_option( 'developer', $this->_developer, true );
			}
		}

		/**
		 * Check if connected to Freemius with valid API credentials.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return bool
		 */
		function is_connected() {
			$dev = $this->get_developer();

			return isset( $dev->email );
		}


		/**
		 * Handle API credentials update.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 */
		function _save_settings() {
			if ( ! fs_request_is_action( 'save_settings' ) ) {
				return;
			}

			check_admin_referer( 'save_settings' );

			$this->update_developer(
				fs_request_get( 'fs_id' ),
				fs_request_get( 'fs_public_key' ),
				fs_request_get( 'fs_secret_key' )
			);
		}

		/**
		 * @return FS_Plugin[]
		 */
		function get_all_local_products() {
			/**
			 * @var WP_Post[] $downloads
			 */
			$downloads = get_posts( array(
				'post_type'      => 'download',
				'posts_per_page' => - 1,
			) );

			$local_products = array();

			foreach ( $downloads as $download ) {
				$local_module        = new FS_Plugin();
				$local_module->id    = $download->ID;
				$local_module->slug  = $download->post_name;
				$local_module->title = $download->post_title;
				$local_products[]    = $local_module;//new EDD_Download( $download->ID );
			}

			return $local_products;
		}

		function get_remote_paid_plan_id( $local_module_id ) {
			return $this->get_remote_id(
				FS_Plan::get_type(),
				$local_module_id . ':' . ( edd_has_variable_prices( $local_module_id ) ? '1' : '0' )
			);
		}

		function get_remote_module_id( $local_module_id ) {
			return $this->get_remote_id(
				FS_Plugin::get_type(),
				$local_module_id
			);
		}

//		function

		protected function shoot_ajax_failure( $message = '' ) {
			$this->shoot_ajax_result( false, null, $message );
		}

		protected function shoot_ajax_result( $success = true, $data = null, $message = '' ) {
			header( 'Content-Type: application/json' );

			$result = array( 'success' => $success );

			if ( ! empty( $data ) ) {
				$result['data'] = $data;
			}

			if ( ! empty( $message ) ) {
				$result['message'] = $message;
			}

			echo json_encode( $result );
			exit;
		}

		/**
		 * Assumes that developer won't have two modules with
		 * identical slug (e.g. plugin and theme with the same slug).
		 *
		 * @param string $slug
		 *
		 * @return \FS_Plugin
		 */
		function get_module_by_slug( $slug ) {
			$result = $this->get_api()->get( '/plugins.json' );

			// @todo check valid result

			foreach ( $result->plugins as $module ) {
				if ( $slug === $module->slug ) {
					return new FS_Plugin( $module );
				}
			}

			return null;
		}

		/**
		 * Sync local module to Freemius. If there's no matching module
		 * with the same slug on Freemius, create new module and sync
		 * the plans & pricing.
		 */
		public function _sync_module_to_freemius_callback() {
			require_once WP_FSM__DIR_INCLUDES . '/migration/class-fs-module-migration-abstract.php';
			require_once WP_FSM__DIR_INCLUDES . '/migration/class-fs-edd-download-migration.php';

			if ( empty( $_POST['local_module_id'] ) || ! is_numeric( $_POST['local_module_id'] ) ) {
				$this->shoot_ajax_failure( 'local_module_id parameter is missing.' );
			}

			// Check if download exist.
			$local_module_id = $_POST['local_module_id'];

			// Try to load post.
			$download_post = WP_Post::get_instance( $local_module_id );

			if ( empty( $download_post ) || 'download' !== $download_post->post_type ) {
				// Post not exist or not an EDD download.
				$this->shoot_ajax_failure( "There's no local module with the specified ID ({$local_module_id})." );
			}

			$local_module = new EDD_Download( $download_post->ID );

			$module = $this->get_module_by_slug( $local_module->post_name );

			$migration = new FS_EDD_Download_Migration(
				$this->get_developer(),
				$module,
				$local_module
			);

			if ( $migration->do_sync( true ) ) {
				// Success.
				$module_id = $this->get_remote_module_id( $local_module_id );
				$plan_id   = $this->get_remote_paid_plan_id( $local_module_id );

				$this->shoot_ajax_result( true, array(
					'module_id' => $module_id,
					'plan_id'   => $plan_id,
				) );
			} else {
				// Failure.
				$this->shoot_ajax_failure( 'Failed syncing the module.' );
			}
		}

		/**
		 * Automatically sync modules that have matching slugs
		 * on local environment and on Freemius.
		 *
		 */
		private function sync_modules_to_freemius_by_slug() {
			require_once WP_FSM__DIR_INCLUDES . '/migration/class-fs-module-migration-abstract.php';
			require_once WP_FSM__DIR_INCLUDES . '/migration/class-fs-edd-download-migration.php';

			$result = $this->get_api()->get( '/plugins.json' );

			// @todo check valid result

			/**
			 * Assumes that developer won't have two modules with
			 * identical slug (e.g. plugin and theme with the same slug).
			 *
			 * @var array<string,FS_Plugin> $modules_map
			 */
			$modules_map = array();

			foreach ( $result->plugins as $module ) {
				$modules_map[ $module->slug ] = new FS_Plugin( $module );
			}

			// Load all downloads.
			$downloads = get_posts( array(
				'post_type'      => 'download',
				'posts_per_page' => - 1,
			) );

			foreach ( $downloads as $download ) {
				/**
				 * @var WP_Post $download
				 */
				if ( isset( $modules_map[ $download->post_name ] ) ) {
					// Found a matching module.
					$migration = new FS_EDD_Download_Migration(
						$this->get_developer(),
						$modules_map[ $download->post_name ],
						new EDD_Download( $download->ID )
					);

					$migration->do_sync();
				}
			}
		}

		#endregion

		function process_request() {
			global $wp_query;

			// Validate it's a freemius webhook callback.
			if ( empty( $wp_query->query_vars[ WP_FSM__MAIN_ENDPOINT ] ) ||
			     'webhook' !== trim( $wp_query->query_vars[ WP_FSM__MAIN_ENDPOINT ], '/' )
			) {
				return;
			}

//			$this->require_entities_files();

			// Retrieve the request body and parse it as JSON.
			$input = @file_get_contents( "php://input" );

			$request_event = json_decode( $input );

			if ( ! isset( $request_event->id ) ||
			     FS_Entity::is_valid_id( $request_event->id ) ||
			     ! isset( $request_event->plugin_id ) ||
			     FS_Entity::is_valid_id( $request_event->plugin_id )
			) {
				http_response_code( 404 );
				exit;
			}

			$this->migrate_license_remote( array() );
		}

		function migrate_license_remote( $data ) {
			$edd_sl = edd_software_licensing();

			$item_id     = ! empty( $data['item_id'] ) ? absint( $data['item_id'] ) : false;
			$item_name   = ! empty( $data['item_name'] ) ? rawurldecode( $data['item_name'] ) : false;
			$license_key = urldecode( $data['license_key'] );
			$url         = isset( $data['url'] ) ? urldecode( $data['url'] ) : '';

			if ( empty( $url ) ) {
				// Attempt to grab the URL from the user agent if no URL is specified
				$domain = array_map( 'trim', explode( ';', $_SERVER['HTTP_USER_AGENT'] ) );
				$url    = trim( $domain[1] );
			}

			$args = array(
				'item_id'   => $item_id,
				'item_name' => $item_name,
				'key'       => $license_key,
				'url'       => $url,
			);

			$result = $edd_sl->check_license( $args );

			switch ( $result ) {
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

			if ( $is_valid_request ) {
				require_once WP_FSM__DIR_INCLUDES . '/migration/class-fs-migration-abstract.php';
				require_once WP_FSM__DIR_INCLUDES . '/migration/class-fs-edd-migration.php';

				$license_id = $edd_sl->get_license_by_key( $args['key'] );

				$migration = FS_EDD_Migration::instance( $license_id );
				$migration->set_api( $this->get_api() );

				// Migrate customer, purchase/subscription, billing and license.
				$customer = $migration->do_migrate_license();

				// Migrate plugin installation.
				$account = $migration->do_migrate_install( $data, $customer );

				$response = array(
					'success' => is_array( $account ),
					'license' => $result,
					'account' => $account,
				);
			} else {
				$response = array(
					'success' => false,
					'license' => $result,
				);
			}

			header( 'Content-Type: application/json' );
			echo json_encode( $response );

			exit;
		}

		function migrate_license_by_id( $license_id ) {
			require_once WP_FSM__DIR_INCLUDES . '/migration/class-fs-migration-abstract.php';
			require_once WP_FSM__DIR_INCLUDES . '/migration/class-fs-edd-migration.php';

			$migration = FS_EDD_Migration::instance( $license_id );
			$migration->set_api( $this->get_api() );
			$migration->do_migrate_license();
		}

		#region API Endpoint

		/**
		 * Check if valid API request and if yes - process it.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 */
		public function maybe_process_api_request() {

			// if this is an API Request, load the Endpoint
			if ( ! is_admin() &&
			     $this->is_api_request() &&
			     ! defined( 'WP_FSM__MIGRATION_DOING_API_REQUEST' )
			) {
				$request_type = $this->get_api_endpoint();

				if ( ! empty( $request_type ) ) {
					define( 'WP_FSM__MIGRATION_DOING_API_REQUEST', true );

					$this->process_request();
				}
			}
		}

		/**
		 * The whitelisted endpoints for the license migration.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return array
		 */
		private function allowed_api_endpoints() {
			return array(
				'migrate-license',
			);
		}

		/**
		 * Check if request match the given API endpoint.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param  string $endpoint
		 *
		 * @return bool
		 */
		private function is_endpoint( $endpoint = '' ) {
			$is_active = ( false !== stristr( $_SERVER['REQUEST_URI'], WP_FSM__MAIN_ENDPOINT . '/' . $endpoint ) );

			return $is_active;
		}

		/**
		 * Check if current request is a valid API request.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return bool
		 */
		private function is_api_request() {
			$is_api_request = false;

			$allowed_endpoints = $this->allowed_api_endpoints();

			foreach ( $allowed_endpoints as $endpoint ) {

				$is_api_request = $this->is_endpoint( $endpoint );

				if ( $is_api_request ) {
					$is_api_request = true;
					break;
				}

			}

			return $is_api_request;
		}

		/**
		 * Fetch the Freemius API endpoint.
		 *
		 *  /freemius/{endpoint}/
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return string Returns the {endpoint}.
		 */
		private function get_api_endpoint() {
			$url_parts = parse_url( $_SERVER['REQUEST_URI'] );
			$paths     = explode( '/', $url_parts['path'] );

			$endpoint = '';

			foreach ( $paths as $index => $path ) {
				if ( WP_FSM__MAIN_ENDPOINT === $path ) {
					$endpoint = $paths[ $index + 1 ];
					break;
				}
			}

			return $endpoint;
		}

		/**
		 * If it's a valid API request, don't init the widgets to reduce load.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 */
		public function reduce_query_load() {

			if ( defined( 'WP_FSM__MIGRATION_DOING_API_REQUEST' ) &&
			     WP_FSM__MIGRATION_DOING_API_REQUEST
			) {
				remove_all_actions( 'widgets_init' );
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
	 * @since 1.0.0
	 *
	 * @return FS_EDD_Migration_Endpoint The one true Easy_Digital_Downloads Instance
	 */
	function fs_edd_migration_manager() {
		return FS_EDD_Migration_Endpoint::instance();
	}

	// Get Freemius EDD Migration running.
	fs_edd_migration_manager();