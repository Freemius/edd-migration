<?php
    /**
     * @package     Freemius Migration
     * @copyright   Copyright (c) 2016, Freemius, Inc.
     * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
     * @since       1.1.0
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * Class FS_EDD_Data_Mapper
     */
    class FS_EDD_Data_Mapper {// extends FS_Data_Mapper_Abstract {

        /**
         * @var FS_EDD_Data_Mapper
         */
        private static $_instance;

        public static function instance() {
            if ( ! isset( self::$_instance ) ) {
                self::$_instance = new FS_EDD_Data_Mapper();
            }

            return self::$_instance;
        }

        /**
         * Local module ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param EDD_Download $edd_download
         *
         * @return string
         */
        public function get_local_module_id( EDD_Download $edd_download ) {
            return $edd_download->ID;
        }

        /**
         * Get EDD payment's processing date.
         *
         * If payment was never completed, return the payment entity creation datetime.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param EDD_Payment $edd_payment
         *
         * @return string
         */
        public function get_payment_process_date( EDD_Payment $edd_payment ) {
            return ! empty( $edd_payment->completed_date ) ?
                $edd_payment->completed_date :
                $edd_payment->date;
        }

        /**
         * Get EDD payment's transaction ID. If empty, use "edd_payment_{payment_id}".
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param EDD_Payment $edd_payment
         *
         * @return string
         */
        public function get_payment_transaction_id( EDD_Payment $edd_payment ) {
            return ! empty( $edd_payment->transaction_id ) ?
                $edd_payment->transaction_id :
                'edd_payment_' . $edd_payment->ID;
        }

        /**
         * Generate payment gross and tax for API based on given EDD payment.
         *
         * When initial payment associated with a cart that have multiple products,
         * find the gross and tax for the product that is associated with the context
         * license.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param EDD_Payment   $edd_payment
         * @param EDD_Download $edd_download
         *
         * @return array
         */
        public function get_payment_gross_and_tax_for_api( EDD_Payment $edd_payment, EDD_Download $edd_download ) {
            $gross_and_vat = array();

            if ( isset( $edd_payment->cart_details ) &&
                 is_array( $edd_payment->cart_details ) &&
                 1 < count( $edd_payment->cart_details )
            ) {
                /**
                 * Purchased multiple products in the same cart, find the gross & tax paid for the
                 * product associated with the license.
                 */
                $cart                      = $edd_payment->cart_details;
                $context_edd_download_name = $edd_download->get_name();
                foreach ( $cart as $edd_download ) {
                    if ( $context_edd_download_name === $edd_download['name'] ) {
                        $gross_and_vat['gross'] = $edd_download['price'];

                        if ( is_numeric( $edd_download['tax'] ) && $edd_download['tax'] > 0 ) {
                            $gross_and_vat['vat'] = $edd_download['tax'];
                        }

                        break;
                    }
                }
            } else {
                /**
                 * Purchased only one product, get the gross & tax directly from the total
                 * payment.
                 */
                $gross_and_vat['gross'] = $edd_payment->total;

                if ( is_numeric( $edd_payment->tax ) && $edd_payment->tax > 0 ) {
                    $gross_and_vat['vat'] = $edd_payment->tax;
                }
            }

            return $gross_and_vat;
        }

        /**
         * Get payment data for API.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param EDD_Payment  $edd_payment
         * @param EDD_Download $edd_download
         *
         * @return array
         */
        public function get_payment_by_edd_for_api( EDD_Payment $edd_payment, EDD_Download $edd_download ) {
            $payment                        = array();
            $payment['processed_at']        = $this->get_payment_process_date( $edd_payment );
            $payment['payment_external_id'] = $this->get_payment_transaction_id( $edd_payment );

            $payment = array_merge( $payment, $this->get_payment_gross_and_tax_for_api( $edd_payment, $edd_download ) );

            return $payment;
        }

    }