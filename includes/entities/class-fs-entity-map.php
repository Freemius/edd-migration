<?php
	/**
	 * @package     Freemius for WC Add-On
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
	 * @since       1.0.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	class FS_Entity_Map extends FS_Entity {

		#region Properties

		/**
		 * @var string
		 */
		public $type;
		/**
		 * @var string
		 */
		public $local_id;
		/**
		 * @var number
		 */
		public $remote_id;

		#endregion Properties

		/**
		 * @param object|bool $entity_map
		 */
		function __construct( $entity_map = false ) {
			parent::__construct( $entity_map );
		}

		static function get_type() {
			return 'map';
		}
	}