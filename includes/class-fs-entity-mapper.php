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

	define( 'WP_FS__ENTITY_MAP_TABLE', 'fs_entity_map' );
	define( 'WP_FS__DB_DATETIME_FORMAT', 'Y-m-d H:i:s' );

	/**
	 * Class FS_Entity_Mapper
	 *
	 * Entity mapper DAL. To map between local WordPress entities to Freemius remote entities.
	 *
	 * Each local entity can be linked to ONE and ONLY ONE remote entity on Freemius.
	 *
	 */
	class FS_Entity_Mapper {
		/**
		 * @var string
		 */
		private $_table_name;

		/**
		 * @var string E.g. EDD or WOO
		 */
		private $_namespace;

		#--------------------------------------------------------------------------------
		#region Singleton
		#--------------------------------------------------------------------------------

		private static $_instances = array();

		/**
		 * @param string $namespace
		 *
		 * @return FS_Entity_Mapper
		 */
		public static function instance( $namespace ) {
			$namespace = strtolower($namespace);

			if ( ! isset( self::$_instances[ $namespace ] ) ) {
				self::$_instances[ $namespace ] = new FS_Entity_Mapper( $namespace );
			}

			return self::$_instances[ $namespace ];
		}

		#endregion

		protected function __construct( $namespace ) {
			$this->_namespace  = $namespace;
			$this->_table_name = self::get_table_name();
		}

		private static function get_table_name() {
			global $wpdb;

			return trim( $wpdb->prefix . WP_FS__ENTITY_MAP_TABLE, '_' );
		}

		/**
		 * Create custom table for optimized performance.
		 *
		 *  [ - Table indexes are intentionally using DESC order since usually the scope of actions related to the last
		 *  inserted records. ] - DEPRECATED
		 *
		 *  - Seems like dbDelta don't trained to handle index ASC / DESC directives.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 */
		static function create_table() {
			if ( ! function_exists( 'dbDelta' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			}

			/**
			 * NOTE:
			 *  dbDelta must use KEY and not INDEX for indexes.
			 *
			 * @link https://core.trac.wordpress.org/ticket/2695
			 */
			$result = dbDelta(
				'CREATE TABLE ' . self::get_table_name() . ' (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  namespace VARCHAR(8) NOT NULL,
  entity_type VARCHAR(32) NOT NULL,
  local_id VARCHAR(32) NOT NULL,
  remote_id BIGINT(20) UNSIGNED NOT NULL,
  created DATETIME NOT NULL,
  updated DATETIME NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY local_indx (local_id,entity_type,namespace),
  KEY remote_indx (remote_id,entity_type,namespace))'
			);
		}

		/**
		 * Link local entity with Freemius entity.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param string $entity_type
		 * @param number $entity_id
		 * @param string $local_entity_id
		 *
		 * @return FS_Entity_Map|false
		 */
		function _link( $entity_type, $entity_id, $local_entity_id ) {
			global $wpdb;

			// For consistency, we want to work based on the server's clock and not the DB clock.
			$created = date( WP_FS__DB_DATETIME_FORMAT, WP_FS__SCRIPT_START_TIME );

			$affected_rows_count = $wpdb->query(
				$wpdb->prepare( '
INSERT INTO
	' . $this->_table_name . ' (namespace, entity_type, local_id, remote_id, created)
VALUES
	(%s, %s, %s, %s, %s)
ON DUPLICATE KEY UPDATE
	remote_id = %s,
	updated = %s
',
					array(
						$this->_namespace,
						$entity_type,
						$local_entity_id,
						$entity_id,
						$created,
						$entity_id,
						$created,
					)
				)
			);

			if ( 0 === $affected_rows_count ) {
				// Failed to link entities.
				return false;
			}

			$map            = new FS_Entity_Map();
			$map->id        = $wpdb->insert_id;
			$map->type      = $entity_type;
			$map->local_id  = $local_entity_id;
			$map->remote_id = $entity_id;
			$map->created   = $created;

			return $map;
		}

		/**
		 * Link local entity with Freemius entity.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param FS_Entity $entity
		 * @param string    $local_entity_id
		 *
		 * @return FS_Entity_Map|false
		 */
		function link( FS_Entity $entity, $local_entity_id ) {
			return $this->_link(
				$entity->get_type(),
				$entity->id,
				$local_entity_id
			);
		}

		/**
		 * Load map by local entity.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param string $type
		 * @param string $local_id
		 *
		 * @return FS_Entity_Map|false
		 */
		function get_by_local( $type, $local_id ) {
			global $wpdb;

			$map = $wpdb->get_row(
				$wpdb->prepare( '
SELECT
	*
FROM
	' . $this->_table_name . '
WHERE
	local_id = %s
AND
	entity_type = %s
AND
	namespace = %s
',
					array(
						$local_id,
						$type,
						$this->_namespace,
					)
				)
			);

			return ! is_null( $map ) && is_object( $map ) ?
				new FS_Entity_Map( $map ) :
				false;
		}

		/**
		 * Get remote entity ID.
		 *
		 * @param string $type
		 * @param string $local_id
		 *
		 * @return number|false
		 */
		function get_remote_id( $type, $local_id ) {
			$map = $this->get_by_local( $type, $local_id );

			return is_object( $map ) ? $map->remote_id : false;
		}

		/**
		 * Load map by remote Freemius entity.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param string $type
		 * @param number $remote_id
		 *
		 * @return FS_Entity_Map|false
		 */
		function get_by_remote( $type, $remote_id ) {
			global $wpdb;

			$map = $wpdb->get_row(
				$wpdb->prepare( '
SELECT
	*
FROM
	' . $this->_table_name . '
WHERE
	remote_id = %s
AND
	entity_type = %s
AND
	namespace = %s
',
					array(
						$remote_id,
						$type,
						$this->_namespace,
					)
				)
			);

			return ! is_null( $map ) && is_object( $map ) ?
				new FS_Entity_Map( $map ) :
				false;
		}

		/**
		 * Load map by remote Freemius entity.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param FS_Entity $entity
		 *
		 * @return FS_Entity_Map|false
		 */
		function get_by_remote_entity( FS_Entity $entity ) {
			return $this->get_by_remote( $entity->get_type(), $entity->id );
		}
	}

	/**
	 * @param string $namespace
	 * @param string $type
	 * @param string $local_id
	 *
	 * @return false|number
	 */
	function fs_get_remote_id( $namespace, $type, $local_id ) {
		return FS_Entity_Mapper::instance($namespace)->get_remote_id($type, $local_id);
	}