<?php
    /**
     * @package     Freemius Migration
     * @copyright   Copyright (c) 2016, Freemius, Inc.
     * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
     * @since       1.0.3
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    if ( class_exists( 'FS_Client_Migration_Abstract_v1' ) ) {
        return;
    }

    abstract class FS_Client_Migration_Abstract_v1 {
        /**
         * @var \Freemius Freemius instance manager.
         */
        protected $_fs;

        /**
         * @var string Store URL.
         */
        protected $_store_url;

        /**
         * @var string Product ID.
         */
        protected $_product_id;

        /**
         * @var string Optional license key override.
         */
        protected $_license_key;

        /**
         * @var string[] Optional children license keys override.
         */
        protected $_children_license_keys;

        /**
         * @var FS_Client_License_Abstract_v1
         */
        protected $_license_accessor;

        /**
         * @var bool
         */
        protected $_is_bundle;

        /**
         * @var bool
         */
        protected $_was_freemius_in_prev_version;

        /**
         * @var string Migration namespace.
         */
        protected $_namespace;

        /**
         * @param string                        $namespace                    Migration namespace (e.g. EDD, WC)
         * @param Freemius                      $freemius
         * @param string                        $store_url                    Store URL.
         * @param string                        $product_id                   The product ID set on the system we're migrating from (not the Freemius product ID).
         * @param FS_Client_License_Abstract_v1 $license_accessor             License accessor.
         * @param bool                          $is_bundle                    Is it a bundle migration or a regular product.
         * @param bool                          $was_freemius_in_prev_version By default, the migration process will only be executed upon activation of the product for the 1st time with Freemius. By modifying this flag to `true`, it will also initiate a migration request even if the user already opted into Freemius. This flag is particularly relevant when the developer already released a Freemius powered version before releasing a version with the migration code.
         * @param bool                          $is_blocking                  Special argument for testing. When false, will
         *                                                                    initiate the migration in the same HTTP request.
         */
        protected function init(
            $namespace,
            Freemius $freemius,
            $store_url,
            $product_id,
            FS_Client_License_Abstract_v1 $license_accessor,
            $is_bundle = false,
            $was_freemius_in_prev_version = false,
            $is_blocking = false
        ) {
            $this->_namespace                    = strtolower( $namespace );
            $this->_fs                           = $freemius;
            $this->_store_url                    = $store_url;
            $this->_product_id                   = $product_id;
            $this->_license_accessor             = $license_accessor;
            $this->_is_bundle                    = $is_bundle;
            $this->_was_freemius_in_prev_version = $was_freemius_in_prev_version;

            /**
             * If no license is set it might be one of the following:
             *  1. User purchased module directly from Freemius.
             *  2. User did purchase from store, but has never activated the license on this site.
             *  3. User got access to the code without ever purchasing.
             *
             * In case it's reason #2 or if the license key is wrong, the migration will not work.
             * Since we do want to support store licenses, hook to Freemius `after_install_failure`
             * event. That way, if a license activation fails, try activating the license on store
             * first, and if works, migrate to Freemius right after.
             */
            $this->_fs->add_filter( 'after_install_failure', array( &$this, 'try_migrate_on_activation' ), 10, 2 );

            if ( $this->should_try_migrate() ) {
                if ( $this->has_any_keys() ) {
                    if ( ! defined( 'DOING_AJAX' ) ) {
                        $this->non_blocking_license_migration( $is_blocking );
                    }
                }
            }
        }

        /**
         * The license migration script.
         *
         * IMPORTANT:
         *  You should use your own function name, and be sure to replace it throughout this file.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.0
         *
         * @param bool $redirect
         *
         * @return bool
         */
        protected function do_license_migration( $redirect = false ) {
            $result = $this->get_site_migration_data_and_licenses();

            $migration_data = $result['data'];
            $all_licenses   = $result['licenses'];

            $transient_key = 'fs_license_migration_' . $this->_product_id . '_' . md5( implode( '', $all_licenses ) );
            $response      = get_transient( $transient_key );

            if ( false === $response ) {
                $response = wp_remote_post(
                    $this->get_migration_endpoint(),
                    array(
                        'timeout'   => 60,
                        'sslverify' => false,
                        'body'      => json_encode( $migration_data ),
                    )
                );

                // Cache result (15-min).
                set_transient( $transient_key, $response, 15 * MINUTE_IN_SECONDS );
            }

            $should_migrate_transient = $this->get_should_migrate_transient_key();

            // make sure the response came back okay
            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
                $error_message = $response->get_error_message();

                return ( is_wp_error( $response ) && ! empty( $error_message ) ) ?
                    $error_message :
                    __( 'An error occurred, please try again.' );

            } else {
                $response = json_decode( wp_remote_retrieve_body( $response ) );

                if ( ! is_object( $response ) ||
                     ! isset( $response->success ) ||
                     true !== $response->success
                ) {
                    if ( isset( $response->error ) ) {
                        switch ( $response->error->code ) {
                            case 'empty_license_key':
                            case 'invalid_license_key':
                            case 'license_expired':
                            case 'license_disabled':
                                set_transient( $should_migrate_transient, 'no', WP_FS__TIME_24_HOURS_IN_SEC * 365 );
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

                // Delete transient on successful migration.
                delete_transient( $transient_key );

                if ( $this->_was_freemius_in_prev_version && $this->_fs->is_registered() ) {
                    if ( $this->_license_accessor->is_network_migration() ) {
                        $this->_fs->delete_network_account_event();
                    } else {
                        $this->_fs->delete_account_event();
                    }
                }

                if ( $this->_license_accessor->is_network_migration() ) {
                    $installs = array();
                    foreach ( $response->data->installs as $install ) {
                        $installs[] = new FS_Site( $install );
                    }

                    $this->_fs->setup_network_account(
                        new FS_User( $response->data->user ),
                        $installs,
                        $redirect
                    );
                } else {
                    $this->_fs->setup_account(
                        new FS_User( $response->data->user ),
                        new FS_Site( $response->data->install ),
                        $redirect
                    );
                }

                // Upon successful migration, store the no-migration flag for 5 years.
                set_transient( $should_migrate_transient, 'no', WP_FS__TIME_24_HOURS_IN_SEC * 365 * 5 );

                return true;
            }
        }

        /**
         * Initiate a non-blocking HTTP POST request to the same URL
         * as the current page, with the addition of "fsm_{namespace}_{product_id}"
         * param in the query string that is set to a unique migration
         * request identifier, making sure only one request will make
         * the migration.
         *
         * @todo     Test 2 threads in parallel and make sure that `add_transient()` works as expected.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.0
         *
         * @return bool Is successfully spawned the migration request.
         */
        protected function spawn_license_migration() {
            #region Make sure only one request handles the migration (prevent race condition)

            // Generate unique md5.
            $migration_uid = md5( rand() . microtime() );

            $loaded_migration_uid = false;

            /**
             * Use `add_transient()` instead of `set_transient()` because
             * we only want that one request will succeed writing this
             * option to the storage.
             */
            $transient_key = "fsm_{$this->_namespace}_{$this->_product_id}";
            if ( $this->add_transient( $transient_key, $migration_uid, MINUTE_IN_SECONDS ) ) {
                $loaded_migration_uid = $this->get_transient( $transient_key );
            }

            if ( $migration_uid !== $loaded_migration_uid ) {
                return false;
            }

            #endregion

            $migration_url = add_query_arg(
                "fsm_{$this->_namespace}_{$this->_product_id}",
                $migration_uid,
                $this->get_current_url()
            );

            // Add cookies to trigger request with same user access permissions.
            $cookies = array();
            foreach ( $_COOKIE as $name => $value ) {
                $cookies[] = new WP_Http_Cookie( array(
                    'name'  => $name,
                    'value' => $value
                ) );
            }

            wp_remote_post(
                $migration_url,
                array(
                    'timeout'   => 0.01,
                    'blocking'  => false,
                    'sslverify' => false,
                    'cookies'   => $cookies,
                )
            );

            return true;
        }

        /**
         * Run non blocking migration if all of the following (AND condition):
         *  1. Has API connectivity to api.freemius.com
         *  2. User isn't yet identified with Freemius.
         *  3. Freemius is in "activation mode".
         *  4. It's a plugin version upgrade.
         *  5. It's the first installation of the context plugin that have Freemius integrated with.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.0
         *
         * @param bool $is_blocking Special argument for testing. When false, will initiate the migration in the same HTTP request.
         *
         * @return string|bool
         */
        protected function non_blocking_license_migration( $is_blocking = false ) {
            if ( ! $this->_fs->has_api_connectivity() ) {
                // No connectivity to Freemius API, it's up to you what to do.
                return 'no_connectivity';
            }

            if ( ! $this->_fs->is_premium() ) {
                // Running the free product version, so don't migrate.
                return 'free_code_version';
            }

            if ( $this->_fs->is_registered() && $this->_fs->has_any_license() ) {
                // User already identified by the API and has a license.
                return 'user_registered_with_license';
            }

            if ( ! $this->_was_freemius_in_prev_version ) {
                if ( $this->_fs->is_registered() ) {
                    // User already identified by the API.
                    return 'user_registered';
                }

                if ( ! $this->_fs->is_activation_mode() ) {
                    // Plugin isn't in Freemius activation mode.
                    return 'not_in_activation';
                }
                if ( ! $this->_fs->is_plugin_upgrade_mode() ) {
                    // Plugin isn't in plugin upgrade mode.
                    return 'not_in_upgrade';
                }

                if ( ! $this->_fs->is_first_freemius_powered_version() ) {
                    // It's not the 1st version of the plugin that runs with Freemius.
                    return 'freemius_installed_before';
                }
            }

            $key = "fsm_{$this->_namespace}_{$this->_product_id}";

            $migration_uid = $this->get_transient( $key );
            $in_migration  = ! empty( $_REQUEST[ $key ] );

            if ( ! $is_blocking && ! $in_migration ) {
                // Initiate license migration in a non-blocking request.
                return $this->spawn_license_migration();
            } else {
                if ( $is_blocking ||
                     ( ! empty( $_REQUEST[ $key ] ) &&
                       $migration_uid === $_REQUEST[ $key ] &&
                       'POST' === $_SERVER['REQUEST_METHOD'] )
                ) {
                    $success = $this->do_license_migration();

                    if ( $success ) {
                        $this->_fs->set_plugin_upgrade_complete();

                        return 'success';
                    }
                }
            }

            return 'failed';
        }

        /**
         * If installation failed due to license activation on Freemius try to
         * activate the license on store first, and if successful, migrate the license
         * with a blocking request.
         *
         * This method will only be triggered upon failed module installation.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.0
         *
         * @param object $response Freemius installation request result.
         * @param array  $args     Freemius installation request arguments.
         *
         * @return object|string
         */
        public function try_migrate_on_activation( $response, $args ) {
            if ( empty( $args['license_key'] ) || 32 !== strlen( $args['license_key'] ) ) {
                // No license key provided (or invalid length), ignore.
                return $response;
            }

            if ( ! $this->_fs->has_api_connectivity() ) {
                // No connectivity to Freemius API, it's up to you what to do.
                return $response;
            }

            if ( ( is_object( $response->error ) && 'invalid_license_key' === $response->error->code ) ||
                 ( is_string( $response->error ) && false !== strpos( strtolower( $response->error ), 'license' ) )
            ) {
                // Set license for migration.
                if ( $this->_is_bundle ) {
                    $this->_children_license_keys = array( $args['license_key'] );
                } else {
                    $this->_license_key = $args['license_key'];
                }

                // Try to migrate the license.
                if ( 'success' === $this->non_blocking_license_migration( true ) ) {
                    /**
                     * If successfully migrated license and got to this point (no redirect),
                     * it means that it's an AJAX installation (opt-in), therefore,
                     * override the response with the after connect URL.
                     */
                    return $this->_fs->get_after_activation_url( 'after_connect_url' );
                }
            }

            return $response;
        }

        #--------------------------------------------------------------------------------
        #region Helper Methods
        #--------------------------------------------------------------------------------

        /**
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.3
         *
         * @return string
         */
        protected function get_migration_endpoint() {
            return sprintf(
                '%s/fs-api/%s/migrate-license.json',
                $this->_store_url,
                $this->_namespace
            );
        }

        /**
         * Prepare data for migration.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.1.0
         *
         * @return array
         */
        private function get_site_migration_data_and_licenses() {
            $is_network_migration = $this->_license_accessor->is_network_migration();

            $this->wp_cookie_constants();

            $migration_data = $this->_fs->get_opt_in_params( array(
                // Include the migrating product ID.
                'module_id'      => $this->_product_id,
                // Override is_premium flat because it's a paid license migration.
                'is_premium'     => true,
                // The plugin is active for sure and not uninstalled.
                'is_active'      => true,
                'is_uninstalled' => false,
            ), ( $is_network_migration ? true : null ) );

            // Clean unnecessary arguments.
            unset( $migration_data['return_url'] );
            unset( $migration_data['account_url'] );

            $all_licenses = array();

            if ( false === $is_network_migration || $this->_license_accessor->are_licenses_network_identical() ) {
                if ( $this->_is_bundle ) {
                    $migration_data['children_license_keys'] = $this->get_children_licenses();

                    $all_licenses = $migration_data['children_license_keys'];
                } else {
                    $migration_data['license_key'] = $this->get_license();

                    $all_licenses[] = $migration_data['license_key'];
                }
            } else {
                $blog_ids = $this->get_blog_ids();

                $keys_by_blog_id = array();

                foreach ( $blog_ids as $blog_id ) {
                    $site_keys = $this->_is_bundle ?
                        $this->get_children_licenses( $blog_id ) :
                        $this->get_license( $blog_id );

                    if ( empty( $site_keys ) ) {
                        continue;
                    }

                    $keys_by_blog_id[ $blog_id ] = $site_keys;
                }

                foreach ( $migration_data['sites'] as $index => &$site ) {
                    if ( ! isset( $keys_by_blog_id[ $site['blog_id'] ] ) ) {
                        unset( $migration_data['sites'][ $index ] );
                    }

                    $site_keys = $keys_by_blog_id[ $site['blog_id'] ];

                    if ( is_array( $site_keys ) ) {
                        $site['children_license_keys'] = $site_keys;

                        $all_licenses = array_merge( $all_licenses, $site_keys );
                    } else {
                        $site['license_key'] = $site_keys;

                        $all_licenses[] = $site_keys;
                    }
                }

                // Reorder indexes.
                $migration_data = array_values( $migration_data );
            }

            return array(
                'data'     => $migration_data,
                'licenses' => $all_licenses,
            );
        }

        /**
         * Get a collection of all the license keys for the migration.
         * We use this to generate a unique transient name to store a helper
         * value to avoid trying a migration with a keys combination that already
         * failed before.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.2.0
         *
         * @return string[]
         */
        private function get_all_migration_licenses() {
            $is_network_migration = $this->_license_accessor->is_network_migration() ? true : null;

            $all_licenses = array();

            if ( false === $is_network_migration || $this->_license_accessor->are_licenses_network_identical() ) {
                if ( $this->_is_bundle ) {
                    $all_licenses = $this->get_children_licenses();
                } else {
                    $all_licenses[] = $this->get_license();
                }
            } else {
                $blog_ids = $this->get_blog_ids();

                foreach ( $blog_ids as $blog_id ) {
                    $site_keys = $this->_is_bundle ?
                        $this->get_children_licenses( $blog_id ) :
                        $this->get_license( $blog_id );

                    if ( empty( $site_keys ) ) {
                        continue;
                    }

                    if ( is_array( $site_keys ) ) {
                        $all_licenses = array_merge( $all_licenses, $site_keys );
                    } else {
                        $all_licenses[] = $site_keys;
                    }
                }
            }

            return $all_licenses;
        }

        /**
         * Define cookie constants which are required by Freemius::get_opt_in_params() since
         * it uses wp_get_current_user() which needs the cookie constants set. When a plugin
         * is network activated the cookie constants are only configured after the network
         * plugins activation, therefore, if we don't define those constants WP will throw
         * PHP warnings/notices.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.1.1
         */
        private function wp_cookie_constants() {
            if ( defined( 'LOGGED_IN_COOKIE' ) &&
                 ( defined( 'AUTH_COOKIE' ) || defined( 'SECURE_AUTH_COOKIE' ) )
            ) {
                return;
            }

            /**
             * Used to guarantee unique hash cookies
             *
             * @since 1.5.0
             */
            if ( ! defined( 'COOKIEHASH' ) ) {
                $siteurl = get_site_option( 'siteurl' );
                if ( $siteurl ) {
                    define( 'COOKIEHASH', md5( $siteurl ) );
                } else {
                    define( 'COOKIEHASH', '' );
                }
            }

            if ( ! defined( 'LOGGED_IN_COOKIE' ) ) {
                define( 'LOGGED_IN_COOKIE', 'wordpress_logged_in_' . COOKIEHASH );
            }

            /**
             * @since 2.5.0
             */
            if ( ! defined( 'AUTH_COOKIE' ) ) {
                define( 'AUTH_COOKIE', 'wordpress_' . COOKIEHASH );
            }

            /**
             * @since 2.6.0
             */
            if ( ! defined( 'SECURE_AUTH_COOKIE' ) ) {
                define( 'SECURE_AUTH_COOKIE', 'wordpress_sec_' . COOKIEHASH );
            }
        }

        /**
         * Get current request full URL.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.3
         *
         * @return string
         */
        private function get_current_url() {
            $host = $_SERVER['HTTP_HOST'];
            $uri  = $_SERVER['REQUEST_URI'];
            $port = $_SERVER['SERVER_PORT'];
            $port = ( ( ! WP_FS__IS_HTTPS && $port == '80' ) || ( WP_FS__IS_HTTPS && $port == '443' ) ) ? '' : ':' . $port;

            return ( WP_FS__IS_HTTPS ? 'https' : 'http' ) . "://{$host}{$port}{$uri}";
        }

        /**
         * Checks if there are any keys set at all.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.1.0
         */
        private function has_any_keys() {
            if ( ! $this->_license_accessor->is_network_migration() ) {
                return $this->_is_bundle ?
                    $this->_license_accessor->site_has_children_keys() :
                    $this->_license_accessor->site_has_key();
            }

            $blog_ids = $this->get_blog_ids();

            foreach ( $blog_ids as $blog_id ) {
                $site_has_keys = $this->_is_bundle ?
                    $this->_license_accessor->site_has_children_keys( $blog_id ) :
                    $this->_license_accessor->site_has_key( $blog_id );

                if ( $site_has_keys ) {
                    return true;
                }
            }

            return true;
        }

        /**
         * @author   Vova Feldman (@svovaf)
         * @since    1.1.0
         *
         * @param int|null $blog_id
         *
         * @return string
         */
        private function get_license( $blog_id = null ) {
            return empty( $this->_license_key ) ?
                $this->_license_accessor->get( $blog_id ) :
                $this->_license_key;
        }

        /**
         * @author   Vova Feldman (@svovaf)
         * @since    1.1.0
         *
         * @param int|null $blog_id
         *
         * @return string[]
         */
        private function get_children_licenses( $blog_id = null ) {
            return empty( $this->_children_license_keys ) ?
                $this->_license_accessor->get_children( $blog_id ) :
                $this->_children_license_keys;
        }

        /**
         * @var string
         */
        private $_shouldMigrateTransientKey;

        /**
         * @author   Vova Feldman (@svovaf)
         * @since    1.1.2
         *
         * @return string
         *
         * @uses     get_all_migration_licenses()
         */
        private function get_should_migrate_transient_key() {
            if ( ! isset( $this->_shouldMigrateTransientKey ) ) {
                $keys = $this->get_all_migration_licenses();

                $this->_shouldMigrateTransientKey = 'fs_should_migrate_' . md5( $this->_store_url . ':' . $this->_product_id . implode( ':', $keys ) );
            }

            return $this->_shouldMigrateTransientKey;
        }

        /**
         * Check if should try to migrate or not.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.1.2
         *
         * @return bool
         */
        private function should_try_migrate() {
            $key = $this->get_should_migrate_transient_key();

            $should_migrate = get_transient( $key );

            return ( ! is_string( $should_migrate ) || 'no' !== $should_migrate );
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Database Transient
        #--------------------------------------------------------------------------------

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
        private function get_transient( $transient ) {
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
        private function add_transient( $transient, $value, $expiration = 0 ) {
            $transient_option  = '_fs_transient_' . $transient;
            $transient_timeout = '_fs_transient_timeout_' . $transient;

            $current_value = $this->get_transient( $transient );

            if ( false === $current_value ) {
                $autoload = 'yes';
                if ( $expiration ) {
                    $autoload = 'no';
                    add_option( $transient_timeout, time() + $expiration, '', 'no' );
                }

                return add_option( $transient_option, $value, '', $autoload );
            } else {
                // If expiration is requested, but the transient has no timeout option,
                // delete, then re-create the timeout.
                if ( $expiration ) {
                    if ( false === get_option( $transient_timeout ) ) {
                        add_option( $transient_timeout, time() + $expiration, '', 'no' );
                    }
                }
            }

            return false;
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Multisite Network
        #--------------------------------------------------------------------------------

        /**
         * @author   Vova Feldman (@svovaf)
         * @since    1.1.0
         *
         * @return int[]
         */
        private function get_blog_ids() {
            global $wpdb;

            return $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
        }

        #endregion
    }