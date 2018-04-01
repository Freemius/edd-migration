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

    require_once WP_FSM__DIR_MIGRATION . '/class-fs-migration-abstract.php';

    class FS_EDD_Migration extends FS_Migration_Abstract {

        /**
         * @var EDD_Software_Licensing
         */
        protected static $_edd_sl;

        protected $_is_subscription = false;

        /**
         * @var FS_EDD_Data_Mapper
         */
        protected $_data_mapper;

        #region EDD Entities

        /**
         * @var EDD_Download
         */
        protected $_edd_download;

        /**
         * @var WP_Post
         */
        protected $_edd_license;

        /**
         * @var EDD_Customer
         */
        protected $_edd_customer;

        /**
         * @var EDD_Subscription
         */
        protected $_edd_subscription;


        /**
         * @var EDD_Payment
         */
        protected $_edd_payment;

        /**
         * @var EDD_Payment[]
         */
        protected $_edd_renewals = array();

        /**
         * @var array
         */
        protected $_edd_installs_data;

        #endregion

        #--------------------------------------------------------------------------------
        #region Singleton
        #--------------------------------------------------------------------------------

        private static $_instances = array();

        /**
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param int $license_id
         *
         * @return FS_EDD_Migration
         */
        public static function instance( $license_id = 0 ) {
            if ( ! isset( self::$_edd_sl ) ) {
                self::$_edd_sl = edd_software_licensing();
            }

            if ( ! isset( self::$_instances[ $license_id ] ) ) {
                self::$_instances[ $license_id ] = new FS_EDD_Migration( $license_id );
            }

            return self::$_instances[ $license_id ];
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Init
        #--------------------------------------------------------------------------------

        private function __construct( $license_id = 0 ) {
            $this->init( WP_FS__NAMESPACE_EDD );

            require_once WP_FSM__DIR_MIGRATION . '/edd/class-fs-edd-data-mapper.php';

            $this->_data_mapper = FS_EDD_Data_Mapper::instance();

            if ( 0 < $license_id ) {
                $this->load_edd_entities( $license_id );
            }
        }

        /**
         * Pre-load all required EDD entities for complete migration process.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param int $license_id
         */
        private function load_edd_entities( $license_id ) {
            $download_id        = get_post_meta( $license_id, '_edd_sl_download_id', true );
            $initial_payment_id = get_post_meta( $license_id, '_edd_sl_payment_id', true );
            $customer_id        = edd_get_payment_customer_id( $initial_payment_id );

            $this->_edd_license  = get_post( $license_id );
            $this->_edd_download = new EDD_Download( $download_id );
            $this->_edd_customer = new EDD_Customer( $customer_id );
            $this->_edd_payment  = new EDD_Payment( $initial_payment_id );

            $this->_edd_subscription = $this->get_edd_subscription(
                $download_id,
                $initial_payment_id
            );

            if ( is_object( $this->_edd_subscription ) ) {
                /**
                 * Load renewals data.
                 *
                 * @var WP_Post[] $edd_renewal_posts
                 */
                $edd_renewal_posts = $this->_edd_subscription->get_child_payments();

                if ( is_array( $edd_renewal_posts ) && 0 < count( $edd_renewal_posts ) ) {
                    foreach ( $edd_renewal_posts as $edd_renewal_post ) {
                        $this->_edd_renewals[] = new EDD_Payment( $edd_renewal_post->ID );
                    }
                }
            }
        }

        #endregion

        /**
         * Init install data before migration.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param array $local_install
         */
        protected function set_local_install( $local_install ) {
            $this->_edd_installs_data = array( $local_install );
        }

        /**
         * Init install(s) data before migration.
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @param array $local_installs
         */
        protected function set_local_installs( $local_installs ) {
            $this->_edd_installs_data = $local_installs;
        }

        /**
         * Init subscription renewal payment data before migration.
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @param array $local_payment
         */
        protected function set_local_payment_renewal( $local_payment ) {
//            $this->_edd_payment  = $local_payment;
            $this->_edd_renewals = array( $local_payment );
        }

        /**
         * Init module data before migration.
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @param EDD_Payment $local_payment
         */
        protected function set_local_module_by_payment( $local_payment ) {
            // Check if the payment is associated with a migrated download.
            foreach ( $local_payment->downloads as $download ) {
                $module_id = $this->_entity_mapper->get_remote_module_id( $download['id'] );

                if ( FS_Plugin::is_valid_id( $module_id ) ) {
                    /**
                     * Found migrated download which is associated with the payment.
                     */
                    $this->_edd_download = new EDD_Download( $download['id'] );
                    break;
                }
            }
        }

        /**
         * Init subscription data before migration.
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @param EDD_Payment $local_subscription
         */
        protected function set_local_subscription( $local_subscription ) {
            $this->_edd_subscription = $local_subscription;
        }

        #--------------------------------------------------------------------------------
        #region Local Data Getters
        #--------------------------------------------------------------------------------

        /**
         * Local module ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string|false
         */
        protected function get_local_module_id() {
            if ( empty( $this->_edd_download ) ) {
                return false;
            }

            return $this->_data_mapper->get_local_module_id( $this->_edd_download );
        }

        /**
         * Local license ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string
         */
        protected function get_local_license_id() {
            return $this->_edd_license->ID;
        }

        /**
         * Local install ID.
         *
         * There's no module install concept nor entity in EDD, therefore,
         * generate a unique ID based on the download ID and site canonized URL.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param int $index Since 1.1.0 we support a multi-site network migration, hence the new index param.
         *
         * @return string
         */
        protected function get_local_install_id( $index = 0 ) {
            /**
             * Limit the ID to 32 chars since the entity mapping
             * local_id column is limited to 32 chars.
             */
            return substr(
                $this->_edd_download->ID . '_' .
                md5( $this->get_edd_canonized_site_home_url( '', $index ) ),
                0,
                32
            );
        }

        /**
         * Get installs count.
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @return int
         */
        protected function get_local_installs_count() {
            return count( $this->_edd_installs_data );
        }

        /**
         * Local customer ID (not the WP user ID).
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string
         */
        protected function get_local_customer_id() {
            return $this->_edd_customer->id;
        }

        /**
         * Local customer's email.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string
         */
        protected function get_local_customer_email() {
            return $this->_edd_customer->email;
        }

        /**
         * Local billing details ID.
         *
         * EDD doesn't have a billing entity so associate it with the customer ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string
         */
        protected function get_local_billing_id() {
            return $this->_edd_customer->id;
        }

        /**
         * Local pricing ID.
         *
         * EDD doesn't have a unique pricing ID.
         *   1. When EDD SL is installed and the license is associated
         *      with a variable price, use the download ID with the variable
         *      price ID ("{download->id}:{price->id}").
         *   2. When EDD SL is NOT installed, use "{download->id}:0" as the pricing ID.
         *   3. When EDD SL is installed but it's a legacy license that is NOT associated
         *      with variable price, find the price ID based on the license activations limit,
         *      and use "{download->id}:{price->id}".
         *      If the license activation quota is different from all the quotas in the prices
         *      then use the first price ID in the variable prices. The quota will be explicitly
         *      set using the `license_quota` API param.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string
         */
        protected function get_local_pricing_id() {
            $price_id = 0;

            if ( edd_has_variable_prices( $this->_edd_download->ID ) ) {

                $price_id = (int) self::$_edd_sl->get_price_id( $this->_edd_license->ID );

                if ( 0 === $price_id ) {
                    /**
                     * Couldn't find matching price ID which means it's a legacy license.
                     *
                     * Fetch the price ID that has the same license activations quota.
                     */
                    $edd_prices = $this->_edd_download->get_prices();

                    $edd_license_activations_limit = self::$_edd_sl->get_license_limit( $this->_edd_download->ID,
                        $this->_edd_license->ID );

                    $price_found = false;
                    foreach ( $edd_prices as $id => $edd_price ) {
                        if ( $edd_license_activations_limit == $edd_price['license_limit'] ) {
                            $price_id    = $id;
                            $price_found = true;
                            break;
                        }
                    }

                    if ( ! $price_found ) {
                        /**
                         * If license limit isn't matching any of the prices, use the first
                         * price ID.
                         */
                        reset( $edd_prices );
                        $price_id = key( $edd_prices );
                    }
                }
            }

            return $this->_edd_download->ID . ':' . $price_id;
        }

        /**
         * Local plan ID.
         *
         * Since EDD doesn't have a concept of plans and since we locally
         * link all local pricing to the remote paid plan, use the local pricing ID
         * as the local plan ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string
         */
        protected function get_local_paid_plan_id() {
            return $this->get_local_pricing_id();
        }

        /**
         * Local payment ID. When subscription return the initial payment ID.
         *
         * Since EDD's initial payment is associated to a cart and can contain
         * multiple products, set the unique per download ID as the download ID
         * with the payment ID combination.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string
         */
        protected function get_local_payment_id() {
            // The initial payment can be associated to multiple downloads,
            // therefore, we want to make it unique per module.
            return $this->_edd_download->ID . ':' . $this->_edd_payment->ID;
        }

        /**
         * Check if the license is associated with a subscription.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return bool
         */
        protected function local_is_subscription() {
            return is_object( $this->_edd_subscription );
        }

        /**
         * Check if the payment is live (not test/sandbox payment).
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @return bool
         */
        protected function local_is_live_payment() {
            return ( 'test' !== $this->_edd_payment->mode );
        }

        /**
         * Check if the renewal payment is live (not test/sandbox payment).
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @param int $index
         *
         * @return bool
         */
        protected function local_is_live_renewal( $index = 0 ) {
            return ( 'test' !== $this->_edd_renewals[ $index ]->mode );
        }

        /**
         * Check if the payment's amount is positive.
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @return bool
         */
        protected function local_is_positive_payment_amount() {
            return ( .0 < $this->_edd_payment->total );
        }

        /**
         * Check if the payment's amount is positive.
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @param int $index
         *
         * @return bool
         */
        protected function local_is_positive_renewal_amount( $index = 0 ) {
            return ( .0 < $this->_edd_renewals[ $index ]->total );
        }

        /**
         * Local subscription ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string|false
         */
        protected function get_local_subscription_id() {
            if ( empty( $this->_edd_subscription ) ) {
                return false;
            }

            return $this->_edd_subscription->id;
        }

        /**
         * Get renewals count. If license isn't associated with a subscription
         * will simply return 0.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return int
         */
        protected function get_local_renewals_count() {
            return is_array( $this->_edd_renewals ) ?
                count( $this->_edd_renewals ) :
                0;
        }

        /**
         * Local context specified renewal payment ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param int $index
         *
         * @return string
         */
        protected function get_local_subscription_renewal_id( $index = 0 ) {
            return $this->_edd_renewals[ $index ]->ID;
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Data Mapping
        #--------------------------------------------------------------------------------

        #region Helper Methods

        /**
         * Get EDD subscription entity subscription.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param int $download_id
         * @param int $parent_payment_id
         *
         * @return EDD_Subscription
         */
        private function get_edd_subscription( $download_id, $parent_payment_id ) {
            if ( ! class_exists( 'EDD_Recurring' ) ) {
                // EDD recurring payments add-on isn't installed.
                return null;
            }

            /**
             * We need to make sure the singleton is initiated, otherwise,
             * EDD_Subscriptions_DB will not be found because the inclusion
             * of the relevant file is executed in the instance init.
             */
            EDD_Recurring::instance();

            $subscriptions_db = new EDD_Subscriptions_DB();

            $edd_subscriptions = $subscriptions_db->get_subscriptions( array(
                'product_id'        => $download_id,
                'parent_payment_id' => $parent_payment_id
            ) );

            return ( is_array( $edd_subscriptions ) && 0 < count( $edd_subscriptions ) ) ?
                $edd_subscriptions[0] :
                null;
        }

        /**
         * Generate customer address for API.
         *
         * 1. First try to load address from payment details.
         * 2. If empty, try to load address from customer details.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return array
         */
        private function get_customer_address_for_api() {
            $user_info = $this->_edd_payment->user_info;

            $address = array(
                'line1'   => '',
                'line2'   => '',
                'city'    => '',
                'state'   => '',
                'country' => '',
                'zip'     => ''
            );

            if ( ! empty( $user_info['address'] ) ) {
                $address = wp_parse_args( $user_info['address'], $address );
            } else if ( ! empty( $this->_edd_customer->user_id ) ) {
                // Enrich data with customer's address.
                $customer_address = get_user_meta( $this->_edd_customer->user_id, '_edd_user_address', true );

                $address = wp_parse_args( $customer_address, $address );
            }

            $api_address = array();
            if ( ! empty( $address['line1'] ) ) {
                $api_address['address_street'] = $address['line1'];
            }
            if ( ! empty( $address['line2'] ) ) {
                $api_address['address_apt'] = $address['line2'];
            }
            if ( ! empty( $address['city'] ) ) {
                $api_address['address_city'] = $address['city'];
            }
            if ( ! empty( $address['state'] ) ) {
                $api_address['address_state'] = $address['state'];
            }
            if ( ! empty( $address['country'] ) ) {
                $api_address['address_country_code'] = strtolower( $address['country'] );
            }
            if ( ! empty( $address['zip'] ) ) {
                $api_address['address_zip'] = $address['zip'];
            }

            return $api_address;
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
         * @param EDD_Payment $edd_payment
         *
         * @return array
         */
        private function get_payment_gross_and_tax_for_api( EDD_Payment $edd_payment ) {
            return $this->_data_mapper->get_payment_gross_and_tax_for_api( $edd_payment, $this->_edd_download );
        }

        /**
         * Get license expiration in UTC datetime.
         * If it's a lifetime license, return null.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string|null
         */
        private function get_local_license_expiration() {
            $license_expiration = self::$_edd_sl->get_license_expiration( $this->_edd_license->ID );

            if ( 'lifetime' === $license_expiration ) {
                return null;
            }

            $timezone = date_default_timezone_get();

            if ( 'UTC' !== $timezone ) {
                // Temporary change time zone.
                date_default_timezone_set( 'UTC' );
            }

            $formatted_license_expiration = date( WP_FSM__LOG_DATETIME_FORMAT, $license_expiration );

            if ( 'UTC' !== $timezone ) {
                // Revert timezone.
                date_default_timezone_set( $timezone );
            }

            return $formatted_license_expiration;
        }

        /**
         * @var string|false
         */
        private $_customerIP;

        /**
         * Try to get customer's IP address.
         *
         *  - First try to get from the initial payment info.
         *  - Then, from the renewals.
         *  - Finally, check the EDD activations log and try to get it from there.
         *
         * If can't find it, return false.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string|false
         */
        private function get_customer_ip() {
            if ( ! isset( $this->_customerIP ) ) {
                // Try to get IP from initial payment.
                if ( ! empty( $this->_edd_payment->ip ) ) {
                    $this->_customerIP = $this->_edd_payment->ip;
                } // Try to get IP from the subscription renewals.
                else if ( $this->local_is_subscription() &&
                          is_array( $this->_edd_renewals )
                ) {
                    foreach ( $this->_edd_renewals as $edd_renewal ) {
                        if ( ! empty( $edd_renewal->ip ) ) {
                            $this->_customerIP = $edd_renewal->ip;
                            break;
                        }
                    }
                }

                if ( ! isset( $this->_customerIP ) ) {
                    // Try to fetch IP from license activation log.
                    $logs = edd_software_licensing()->get_license_logs( $this->_edd_license->ID );

                    if ( is_array( $logs ) && 0 < count( $logs ) ) {
                        $activation_log_post_name_prefix        = 'log-license-activated-';
                        $activation_log_post_name_prefix_length = strlen( $activation_log_post_name_prefix );

                        foreach ( $logs as $log ) {
                            if ( ! has_term( 'renewal_notice', 'edd_log_type', $log->ID ) ) {
                                /**
                                 * @var WP_Post $log
                                 */
                                if ( $activation_log_post_name_prefix === substr( $log->post_name, 0,
                                        $activation_log_post_name_prefix_length )
                                ) {
                                    $activation_info = json_decode( get_post_field( 'post_content', $log->ID ) );
                                    if ( isset( $activation_info->REMOTE_ADDR ) &&
                                         ! empty( $activation_info->REMOTE_ADDR )
                                    ) {
                                        $this->_customerIP = $activation_info->REMOTE_ADDR;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }

                if ( ! isset( $this->_customerIP ) ) {
                    $this->_customerIP = false;
                }
            }

            return $this->_customerIP;
        }

        /**
         * Check if sandbox purchase.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return bool
         */
        private function local_is_sandbox_purchase() {
            return ( 'live' !== $this->_edd_payment->mode );
        }

        /**
         * Get purchase gateway.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string
         */
        private function get_local_purchase_gateway() {
            /**
             * 1. Freemius doesn't have the concept of Test ("manual") gateway.
             * 2. Freemius only have PayPal or CreditCard options at the moment.
             */
            return ( 'paypal' === $this->_edd_payment->gateway ) ?
                'paypal' :
                'cc';
        }

        /**
         * Get subscription gateway.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string
         */
        private function get_local_subscription_gateway() {
            return $this->get_local_purchase_gateway();
        }

        /**
         * Check if sandbox subscription.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return bool
         */
        private function is_sandbox_subscription() {
            return $this->local_is_sandbox_purchase();
        }

        /**
         * Get billing cycle in months.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return int
         */
        private function get_local_billing_cycle_in_months() {
            switch ( $this->_edd_subscription->period ) {
                case 'day':
                case 'week':
                    // @todo The shortest supported billing period by Freemius is a Month.
                case 'month':
                    return 1;
                case 'quarter':
                    return 3;
                case 'semi-year':
                    return 6;
                case 'year':
                default:
                    return 12;
            }
        }

        /**
         * Get payment data for API.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param EDD_Payment $edd_payment
         *
         * @return array
         */
        private function get_payment_by_edd_for_api( EDD_Payment $edd_payment ) {
            return $this->_data_mapper->get_payment_by_edd_for_api( $edd_payment, $this->_edd_download );
        }

        /**
         * EDD licensing validation logic works with domains/subdomains without a path.
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @param string $url
         *
         * @return string
         */
        public static function strip_path_from_url( $url ) {
            // Strip path from site's URL (EDD licensing logic works with subdomains).
            if ( false !== strpos( $url, '/' ) ) {
                $protocol_pos = strpos( $url, '://' );
                $path_pos     = strpos( $url, '/', ( false !== $protocol_pos ) ? $protocol_pos + 3 : 0 );

                if ( false !== $path_pos ) {
                    $url = substr( $url, 0, $path_pos );
                }
            }

            return $url;
        }

        /**
         * Generate site's URL based on how EDD stores the URLs in the
         * license post meta ('_edd_sl_sites').
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param string $url
         * @param int    $site_index
         *
         * @return string
         */
        private function get_edd_canonized_site_home_url( $url = '', $site_index = 0 ) {
            if ( empty( $url ) ) {
                $url = ! empty( $this->_edd_installs_data[ $site_index ]['site_url'] ) ?
                    $this->_edd_installs_data[ $site_index ]['site_url'] :
                    '';
            }

            $url = $this->strip_path_from_url( $url );

            if ( empty( $url ) ) {

                // Attempt to grab the URL from the user agent if no URL is specified
                $domain = array_map( 'trim', explode( ';', $_SERVER['HTTP_USER_AGENT'] ) );
                $url    = trim( $domain[1] );

            }

            return trailingslashit( self::$_edd_sl->clean_site_url( $url ) );
        }

        /**
         * Try to find installation date based on EDD license activation log.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return false|string
         */
        private function get_local_install_datetime() {
            $logs = edd_software_licensing()->get_license_logs( $this->_edd_license->ID );

            if ( is_array( $logs ) && 0 < count( $logs ) ) {
                $activation_log_post_name_prefix        = 'log-license-activated-';
                $activation_log_post_name_prefix_length = strlen( $activation_log_post_name_prefix );
                $canonized_url                          = trim( $this->get_edd_canonized_site_home_url(), '/' );

                foreach ( $logs as $log ) {
                    if ( ! has_term( 'renewal_notice', 'edd_log_type', $log->ID ) ) {
                        /**
                         * @var WP_Post $log
                         */
                        if ( $activation_log_post_name_prefix === substr( $log->post_name, 0,
                                $activation_log_post_name_prefix_length )
                        ) {
                            $activation_info = json_decode( get_post_field( 'post_content', $log->ID ) );
                            if ( isset( $activation_info->HTTP_USER_AGENT ) ) {
                                if ( false !== strpos( $activation_info->HTTP_USER_AGENT, $canonized_url ) ) {
                                    // Found matching URL activation.
                                    return $log->post_date_gmt;
                                }
                            }
                        }
                    }
                }
            }

            return false;
        }

        /**
         * Try to find installation date based on EDD license activation log.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return false|string
         */
        private function get_local_installs_datetime() {
            $logs            = edd_software_licensing()->get_license_logs( $this->_edd_license->ID );
            $activation_logs = array();

            if ( is_array( $logs ) && 0 < count( $logs ) ) {
                $activation_log_post_name_prefix        = 'log-license-activated-';
                $activation_log_post_name_prefix_length = strlen( $activation_log_post_name_prefix );


                foreach ( $logs as $log ) {
                    if ( ! has_term( 'renewal_notice', 'edd_log_type', $log->ID ) ) {
                        /**
                         * @var WP_Post $log
                         */
                        if ( $activation_log_post_name_prefix === substr( $log->post_name, 0,
                                $activation_log_post_name_prefix_length )
                        ) {
                            $activation_info = json_decode( get_post_field( 'post_content', $log->ID ) );
                            if ( isset( $activation_info->HTTP_USER_AGENT ) ) {
                                $activation_logs[] = array(
                                    'activation_info' => $activation_info,
                                    'log'             => $log,
                                );
                            }
                        }
                    }
                }
            }

            $installs_datetime = array();
            for ( $i = 0, $len = count( $this->_edd_installs_data ); $i < $len; $i ++ ) {
                $install_datetime = false;

                if ( ! empty( $activation_logs ) ) {
                    $canonized_url = trim( $this->get_edd_canonized_site_home_url( '', $i ), '/' );

                    foreach ( $activation_logs as $activation_log ) {
                        if ( false !== strpos( $activation_log['activation_info']->HTTP_USER_AGENT, $canonized_url ) ) {
                            // Found matching URL activation.
                            $install_datetime = $activation_log['log']->post_date_gmt;
                            break;
                        }
                    }
                }

                $installs_datetime[] = $install_datetime;
            }

            return $installs_datetime;
        }

        /**
         * Get license quota. If unlimited license, return NULL.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return int|null
         */
        private function get_license_quota() {
            $quota = (int) self::$_edd_sl->get_license_limit(
                $this->_edd_download->ID,
                $this->_edd_license->ID
            );

            return ( $quota > 0 ) ? $quota : null;
        }

        #endregion

        /**
         * Generate customer details from local data for API.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return array
         */
        protected function get_customer_for_api() {
            $customer                            = array();
            $customer['email']                   = $this->_edd_customer->email;
            $customer['name']                    = $this->_edd_customer->name;
            $customer['is_verified']             = true;
            $customer['send_verification_email'] = false; // Do NOT send verification emails.
            $customer['password']                = wp_generate_password( 8 ); // Generate random 8 char pass for FS.

            $ip = $this->get_customer_ip();
            if ( ! empty( $ip ) ) {
                $customer['ip'] = $ip;
            }

            return $customer;
        }

        /**
         * Generate customer billing details from local data for API.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return array
         */
        protected function get_customer_billing_for_api() {
            $payment_meta = $this->_edd_payment->payment_meta;
            $user_info    = $this->_edd_payment->user_info;

            $billing          = array();
            $billing['first'] = $user_info['first_name'];
            $billing['last']  = $user_info['last_name'];
            $billing['email'] = $payment_meta['email'];

            $billing = array_merge( $billing, $this->get_customer_address_for_api() );

            return $billing;
        }

        /**
         * Generate install details from local data for API.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param number $license_id
         *
         * @return array
         */
        protected function get_install_for_api( $license_id ) {
            $install                                 = array();
            $install['url']                          = $this->_edd_installs_data[0]['site_url'];
            $install['version']                      = $this->_edd_installs_data[0]['plugin_version'];
            $install['is_premium']                   = $this->_edd_installs_data[0]['is_premium'];
            $install['is_active']                    = $this->_edd_installs_data[0]['is_active'];
            $install['is_uninstalled']               = $this->_edd_installs_data[0]['is_uninstalled'];
            $install['uid']                          = $this->_edd_installs_data[0]['site_uid'];
            $install['title']                        = $this->_edd_installs_data[0]['site_name'];
            $install['language']                     = $this->_edd_installs_data[0]['language'];
            $install['charset']                      = $this->_edd_installs_data[0]['charset'];
            $install['platform_version']             = $this->_edd_installs_data[0]['platform_version'];
            $install['programming_language_version'] = ! empty( $this->_edd_installs_data[0]['php_version'] ) ?
                $this->_edd_installs_data[0]['php_version'] :
                $this->_edd_installs_data[0]['programming_language_version'];
            $install['license_id']                   = $license_id;

            $install_at = $this->get_local_install_datetime();

            if ( ! empty( $install_at ) ) {
                $install['installed_at'] = $install_at;
            }

            return $install;
        }

        /**
         * Generate install(s) details from local data for API.
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @param number $license_id
         * @param int[]  $indexes
         *
         * @return array
         */
        protected function get_installs_for_api( $license_id, array $indexes ) {
            $install                                 = array();
            $install['is_premium']                   = $this->_edd_installs_data[0]['is_premium'];
            $install['version']                      = $this->_edd_installs_data[0]['plugin_version'];
            $install['platform_version']             = $this->_edd_installs_data[0]['platform_version'];
            $install['programming_language_version'] = ! empty( $this->_edd_installs_data[0]['php_version'] ) ?
                $this->_edd_installs_data[0]['php_version'] :
                $this->_edd_installs_data[0]['programming_language_version'];
            $install['license_id']                   = $license_id;

//            $install['is_active']                    = $this->_edd_installs_data['is_active'];
//            $install['is_uninstalled']               = $this->_edd_installs_data['is_uninstalled'];

            // Fetch installations creation dates.
            $installs_created_at = $this->get_local_installs_datetime();

            $sites = array();
            foreach ( $indexes as $index ) {
                $site = array(
                    'uid'      => $this->_edd_installs_data[ $index ]['site_uid'],
                    'url'      => $this->_edd_installs_data[ $index ]['site_url'],
                    'title'    => $this->_edd_installs_data[ $index ]['site_name'],
                    'language' => $this->_edd_installs_data[ $index ]['language'],
                    'charset'  => $this->_edd_installs_data[ $index ]['charset'],
                );

                if ( ! empty( $installs_created_at[ $index ] ) ) {
                    $site['installed_at'] = $installs_created_at[ $index ];
                }

                $sites[] = $site;
            }

            $install['sites'] = $sites;

            return $install;
        }

        /**
         * Generate purchase EU VAT details from local data for API.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return array
         */
        protected function get_purchase_vat_for_api() {
            $vat = array();

            $address = $this->get_customer_address_for_api();

            if ( ! empty( $address['address_country_code'] ) ) {
                $vat['country_code'] = $address['address_country_code'];
            }

            if ( class_exists( '\lyquidity\edd_vat\Actions' ) ) {
                if ( ! empty( $this->_edd_customer->user_id ) ) {
                    $vat_id = \lyquidity\edd_vat\Actions::instance()->get_vat_number( '',
                        $this->_edd_customer->user_id );

                    if ( ! empty( $vat_id ) ) {
                        $vat['vat_id'] = $vat_id;
                    }
                }
            }

            return $vat;
        }

        /**
         * Generate purchase details from local data for API.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return array
         */
        protected function get_purchase_for_api() {
            $purchase                         = array();
            $purchase['billing_cycle']        = 0;
            $purchase['payment_method']       = $this->get_local_purchase_gateway();
            $purchase['customer_external_id'] = 'edd_customer_' . $this->_edd_customer->id;
            $purchase['license_key']          = self::$_edd_sl->get_license_key( $this->_edd_license->ID ); // Preserve the same keys.
            $purchase['license_quota']        = $this->get_license_quota(); // Preserve license activations limit.
            $purchase['processed_at']         = $this->_data_mapper->get_payment_process_date( $this->_edd_payment );
            $purchase['payment_external_id']  = $this->_data_mapper->get_payment_transaction_id( $this->_edd_payment );

            // Set license expiration if not a lifetime license via a purchase.
            $license_expiration = $this->get_local_license_expiration();
            if ( null !== $license_expiration ) {
                $purchase['license_expires_at'] = $license_expiration;
            }

            $ip = $this->get_customer_ip();
            if ( ! empty( $ip ) ) {
                $purchase['ip'] = $ip;
            }

            if ( $this->local_is_sandbox_purchase() ) {
                $purchase['is_sandbox'] = true;
            }

            $purchase = array_merge( $purchase, $this->get_payment_gross_and_tax_for_api( $this->_edd_payment ) );

            $purchase = array_merge( $purchase, $this->get_purchase_vat_for_api() );

            return $purchase;
        }

        /**
         * Generate onetime payment details from local data for API.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return array
         */
        protected function get_onetime_payment_for_api() {
            $payment = $this->get_payment_by_edd_for_api( $this->_edd_payment );

            return $payment;
        }

        /**
         * Generate subscription details from local data for API.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return array
         */
        protected function get_subscription_for_api() {

            $subscription                             = array();
            $subscription['payment_method']           = $this->get_local_subscription_gateway();
            $subscription['billing_cycle']            = $this->get_local_billing_cycle_in_months();
            $subscription['subscription_external_id'] = ! empty( $this->_edd_subscription->profile_id ) ?
                $this->_edd_subscription->profile_id :
                'edd_subscription_' . $this->_edd_subscription->id;
            $subscription['customer_external_id']     = 'edd_customer_' . $this->_edd_customer->id;
            $subscription['next_payment']             = $this->_edd_subscription->get_expiration();
            $subscription['processed_at']             = $this->_edd_subscription->created;
            $subscription['license_key']              = self::$_edd_sl->get_license_key( $this->_edd_license->ID ); // Preserve the same keys.
            $subscription['license_quota']            = $this->get_license_quota(); // Preserve license activations limit.

            /**
             * Set license expiration for cases when the subscription's next
             * payment isn't matching the license expiration.
             *
             * Also allow migration of a lifetime license with a subscription.
             */
            $subscription['license_expires_at'] = $this->get_local_license_expiration();

            $ip = $this->get_customer_ip();
            if ( ! empty( $ip ) ) {
                $subscription['ip'] = $ip;
            }

            if ( $this->is_sandbox_subscription() ) {
                $subscription['is_sandbox'] = true;
            }

            // @todo Enrich API to accept is_cancelled as an optional argument during migration.
            if ( 'cancelled' === $this->_edd_subscription->get_status() ) {
                $subscription['is_cancelled'] = true;
            }

            $subscription = array_merge( $subscription, $this->get_purchase_vat_for_api() );

            return $subscription;
        }

        /**
         * Generate subscription initial payment details from local data for API.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return array
         */
        protected function get_initial_payment_for_api() {
            $payment = $this->get_payment_by_edd_for_api( $this->_edd_payment );

            $transaction_id = $this->_edd_payment->transaction_id;

            if ( empty( $transaction_id ) ) {
                /**
                 * From some reason when the gateway is Stripe the initial payment
                 * transaction ID is stored as the transaction ID of the subscription.
                 */
                $transaction_id = $this->_edd_subscription->get_transaction_id();
            }

            if ( empty( $transaction_id ) ) {
                // Fallback to EDD payment ID.
                $transaction_id = 'edd_payment_' . $this->_edd_payment->ID;
            }

            $payment['payment_external_id'] = $transaction_id;
            $payment['is_extend_license']   = false;

            return $payment;
        }

        /**
         * Generate subscription renewal details from local data for API.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param int  $index Renewal payment index.
         * @param bool $renew_license
         *
         * @return array
         */
        protected function get_subscription_renewal_for_api( $index = 0, $renew_license = false ) {
            $renewal = $this->get_payment_by_edd_for_api( $this->_edd_renewals[ $index ] );

            $renewal['is_extend_license'] = $renew_license;

            return $renewal;
        }

        #endregion
    }
