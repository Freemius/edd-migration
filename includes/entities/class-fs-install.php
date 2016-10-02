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

	class FS_Install extends FS_Scope_Entity {

		#region Properties

		/**
		 * @var number
		 */
		public $site_id;
		/**
		 * @var number
		 */
		public $plugin_id;
		/**
		 * @var number
		 */
		public $user_id;
		/**
		 * @var string
		 */
		public $url;
		/**
		 * @var string
		 */
		public $title;
		/**
		 * @var string Plugin version.
		 */
		public $version;
		/**
		 * @var number
		 */
		public $plan_id;
		/**
		 * @var number
		 */
		public $license_id;
		/**
		 * @var number
		 */
		public $trial_plan_id;
		/**
		 * @var number
		 */
		public $trial_ends;
		/**
		 * @var number
		 */
		public $subscription_id;
		/**
		 * @var float
		 */
		public $gross;
		/**
		 * @var string ISO 3166-1 alpha-2 - two-letter country code.
		 *
		 * @link http://www.wikiwand.com/en/ISO_3166-1_alpha-2
		 */
		public $country_code;
		/**
		 * @var string E.g. en-GB
		 */
		public $language;
		/**
		 * @var string E.g. UTF-8
		 */
		public $charset;
		/**
		 * @var string Platform version (e.g WordPress version).
		 */
		public $platform_version;
		/**
		 * @var string Programming language version (e.g PHP version).
		 */
		public $programming_language_version;
		/**
		 * @var bool
		 */
		public $is_active;
		/**
		 * @var bool Is install using premium code.
		 */
		public $is_premium;
		/**
		 * @var bool
		 */
		public $is_uninstalled;
		/**
		 * @var bool
		 */
		public $is_locked;

		#endregion Properties

		/**
		 * @param stdClass|bool $site
		 */
		function __construct( $site = false ) {
			parent::__construct( $site );
		}

		static function get_type() {
			return 'install';
		}

		/**
		 * Check if install in trial.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.9
		 *
		 * @return bool
		 */
		function is_trial() {
			return is_numeric( $this->trial_plan_id ) && ( strtotime( $this->trial_ends ) > WP_FS__SCRIPT_START_TIME );
		}

		/**
		 * Check if user already utilized the trial with the current install.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.9
		 *
		 * @return bool
		 */
		function is_trial_utilized() {
			return is_numeric( $this->trial_plan_id );
		}
	}