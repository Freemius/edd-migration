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

	if ( ! class_exists( 'FS_Cache_Manager' ) ) {
		require_once WP_FSM__DIR_INCLUDES . '/managers/class-fs-cache-manager.php';
	}
	if ( ! class_exists( 'FS_Api' ) ) {
		require_once WP_FSM__DIR_INCLUDES . '/class-fs-api.php';
	}

	abstract class FS_Migration_Endpoint_Abstract {
		/**
		 * @var string
		 */
		protected $_namespace;

		/**
		 * @var FS_Developer
		 */
		protected $_developer;

		/**
		 * @var FS_Option_Manager
		 */
		protected $_options;

		/**
		 * @var FS_Api
		 */
		protected $_api;

		/**
		 * @var array
		 */
		protected $_request_data;

		/**
		 * @var FS_Entity_Mapper
		 */
		protected $_entity_mapper;

		#--------------------------------------------------------------------------------
		#region Init
		#--------------------------------------------------------------------------------

		/**
		 * This method MUST be executed in the constructor of the
		 * inheriting class.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param string $namespace
		 */
		protected function init( $namespace ) {
			if ( ! defined( 'DOING_AJAX' ) ) {
				add_action( 'init', array( $this, 'maybe_process_api_request' ) );
			}

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

			$this->_namespace     = $namespace;
			$this->_options       = FS_Option_Manager::get_manager( 'migration_options', true );
			$this->_entity_mapper = FS_Entity_Mapper::instance( $namespace );

			add_action( 'wp_ajax_fs_sync_module', array( &$this, '_sync_module_to_freemius_callback' ) );
			add_action( 'wp_ajax_fs_clear_mapping', array( &$this, '_clear_mapping_callback' ) );
		}

		#endregion

		#--------------------------------------------------------------------------------
		#region Testing
		#--------------------------------------------------------------------------------

		/**
		 * Generates Freemius unique anonymous site identifier.
		 *
		 * Note: This method is only for testing reasons.
		 *
		 * @author   Vova Feldman (@svovaf)
		 * @since    1.0.0
		 *
		 * @param string $site_url
		 *
		 * @return string
		 */
		protected function get_anonymous_id( $site_url = '' ) {
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

		#endregion

		#--------------------------------------------------------------------------------
		#region Entities Linking/Mapping
		#--------------------------------------------------------------------------------

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
		protected function get_remote_id( $type, $local_id ) {
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

		/**
		 * Get the corresponding Freemius paid plan ID.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param string $local_module_id
		 *
		 * @return false|number
		 */
		function get_remote_paid_plan_id( $local_module_id ) {
			return $this->get_remote_id(
				FS_Plan::get_type(),
				$local_module_id . ':' . ( edd_has_variable_prices( $local_module_id ) ? '1' : '0' )
			);
		}

		/**
		 * Get the corresponding Freemius module/product ID.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param string $local_module_id
		 *
		 * @return false|number
		 */
		function get_remote_module_id( $local_module_id ) {
			return $this->get_remote_id(
				FS_Plugin::get_type(),
				$local_module_id
			);
		}

		#endregion

		#--------------------------------------------------------------------------------
		#region Plugin Settings
		#--------------------------------------------------------------------------------

		/**
		 * Lazy load of developer.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return FS_Developer
		 */
		public function get_developer() {
			if ( ! isset( $this->_developer ) ) {
				$this->_developer = $this->_options->get_option( 'developer', new FS_Developer() );
			}

			return $this->_developer;
		}

		/**
		 * Update local context developer data.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param number $id
		 * @param string $public_key
		 * @param string $secret_key
		 */
		protected function update_developer( $id, $public_key, $secret_key ) {
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
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return bool
		 */
		public function is_connected() {
			$dev = $this->get_developer();

			return isset( $dev->email );
		}

		#endregion

		#--------------------------------------------------------------------------------
		#region WP Admin Settings
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
			$params = array( 'endpoint' => $this );
			$this->load_template( 'settings.php', $params );
		}

		/**
		 * Load template.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param string $path
		 * @param array  $params
		 */
		private function load_template( $path, $params ) {
			$VARS = &$params;
			require WP_FSM__DIR_TEMPLATES . '/' . trim( $path, '/' );
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
		 * Get all EDD downloads mapped to FS objects for the settings page.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return FS_Plugin[]
		 */
		function get_all_local_modules_for_settings() {
			$converted_modules = array();

			$local_modules = $this->get_all_local_modules();

			foreach ( $local_modules as $local_module ) {
				$converted_modules[] = $this->local_to_remote_module( $local_module );
			}

			return $converted_modules;
		}

		#endregion

		/**
		 * Assumes that developer won't have two modules with
		 * identical slug (e.g. plugin and theme with the same slug).
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param string $slug
		 *
		 * @return \FS_Plugin
		 */
		protected function get_module_by_slug( $slug ) {
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
		 * Lazy load of the developer's API.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return FS_Api
		 */
		protected function get_api() {
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

		#--------------------------------------------------------------------------------
		#region Local Store's Endpoint
		#--------------------------------------------------------------------------------

		/**
		 * Check if current request is a valid API request.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return bool
		 */
		protected function is_api_request() {
			$endpoint_path = WP_FSM__MAIN_ENDPOINT . "/{$this->_namespace}/migrate-license.json";

			return ( false !== stristr( $_SERVER['REQUEST_URI'], $endpoint_path ) );
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

		/**
		 * Get API request parameter or return $default if not set.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param string $name
		 * @param bool   $default
		 *
		 * @return mixed
		 */
		protected function get_param( $name, $default = false ) {
			return isset( $this->_request_data[ $name ] ) ?
				( is_string( $this->_request_data[ $name ] ) ?
					urldecode( $this->_request_data[ $name ] ) :
					$this->_request_data[ $name ]
				) :
				$default;
		}

		/**
		 * Process license migration request.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param array|null $test_request_data Special parameter for local testing.
		 */
		public function maybe_process_api_request( $test_request_data = null ) {
			if ( is_array( $test_request_data ) && ! empty( $test_request_data ) ) {
				// Use testing request data.
				$request_data = $test_request_data;
			} else {
				if ( is_admin() ) {
					// Endpoint isn't part of /wp-admin/...
					return;
				}

				if ( defined( 'WP_FSM__MIGRATION_DOING_API_REQUEST' ) ) {
					// Already running in API migration.
					return;
				}

				if ( ! $this->is_api_request() ) {
					// Request path isn't matching the migration endpoint.
					return;
				}

				require_once WP_FSM__DIR_INCLUDES . '/class-fs-endpoint-exception.php';

				define( 'WP_FSM__MIGRATION_DOING_API_REQUEST', true );

				// Retrieve the request body and parse it as JSON.
				$input = @file_get_contents( "php://input" );

				$request_data = json_decode( $input, true );
			}

			if ( ! is_array( $request_data ) ) {
				$request_data = array();
			}

			$this->_request_data = $request_data;

			try {

				$this->enrich_optional_params_with_defaults();

				$this->validate_all_params();

				$account = $this->migrate_install_license();

				$this->shoot_json_success( $account );
			} catch ( FS_Endpoint_Exception $e ) {
				// Shoot API failure.
				$this->shoot_json_exception( $e );
			}
		}

		#--------------------------------------------------------------------------------
		#region API Request Params Validation
		#--------------------------------------------------------------------------------

		/**
		 * Make sure given params are set in the API request.
		 *
		 * If any of the params is missing, the endpoint will throw an exception.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param array $params
		 *
		 * @throws FS_Endpoint_Exception
		 */
		protected function require_params( array $params ) {
			foreach ( $params as $p ) {
				if ( ! isset( $this->_request_data[ $p ] ) ) {
					throw new FS_Endpoint_Exception( "{$p} is a required parameter.", "{$p}_required" );
				}
			}
		}

		/**
		 * Make sure given params are set and not empty in the API request.
		 *
		 * If any of the params is empty, the endpoint will throw an exception.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param array $params
		 *
		 * @throws FS_Endpoint_Exception
		 */
		protected function require_non_empty_params( array $params ) {
			foreach ( $params as $p ) {
				if ( empty( $this->_request_data[ $p ] ) ) {
					throw new FS_Endpoint_Exception( "{$p} cannot be empty.", "{$p}_empty" );
				}
			}
		}

		/**
		 * Make sure an API request parameter is unsigned integer.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param string $name
		 *
		 * @throws FS_Endpoint_Exception
		 */
		protected function require_unsigned_int( $name ) {
			$this->require_params( array( $name ) );

			if ( is_int( $this->_request_data[ $name ] ) &&
			     $this->_request_data[ $name ] > 0
			) {
				// Unsigned integer.
				return;
			}

			if ( ! is_numeric( $this->_request_data[ $name ] ) ||
			     ! ctype_digit( $this->_request_data[ $name ] )
			) {
				throw new FS_Endpoint_Exception( "{$name} must be unsigned integer.", "{$name}_not_integer", 400 );
			}
		}

		/**
		 * Validate common required params.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @throws FS_Endpoint_Exception
		 */
		protected function validate_all_params() {
			$this->require_params( array(
				// License key.
				'license_key',
				// Install details.
				'site_url',
				'plugin_version',
				'is_premium',
				'site_uid',
				'site_name',
				'language',
				'charset',
				'platform_version',
				'php_version',
			) );

			$this->require_non_empty_params( array(
				// License key.
				'license_key',
				// Install details.
				'site_url',
				'plugin_version',
			) );

			$this->validate_params();
		}

		/**
		 * Enrich request's optional params with default values.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 */
		protected function enrich_optional_params_with_defaults() {
			$defaults = array(
				// Assume premium code migration.
				'is_premium'     => true,
				// Assume module is installed and active.
				'is_uninstalled' => false,
				'is_active'      => true,
			);

			foreach ( $defaults as $k => $v ) {
				if ( ! isset( $this->_request_data[ $k ] ) ) {
					$this->_request_data[ $k ] = $v;
				}
			}

			if ( empty( $this->_request_data['url'] ) ) {
				// Attempt to grab the URL from the user agent if no URL is specified
				$domain = array_map( 'trim', explode( ';', $_SERVER['HTTP_USER_AGENT'] ) );
				if ( 1 < count( $domain ) ) {
					$url = trim( $domain[1] );
				} else {
					// If URL is missing and can't fetch from user agent, use required 'site_url' instead.
					$url = $this->get_param( 'site_url' );
				}

				$this->_request_data['url'] = $url;
			}
		}

		/**
		 * Should validate platform specific required params.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @throws FS_Endpoint_Exception
		 */
		abstract protected function validate_params();

		#endregion

		#endregion

		#--------------------------------------------------------------------------------
		#region Local API Response
		#--------------------------------------------------------------------------------

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param bool   $success
		 * @param mixed  $data
		 * @param string $message
		 */
		protected function shoot_json_result( $success = true, $data = null, $message = '' ) {
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
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param string $message
		 * @param mixed  $data
		 */
		protected function shoot_json_failure( $message = '', $data = null ) {
			$this->shoot_json_result( false, $data, $message );
		}

		/**
		 * @author   Vova Feldman (@svovaf)
		 * @since    1.0.0
		 *
		 * @param FS_Endpoint_Exception $e
		 */
		protected function shoot_json_exception( FS_Endpoint_Exception $e ) {
			header( 'Content-Type: application/json' );

			$result = array(
				'success' => false,
				'error'   => $e->toArray()
			);

			echo json_encode( $result );
			exit;
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param mixed  $data
		 * @param string $message
		 */
		protected function shoot_json_success( $data = null, $message = '' ) {
			$this->shoot_json_result( true, $data, $message );
		}

		#endregion

		#--------------------------------------------------------------------------------
		#region Data Sync
		#--------------------------------------------------------------------------------

		/**
		 * Sync local module to Freemius. If there's no matching module
		 * with the same slug on Freemius, create new module and sync
		 * the plans & pricing.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 */
		public function _sync_module_to_freemius_callback() {
			require_once WP_FSM__DIR_INCLUDES . '/migration/class-fs-module-migration-abstract.php';

			if ( empty( $_POST['local_module_id'] ) || ! is_numeric( $_POST['local_module_id'] ) ) {
				$this->shoot_json_failure( 'local_module_id parameter is missing.' );
			}

			// Check if download exist.
			$local_module_id = $_POST['local_module_id'];

			// Try to load local module.
			$local_module = $this->get_local_module_by_id( $local_module_id );

			if ( false === $local_module ) {
				// Post not exist or not an EDD download.
				$this->shoot_json_failure( "There's no local module with the specified ID ({$local_module_id})." );
			}

			$migration = $this->get_local_module_migration_manager( $local_module );

			if ( $migration->do_sync( true ) ) {
				// Success.
				$module_id = $this->get_remote_module_id( $local_module_id );
				$plan_id   = $this->get_remote_paid_plan_id( $local_module_id );

				$this->shoot_json_success( array(
					'module_id' => $module_id,
					'plan_id'   => $plan_id,
				) );
			} else {
				// Failure.
				$this->shoot_json_failure( 'Failed syncing the module.' );
			}
		}

		/**
		 * Clear all mapping.
		 *
		 * @author Vova Feldman
		 * @since  1.0.2
		 */
		public function _clear_mapping_callback(){
			$this->_entity_mapper->clear_mapping();
			$this->shoot_json_success();
		}

		/**
		 * Automatically sync modules that have matching slugs
		 * on local environment and on Freemius.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 */
		protected function sync_modules_to_freemius_by_slug() {
			require_once WP_FSM__DIR_INCLUDES . '/migration/class-fs-module-migration-abstract.php';

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

			$local_modules = $this->get_all_local_modules();

			foreach ( $local_modules as $local_module ) {
				$local_module_slug = $this->get_local_module_slug( $local_module );

				if ( isset( $modules_map[ $local_module_slug ] ) ) {
					// Found a matching module.
					$migration = $this->get_local_module_migration_manager(
						$local_module,
						$modules_map[ $local_module_slug ]
					);

					$migration->do_sync();
				}
			}
		}

		#endregion

		#--------------------------------------------------------------------------------
		#region Local Data Getters
		#--------------------------------------------------------------------------------

		/**
		 * Should return an instance of module migration manager.
		 *
		 * If `$module` is not given, the method should find the matching module on Freemius.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param mixed      $local_module Local module object.
		 * @param \FS_Plugin $module
		 *
		 * @return \FS_Module_Migration_Abstract
		 */
		abstract protected function get_local_module_migration_manager( $local_module, FS_Plugin $module = null );

		/**
		 * Should load local module object by ID.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param string $local_module_id
		 *
		 * @return mixed
		 */
		abstract protected function get_local_module_by_id( $local_module_id );

		/**
		 * Should return an array of all local modules.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return array
		 */
		abstract protected function get_all_local_modules();

		/**
		 * Should extract the local module's slug from the module object.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param mixed $local_module
		 *
		 * @return string
		 */
		abstract protected function get_local_module_slug( $local_module );

		/**
		 * Map local module data into FS object.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param mixed $local_module
		 *
		 * @return \FS_Plugin
		 */
		abstract protected function local_to_remote_module( $local_module );

		#endregion

		/**
		 * Should migrate install's license and return FS account's data (user + install).
		 *
		 * Result looks like:
		 *  array(
		 *      'install' => FS_Install,
		 *      'user' => FS_User,
		 *  )
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return array
		 *
		 * @throws FS_Endpoint_Exception
		 */
		abstract protected function migrate_install_license();
	}