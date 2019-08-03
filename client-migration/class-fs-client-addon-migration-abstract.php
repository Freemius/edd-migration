<?php
    /**
     * @package     Freemius Migration
     * @copyright   Copyright (c) 2016, Freemius, Inc.
     * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
     * @since       2.0.0
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * @author   Vova Feldman (@svovaf)
     * @since    2.0.0
     *
     * Class FS_Client_Addon_Migration_Abstract_v2
     */
    abstract class FS_Client_Addon_Migration_Abstract_v2 {
        /**
         * @var string
         */
        protected $_parent_shortcode;
        /**
         * @var Freemius
         */
        protected $_addon_fs;

        /**
         * @param array $config
         *
         * @return Freemius
         */
        public function init_sdk( array $config ) {
            if ( ! isset( $this->_addon_fs ) ) {
                // Include Freemius SDK.
                file_exists( WP_PLUGIN_DIR . '/wp-security-audit-log-premium/sdk/freemius/start.php' );

                if ( empty( $config['premium_slug'] ) ) {
                    // Currently all premium add-ons are premium-only.
                    $config['premium_slug'] = $config['slug'];
                }

                $this->_addon_fs = fs_dynamic_init( array_merge(
                    $this->get_addons_sdk_init_common_config(),
                    $config
                ) );
            }

            return $this->_addon_fs;
        }

        /**
         * Initialize the add-on client migration logic.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    2.0.0
         *
         * @param string $addon_shortcode
         */
        public function init( $addon_shortcode ) {
            if ( ! $this->is_parent_included() ) {
                if ( is_admin() ) {
                    // Add error admin notice telling the add-on cannot work without the theme.
                }

                return;
            }

            // Init Freemius.
            call_user_func( $addon_shortcode );

            $migration_fun_name = "{$addon_shortcode}_try_migrate";

            if ( did_action( "{$this->_parent_shortcode}_client_migration_loaded" ) ) {
                call_user_func( $migration_fun_name );
            } else {
                add_action( "{$this->_parent_shortcode}_client_migration_loaded", $migration_fun_name );
            }
        }

        /**
         * @author   Vova Feldman (@svovaf)
         * @since    2.0.0
         *
         * @param number $addon_edd_download_id
         * @param string $addon_class
         * @param string $addon_name
         */
        function try_migrate_addon(
            $addon_edd_download_id,
            $addon_class,
            $addon_name
        ) {
            $is_migration_debug = self::is_migration_debug();

            if ( self::is_migration_on() ) {
                if ( ! $is_migration_debug ||
                     ( ! self::is_wp_ajax() && ! self::is_wp_cron() )
                ) {
                    new FS_EDD_Client_Migration_v2(
                    // This should be replaced with your custom Freemius shortcode.
                        $this->_addon_fs,

                        // This should point to your EDD store root URL.
                        '<HTTPS://YOUR-EDD-STORE.COM>',

                        // The EDD download ID of your product.
                        $addon_edd_download_id,

                        self::get_new_license_key_manager( false, $addon_class, $addon_name ),

                        // Migration type.
                        FS_Client_Migration_Abstract_v2::TYPE_PRODUCT_TO_PRODUCT,

                        // Freemius was NOT included in the previous (last) version of the product.
                        false,

                        // For testing, you can change that argument to TRUE to trigger the migration in the same HTTP request.
                        $is_migration_debug
                    );
                }
            }
        }

        /**
         * Check if add-on's parent product is running.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    2.0.0
         *
         * @return bool
         */
        abstract protected function is_parent_included();

        /**
         * @author   Vova Feldman (@svovaf)
         * @since    2.0.0
         *
         * @return array
         */
        abstract protected function get_addons_sdk_init_common_config();

        /**
         * @author   Vova Feldman (@svovaf)
         * @since    2.0.0
         *
         * @param bool   $is_bundle
         * @param string $addon_class
         * @param string $addon_name
         *
         * @return FS_Client_License_Abstract_v2
         */
        abstract protected function get_new_license_key_manager( $is_bundle, $addon_class = '', $addon_name = '' );

        #region Helper Methods

        /**
         * @author   Vova Feldman (@svovaf)
         * @since    2.0.0
         *
         * @return bool
         */
        public static function is_migration_debug() {
            return (
                defined( 'WP_FS__MIGRATION_DEBUG' ) &&
                true === WP_FS__MIGRATION_DEBUG
            );
        }

        /**
         * @author   Vova Feldman (@svovaf)
         * @since    2.0.0
         *
         * @return bool
         */
        public static function is_migration_off() {
            return (
                defined( 'WP_FS__MIGRATION_OFF' ) &&
                true === WP_FS__MIGRATION_OFF
            );
        }

        /**
         * @author   Vova Feldman (@svovaf)
         * @since    2.0.0
         *
         * @return bool
         */
        public static function is_migration_on() {
            return ! self::is_migration_off();
        }

        /**
         * @author   Vova Feldman (@svovaf)
         * @since    2.0.0
         *
         * @return bool
         */
        public static function is_wp_ajax() {
            return (
                defined( 'DOING_AJAX' ) &&
                true === DOING_AJAX
            );
        }

        /**
         * @author   Vova Feldman (@svovaf)
         * @since    2.0.0
         *
         * @return bool
         */
        public static function is_wp_cron() {
            return (
                defined( 'DOING_CRON' ) &&
                true === DOING_CRON
            );
        }

        #endregion
    }