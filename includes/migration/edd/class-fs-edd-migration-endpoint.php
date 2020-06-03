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

    require_once WP_FSM__DIR_MIGRATION . '/class-fs-migration-endpoint-abstract.php';

    /**
     * Class FS_EDD_Migration_Endpoint
     */
    class FS_EDD_Migration_Endpoint extends FS_Migration_Endpoint_Abstract {

        /**
         * @var FS_EDD_Migration_Endpoint
         */
        private static $_instance;

        public static function instance() {
            if ( ! isset( self::$_instance ) ) {
                self::$_instance = new FS_EDD_Migration_Endpoint();
            }

            return self::$_instance;
        }

        private function __construct() {
            $this->init( WP_FS__NAMESPACE_EDD );

            if ( false && ! defined( 'DOING_AJAX' ) ) {
                $this->test_multisite_network_bundle_migration();
            }
        }

        #--------------------------------------------------------------------------------
        #region Testing
        #--------------------------------------------------------------------------------

        /**
         * Migrate license by ID.
         *
         * Note: This method is only for testing reasons.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.0
         *
         * @param int $license_id
         *
         * @throws Exception
         */
        function migrate_license_by_id( $license_id ) {
            require_once WP_FSM__DIR_MIGRATION . '/edd/class-fs-edd-migration.php';

            $migration = FS_EDD_Migration::instance( $license_id );
            $migration->set_api( $this->get_api() );
            $migration->do_migrate_license();
        }

        /**
         * Test full install's license migration.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.0
         */
        private function test_full_migration() {
            $url         = 'http://test9.freemius.com';
            $download_id = 25;
            $license_key = '74062bc8b9cc256823f8f08d0f8feedf';

            $params = array(
                'license_key'      => $license_key,
                'module_id'        => $download_id,
//                'url'              => $url,
                'site_url'         => $url,
                'plugin_version'   => '1.2.1',
                'site_uid'         => $this->get_anonymous_id( $url ),
                'site_name'        => 'Freemius Test',
                'platform_version' => get_bloginfo( 'version' ),
                'php_version'      => phpversion(),
                'language'         => get_bloginfo( 'language' ),
                'charset'          => get_bloginfo( 'charset' ),
                'is_premium'       => true,
                'is_active'        => true,
                'is_uninstalled'   => false,
            );

            $this->maybe_process_api_request( $params );
        }

        /**
         * Test full install's license migration.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.0
         */
        private function test_bundle_migration() {
            $url                   = 'http://fswp';
            $download_id           = 2116;
            $children_license_keys = array(
                'd0fa47797dc03c896bf66058c599d9cd',
                '356c232c5988fdd03f4f3eff403012f9',
            );

            $params = array(
                'user_firstname'               => 'vova',
                'user_lastname'                => '',
                'user_nickname'                => 'vova',
                'user_email'                   => 'fgt1@freemius.com',
                'user_ip'                      => '::1',
                'plugin_slug'                  => 'wp-security-audit-log',
                'plugin_id'                    => '94',
                'plugin_public_key'            => 'pk_d602740d3088272d75906045af9fa',
                'plugin_version'               => '2.6.9.1',
                'site_uid'                     => 'b7fdc0a5fd94548a2c00f914f8254313',
                'site_url'                     => $url,
                'site_name'                    => 'WP Freemius Testing',
                'platform_version'             => '4.8.2',
                'sdk_version'                  => '1.2.3',
                'programming_language_version' => '5.5.38',
                'language'                     => 'en-US',
                'charset'                      => 'UTF-8',
                'is_premium'                   => true,
                'is_active'                    => true,
                'is_uninstalled'               => false,
                'ts'                           => 1513776711,
                'salt'                         => '1f20b47846f3cd7311cdabdd99406959',
                'secure'                       => '97c424911d2c0bc89a794a9f4a0d4c1b',
                'module_id'                    => $download_id,
//                'url'                          => $url,
                'children_license_keys'        => $children_license_keys,
            );

            $this->maybe_process_api_request( $params );
        }

        /**
         * Test full install's license migration.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.0
         */
        private function test_multisite_network_bundle_migration() {
            $download_id           = 2116;
            $children_license_keys = array(
                'd0fa47797dc03c896bf66058c599d9cd',
                '356c232c5988fdd03f4f3eff403012f9',
            );

            $sites = array();
            $languages = array( 'en-US', 'fr-FR', 'ru-RU' );
            for ( $i = 0; $i < 5; $i ++ ) {
                $sites[] = array(
                    'uid'      => "b7fdc0a5fd94548a2c00f914f825431{$i}",
                    'url'      => "http://test{$i}.com",
                    'name'     => "MS Network Migration {$i}",
                    'charset'  => 'UTF-8',
                    'language' => $languages[array_rand( $languages )],
                );
            }

            $params = array(
                'user_firstname'               => 'vova',
                'user_lastname'                => '',
                'user_nickname'                => 'vova',
                'user_email'                   => 'vova@freemius.com',
                'user_ip'                      => '::1',
                'plugin_slug'                  => 'wp-security-audit-log',
                'plugin_id'                    => '94',
                'plugin_public_key'            => 'pk_d602740d3088272d75906045af9fa',
                'plugin_version'               => '2.6.9.1',
                'platform_version'             => '4.8.2',
                'sdk_version'                  => '2.0.1',
                'programming_language_version' => '5.5.38',
                'is_premium'                   => true,
                'is_active'                    => true,
                'is_uninstalled'               => false,
                'ts'                           => 1513776711,
                'salt'                         => '1f20b47846f3cd7311cdabdd99406959',
                'secure'                       => '97c424911d2c0bc89a794a9f4a0d4c1b',
                'module_id'                    => $download_id,
                'children_license_keys'        => $children_license_keys,
                'sites'                        => $sites,
            );

            $this->maybe_process_api_request( $params );
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Local Data Getters
        #--------------------------------------------------------------------------------

        /**
         * Map EDD download into FS object.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.0
         *
         * @param EDD_Download $local_module
         *
         * @return FS_Plugin
         */
        protected function local_to_remote_module( $local_module ) {
            $module        = new FS_Plugin();
            $module->id    = $local_module->get_ID();
            $module->slug  = $local_module->post_name;
            $module->title = $local_module->get_name();

            return $module;
        }

        /**
         * Get all EDD downloads.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return array
         */
        protected function get_all_local_modules() {
            /**
             * @var WP_Post[] $downloads
             */
            $downloads = get_posts( array(
                'post_type'      => 'download',
                'posts_per_page' => - 1,
            ) );

            for ( $i = 0, $len = count( $downloads ); $i < $len; $i ++ ) {
                $downloads[ $i ] = new EDD_Download( $downloads[ $i ]->ID );
            }

            return $downloads;
        }

        /**
         * Load EDD download by ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param string $local_module_id
         *
         * @return false|\EDD_Download
         */
        protected function get_local_module_by_id( $local_module_id ) {
            $download_post = WP_Post::get_instance( $local_module_id );

            return ( empty( $download_post ) || 'download' !== $download_post->post_type ) ?
                false :
                new EDD_Download( $local_module_id );
        }

        /**
         * EDD Download slug (post_name).
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param mixed $local_module
         *
         * @return string
         */
        protected function get_local_module_slug( $local_module ) {
            return $local_module->post_name;
        }

        /**
         * EDD paid plan ID.
         *
         * @param string $local_module_id
         *
         * @return string
         */
        protected function get_local_paid_plan_id( $local_module_id ) {
            return $local_module_id . ':' . ( edd_has_variable_prices( $local_module_id ) ? '1' : '0' );
        }

        /**
         * Return the instance of the EDD download migration manager.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param mixed     $local_module
         * @param FS_Plugin $module
         *
         * @return \FS_EDD_Download_Migration
         */
        protected function get_local_module_migration_manager( $local_module, FS_Plugin $module = null ) {
            require_once WP_FSM__DIR_MIGRATION . '/edd/class-fs-edd-download-migration.php';

            if ( is_null( $module ) ) {
                $module = $this->get_module_by_slug( $this->get_local_module_slug( $local_module ) );
            }

            return new FS_EDD_Download_Migration(
                $this->get_developer(),
                $module,
                $local_module
            );
        }

        #endregion

        /**
         * Migrate install's license and return FS account's data (user + install).
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return array
         *
         * @throws FS_Endpoint_Exception
         */
        protected function migrate_license_and_installs() {
            require_once WP_FSM__DIR_MIGRATION . '/edd/class-fs-edd-migration.php';

            $license_key = $this->get_param( 'license_key' );
            $license_id  = edd_software_licensing()->get_license_by_key( $license_key );

            $migration = FS_EDD_Migration::instance( $license_id );
            $migration->set_api( $this->get_api() );

            if ( ! empty( $this->get_param( 'sites' ) ) ) {
                // If multisite migration, create an unlimited license.
                add_filter( 'edd_get_license_limit', '__return_zero' );
            }

            // Migrate customer, purchase/subscription, billing and license.
            $customer = $migration->do_migrate_license();

            $parent_plugin_id = $this->get_param( 'parent_plugin_id' );

            $is_type_bundle_or_all_access = $migration->is_local_type_bundle_or_all_access();

            if ( is_numeric( $parent_plugin_id ) || $is_type_bundle_or_all_access ) {
                if ( ! is_object( $customer ) ) {
                    $result = $migration->fetch_user_from_freemius_by_id( $customer );

                    if ( ! is_object( $result ) ) {
                        throw new FS_Endpoint_Exception( "Failed to fetch user ({$customer}) from Freemius." );
                    }

                    $customer = $result;
                }

                // When migrating a bundle's or an addon's license, do not create the installs, those will be created on the client's side by simply activating the license after it was already migrated.
                return array(
                    'user'        => $customer,
                    'type'        => $is_type_bundle_or_all_access ? 'bundle' : 'addon',
                    /**
                     * Return the license key for cases when the migration was initiated by a product license key that is associated with a bundle or an add-on.
                     *
                     * @author Vova Feldman
                     */
                    'license_key' => $license_key,
                );
            }

            if ( ! $this->is_multisite_migration() ) {
                // Migrate plugin installation.
                return $migration->do_migrate_install( $this->_request_data, $customer );
            }

            // Get sites.
            $sites = $this->get_param('sites');

            unset($this->_request_data['sites']);

            foreach ( $sites as &$site ) {
                $site['site_uid']  = $site['uid'];
                $site['site_url']  = $site['url'];
                $site['site_name'] = $site['title'];

                unset( $site['uid'] );
                unset( $site['url'] );
                unset( $site['title'] );

                $site = array_merge( $site, $this->_request_data );
            }

            return $migration->do_migrate_installs( $sites, $customer );
        }

        #--------------------------------------------------------------------------------
        #region API Request Params Validation
        #--------------------------------------------------------------------------------

        /**
         * Validate EDD download license parameters per site.
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @param int      $download_id
         * @param string   $url
         * @param string   $license_key
         * @param string[] $children_license_keys
         *
         * @throws \FS_Endpoint_Exception
         */
        protected function validate_site_params(
            &$download_id,
            $url,
            &$license_key,
            $children_license_keys
        ) {
            $url = FS_EDD_Migration::strip_path_from_url( $url );

            if ( ! empty( $license_key ) ) {
                $license = edd_software_licensing()->get_license( $license_key, true );

                if (is_object( $license) &&
                    !empty( $license->parent )
                ) {
                    /**
                     * If the license key is associated with a bundle license, migrate the bundle license instead of the individual product license.
                     *
                     * @author Vova Feldman
                     *
                     * @var EDD_SL_License $parent_license
                     */
                    $parent_license = edd_software_licensing()->get_license( $license->parent );

                    if ( is_object( $parent_license ) &&
                         is_object( $parent_license->download ) &&
                         $parent_license->download->is_bundled_download()
                    ) {
                        // Current's license's parent is corrupted.
                        $download_id = $parent_license->download->ID;
                        $license_key = $parent_license->key;
                    }
                }

                // Get EDD license state.
                $edd_license_state = edd_software_licensing()->check_license( array(
                    'item_id'   => $download_id,
                    'item_name' => '',
                    'key'       => $license_key,
                    'url'       => $url,
                ) );
            } else {
                if ( ! is_array( $children_license_keys ) || empty( $children_license_keys ) ) {
                    throw new FS_Endpoint_Exception( "Missing license key.", 'empty_license_key', 400 );
                }

                $edd_license_state = '';

                foreach ( $children_license_keys as $child_license_key ) {
                    /**
                     * @var EDD_SL_License $license_information
                     */
                    $child_license = edd_software_licensing()->get_license( $child_license_key, true );

                    if ( ! is_object( $child_license ) || ! is_object( $child_license->download ) ) {
                        throw new FS_Endpoint_Exception( "Invalid license key ({$child_license_key}).", 'invalid_license_key', 400 );
                    }

                    if ( empty( $child_license->parent ) ) {
                        // License doesn't have a parent license, so just skip it.
                        $edd_license_state = 'no_parent';
                        continue;
                    }

                    /**
                     * Load bundle's license.
                     *
                     * @var EDD_SL_License $parent_license
                     */
                    $parent_license = edd_software_licensing()->get_license( $child_license->parent );

                    if ( ! is_object( $parent_license ) || ! is_object( $parent_license->download ) ) {
                        // Current's license's parent is corrupted.
                        continue;
                    }

                    if ( ! $parent_license->download->is_bundled_download() ) {
                        // Parent license is not associated with a bundle, so skip.
                        continue;
                    }

                    if ( $download_id == $parent_license->download->ID ) {
                        // Found a match.
                        $edd_license_state = edd_software_licensing()->check_license( array(
                            'item_id'   => $child_license->download->ID,
                            'item_name' => '',
                            'key'       => $child_license_key,
                            'url'       => $url,
                        ) );

                        // If license is valid then override the parent license key.
                        if ( in_array( $edd_license_state, array(
                            'valid',
                            'inactive',
                            'site_inactive',
                        ) ) ) {
                            $license_key = $parent_license->key;
                            break;
                        }
                    }
                }

                if ('no_parent' === $edd_license_state){
                    throw new FS_Endpoint_Exception( "Add-on license key(s) are not associated with a bundle's license key.", 'invalid_license_key', 400 );
                }
            }

            if ( empty( $license_key ) ) {
                throw new FS_Endpoint_Exception( "Invalid license key ({$license_key}).", 'invalid_license_key', 400 );
            }

            switch ( $edd_license_state ) {
                case 'invalid':
                    // Invalid license key.
                    throw new FS_Endpoint_Exception( "Invalid license key ({$license_key}).", 'invalid_license_key', 400 );
                case 'invalid_item_id':
                    // Invalid download ID.
                    throw new FS_Endpoint_Exception( "Invalid download ID ({$download_id}).", 'invalid_download_id', 400 );
                case 'expired':
                case 'disabled':
                    /**
                     * License expired/disabled, hence, the new version of the product with the
                     * migration script shouldn't be accessible to the customer.
                     */
                    throw new FS_Endpoint_Exception( "License {$edd_license_state}.", "license_{$edd_license_state}", 400 );
                case 'inactive':
                    /**
                     * License not yet activated.
                     *
                     * This use-case should not happen since if the client triggered a migration
                     * request with a valid license key, it means that the license was activated
                     * at least once. Hence, 'inactive' isn't possible.
                     */
                case 'site_inactive':
                    /**
                     * Migrate license & site.
                     *
                     * Based on the EDD SL logic this result is trigger when it's a production
                     * site (not localhost), and the site license wasn't activated.
                     *
                     * It can happen for example when the user trying to activate a license
                     * that is already fully utilized.
                     *
                     * @todo what to do in that case?
                     */
                case 'valid':
                    /**
                     * Migrate license & site.
                     *
                     * License is valid and activated for the context site.
                     */
                    break;
                case 'item_name_mismatch':
                    /**
                     * This use case should never happen since we check the license state
                     * based on the EDD download ID, not the name.
                     */
                    break;
                default:
                    // Unexpected license state. This case should never happen.
                    throw new FS_Endpoint_Exception( 'Unexpected EDD download license state.' );
                    break;
            }
        }

        /**
         * Validate EDD download license parameters.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @throws FS_Endpoint_Exception
         */
        protected function validate_params() {
            // Require download ID.
            $this->require_unsigned_int( 'module_id' );

            $download_id = $this->get_param( 'module_id' );
            $license_key = $this->get_param( 'license_key' );
            $children_license_keys = $this->get_param( 'children_license_keys' );

            // Before checking license with EDD, make sure module is synced.
            if ( false === $this->get_remote_module_id( $download_id ) ) {
                throw new FS_Endpoint_Exception( "Invalid download ID ({$download_id}).", 'invalid_download_id', 400 );
            }

            require_once WP_FSM__DIR_MIGRATION . '/edd/class-fs-edd-migration.php';

            if ( ! $this->is_multisite_migration() ) {
                $this->validate_site_params(
                    $download_id,
                    $this->get_param( 'site_url' ),
                    $license_key,
                    $children_license_keys
                );

                $this->set_param( 'license_key', $license_key );
            } else {
                $sites = $this->get_param( 'sites', array() );

                // Multi-site network migration.
                if ( ! is_array( $sites ) ) {
                    throw new FS_Endpoint_Exception( "sites cannot be empty when migrating a multi-site network license.", "sites_empty" );
                }

                foreach ( $sites as $id => $site ) {
                    $site_license_key = !empty($site['license_key']) ? $site['license_key'] : '';
                    $site_children_license_keys = !empty($site['children_license_keys']) ? $site['children_license_keys'] : '';

                    $site_license_key = empty($site_license_key) ? $license_key : $site_license_key;
                    $site_children_license_keys = empty($site_children_license_keys) ? $children_license_keys : $site_children_license_keys;

                    $this->validate_site_params(
                        $download_id,
                        $site['url'],
                        $site_license_key,
                        $site_children_license_keys
                    );

                    /**
                     * @todo This logic currently only supports setting up a common license for all migrating sites. While the parsing above will correctly validate per sub-site licenses (if those are set), the actual migration logic currently only supports single license per migration. The best solution would be splitting the migration and calling the actual migration logic several times, once per license.
                     */
                    $this->set_param( 'license_key', $site_license_key );
                }
            }
        }

        #endregion
    }

    /**
     * The main function responsible for returning the FS_EDD_Migration_Endpoint
     * instance.
     *
     * Example: <?php $fs_migration_manager = fs_edd_migration_manager(); ?>
     *
     * @author Vova Feldman
     * @since  1.0.0
     *
     * @return FS_EDD_Migration_Endpoint The one true Easy_Digital_Downloads Instance
     */
    function fs_edd_migration_manager() {
        return FS_EDD_Migration_Endpoint::instance();
    }

    // Get Freemius EDD Migration running.
    fs_edd_migration_manager();