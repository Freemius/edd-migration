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
                $this->test_full_migration();
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
            require_once WP_FSM__DIR_MIGRATION . '/class-fs-migration-abstract.php';
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
                'url'              => $url,
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
        protected function migrate_install_license() {
            require_once WP_FSM__DIR_MIGRATION . '/class-fs-migration-abstract.php';
            require_once WP_FSM__DIR_MIGRATION . '/edd/class-fs-edd-migration.php';

            $license_id = edd_software_licensing()->get_license_by_key( $this->get_param( 'license_key' ) );

            $migration = FS_EDD_Migration::instance( $license_id );
            $migration->set_api( $this->get_api() );

            // Migrate customer, purchase/subscription, billing and license.
            $customer = $migration->do_migrate_license();

            // Migrate plugin installation.
            return $migration->do_migrate_install( $this->_request_data, $customer );
        }

        #--------------------------------------------------------------------------------
        #region API Request Params Validation
        #--------------------------------------------------------------------------------

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
            $url         = $this->get_param( 'url' );

            // Before checking license with EDD, make sure module is synced.
            if ( false === $this->get_remote_module_id( $download_id ) ) {
                throw new FS_Endpoint_Exception( "Invalid download ID ({$download_id}).", 'invalid_download_id', 400 );
            }

            // Get EDD license state.
            $edd_license_state = edd_software_licensing()->check_license( array(
                'item_id'   => $download_id,
                'item_name' => '',
                'key'       => $license_key,
                'url'       => $url,
            ) );

            switch ( $edd_license_state ) {
                case 'invalid':
                    // Invalid license key.
                    throw new FS_Endpoint_Exception( "Invalid license key ({$license_key}).", 'invalid_license_key',
                        400 );
                case 'invalid_item_id':
                    // Invalid download ID.
                    throw new FS_Endpoint_Exception( "Invalid download ID ({$download_id}).", 'invalid_download_id',
                        400 );
                /**
                 * Migrate expired license since all EDD licenses are not blocking.
                 */
                case 'expired':
                    /**
                     * License not yet activated.
                     *
                     * This use-case should not happen since if the client triggered a migration
                     * request with a valid license key, it means that the license was activated
                     * at least once. Hence, 'inactive' isn't possible.
                     */
                case 'inactive':
                    /**
                     * License was disabled, therefore, ...
                     *
                     * @todo what to do in that case?
                     */
                case 'disabled':
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
                case 'site_inactive':
                    /**
                     * Migrate license & site.
                     *
                     * License is valid and activated for the context site.
                     */
                case 'valid':
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