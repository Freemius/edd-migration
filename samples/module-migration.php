<?php
	if ( ! defined( 'MY__EDD_STORE_URL' ) ) {
		// This should point to your EDD install.
		define( 'MY__EDD_STORE_URL', 'https://your-edd-store.com' ); // you should use your own CONSTANT name, and be sure to replace it throughout this file
	}

	// The EDD download ID of your product.
	define( 'MY__EDD_DOWNLOAD_ID', '12345' ); // you should use your own CONSTANT name, and be sure to replace it throughout this file

	/**
	 * The license migration script.
	 *
	 * IMPORTANT:
	 *  You should use your own function name, and be sure to replace it throughout this file.
	 *
	 * @param int    $edd_download_id
	 * @param string $edd_license_key
	 *
	 * @param        $edd_store_url
	 *
	 * @return bool
	 */
	function do_my_freemius_license_migration(
		$edd_download_id,
		$edd_license_key,
		$edd_store_url
	) {
		/**
		 * @var \Freemius $fs
		 */
		$fs = my_freemius();

		$install_details = $fs->get_opt_in_params();

		// Override is_premium flat because it's a paid license migration.
		$install_details['is_premium'] = true;
		// The plugin is active for sure and not uninstalled.
		$install_details['is_active']      = true;
		$install_details['is_uninstalled'] = false;

		// Clean unnecessary arguments.
		unset( $install_details['return_url'] );
		unset( $install_details['account_url'] );


		// Call the custom license and account migration endpoint.
		$response = get_transient( 'fs_license_migration_' . $edd_download_id );

		if ( false === $response ) {
			$response = wp_remote_post(
				$edd_store_url . '/fs-api/edd/migrate-license.json',
				array_merge( $install_details, array(
					'timeout'   => 15,
					'sslverify' => false,
					'body'      => array_merge( $install_details, array(
						'module_id'   => $edd_download_id,
						'license_key' => $edd_license_key,
						'url'         => home_url()
					) )
				) )
			);

			// Cache result (5-min).
			set_transient( 'fs_license_migration_' . $edd_download_id, $response, 5 * MINUTE_IN_SECONDS );
		}

		// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$error_message = $response->get_error_message();

			$message = ( is_wp_error( $response ) && ! empty( $error_message ) ) ?
				$error_message :
				__( 'An error occurred, please try again.' );

		} else {
			if ( ! is_object( $response ) ||
			     isset( $response->success ) ||
			     true !== $response->success
			) {
				if ( isset( $response->error ) ) {
					switch ( $response->error->code ) {
						case 'invalid_license_key':
							// Invalid license key.
							break;
						case 'invalid_download_id':
							// Invalid download ID.
							break;
						default:
							// Unexpected error.
							break;
					}
				} else {
					// Unexpected error.
				}

				// Failed to pull account information.
				return false;
			}

			$fs->setup_account(
				new FS_User( $response->data->user ),
				new FS_Site( $response->data->install ),
				false
			);

			return true;
		}
	}


	function spawn_my_freemius_license_migration( $edd_download_id ) {
		global $wp;

		#region Make sure only one request handles the migration (prevent race condition)

		// Generate unique md5.
		$unique_migration_id = md5( rand() . microtime() );

		$loaded_unique_migration_id = false;

		/**
		 * Use `fs_add_transient()` instead of `set_transient()` because
		 * we only want that one request will succeed writing this
		 * option to the storage.
		 */
		if ( fs_add_transient( 'fsm_edd_' . $edd_download_id, $unique_migration_id ) ) {
			$loaded_unique_migration_id = fs_get_transient( 'fsm_edd_' . $edd_download_id );
		}

		if ( $unique_migration_id !== $loaded_unique_migration_id ) {
			return;
		}

		#endregion

		$current_url   = add_query_arg( $wp->query_string, '', home_url( $wp->request ) );
		$migration_url = add_query_arg(
			'fsm_edd_' . $edd_download_id,
			$unique_migration_id,
			$current_url
		);

		wp_remote_post(
			$migration_url,
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => false,
			)
		);
	}

	function my_maybe_migrate_license(
		$edd_download_id,
		$edd_license_key,
		$edd_store_url
	) {
		/**
		 * @var \Freemius $fs
		 */
		$fs = my_freemius();

		if ( ! $fs->has_api_connectivity() ) {
			// No connectivity to Freemius API, it's up to you what to do.
			return;
		}

		if ( $fs->is_registered() ) {
			// User already identified by the API.
			return;
		}

		if ( ! $fs->is_activation_mode() ||
		     ! $fs->is_plugin_upgrade_mode()
		) {
			// Plugin isn't in Freemius activation mode and not in plugin upgrade mode.
			return;
		}

		if ( ! $fs->is_first_freemius_powered_version() ) {
			// It's not the 1st version of the plugin that runs with Freemius.
			return;
		}

		$uid = fs_get_transient( 'fsm_edd_' . $edd_download_id );

		$in_migration = ( false !== $uid );

		if ( ! $in_migration ) {
			// Initiate license migration in a non-blocking request.
			spawn_my_freemius_license_migration( $edd_download_id );
		} else {
			if ( $uid === get_query_var( 'fsm_edd_' . $edd_download_id, false ) &&
			     'POST' === $_SERVER['REQUEST_METHOD']
			) {
				if ( do_my_freemius_license_migration( $edd_download_id, $edd_license_key, $edd_store_url ) ) {
					$fs->set_plugin_upgrade_complete();
				}
			}
		}
	}

	#region Database Transient

	if ( ! function_exists( 'fs_get_transient' ) ) {
		/**
		 * Very similar to the WP transient mechanism.
		 *
		 * @author   Vova Feldman (@svovaf)
		 * @since    1.0.0
		 *
		 * @param string $transient
		 *
		 * @return mixed
		 */
		function fs_get_transient( $transient ) {
			$transient_option  = '_fs_transient_' . $transient;
			$transient_timeout = '_fs_transient_timeout_' . $transient;

			$timeout = get_option( $transient_timeout );

			if ( false !== $timeout && $timeout < time() ) {
				delete_option( $transient_option );
				delete_option( $transient_timeout );
				$value = false;
			} else {
				$value = get_option( $transient_option );
			}

			return $value;
		}

		/**
		 * Not like `set_transient()`, this function will only ADD
		 * a transient if it's not yet exist.
		 *
		 * @author   Vova Feldman (@svovaf)
		 * @since    1.0.0
		 *
		 * @param string $transient
		 * @param mixed  $value
		 * @param int    $expiration
		 *
		 * @return bool TRUE if successfully added a transient.
		 */
		function fs_add_transient( $transient, $value, $expiration = 0 ) {
			$transient_option  = '_fs_transient_' . $transient;
			$transient_timeout = '_fs_transient_timeout_' . $transient;

			if ( false === get_option( $transient_option ) ) {
				$autoload = 'yes';
				if ( $expiration ) {
					$autoload = 'no';
					add_option( $transient_timeout, time() + $expiration, '', 'no' );
				}

				return add_option( $transient_option, $value, '', $autoload );
			}

			return false;
		}
	}

	#endregion

	if ( ! defined( 'DOING_AJAX' ) && ! defined( 'DOING_CRON' ) ) {
		// Pull license key from storage.
		$license_key = trim( get_option( 'edd_sample_license_key' ) );

		if ( empty( $license_key ) ) {
			// No license key, therefore, no migration required.
		} else {
			my_maybe_migrate_license(
				MY__EDD_DOWNLOAD_ID,
				$license_key,
				MY__EDD_STORE_URL
			);
		}
	}