<?
	/**
	 * @package     Freemius Migration
	 * @copyright   Copyright (c) 2016, Freemius, Inc.
	 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
	 * @since       1.0.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! defined( 'WP_FSM__SLUG' ) ) {
		define( 'WP_FSM__SLUG', 'fs-migration' );
	}

	if ( ! defined( 'WP_FS__SLUG' ) ) {
		define( 'WP_FS__SLUG', 'freemius' );
	}

	if ( ! defined( 'WP_FSM__MAIN_ENDPOINT' ) ) {
		define( 'WP_FSM__MAIN_ENDPOINT', 'fs-api' );
	}

	if ( ! defined( 'WP_FS__NAMESPACE_EDD' ) ) {
		define( 'WP_FS__NAMESPACE_EDD', 'EDD' );
	}

	if ( ! defined( 'WP_FS__NAMESPACE_WC' ) ) {
		define( 'WP_FS__NAMESPACE_WC', 'WC' );
	}

	if ( ! defined( 'WP_FS__IS_PRODUCTION_MODE' ) ) {
		// By default, run with Freemius production servers.
		define( 'WP_FS__IS_PRODUCTION_MODE', true );
	}

	#--------------------------------------------------------------------------------
	#region HTTP
	#--------------------------------------------------------------------------------

	if ( ! defined( 'WP_FS__IS_HTTP_REQUEST' ) ) {
		define( 'WP_FS__IS_HTTP_REQUEST', isset( $_SERVER['HTTP_HOST'] ) );
	}

	if ( ! defined( 'WP_FS__IS_HTTPS' ) ) {
		define( 'WP_FS__IS_HTTPS', ( WP_FS__IS_HTTP_REQUEST &&
		                             // Checks if CloudFlare's HTTPS (Flexible SSL support).
		                             isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) &&
		                             'https' === strtolower( $_SERVER['HTTP_X_FORWARDED_PROTO'] )
		                           ) ||
		                           // Check if HTTPS request.
		                           ( isset( $_SERVER['HTTPS'] ) && 'on' == $_SERVER['HTTPS'] ) ||
		                           ( isset( $_SERVER['SERVER_PORT'] ) && 443 == $_SERVER['SERVER_PORT'] )
		);
	}

	if ( ! defined( 'WP_FS__IS_POST_REQUEST' ) ) {
		define( 'WP_FS__IS_POST_REQUEST', ( WP_FS__IS_HTTP_REQUEST &&
		                                    strtoupper( $_SERVER['REQUEST_METHOD'] ) == 'POST' ) );
	}

	if ( ! defined( 'WP_FS__REMOTE_ADDR' ) ) {
		define( 'WP_FS__REMOTE_ADDR', fs_get_ip() );
	}

	if ( ! defined( 'WP_FS__IS_LOCALHOST' ) ) {
		if ( defined( 'WP_FS__LOCALHOST_IP' ) ) {
			define( 'WP_FS__IS_LOCALHOST', ( WP_FS__LOCALHOST_IP === WP_FS__REMOTE_ADDR ) );
		} else {
			define( 'WP_FS__IS_LOCALHOST', WP_FS__IS_HTTP_REQUEST &&
			                               is_string( WP_FS__REMOTE_ADDR ) &&
			                               ( substr( WP_FS__REMOTE_ADDR, 0, 4 ) === '127.' ||
			                                 WP_FS__REMOTE_ADDR === '::1' )
			);
		}
	}

	if ( ! defined( 'WP_FS__IS_LOCALHOST_FOR_SERVER' ) ) {
		define( 'WP_FS__IS_LOCALHOST_FOR_SERVER', ( ! WP_FS__IS_HTTP_REQUEST ||
		                                            false !== strpos( $_SERVER['HTTP_HOST'], 'localhost' ) ) );
	}

	#endregion

	#--------------------------------------------------------------------------------
	#region API
	#--------------------------------------------------------------------------------

	if ( ! defined( 'WP_FS__API_ADDRESS_LOCALHOST' ) ) {
		define( 'WP_FS__API_ADDRESS_LOCALHOST', 'http://api.freemius:8080' );
	}
	if ( ! defined( 'WP_FS__API_SANDBOX_ADDRESS_LOCALHOST' ) ) {
		define( 'WP_FS__API_SANDBOX_ADDRESS_LOCALHOST', 'http://sandbox-api.freemius:8080' );
	}

	// Set API address for local testing.
	if ( ! WP_FS__IS_PRODUCTION_MODE ) {
		if ( ! defined( 'FS_API__ADDRESS' ) ) {
			define( 'FS_API__ADDRESS', WP_FS__API_ADDRESS_LOCALHOST );
		}
		if ( ! defined( 'FS_API__SANDBOX_ADDRESS' ) ) {
			define( 'FS_API__SANDBOX_ADDRESS', WP_FS__API_SANDBOX_ADDRESS_LOCALHOST );
		}
	}

	#endregion

	#--------------------------------------------------------------------------------
	#region Directories
	#--------------------------------------------------------------------------------

	define( 'WP_FSM__DIR', dirname( __FILE__ ) );
	define( 'WP_FSM__DIR_INCLUDES', WP_FSM__DIR . '/includes' );
	define( 'WP_FSM__DIR_ENTITIES', WP_FSM__DIR . '/includes/entities' );
	define( 'WP_FSM__DIR_MIGRATION', WP_FSM__DIR . '/includes/migration' );
	define( 'WP_FSM__DIR_TEMPLATES', WP_FSM__DIR . '/templates' );
	define( 'WP_FSM__DIR_ASSETS', WP_FSM__DIR . '/assets' );
	define( 'WP_FSM__DIR_CSS', WP_FSM__DIR_ASSETS . '/css' );
	define( 'WP_FSM__DIR_JS', WP_FSM__DIR_ASSETS . '/js' );
	define( 'WP_FSM__DIR_SDK', WP_FSM__DIR_INCLUDES . '/sdk' );

	if ( ! defined( 'WP_FS__DIR_SDK' ) ) {
		define( 'WP_FS__DIR_SDK', WP_FSM__DIR_INCLUDES . '/sdk' );
	}


	#endregion

	if ( ! defined( 'WP_FS___OPTION_PREFIX' ) ) {
		define( 'WP_FS___OPTION_PREFIX', 'fs' . ( WP_FS__IS_PRODUCTION_MODE ? '' : '_dbg' ) . '_' );
	}

	if ( ! defined( 'WP_FS__OPTIONS_OPTION_NAME' ) ) {
		define( 'WP_FS__OPTIONS_OPTION_NAME', WP_FS___OPTION_PREFIX . 'options' );
	}
	if ( ! defined( 'WP_FS__API_CACHE_OPTION_NAME' ) ) {
		define( 'WP_FS__API_CACHE_OPTION_NAME', WP_FS___OPTION_PREFIX . 'api_cache' );
	}

	if ( ! defined( 'WP_FS__SCRIPT_START_TIME' ) ) {
		define( 'WP_FS__SCRIPT_START_TIME', time() );
	}
	if ( ! defined( 'WP_FS__DEFAULT_PRIORITY' ) ) {
		define( 'WP_FS__DEFAULT_PRIORITY', 10 );
	}
	if ( ! defined( 'WP_FS__LOWEST_PRIORITY' ) ) {
		define( 'WP_FS__LOWEST_PRIORITY', 999999999 );
	}

	#--------------------------------------------------------------------------------
	#region Debugging
	#--------------------------------------------------------------------------------

	if ( ! defined( 'WP_FS__DEBUG_SDK' ) ) {
		$debug_mode = get_option( 'fs_debug_mode', null );

		if ( $debug_mode === null ) {
			$debug_mode = false;
			add_option( 'fs_debug_mode', $debug_mode );
		}

		define( 'WP_FS__DEBUG_SDK', is_numeric( $debug_mode ) ? ( 0 < $debug_mode ) : WP_FS__DEV_MODE );
	}

	if ( ! defined( 'WP_FS__ECHO_DEBUG_SDK' ) ) {
		define( 'WP_FS__ECHO_DEBUG_SDK', WP_FS__DEV_MODE && ! empty( $_GET['fs_dbg_echo'] ) );
	}
	if ( ! defined( 'WP_FS__LOG_DATETIME_FORMAT' ) ) {
		define( 'WP_FS__LOG_DATETIME_FORMAT', 'Y-m-d H:i:s' );
	}
	/**
	 * This one is redundant define but mandatory since older version
	 * of Freemius set WP_FS__LOG_DATETIME_FORMAT to 'Y-n-d H:i:s' (used 'n' instead of 'm')
	 * which renders the date incorrectly (don't add '0' prefix to the month element).
	 */
	if ( ! defined( 'WP_FSM__LOG_DATETIME_FORMAT' ) ) {
		define( 'WP_FSM__LOG_DATETIME_FORMAT', 'Y-m-d H:i:s' );
	}
	if ( ! defined( 'FS_API__LOGGER_ON' ) ) {
		define( 'FS_API__LOGGER_ON', WP_FS__DEBUG_SDK );
	}

	if ( WP_FS__ECHO_DEBUG_SDK ) {
		error_reporting( E_ALL );
		ini_set( 'error_reporting', E_ALL );
		ini_set( 'display_errors', true );
		ini_set( 'html_errors', true );
	}

	#endregion

	/**
	 * Times in seconds
	 */
	if ( ! defined( 'WP_FS__TIME_5_MIN_IN_SEC' ) ) {
		define( 'WP_FS__TIME_5_MIN_IN_SEC', 300 );
	}
	if ( ! defined( 'WP_FS__TIME_10_MIN_IN_SEC' ) ) {
		define( 'WP_FS__TIME_10_MIN_IN_SEC', 600 );
	}
//	define( 'WP_FS__TIME_15_MIN_IN_SEC', 900 );
	if ( ! defined( 'WP_FS__TIME_24_HOURS_IN_SEC' ) ) {
		define( 'WP_FS__TIME_24_HOURS_IN_SEC', 86400 );
	}