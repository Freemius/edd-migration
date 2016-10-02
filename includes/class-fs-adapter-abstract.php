<?php

	/**
	 * @package     Freemius for EDD Add-On
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
	 * @since       1.0.0
	 */
	abstract class FS_Adapter_Abstract {
		abstract function name();

		/**
		 * @return FS_Entity_Mapper
		 */
		abstract function get_entity_mapper();

		abstract function get_fs_api();

		#region Licensing -------------------------------------------------

		abstract function get_license( FS_License $fs_license );

		abstract function create_license( FS_License $fs_license, FS_Install $fs_site, FS_User $fs_user );

		abstract function activate_license( FS_License $fs_license, FS_Install $fs_site, FS_User $fs_user );

		abstract function deactivate_license( FS_License $fs_license, FS_Install $fs_site, FS_User $fs_user );

		abstract function expire_license( FS_License $fs_license, FS_Install $fs_site, FS_User $fs_user );

		abstract function cancel_license( FS_License $fs_license, FS_Install $fs_site, FS_User $fs_user );

		abstract function extend_license( FS_License $fs_license, FS_Install $fs_site );

		#endregion Licensing -------------------------------------------------

		abstract function get_customer( FS_User $fs_user );

		abstract function create_customer( FS_User $fs_user );

		#region Payments -------------------------------------------------

		abstract function create_payment( FS_Payment $fs_payment, FS_User $fs_user );

		abstract function refund_payment( FS_Payment $fs_refund, FS_User $fs_user );

		#endregion Payments -------------------------------------------------

		#region Subscriptions -------------------------------------------------

		abstract function get_subscription( FS_Subscription $fs_subscription );

		abstract function create_subscription( FS_Subscription $fs_subscription, FS_User $fs_user, FS_Install $fs_site );

		abstract function cancel_subscription( FS_Subscription $fs_subscription, FS_User $fs_user, FS_Install $fs_site );

		#endregion Subscriptions -------------------------------------------------

		#region Helper Methods -------------------------------------------------

		/**
		 * Link local entity to remote entity by IDs.
		 *
		 * @author   Vova Feldman (@svovaf)
		 * @since    1.0.0
		 *
		 * @param FS_Entity $fs_entity
		 * @param number    $local_entity_id
		 *
		 * @return false|FS_Entity_Map
		 */
		protected function link_entity( FS_Entity $fs_entity, $local_entity_id ) {
			return $this->get_entity_mapper()->link( $fs_entity, $fs_entity->get_type(), $local_entity_id );
		}

		/**
		 * @param FS_Entity $fs_entity
		 *
		 * @return bool|number
		 */
		protected function get_local_id( FS_Entity $fs_entity ) {
			$map = $this->get_entity_mapper()->get_by_remote_entity( $fs_entity );

			return is_object( $map ) ?
				$map->local_id :
				false;
		}

		#endregion Helper Methods -------------------------------------------------
	}

