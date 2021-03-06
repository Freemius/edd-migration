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

	class FS_Developer extends FS_Scope_Entity {

		#region Properties

		/**
		 * @var string
		 */
		public $email;
		/**
		 * @var string
		 */
		public $first;
		/**
		 * @var string
		 */
		public $last;
		/**
		 * @var bool
		 */
		public $is_verified;

		#endregion Properties

		/**
		 * @param object|bool $user
		 */
		function __construct( $user = false ) {
			parent::__construct( $user );
		}

		function get_name() {
			return trim( ucfirst( trim( is_string( $this->first ) ? $this->first : '' ) ) . ' ' . ucfirst( trim( is_string( $this->last ) ? $this->last : '' ) ) );
		}

		function is_verified() {
			return ( isset( $this->is_verified ) && true === $this->is_verified );
		}

		static function get_type() {
			return 'developer';
		}
	}