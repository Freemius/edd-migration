<?php
	// This should point to your EDD install.
	define( 'MY__EDD_STORE_URL', 'http://easydigitaldownloads.com' ); // you should use your own CONSTANT name, and be sure to replace it throughout this file

	// The name of your product. This should match the download name in EDD exactly.
	define( 'MY__EDD_ITEM_NAME', 'My Awesome Plugin' ); // you should use your own CONSTANT name, and be sure to replace it throughout this file

	/**
	 * @var \Freemius $fs
	 */
	$fs = my_freemius();

	function my_freemius_migration() {
		/**
		 * @var Freemius $my_fs
		 */
		global $my_fs;

		$license_key = trim( get_option( 'edd_sample_license_key' ) );

		/**
		 * What happens if:
		 *  1. Purchased on site with EDD
		 *  2. Never activated the premium version or license
		 *  3. Installs a premium version that is already running Freemius.
		 *
		 * In that case, we should hook to the license activation and run the migration there.
		 * But this means that we have to keep the EDD license activation field?
		 * Maybe we should somehow hook to Freemius license activation process?
		 */

		if ( empty( $license_key ) ) {
			// No license key, therefore, no migration required.
			return true;
		}

		$install_details = $my_fs->get_opt_in_params();

		// Override is_premium flat because it's a paid license migration.
		$install_details['is_premium']     = true;
		// The plugin is active for sure and not uninstalled.
		$install_details['is_active']      = true;
		$install_details['is_uninstalled'] = false;

		// Clean unnecessary arguments.
		unset( $install_details['return_url'] );
		unset( $install_details['account_url'] );


		// Call the custom API.
		$response = wp_remote_post( MY__EDD_STORE_URL, array_merge( $install_details, array(
			'timeout'   => 15,
			'sslverify' => false,
			'body'      => array_merge( $install_details, array(
				'fs_action'   => 'migrate_license',
				'license_key' => $license_key,
				'item_name'   => urlencode( MY__EDD_ITEM_NAME ),
				'url'         => home_url()
			) )
		) ) );

		// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$error_message = $response->get_error_message();

			$message = ( is_wp_error( $response ) && ! empty( $error_message ) ) ?
				$error_message :
				__( 'An error occurred, please try again.' );

		} else {

		}
	}

	if ( $fs->has_api_connectivity() ) {
		if ( $fs->is_plugin_upgrade_mode() && $fs->is_first_freemius_powered_version() ) {
			if ( $fs->is_activation_mode() ) {
				if ( my_freemius_migration() ) {
					$fs->set_plugin_upgrade_complete();
				}
			}
		}
	} else {
		// What to do if there's no API connectivity? Should we just run the premium logic?
	}

	/**
	 * Add custom action that is triggered on successful license activation.
	 */
	add_action( 'my_edd_license_activation_completed', 'my_freemius_migration' );