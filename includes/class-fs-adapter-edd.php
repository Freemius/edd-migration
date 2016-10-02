<?php
	define( 'WP_FS__NAMESPACE_EDD', 'edd' );

	class FS_Adapter_EDD extends FS_Adapter_Abstract {
		private $_mapper;

		function __construct() {
			$this->_mapper = new FS_Entity_Mapper( WP_FS__NAMESPACE_EDD );
		}

		function name() {
			return WP_FS__NAMESPACE_EDD;
		}

		function get_entity_mapper() {
			return $this->_mapper;
		}

		function get_fs_api() {
			// TODO: Implement get_fs_api() method.
		}

		/**
		 * Load local license by FS license.
		 *
		 * @param FS_License $fs_license
		 *
		 * @return mixed|false
		 */
		function get_license( FS_License $fs_license ) {
			$license_id = $this->get_local_id( $fs_license );

			if ( false === $license_id ) {
				// License doesn't exist locally.
				return false;
			}

			// @todo IMPLEMENT LICENSE OBJECT LOAD
		}

		function activate_license( FS_License $license, FS_Install $site, FS_User $user ) {
			$license = $this->get_license( $license );

			// @todo IMPLEMENT ACTIVATION
		}

		function create_license( FS_License $fs_license, FS_Install $fs_site, FS_User $fs_user ) {
			// TODO: Implement create_license() method.
		}

		function deactivate_license( FS_License $fs_license, FS_Install $fs_site, FS_User $fs_user ) {
			// TODO: Implement deactivate_license() method.
		}

		function expire_license( FS_License $fs_license, FS_Install $fs_site, FS_User $fs_user ) {
			// TODO: Implement expire_license() method.
		}

		function cancel_license( FS_License $fs_license, FS_Install $fs_site, FS_User $fs_user ) {
			// TODO: Implement cancel_license() method.
		}

		function extend_license( FS_License $fs_license, FS_Install $fs_site ) {
			// TODO: Implement extend_license() method.
		}

		function get_customer( FS_User $fs_user ) {
			// TODO: Implement get_customer() method.
		}

		function create_customer( FS_User $fs_user ) {
			// TODO: Implement create_customer() method.
		}

		function create_payment( FS_Payment $fs_payment, FS_User $fs_user ) {
			// TODO: Implement create_payment() method.
		}

		function refund_payment( FS_Payment $fs_refund, FS_User $fs_user ) {
			// TODO: Implement refund_payment() method.
		}

		function get_subscription( FS_Subscription $fs_subscription ) {
			// TODO: Implement get_subscription() method.
		}

		function create_subscription( FS_Subscription $fs_subscription, FS_User $fs_user, FS_Install $fs_site ) {
			// TODO: Implement create_subscription() method.
		}

		function cancel_subscription( FS_Subscription $fs_subscription, FS_User $fs_user, FS_Install $fs_site ) {
			// TODO: Implement cancel_subscription() method.
		}
	}
