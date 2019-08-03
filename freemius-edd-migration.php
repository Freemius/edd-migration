<?php
    /**
     * Plugin Name: Freemius for EDD Migration
     * Plugin URI:  http://freemius.com/
     * Description: Server side endpoint to sync data between Freemius and EDD.
     * Version:     2.0.0
     * Author:      Freemius
     * Author URI:  http://freemius.com
     * License: GPL2
     *
     * @requires
     *  1. EDD 2.5 or higher, assuming payments currency is USD and have no fees.
     *  2. PHP 5.3 or higher [using spl_autoload_register()]
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    function fs_edd_migration_init() {
        if ( ! class_exists( 'Easy_Digital_Downloads' ) ||
             ! class_exists( 'EDD_Software_Licensing' )
        ) {
            deactivate_plugins( basename( __FILE__ ) );

            // Message error + allow back link.
            wp_die(
                __( 'Freemius for EDD Migration plugin requires Easy Digital Downloads and its Software Licensing extension to be active.',
                    'fs-edd-migration' ),
                __( 'Error' ),
                array( 'back_link' => true )
            );
        }

        // Load config.
        require_once __DIR__ . '/start.php';

        // Load migration module.
        require_once WP_FSM__DIR_MIGRATION . '/edd/class-fs-edd-migration-endpoint.php';

        add_action( 'edd_recurring_add_subscription_payment', 'fs_edd_migrate_subscription_renewal', 10, 2 );
    }

    function fs_edd_migrate_subscription_renewal( EDD_Payment $edd_payment, EDD_Subscription $edd_subscription ) {
        require_once WP_FSM__DIR_MIGRATION . '/edd/class-fs-edd-migration.php';

        $migration = FS_EDD_Migration::instance();
        $migration->set_api( fs_edd_migration_manager()->get_api() );
        $migration->do_migrate_subscription_renewal( $edd_payment, $edd_subscription );
    }

    // Get Freemius EDD Migration running.
    add_action( 'plugins_loaded', 'fs_edd_migration_init' );

    function fs_edd_migration_auto_redirect() {
        if ( ! function_exists( 'is_network_admin' ) || ! is_network_admin() ) {
            if ( 'true' === get_option( 'fs_edd_migration_activated' ) ) {
                // Load config.
                require_once __DIR__ . '/start.php';

                update_option( 'fs_edd_migration_activated', null );

                if ( fs_redirect( add_query_arg( array(
                    'post_type' => 'download',
                    'page'      => 'fs-migration',
                ), admin_url( 'edit.php', 'admin' ) ) ) ) {
                    exit;
                }
            }
        }
    }

    add_action( 'admin_init', 'fs_edd_migration_auto_redirect' );

    function fs_migration_plugin_activation() {
        require_once __DIR__ . '/includes/class-fs-entity-mapper.php';

        // Create mapping table if not exist.
        FS_Entity_Mapper::create_table();

        // Hint the plugin that it was just activated.
        update_option( "fs_edd_migration_activated", 'true' );
    }

    register_activation_hook( __FILE__, 'fs_migration_plugin_activation' );
