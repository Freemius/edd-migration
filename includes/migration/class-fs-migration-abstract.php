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

    /**
     * Important Assumptions for this version:
     *  - Payments are in USD.
     *  - Payments have no fees (only possible VAT).
     *  - Each module have ONLY one paid plan (plans differentiated by features-set, not licenses number).
     *
     * Class FS_Migration_Abstract
     */
    abstract class FS_Migration_Abstract {
        /**
         * @var FS_Api
         */
        protected static $_api;

        /**
         * @var number Context plugin or theme Freemius ID
         */
        private $_module_id;

        /**
         * @var FS_Entity_Mapper_Enriched
         */
        protected $_entity_mapper;

        /**
         * @since 1.0.0
         *
         * @var FS_Logger
         */
        private $_logger;

        /**
         * @since 1.0.0
         *
         * @var string
         */
        private $_namespace;

        #--------------------------------------------------------------------------------
        #region Init
        #--------------------------------------------------------------------------------

        /**
         * This method MUST be executed in the constructor of the
         * inheriting class.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param string $namespace
         */
        protected function init( $namespace ) {
            $this->_namespace     = $namespace;
            $this->_entity_mapper = FS_Entity_Mapper_Enriched::instance( $namespace );
        }

        /**
         * Required method for implementation to set the local install data.
         *
         * This method is executed right before the install migration at do_migrate_install();
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param mixed $local_install
         */
        abstract protected function set_local_install( $local_install );

        /**
         * Required method for implementation to set the local multisite network install(s) data.
         *
         * This method is executed right before the install migration at do_migrate_installs();
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @param mixed $local_installs
         */
        abstract protected function set_local_installs( $local_installs );

        /**
         * Required method for implementation to set the local payment data.
         *
         * This method is executed right before the subscription renewal migration at do_migrate_subscription_renewal();
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @param mixed $local_payment
         */
        abstract protected function set_local_payment_renewal( $local_payment );

        /**
         * Required method for implementation to set the local module data.
         *
         * This method is executed within the subscription renewal migration at do_migrate_subscription_renewal();
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @param mixed $local_payment
         */
        abstract protected function set_local_module_by_payment( $local_payment );

        /**
         * Required method for implementation to set the local subscription data.
         *
         * This method is executed within the subscription renewal migration at do_migrate_subscription_renewal();
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @param mixed $local_subscription
         */
        abstract protected function set_local_subscription( $local_subscription );

        #endregion

        #--------------------------------------------------------------------------------
        #region Main Public Methods
        #--------------------------------------------------------------------------------

        /**
         * The main license migration script. Migrates:
         *      1. Customer
         *      2. Customer billing
         *      3. Purchase or subscription
         *      4. If subscription, also migrate all payments.
         *      5. License
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @throws Exception
         *
         * @return number|\FS_User|false
         */
        public function do_migrate_license() {
            if ( ! isset( $this->_entity_mapper ) ) {
                throw new Exception( get_class( $this ) . ' must execute $this->init() in the constructor.' );
            }

            $this->log( "Starting to handle license key...\n------------------------------------------------------------------------------------\n" );

            $customer = $this->sync_customer();

            if ( false === $customer ) {
                return false;
            }

            $customer_id = is_object( $customer ) ?
                $customer->id :
                $customer;

            $local_customer_id = $this->get_local_customer_id();

            $customer_billing_id = $this->get_remote_id(
                FS_Billing::get_type(),
                $local_customer_id
            );

            if ( FS_Entity::is_valid_id( $customer_billing_id ) ) {
                $this->log( "Customer ({$local_customer_id}) billing already associated with a Freemius billing ({$customer_billing_id})." );
            } else {
                $customer_billing = $this->migrate_customer_billing( $customer_id );

                if ( $this->log_on_error( $customer_billing, "Failed creating customer's billing on Freemius." ) ) {
                    $this->log( 'Continue anyway...' );
                }
            }

            /**
             * @var false|number $license_id
             */
            $new_license_id = false;
            /**
             * @var false|number|FS_Payment $payment
             */
            $payment = false;
            /**
             * @var false|number|FS_Subscription $subscription
             */
            $subscription = false;

            if ( ! $this->local_is_subscription() ) {
                // Purchase.
                $payment = $this->sync_purchase( $customer_id );

                if ( false === $payment ) {
                    return false;
                }

                if ( $payment instanceof FS_Payment ) {
                    $new_license_id = $payment->license_id;
                }
            } else {
                // Subscription.
                $subscription = $this->sync_subscription( $customer_id );

                if ( false === $subscription ) {
                    return false;
                }

                if ( $subscription instanceof FS_Subscription ) {
                    $new_license_id = $subscription->license_id;
                }
            }

            $local_license_id = $this->get_local_license_id();

            $license_id = $this->get_remote_id(
                FS_License::get_type(),
                $local_license_id
            );

            if ( FS_Entity::is_valid_id( $license_id ) ) {
                $this->log( "License ({$local_license_id}) already associated with a Freemius license ({$license_id})." );
            } else {
                $license = new FS_License();

                if ( is_numeric( $new_license_id ) ) {
                    $license->id = $new_license_id;
                } else {
                    if ( ! $this->local_is_subscription() ) {
                        // Load license ID from purchase payment.
                        $result = $this->api_call( "/payments/{$payment}.json?fields=id,license_id" );
                    } else {
                        // Load license ID from subscription.
                        $result = $this->api_call( "/subscriptions/{$subscription}.json?fields=id,license_id" );
                    }

                    if ( $this->log_on_error( $result,
                        "Failed to fetch Freemius license ID from " . ( $this->local_is_subscription() ? "subscription ({$subscription})" : "purchase payment ({$payment})" ) . '.' )
                    ) {
                        return false;
                    }

                    $license->id = $result->license_id;
                }

                // Link license.
                $this->link_entity( $license, $local_license_id );

                $license_id = $license->id;

                $this->log_success( "Successfully linked local license ({$local_license_id}) with a Freemius license ({$license_id})." );
            }

            return $customer;
        }

        /**
         * Migrate install after the context license, customer,
         * purchase or subscription, payments, were migrated with
         * do_migrate_license().
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param mixed   $local_install
         * @param FS_User $customer
         *
         * @throws Exception
         *
         * @return array|bool
         *
         * @uses   set_local_install()
         */
        public function do_migrate_install(
            $local_install,
            $customer = null
        ) {
            $result = $this->do_migrate_installs( array( $local_install ), $customer );

            if ( empty( $result ) ) {
                return $result;
            }

            return array(
                'user'    => $result['user'],
                'install' => $result['installs'][0],
            );
        }

        /**
         * Migrate multi-site network install(s) after the context license, customer,
         * purchase or subscription, payments, were migrated with
         * do_migrate_license().
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @param array   $local_installs
         * @param FS_User $customer
         *
         * @throws Exception
         *
         * @return array|bool
         *
         * @uses   set_local_install()
         */
        public function do_migrate_installs(
            array $local_installs,
            $customer = null
        ) {
            if ( ! isset( $this->_entity_mapper ) ) {
                throw new Exception( get_class( $this ) . ' must execute $this->init() in the constructor.' );
            }

            $this->set_local_installs( $local_installs );

            $local_license_id = $this->get_local_license_id();

            $license_id = $this->get_remote_id(
                FS_License::get_type(),
                $local_license_id
            );

            if ( false === $license_id ) {
                throw new Exception( 'Calling do_migrate_installs() before the license was fully migrated with do_migrate_license() is not supported.' );
            }

            $local_customer_id = $this->get_local_customer_id();

            $customer_id = $this->get_remote_id(
                FS_User::get_type(),
                $local_customer_id
            );

            $installs = $this->sync_installs( $customer_id, $license_id );

            if ( empty( $installs ) ) {
                return false;
            }

            $install_ids_to_fetch = array();
            foreach ( $installs as $i => $install ) {
                if ( is_object( $install ) ) {
                    continue;
                }

                $install_ids_to_fetch[] = $install;

                unset( $installs[ $i ] );
            }

            if ( ! empty( $install_ids_to_fetch ) ) {
                $ids = implode( ',', $install_ids_to_fetch );

                $result = $this->api_call( "/installs.json?ids={$ids}" );

                if ( $this->log_on_error( $result, "Failed to fetch installs ({$ids}) from Freemius." ) ) {
                    return false;
                }

                if ( is_object( $result ) && ! empty( $result->installs ) && is_array( $result->installs ) ) {
                    foreach ( $result->installs as $install_data ) {
                        $installs[] = new FS_Install( $install_data );
                    }
                }

                // Reindex array.
                $installs = array_values( $installs );
            }

            if ( ! is_object( $customer ) ) {
                $result = $this->fetch_user_from_freemius_by_id( $customer_id );

                if ( ! is_object( $result ) ) {
                    return false;
                }

                $customer = $result;
            }

            return array(
                'user'     => $customer,
                'installs' => $installs,
            );
        }

        /**
         * Migrate install after the context license, customer,
         * purchase or subscription, payments, were migrated with
         * do_migrate_license().
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param mixed $local_payment
         * @param mixed $local_subscription
         *
         * @throws Exception
         *
         * @return array|bool
         *
         * @uses   set_local_payment_renewal()
         * @uses   set_local_module_by_payment()
         * @uses   set_local_subscription()
         */
        public function do_migrate_subscription_renewal( $local_payment, $local_subscription ) {
            if ( ! isset( $this->_entity_mapper ) ) {
                throw new Exception( get_class( $this ) . ' must execute $this->init() in the constructor.' );
            }

            $this->set_local_payment_renewal( $local_payment );

            if ( ! $this->local_is_live_renewal() ) {
                // Don't migrate sandbox payments.
                return false;
            }

            if ( ! $this->local_is_positive_renewal_amount() ) {
                // Don't migrate empty payments or refunds, this one is for subscription renewals.
                return false;
            }

            $this->set_local_module_by_payment( $local_payment );

            $payment_id = $this->_entity_mapper->get_remote_payment_id(
                $this->get_local_subscription_renewal_id()
            );

            if ( FS_Payment::is_valid_id( $payment_id ) ) {
                // Payment was already migrated to Freemius.
                return false;
            }

            // Check if subscription was already migrated.
            $this->set_local_subscription( $local_subscription );

            $subscription_id = $this->_entity_mapper->get_remote_subscription_id(
                $this->get_local_subscription_id()
            );

            if ( ! FS_Subscription::is_valid_id( $subscription_id ) ) {
                // Subscription was never migrated to Freemius, so no reason to migrate the renewal. It will be lazy migrated upon the module upgrade.
                return false;
            }

            $renewal = $this->migrate_subscription_renewal(
                $subscription_id,
                0,
                true
            );

            return array(
                'payment' => $renewal,
            );
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Entities Linking/Mapping
        #--------------------------------------------------------------------------------

        /**
         * Get remote entity ID.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.0
         *
         * @param string $type
         * @param string $local_id
         *
         * @return number|false
         */
        protected function get_remote_id( $type, $local_id ) {
            return $this->_entity_mapper->get_remote_id( $type, $local_id );
        }

        /**
         * Unlink entities.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.1
         *
         * @param string $type
         * @param string $local_id
         *
         * @return number|false
         */
        protected function unlink_entity( $type, $local_id ) {
            return $this->_entity_mapper->unlink( $type, $local_id );
        }

        /**
         * Link local entity to remote.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.0
         *
         * @param FS_Entity $entity
         * @param string    $local_entity_id
         *
         * @return false|FS_Entity_Map
         */
        protected function link_entity( FS_Entity $entity, $local_entity_id ) {
            return $this->_entity_mapper->link(
                $entity,
                $local_entity_id
            );
        }

        /**
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.0
         *
         * @return false|number
         */
        protected function get_module_id() {
            if ( ! isset( $this->_module_id ) ) {
                $this->_module_id = $this->get_remote_id(
                    FS_Plugin::get_type(),
                    $this->get_local_module_id()
                );
            }

            return $this->_module_id;
        }

        /**
         * @return number
         */
        protected function get_plan_id() {
            return $this->get_remote_id(
                FS_Plan::get_type(),
                $this->get_local_paid_plan_id()
            );
        }

        /**
         * @return number
         */
        protected function get_pricing_id() {
            return $this->get_remote_id(
                FS_Pricing::get_type(),
                $this->get_local_pricing_id()
            );
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Local Data Getters
        #--------------------------------------------------------------------------------

        /**
         * Should return the local module ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string|false
         */
        abstract protected function get_local_module_id();

        /**
         * Should return the local context license ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string
         */
        abstract protected function get_local_license_id();

        /**
         * Should return the local context plugin or theme installation ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param int $index Since 1.1.0 we support a multi-site network migration, hence the new index param.
         *
         * @return string
         */
        abstract protected function get_local_install_id( $index = 0 );

        /**
         * Get installs count.
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @return int
         */
        abstract protected function get_local_installs_count();

        /**
         * Should return the local context customer ID (not the WP user ID).
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string
         */
        abstract protected function get_local_customer_id();

        /**
         * Should return the local context customer's email.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string
         */
        abstract protected function get_local_customer_email();

        /**
         * Should return the local context customer billing details ID (could also be the customer ID).
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string
         */
        abstract protected function get_local_billing_id();

        /**
         * Should return the local pricing ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string
         */
        abstract protected function get_local_pricing_id();

        /**
         * Should return the local plan ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string
         */
        abstract protected function get_local_paid_plan_id();

        /**
         * Should return the local context payment ID.
         * If it's a subscription, then should return the initial payment ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string
         */
        abstract protected function get_local_payment_id();

        /**
         * Check if the license is associated with a subscription.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return bool
         */
        abstract protected function local_is_subscription();

        /**
         * Check if the payment is live (not test/sandbox payment).
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @return bool
         */
        abstract protected function local_is_live_payment();

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
        abstract protected function local_is_live_renewal( $index = 0 );

        /**
         * Check if the payment's amount is positive.
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @return bool
         */
        abstract protected function local_is_positive_payment_amount();

        /**
         * Check if the renewal's amount is positive.
         *
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @param int $index
         *
         * @return bool
         */
        abstract protected function local_is_positive_renewal_amount( $index = 0 );

        /**
         * Should return the local context subscription ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return string|false
         */
        abstract protected function get_local_subscription_id();

        /**
         * Get renewals count. If license isn't associated with a subscription
         * will simply return 0.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return int
         */
        abstract protected function get_local_renewals_count();

        /**
         * Should return the local context specified renewal payment ID.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param int $index
         *
         * @return string
         */
        abstract protected function get_local_subscription_renewal_id( $index = 0 );

        /**
         * Checks if migrating a license that is associated with a bundle.
         *
         * @author Vova Feldman
         * @since  2.0.0
         *
         * @return bool
         */
        abstract public function local_is_bundle();

        #endregion

        #--------------------------------------------------------------------------------
        #region Freemius API
        #--------------------------------------------------------------------------------

        /**
         * Get Freemius SDK with developer's scope.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.0
         */
        protected static function get_api() {
            if ( ! isset( self::$_api ) ) {
                throw new Exception( 'API manager is not set. You have to set the API by calling set_api().' );
            }

            return self::$_api;
        }

        public static function set_api( FS_Api $api ) {
            self::$_api = $api;
        }

        /**
         * Prepend developer scope to API request path.
         *
         * @param string $path
         *
         * @return string
         */
        protected function get_api_path( $path ) {
            return "/plugins/{$this->get_module_id()}/" . ltrim( $path, '/' );
        }

        /**
         * API calls wrapper/alias for cleaner code.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.0
         *
         * @param string $path
         * @param string $method
         * @param array  $params
         *
         * @return array|mixed|string|void
         *
         * @throws Freemius_Exception
         */
        function api_call( $path, $method = 'GET', $params = array() ) {
            if ( 'GET' !== strtoupper( $method ) ) {
                // Hint that API that it's a migration mode.
                $params['is_migration'] = true;
                $params['source']       = $this->_namespace;
            }

            return self::get_api()->call( $this->get_api_path( $path ), $method, $params );
        }

        /**
         * Check if API request resulted an error.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.0
         *
         * @param mixed $result
         *
         * @return bool Is API result contains an error.
         */
        protected static function is_api_error( $result ) {
            return ( is_object( $result ) && isset( $result->error ) ) ||
                   is_string( $result );
        }

        /**
         * Process API response.
         *
         * @param mixed $result
         * @param bool  $exception_on_error
         *
         * @return object
         *
         * @throws Exception
         */
        protected function process_api_response( $result, $exception_on_error = true ) {
            if ( self::is_api_error( $result ) ) {
                // Do something.
                if ( $exception_on_error ) {
                    if ( isset( $result->error ) ) {
                        switch ( $result->error->code ) {
                            case 'install_not_found':
                                $this->unlink_entity(
                                    FS_Install::get_type(),
                                    $this->get_local_install_id()
                                );
                                break;
                        }

                        throw new FS_Endpoint_Exception(
                            'Freemius migration error: ' . $result->error->message . ' API Result: ' . var_export( $result, true ),
                            $result->error->code,
                            $result->error->http
                        );
                    } else {
                        throw new FS_Endpoint_Exception( 'Freemius migration error: ' . var_export( $result, true ) );
                    }
                }
            }

            return $result;
        }

        /**
         * @author Vova Feldman
         *
         * @param number $user_freemius_id
         *
         * @return false|\FS_User
         */
        public function fetch_user_from_freemius_by_id( $user_freemius_id ) {
            $result = $this->api_call( "/users/{$user_freemius_id}.json" );

            if ( $this->log_on_error( $result, "Failed to fetch user ({$user_freemius_id}) from Freemius." ) ) {
                return false;
            }

            return new FS_User( $result );
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Logging
        #--------------------------------------------------------------------------------

        /**
         * Log message.
         *
         * @author Vova Feldman
         *
         * @param string $message
         */
        protected function log( $message ) {
            if ( ! isset( $this->_logger ) ) {
                $this->_logger = FS_Logger::get_logger( WP_FSM__SLUG, WP_FS__DEBUG_SDK, WP_FS__ECHO_DEBUG_SDK );
            }

            $this->_logger->log( $message );
        }

        /**
         * Log failure message.
         *
         * @author Vova Feldman
         *
         * @param string $message
         */
        protected function log_failure( $message ) {
            $this->log( "[FAILURE] {$message}" );
        }

        /**
         * Log success message.
         *
         * @author Vova Feldman
         *
         * @param string $message
         */
        protected function log_success( $message ) {
            $this->log( "[SUCCESS] {$message}" );
        }

        /**
         * If API resulted an error, log it as a failure and return TRUE.
         *
         * @author Vova Feldman
         *
         * @param string|object $api_result
         * @param string        $message
         *
         * @return bool
         */
        protected function log_on_error( $api_result, $message ) {
            if ( self::is_api_error( $api_result ) ) {
                $this->log_failure( "{$message}\n" . var_export( $api_result, true ) );

                return true;
            }

            return false;
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Data Mapping for API
        #--------------------------------------------------------------------------------

        /**
         * Generate customer details from local data for API.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return array
         */
        abstract protected function get_customer_for_api();

        /**
         * Generate customer billing details from local data for API.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return array
         */
        abstract protected function get_customer_billing_for_api();

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
        abstract protected function get_install_for_api( $license_id );

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
        abstract protected function get_installs_for_api( $license_id, array $indexes );

        /**
         * Generate purchase EU VAT details from local data for API.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return array
         */
        abstract protected function get_purchase_vat_for_api();

        /**
         * Generate purchase details from local data for API.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return array
         */
        abstract protected function get_purchase_for_api();

        /**
         * Generate onetime payment details from local data for API.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return array
         */
        abstract protected function get_onetime_payment_for_api();

        /**
         * Generate subscription details from local data for API.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return array
         */
        abstract protected function get_subscription_for_api();

        /**
         * Generate subscription initial payment details from local data for API.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return array
         */
        abstract protected function get_initial_payment_for_api();

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
        abstract protected function get_subscription_renewal_for_api( $index = 0, $renew_license = false );

        #endregion

        #--------------------------------------------------------------------------------
        #region Entities Sync
        #--------------------------------------------------------------------------------

        /**
         * - If customer already migrated, return the user ID.
         * - If customer not yet migrated, return the user object.
         * - If fails, return false.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return false|number|\FS_User
         */
        protected function sync_customer() {
            $local_customer_id = $this->get_local_customer_id();

            $customer_id = $this->get_remote_id(
                FS_User::get_type(),
                $local_customer_id
            );

            if ( FS_Entity::is_valid_id( $customer_id ) ) {
                // Customer already migrated.
                $this->log( "Customer ({$local_customer_id}) already associated with a Freemius user ({$customer_id})." );


                return $customer_id;
            }

            $customer = $this->migrate_customer();

            if ( $customer instanceof FS_User ) {
                $this->log_success( "Customer ({$local_customer_id}) was successfully created on Freemius ({$customer->id})." );

                return $customer;
            }

            if ( is_object( $customer ) &&
                 isset( $customer->error ) &&
                 'user_exist' === $customer->error->code
            ) {
                // A user with an identical email already linked with the current product.
                $local_customer_email = $this->get_local_customer_email();

                $this->log( "A user with an identical email [{$local_customer_email}] already linked with the current module [{$this->get_module_id()}]." );

                // Load user details.
                $result = $this->api_call( '/users.json?' . http_build_query( array(
                        'email' => $local_customer_email,
                    ) ) );

                if ( $this->log_on_error( $result,
                    "Failed to load Freemius user by email [{$local_customer_email}] - skipping license..." )
                ) {
                    return false;
                }

                if ( ! is_array( $result->users ) || 0 === count( $result->users ) ) {
                    $this->log_failure( "Very strange... Failed to find Freemius user by email [{$local_customer_email}] associated with the current plugin." );

                    return false;
                }

                $customer = new FS_User( $result->users[0] );

                return $customer->id;
            }

            $this->log_failure( "Failed creating customer ({$local_customer_id}) on Freemius." );

            return false;
        }

        /**
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @param string $local_subscription_id
         *
         * @return false|number
         */
        public function get_subscription_remote_id( $local_subscription_id ) {
            return $this->get_remote_id(
                FS_Subscription::get_type(),
                $local_subscription_id
            );
        }

        /**
         * - If subscription already migrated, return the subscription ID.
         * - If subscription not yet migrated, return the subscription object.
         * - If fails, return false.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param number $customer_id
         *
         * @return false|number|\FS_Subscription
         */
        protected function sync_subscription( $customer_id ) {
            $this->log( "Starting to handle subscription migration." );

            $local_subscription_id = $this->get_local_subscription_id();

            $subscription_id = $this->get_subscription_remote_id( $local_subscription_id );

            $subscription = false;

            if ( FS_Entity::is_valid_id( $subscription_id ) ) {
                $this->log( "Subscription ({$local_subscription_id}) already associated with a Freemius subscription ({$subscription_id})." );
            } else {
                $subscription = $this->migrate_subscription( $customer_id );

                if ( $this->log_on_error( $subscription, "Failed subscribing user ({$customer_id})." ) ) {
                    return false;
                }

                $subscription_id = $subscription->id;

                $this->log_success( "Subscription ({$local_subscription_id}) was successfully created on Freemius ({$subscription_id})." );
            }

            $local_payment_id = $this->get_local_payment_id();
            $payment_id       = $this->get_remote_id(
                FS_Payment::get_type(),
                $local_payment_id
            );

            if ( FS_Entity::is_valid_id( $payment_id ) ) {
                $this->log( "Subscription initial payment ({$local_payment_id}) already associated with a Freemius payment ({$payment_id})." );
            } else {
                $payment = $this->migrate_subscription_initial_payment( $subscription_id );

                if ( $this->log_on_error( $payment,
                    "Failed creating subscription's initial payment ({$local_payment_id}) on Freemius." )
                ) {
                    return false;
                }

                $payment_id = $payment->id;

                $this->log_success( "Subscription initial payment ({$local_payment_id}) was successfully created on Freemius ({$payment_id})." );
            }

            for ( $i = 0, $len = $this->get_local_renewals_count(); $i < $len; $i ++ ) {
                $local_renewal_id = $this->get_local_subscription_renewal_id( $i );
                $renewal_id       = $this->get_remote_id(
                    FS_Payment::get_type(),
                    $local_renewal_id
                );

                if ( FS_Entity::is_valid_id( $renewal_id ) ) {
                    $this->log( "Subscription renewal payment ({$local_renewal_id}) already associated with a Freemius payment ({$renewal_id})." );
                } else {
                    $renewal = $this->migrate_subscription_renewal( $subscription_id, $i );

                    if ( $this->log_on_error( $renewal,
                        "Failed creating subscription's renewal payment ({$local_renewal_id}) on Freemius." )
                    ) {
                        return false;
                    }

                    $renewal_id = $renewal->id;

                    $this->log_success( "Subscription renewal payment ({$local_renewal_id}) was successfully created on Freemius ({$renewal_id})." );
                }
            }

            return is_object( $subscription ) ?
                $subscription :
                $subscription_id;
        }

        /**
         * - If purchase already migrated, return the payment ID.
         * - If purchase not yet migrated, return the payment object.
         * - If fails, return false.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param number $customer_id
         *
         * @return false|number|\FS_Payment
         */
        protected function sync_purchase( $customer_id ) {
            $this->log( "Starting to handle purchase migration." );

            $local_payment_id = $this->get_local_payment_id();

            $payment_id = $this->get_remote_id(
                FS_Payment::get_type(),
                $local_payment_id
            );

            if ( FS_Entity::is_valid_id( $payment_id ) ) {
                $this->log( "Purchase payment ({$local_payment_id}) already associated with a Freemius payment ({$payment_id})." );

                return $payment_id;
            }

            $payment = $this->migrate_purchase( $customer_id );

            if ( $this->log_on_error( $payment,
                "Failed creating purchase payment ({$local_payment_id}) on Freemius." )
            ) {
                return false;
            }

            $payment_id = $payment->id;

            $this->log_success( "Purchase payment ({$local_payment_id}) was successfully created on Freemius ({$payment_id})." );

            return $payment;
        }

        /**
         * - If install already migrated, return the install ID.
         * - If install not yet migrated, return the install object.
         * - If fails, return false.
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param number $customer_id
         * @param number $license_id
         *
         * @return false|mixed[]
         */
        protected function sync_installs( $customer_id, $license_id ) {
            $installs_or_ids             = array();
            $installs_indexes_to_migrate = array();

            for ( $i = 0, $len = $this->get_local_installs_count(); $i < $len; $i ++ ) {
                $local_install_id = $this->get_local_install_id( $i );

                $install_id = $this->get_remote_id(
                    FS_Install::get_type(),
                    $local_install_id
                );

                if ( ! FS_Entity::is_valid_id( $install_id ) ) {
                    $installs_indexes_to_migrate[] = $i;
                } else {
                    // Install already migrated.
                    $this->log( "Install ({$local_install_id}) already associated with a Freemius install ({$install_id})." );

                    // Get license associated with the install.
                    $install_current_license_id = $this->get_remote_id(
                        FS_License::get_type(),
                        $local_install_id
                    );

                    if ( $license_id != $install_current_license_id ) {
                        /**
                         * Migration request from a site that was already migrated
                         * before with the exact same module and user context, but
                         * this time with a different license.
                         *
                         * Therefore, deactivate the previous license and activate the new one.
                         */
                        $this->migrate_new_license_activation( $install_id, $local_install_id, $license_id );
                    }

                    $installs_or_ids[ $i ] = $install_id;
                }
            }

            if ( ! empty( $installs_indexes_to_migrate ) ) {
                $installs = $this->migrate_installs( $customer_id, $license_id, $installs_indexes_to_migrate );

                if ( $this->log_on_error( $installs, "Failed creating module installs on Freemius." )
                ) {
                    return false;
                }

                for ( $j = 0, $len_j = count( $installs ); $j < $len_j; $j ++ ) {
                    $installs_or_ids[ $installs_indexes_to_migrate[ $j ] ] = $installs[ $j ];

                    $local_install_id = $this->get_local_install_id( $installs_indexes_to_migrate[ $j ] );

                    $this->log_success( "Successfully created install ({$local_install_id}) on Freemius ({$installs[ $j ]->id})." );
                }
            }

            return $installs_or_ids;
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Entities Migration (the actual API calls)
        #--------------------------------------------------------------------------------

        /**
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @return \FS_User
         */
        protected function migrate_customer() {
            $result = $this->api_call(
                "/users.json",
                'post',
                $this->get_customer_for_api()
            );

            $user = new FS_User( $this->process_api_response( $result ) );

            // Link the local and remote customer.
            $this->link_entity( $user, $this->get_local_customer_id() );

            // Link the local combination of module/customer to remote customer.
//			$this->link_entity( $user, $this->get_local_module_id() . ':' . $this->get_local_customer_id() );

            return $user;
        }

        /**
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param number $customer_id
         *
         * @return \FS_Billing
         */
        protected function migrate_customer_billing( $customer_id ) {
            // Check if customer already have billing details on Freemius.
            $result = $this->api_call( "/users/{$customer_id}/billing.json" );

            if ( ! is_object( $result ) ||
                 ! isset( $result->error ) ||
                 404 != $result->error->http
            ) {
                // Migrate customer billing only if not yet exist.
                return null;
            }

            $result = $this->api_call(
                "/users/{$customer_id}/billing.json",
                'put',
                $this->get_customer_billing_for_api()
            );

            $billing = new FS_Billing( $this->process_api_response( $result ) );

            $this->link_entity( $billing, $this->get_local_billing_id() );

            return $billing;
        }

        /**
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param number $customer_id
         * @param number $license_id
         *
         * @return \FS_Install
         */
        protected function migrate_install( $customer_id, $license_id ) {

            // Link site to install.
            $result = $this->api_call(
                "/users/{$customer_id}/installs.json",
                'post',
                $this->get_install_for_api( $license_id )
            );

            $install = new FS_Install( $this->process_api_response( $result ) );

            $local_install_id = $this->get_local_install_id();

            // Link the install to the remote one.
            $this->link_entity( $install, $local_install_id );

            /**
             * Also link the install to the remote license for cases when
             * the same install tries to migrate a different license.
             */
            $license     = new FS_License();
            $license->id = $license_id;
            $this->link_entity( $license, $local_install_id );

            return $install;
        }

        /**
         * @author Vova Feldman
         * @since  1.1.0
         *
         * @param number $customer_id
         * @param number $license_id
         * @param array  $indexes
         *
         * @return \FS_Install[]
         */
        protected function migrate_installs( $customer_id, $license_id, array $indexes ) {

            // Link site to install.
            $result = $this->process_api_response( $this->api_call(
                "/users/{$customer_id}/installs.json",
                'post',
                $this->get_installs_for_api( $license_id, $indexes )
            ) );

            $installs = $result->installs;

            for ( $i = 0, $len = count( $installs ); $i < $len; $i ++ ) {
                $installs[ $i ] = new FS_Install( $installs[ $i ] );

                $local_install_id = $this->get_local_install_id( $indexes[ $i ] );

                // Link the install to the remote one.
                $this->link_entity( $installs[ $i ], $local_install_id );

                /**
                 * Also link the install to the remote license for cases when
                 * the same install tries to migrate a different license.
                 */
                $license     = new FS_License();
                $license->id = $license_id;
                $this->link_entity( $license, $local_install_id );
            }

            return $installs;
        }

        /**
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param string $install_id
         * @param string $local_install_id
         * @param string $license_id
         *
         * @return \FS_License
         */
        protected function migrate_new_license_activation( $install_id, $local_install_id, $license_id ) {
            $result = $this->api_call(
                "/installs/{$install_id}/licenses/{$license_id}.json",
                'put'
            );

            $license = new FS_License( $this->process_api_response( $result ) );

            // Link local install to new license.
            $this->link_entity( $license, $local_install_id );

            return $license;
        }

        /**
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param number $customer_id
         *
         * @return \FS_Payment
         */
        protected function migrate_purchase( $customer_id ) {
            $result = $this->api_call(
                "/users/{$customer_id}/plans/{$this->get_plan_id()}/pricing/{$this->get_pricing_id()}.json",
                'post',
                $this->get_purchase_for_api()
            );

            $payment = new FS_Payment( $this->process_api_response( $result ) );

            $this->link_entity( $payment, $this->get_local_payment_id() );

            // Link license.
            $license     = new FS_License();
            $license->id = $payment->license_id;
            $this->link_entity( $license, $this->get_local_license_id() );

            return $payment;
        }

        /**
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param number $customer_id
         *
         * @return \FS_Subscription
         */
        protected function migrate_subscription( $customer_id ) {
            $result = $this->api_call(
                "/users/{$customer_id}/plans/{$this->get_plan_id()}/pricing/{$this->get_pricing_id()}.json",
                'post',
                $this->get_subscription_for_api()
            );

            $subscription = new FS_Subscription( $this->process_api_response( $result ) );

            $this->link_entity( $subscription, $this->get_local_subscription_id() );

            // Link license.
            $license     = new FS_License();
            $license->id = $subscription->license_id;
            $this->link_entity( $license, $this->get_local_license_id() );

            return $subscription;
        }

        /**
         * Migrate any subscription payment (initial or renewal).
         *
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param number $subscription_id
         * @param array  $payment_data
         * @param string $local_payment_id
         *
         * @return \FS_Payment
         */
        private function migrate_subscription_payment( $subscription_id, $payment_data, $local_payment_id ) {
            $result = $this->api_call(
                "/subscriptions/{$subscription_id}/payments.json",
                'post',
                $payment_data
            );

            $payment = new FS_Payment( $this->process_api_response( $result ) );

            $this->link_entity( $payment, $local_payment_id );

            return $payment;
        }

        /**
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param number $subscription_id
         *
         * @return \FS_Payment
         */
        protected function migrate_subscription_initial_payment( $subscription_id ) {
            return $this->migrate_subscription_payment(
                $subscription_id,
                $this->get_initial_payment_for_api(),
                $this->get_local_payment_id()
            );
        }

        /**
         * @author Vova Feldman
         * @since  1.0.0
         *
         * @param number $subscription_id
         * @param int    $index
         * @param bool   $renew_license
         *
         * @return \FS_Payment
         */
        protected function migrate_subscription_renewal( $subscription_id, $index, $renew_license = false ) {
            return $this->migrate_subscription_payment(
                $subscription_id,
                $this->get_subscription_renewal_for_api( $index, $renew_license ),
                $this->get_local_subscription_renewal_id( $index )
            );
        }

        #endregion
    }