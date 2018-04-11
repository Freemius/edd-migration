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

    if ( defined( 'DOING_CRON' ) ) {
        return;
    }

    if ( ! class_exists( 'FS_Client_License_Abstract_v1' ) ) {
        require_once dirname( __FILE__ ) . '/class-fs-client-license-abstract.php';
    }

    if ( ! class_exists( 'FS_EDD_Client_Migration_v1' ) ) {
        require_once dirname( __FILE__ ) . '/class-fs-edd-client-migration.php';
    }

    /**
     * You should use your own unique CLASS name, and be sure to replace it
     * throughout this file. For example, if your product's name is "Awesome Product"
     * then you can rename it to "Awesome_Product_EDD_License_Key".
     */
    class My_EDD_License_Key extends FS_Client_License_Abstract_v1 {
        /**
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.3
         *
         * @param int|null $blog_id
         *
         * @return string
         */
        function get( $blog_id = null ) {
            return trim( get_site_option( 'edd_sample_license_key', '' ) );
        }

        /**
         * When migrating a bundle license and the sales platform creates a different
         * license key for every product in the bundle which is the key that actually
         * used for activation, this method should return the collection of all
         * child license keys that were activated on the current website.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.1.0
         *
         * @param int|null $blog_id
         *
         * @return string[]
         */
        function get_children( $blog_id = null ) {
            global $wpdb;

            $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

            $children_license_keys = array();
            foreach ( $blog_ids as $blog_id ) {
                $license_key = trim( get_blog_option( $blog_id, 'edd_sample_addon_license_key', '' ) );

                if ( ! empty( $license_key ) ) {
                    $children_license_keys[] = $license_key;
                }
            }

            return $children_license_keys;
        }

        /**
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.3
         *
         * @param string   $license_key
         * @param int|null $blog_id
         *
         * @return bool True if successfully updated.
         */
        function set( $license_key, $blog_id = null ) {
            return update_site_option( 'edd_sample_license_key', $license_key );
        }

        /**
         * Override this only when the product supports a network level integration.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.1.0
         *
         * @return bool
         */
        public function is_network_migration() {
            /**
             * Comment the line below if you'd like to support network level licenses migration.
             * This is only relevant if you have a special network level integration with your plugin
             * and you're utilizing the Freemius SDK's multisite network integration mode.
             */
            return false;

            // Adjust the value of this assignment to your plugin's main file path.
            $main_plugin_file_path = trailingslashit( dirname( dirname( __FILE__ ) ) ) . 'my-plugin.php';

            $basename = plugin_basename( $main_plugin_file_path );

            if ( ! is_multisite() ) {
                // Not a multisite environment.
                return false;
            }

            if ( is_plugin_active_for_network( $basename ) ) {
                // Network active.
                return true;
            }

            if ( ! is_plugin_active( $basename ) && is_network_admin() ) {
                // Network activation.
                return true;
            }

            return false;
        }

        /**
         * This method is only relevant when you're using the network level migration mode.
         * The method should return true only if you restrict a network level license activation
         * to apply the exact same license for the products network wide.
         *
         * For example, if a network with 5-sites can have license1 on sub-sites 1-3,
         * and license2 on sub-sites 4-5, then the result of this method should be set to `false`.
         * BUT, if you the only way to activate the license is that it will be the same license on
         * all sub-sites 1-5, then this method should return `true`.
         *
         * @return bool
         */
        public function are_licenses_network_identical() {
            return false;
        }
    }

    new FS_EDD_Client_Migration_v1(
        // This should be replaced with your custom Freemius shortcode.
        my_freemius(),

        // This should point to your EDD store root URL.
        'https://your-edd-store.com',

        // The EDD download ID of your product.
        '2116',

        new My_EDD_License_Key(),

        // Is it a bundle to a single product migration?
        true,

        // For testing, you can change that argument to TRUE to trigger the migration in the same HTTP request.
        true
    );