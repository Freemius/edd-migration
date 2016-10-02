<?php
	/**
	 * @package     Freemius for EDD Add-On
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
	 * @since       1.0.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class FS_Webhook
	 *
	 * Exposes an endpoint to process Freemius events.
	 *
	 *      www.blog.com/freemius/v1/{request}
	 *
	 * @since       1.0.0
	 */
	class FS_Webhook {
		/**
		 * @var FS_Adapter_Abstract
		 */
		private $_adapter;

		/**
		 * @var FS_Option_Manager
		 */
		private $_options;

		/**
		 * @var FS_Api
		 */
		private $_api;

		/**
		 * @var FS_Developer
		 */
		private $_developer;

		/**
		 * @var string MD5 random token.
		 */
		private $_token;

		/**
		 * @var string
		 */
		private $_menu_slug;

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param FS_Adapter_Abstract $adapter
		 */
		function __construct( FS_Adapter_Abstract $adapter, $menu_slug ) {
			$this->_adapter   = $adapter;
			$this->_menu_slug = $menu_slug;

			$this->_options = FS_Option_Manager::get_manager( $this->_adapter->name() . '-webhook-options', true );

			$this->init_hooks();
		}

		/**
		 * Initialize hooks.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 */
		private function init_hooks() {
			register_activation_hook( $this->find_caller_plugin_file(), array( &$this, '_plugin_activation' ) );

			add_action( 'init', array( &$this, '_add_endpoint' ) );
			add_action( 'template_redirect', array( $this, 'process_request' ), - 1 );
			add_action( 'wp_ajax_fs_get_secure_token', array( &$this, '_random_token_ajax_callback' ) );
			add_action( 'admin_menu', array( &$this, '_add_submenu' ), 99999999 );
			add_action( 'admin_notices', array( $this, '_notices' ) );
//			add_filter( 'query_vars', array( $this, 'query_vars' ) );
		}

		function _plugin_activation() {
			$this->flush_rewrite_rules();

			// Create mapping table if not exist.
			FS_Entity_Mapper::create_table();
		}


		#region Routing

		/**
		 * Add webhook endpoint and flush rewrite rules.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 */
		private function flush_rewrite_rules() {
			$this->_add_endpoint();
			flush_rewrite_rules();
		}

		/**
		 * Adds special endpoint for '/blog-www/freemius-api/'.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 */
		function _add_endpoint() {
			add_rewrite_endpoint( WP_FS__WEBHOOK_ENDPOINT, EP_ROOT );
		}

		#endregion Routing

		#region Helper Methods

		/**
		 * Leverage backtrace to find caller plugin file path.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return string
		 */
		private function find_caller_plugin_file() {
			$bt              = debug_backtrace();
			$abs_path_length = strlen( ABSPATH );
			$i               = 1;
			while (
				$i < count( $bt ) - 1 &&
				// substr is used to prevent cases where a includes folder appears
				// in the path. For example, if WordPress is installed on:
				//  /var/www/html/some/path/includes/path/wordpress/wp-content/...
				( false !== strpos( substr( fs_normalize_path( $bt[ $i ]['file'] ), $abs_path_length ), '/includes/' ) ||
				  fs_normalize_path( dirname( dirname( $bt[ $i ]['file'] ) ) ) !== fs_normalize_path( WP_PLUGIN_DIR ) )
			) {
				$i ++;
			}

			return $bt[ $i ]['file'];
		}

		/**
		 * Check if string starts with a prefix.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param string $string
		 * @param string $prefix
		 *
		 * @return bool
		 */
		private function starts_with( &$string, $prefix ) {
			return ( $prefix === substr( $string, 0, strlen( $prefix ) ) );
		}

		#endregion Helper Methods

		/**
		 * Lazy load of entity files.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 */
		private function require_entities_files() {
			require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-event.php';
			require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-user.php';
			require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-install.php';
			require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-license.php';
			require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-payment.php';
			require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-pricing.php';
			require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-subscription.php';
		}

		#region Developer Info

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
			$developer = $fs_api->get_developer();

			if ( ! is_object( $developer ) ) {
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

				$this->_developer = $developer;

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

		#endregion Developer Info

		#region Token

		/**
		 * Generates secure token, echo the token and exit script.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 */
		function _random_token_ajax_callback() {
			$token = $this->generate_token();
			echo $token;
			exit;
		}

		/**
		 * Generates pretty decent security token.
		 *
		 * Note: To create cryptographically secure token openssl must be installed on the server.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return string
		 */
		private function generate_token() {
			$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

			$key         = '';
			$chars_count = strlen( $chars );
			for ( $i = 0; $i < 64; $i ++ ) {
				$key .= substr( $chars, fs_crypto_rand_secure( 0, $chars_count - 1 ), 1 );
			}

			return $key;
		}

		/**
		 * Lazy load of token. If not exist, generate one.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return string
		 */
		function get_token() {
			if ( ! isset( $this->_token ) ) {
				$this->_token = $this->_options->get_option( 'token' );

				if ( ! is_string( $this->_token ) ) {
					$this->_token = $this->generate_token();

					// Store token.
					$this->_options->set_option( 'token', $this->_token, true );
				}
			}

			return $this->_token;
		}

		#endregion Token

		#region Admin Settings

		private $_notices = array();

		/**
		 * Render all admin notices added by webhook.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 */
		function _notices() {
			foreach ( $this->_notices as $notice ) {
				fs_require_template( 'admin-notice.php', $notice );
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
				$this->_menu_slug,
				'Freemius',
				'Freemius',
				'manage_options',
				WP_FS__SLUG,
				array( &$this, '_admin_settings' )
			);

			add_action( "load-$hook", array( &$this, '_save_settings' ) );
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
		 * Renders Freemius settings page.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 */
		function _admin_settings() {
			$params = array( 'webhook' => $this );
			fs_require_template( 'settings.php', $params );
		}

		#endregion Admin Settings

		private function has_plugin( $id ) {
			return true;
		}

		/**
		 * Lazy load of the developer's API.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return FS_Entity_Api
		 */
		private function get_api() {
			if ( ! isset( $this->_api ) ) {
				require_once WP_FS__DIR_INCLUDES . '/class-fs-api.php';
				require_once WP_FS__DIR_INCLUDES . '/class-fs-entity-api.php';

				$developer = $this->get_developer();

				$this->_api = FS_Entity_Api::instance(
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
		 * Make sure valid FS_Entity. If invalid, handle error.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param mixed $object
		 * @param int   $http_error_code
		 */
		private function require_entity(
			&$object,
			$http_error_code = 404
		) {
			if ( ! is_object( $object ) || ! ( $object instanceof FS_Entity ) ) {
				// Failed to fetch event.
				http_response_code( $http_error_code );
				exit;
			}
		}

		function process_request() {
			global $wp_query;

			// Validate it's a freemius webhook callback.
			if ( empty( $wp_query->query_vars[ WP_FS__WEBHOOK_ENDPOINT ] ) ||
			     'webhook' !== trim( $wp_query->query_vars[ WP_FS__WEBHOOK_ENDPOINT ], '/' )
			) {
				return;
			}

			$this->require_entities_files();

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

			// Authenticate request.
			if ( $_REQUEST['token'] !== $this->_options->get_option( 'token' ) ) {
				http_response_code( 401 );
				exit;
			}

			// Make sure plugin exist.
			if ( $this->has_plugin( $request_event->plugin_id ) ) {
				// Plugin don't exist locally.
				http_response_code( 404 );
				exit;
			}

			$fs_api = $this->get_api();

			// Set context plugin for clearer code.
			$fs_api->set_context_plugin( $request_event->plugin_id );

			// Validation.
			$event = $fs_api->get_event( $request_event->id );

			$this->require_entity( $event, 404 );

			if ( $this->starts_with( $event->type, 'license.' ) ) {
				$license = is_object( $request_event->objects->license ) ?
					new FS_License( $request_event->objects->license ) :
					null;
				$install = is_object( $request_event->objects->install ) ?
					new FS_Install( $request_event->objects->install ) :
					null;
				$user    = is_object( $request_event->objects->user ) ?
					new FS_User( $request_event->objects->user ) :
					null;

				switch ( $event->type ) {
					case 'license.created':
						$this->_adapter->create_license( $license, $install, $user );
						break;
					case 'license.activated':
						$this->_adapter->activate_license( $license, $install, $user );
						break;
					case 'license.expired':
						$this->_adapter->expire_license( $license, $install, $user );
						break;
					case 'license.deactivated':
						$this->_adapter->deactivate_license( $license, $install, $user );
						break;
					case 'license.cancelled':
						$this->_adapter->cancel_license( $license, $install, $user );
						break;
					case 'license.extended':
						$this->_adapter->extend_license( $license, $install );
						break;
				}
			} else if ( $this->starts_with( $event->type, 'payment.' ) ) {
				$payment = is_object( $request_event->objects->payment ) ?
					new FS_Payment( $request_event->objects->payment ) :
					null;

				$user = is_object( $request_event->objects->user ) ?
					new FS_User( $request_event->objects->user ) :
					null;

				switch ( $event->type ) {
					case 'payment.created':
						$this->_adapter->create_payment( $payment, $user );
						break;
					case 'payment.refund':
						$this->_adapter->refund_payment( $payment, $user );
						break;
				}
			} else if ( $this->starts_with( $event->type, 'subscription.' ) ) {
				$subscription = is_object( $request_event->objects->subscription ) ?
					new FS_Subscription( $request_event->objects->subscription ) :
					null;

				$user = is_object( $request_event->objects->user ) ?
					new FS_User( $request_event->objects->user ) :
					null;

				$install = is_object( $request_event->objects->install ) ?
					new FS_Install( $request_event->objects->install ) :
					null;

				switch ( $event->type ) {
					case 'subscription.created':
						$this->_adapter->create_subscription( $subscription, $user, $install );
						break;
					case 'subscription.cancelled':
						$this->_adapter->cancel_subscription( $subscription, $user, $install );
						break;
				}
			} else {
				switch ( $event->type ) {
					case 'plan.lifetime.purchase':
						break;
				}
			}


			http_response_code( 200 );
			exit;
		}
	}