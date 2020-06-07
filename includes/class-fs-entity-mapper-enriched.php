<?php
    /**
     * @package     Freemius Migration
     * @copyright   Copyright (c) 2018, Freemius, Inc.
     * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
     * @since       1.1.0
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * Class FS_Entity_Mapper_Enriched
     *
     * Entity mapper DAL. To map between local WordPress entities to Freemius remote entities.
     *
     * Each local entity can be linked to ONE and ONLY ONE remote entity on Freemius.
     *
     * Unlike the FS_Entity_Mapper which is entity agnostic, this enriched mapper comes with a set of helper methods for specific entity types.
     *
     */
    class FS_Entity_Mapper_Enriched extends FS_Entity_Mapper {

        #--------------------------------------------------------------------------------
        #region Singleton
        #--------------------------------------------------------------------------------

        private static $_instances = array();

        /**
         * @param string $namespace
         *
         * @return FS_Entity_Mapper
         */
        public static function instance( $namespace ) {
            $namespace = strtolower( $namespace );

            if ( ! isset( self::$_instances[ $namespace ] ) ) {
                self::$_instances[ $namespace ] = new FS_Entity_Mapper_Enriched( $namespace );
            }

            return self::$_instances[ $namespace ];
        }

        #endregion

        protected function __construct( $namespace ) {
            parent::__construct( $namespace );
        }

        /**
         * Get the corresponding Freemius module/product ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param string $local_module_id
         *
         * @return false|number
         */
        public function get_remote_module_id( $local_module_id ) {
            return parent::get_remote_id(
                FS_Plugin::get_type(),
                $local_module_id
            );
        }

        /**
         * Get the corresponding Freemius paid plan ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param string $local_plan_id
         *
         * @return false|number
         */
        function get_remote_paid_plan_id( $local_plan_id ) {
            return $this->get_remote_id(
                FS_Plan::get_type(),
                $local_plan_id
            );
        }

        /**
         * Get the corresponding Freemius pricing ID.
         *
         * @author Leo Fajardo
         * @since  2.0.1
         *
         * @param string $local_pricing_id
         *
         * @return false|number
         */
        function get_remote_pricing_id( $local_pricing_id ) {
            return $this->get_remote_id(
                FS_Pricing::get_type(),
                $local_pricing_id
            );
        }

        /**
         * Get the corresponding Freemius subscription ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param string $local_subscription_id
         *
         * @return false|number
         */
        public function get_remote_subscription_id( $local_subscription_id ) {
            return parent::get_remote_id(
                FS_Subscription::get_type(),
                $local_subscription_id
            );
        }

        /**
         * Get the corresponding Freemius payment ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param string $local_payment_id
         *
         * @return false|number
         */
        public function get_remote_payment_id( $local_payment_id ) {
            return parent::get_remote_id(
                FS_Payment::get_type(),
                $local_payment_id
            );
        }
    }