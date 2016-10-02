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

	class FS_Event extends FS_Entity {

		#region Properties

		/**
		 * @var string
		 */
		public $type;
		/**
		 * @var number
		 */
		public $developer_id;
		/**
		 * @var number
		 */
		public $plugin_id;
		/**
		 * @var number
		 */
		public $user_id;
		/**
		 * @var number
		 */
		public $install_id;
		/**
		 * @var string
		 */
		public $data;
		/**
		 * @var string
		 */
		public $event_trigger;
		/**
		 * @var string Datetime value in 'YYYY-MM-DD HH:MM:SS' format.
		 */
		public $process_time;

		#endregion Properties

		/**
		 * @param object|bool $event
		 */
		function __construct( $event = false ) {
			parent::__construct( $event );
		}

		static function get_type() {
			return 'event';
		}
	}