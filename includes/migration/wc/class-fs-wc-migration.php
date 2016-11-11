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

	class FS_WC_Migration extends FS_Migration_Abstract {

		/** @var FS_WC_Migration_Endpoint Instance */
		protected $ep;

		protected $_is_subscription = false;

		#region WC Entities

		/** @var WC_Product Current product instance */
		protected $_product;

		/** @var WP_Post */
		protected $_edd_license;

		/** @var null Not implemented */
		protected $_wc_subscription;


		/** @var WC_Order Current order instance */
		protected $_wc_order;

		/**
		 * @var WC_Payment[]
		 */
		protected $_edd_renewals = array();

		/**
		 * @var array
		 */
		protected $_edd_install_data;

		#endregion

		#--------------------------------------------------------------------------------
		#region Singleton
		#--------------------------------------------------------------------------------

		/** @var FS_WC_Migration Instance */
		private static $_instance;

		/**
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param FS_WC_Migration_Endpoint $endpoint
		 *
		 * @return FS_WC_Migration
		 */
		public static function instance( $endpoint ) {
			if ( ! isset( self::$_instance ) ) {
				self::$_instance = new FS_WC_Migration( $endpoint );
			}

			return self::$_instance;
		}

		#endregion

		#--------------------------------------------------------------------------------
		#region Init
		#--------------------------------------------------------------------------------

		private function __construct( $endpoint ) {
			$this->init( WP_FS__NAMESPACE_WC );

			$this->ep = $endpoint;

			$this->load_edd_entities();
		}

		/**
		 * Pre-load all required WC entities for complete migration process.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 */
		private function load_edd_entities() {

			$this->_product = wc_get_product( $this->ep->order->product_id );
			$this->_wc_order  = new WC_Order( $this->ep->order->order_id ); //@TODO Replace with WC equivalent for WC_Payment( $initial_payment_id );

			return; // Subscriptions not implemented

			$this->_wc_subscription = $this->get_edd_subscription(
				$download_id,
				$initial_payment_id
			);

			if ( is_object( $this->_wc_subscription ) ) {
				/**
				 * Load renewals data.
				 *
				 * @var WP_Post[] $edd_renewal_posts
				 */
				$edd_renewal_posts = $this->_wc_subscription->get_child_payments();

				if ( is_array( $edd_renewal_posts ) && 0 < count( $edd_renewal_posts ) ) {
					foreach ( $edd_renewal_posts as $edd_renewal_post ) {
						$this->_edd_renewals[] = new WC_Payment( $edd_renewal_post->ID );
					}
				}
			}
		}

		/**
		 * Get WC subscription entity when license associated with a subscription.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param int $download_id
		 * @param int $parent_payment_id
		 *
		 * @return \WC_Subscription|false
		 */
		private function get_edd_subscription( $download_id, $parent_payment_id ) {
			throw new Exception( 'Not implemented' );

			if ( ! class_exists( 'WC_Recurring' ) ) {
				// WC recurring payments add-on isn't installed.
				return false;
			}

			/**
			 * We need to make sure the singleton is initiated, otherwise,
			 * WC_Subscriptions_DB will not be found because the inclusion
			 * of the relevant file is executed in the instance init.
			 */
			WC_Recurring::instance();

			$subscriptions_db = new WC_Subscriptions_DB();

			$edd_subscriptions = $subscriptions_db->get_subscriptions( array(
				'product_id'        => $download_id,
				'parent_payment_id' => $parent_payment_id
			) );

			return ( is_array( $edd_subscriptions ) && 0 < count( $edd_subscriptions ) ) ?
				$edd_subscriptions[0] :
				false;
		}

		#endregion

		#--------------------------------------------------------------------------------
		#region Local Data Getters
		#--------------------------------------------------------------------------------


		/**
		 * Init install migration data before
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param array $local_install
		 */
		protected function set_local_install( $local_install ) {
			$this->_edd_install_data = $local_install;
		}

		/**
		 * Local module ID.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return string
		 */
		protected function get_local_module_id() {
			return $this->ep->order->parent_product_id;
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
			return $this->ep->order->order_id;
		}

		/**
		 * Local install ID.
		 *
		 * There's no module install concept nor entity in WC, therefore,
		 * generate a unique ID based on the download ID and site canonized URL.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return string
		 */
		protected function get_local_install_id() {
			/**
			 * Limit the ID to 32 chars since the entity mapping
			 * local_id column is limited to 32 chars.
			 */
			return $this->ep->site_uid;
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
			return $this->ep->order->user_id;
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
			return $this->ep->order->license_email;
		}

		/**
		 * Local billing details ID.
		 *
		 * WC doesn't have a billing entity so associate it with the customer ID.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return string
		 */
		protected function get_local_billing_id() {
			return $this->ep->order->user_id;
		}

		/**
		 * Local pricing ID.
		 *
		 * WC doesn't have a unique pricing ID.
		 *   1. When WC SL is installed and the license is associated
		 *      with a variable price, use the download ID with the variable
		 *      price ID ("{download->id}:{price->id}").
		 *   2. When WC SL is NOT installed, use "{download->id}:0" as the pricing ID.
		 *   3. When WC SL is installed but it's a legacy license that is NOT associated
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

			if ( $this->_product->is_type( 'variable' ) ) {

				$price_id = (int) self::$_edd_sl->get_price_id( $this->_edd_license->ID );

				if ( 0 === $price_id ) {
					/**
					 * Couldn't find matching price ID which means it's a legacy license.
					 *
					 * Fetch the price ID that has the same license activations quota.
					 */
					$edd_prices = $this->_product->get_prices();

					$edd_license_activations_limit = self::$_edd_sl->get_license_limit( $this->_product->ID,
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

			return $this->ep->order->parent_product_id . ':' . $this->ep->order->product_id;
		}

		protected function get_local_paid_plan_id() {
			return $this->get_local_pricing_id();
		}

		/**
		 * Local payment ID. When subscription return the initial payment ID.
		 *
		 * Since WC's initial payment is associated to a cart and can contain
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
			return 'wc_paid_' . $this->ep->order->product_id . '_' . $this->ep->order->order_id;
		}

		/**
		 * Check if the license is associated with a subscription.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return false
		 */
		protected function local_is_subscription() {
			return false;
		}

		/**
		 * Local subscription ID.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return null No subscriptions
		 */
		protected function get_local_subscription_id() {
			return null;
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
			return 0;
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
			return null;
		}

		#endregion

		#--------------------------------------------------------------------------------
		#region Data Mapping
		#--------------------------------------------------------------------------------

		#region Helper Methods

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
			$user_info = $this->_wc_order->get_address();

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
			} else if ( ! empty( $this->ep->order->user_id ) ) {
				// Enrich data with customer's address.
				$customer_address = get_user_meta( $this->ep->order->user_id, '_edd_user_address', true );

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
		 * Generate payment gross and tax for API based on given WC payment.
		 *
		 * When initial payment associated with a cart that have multiple products,
		 * find the gross and tax for the product that is associated with the context
		 * license.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param WC_Order $order
		 *
		 * @return array
		 */
		private function get_payment_gross_and_tax_for_api( WC_Order $order ) {
			$order_item = $order->get_items();

			$gross_and_vat = array(
				'gross'	=> 0,
				'vat'	=> 0,
			);

			foreach( $order_item as $product ) {
				if ( $product['product_id'] == $this->ep->order->product_id ) {
					$gross_and_vat['gross']	= $product['line_total'];
					$gross_and_vat['vat']	= $product['line_tax'];
				}
			}

			return $gross_and_vat;
		}

		/**
		 * Get license expiration in UTC datetime.
		 * If it's a lifetime license, return null.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param WC_Order $order
		 *
		 * @return null|string
		 */
		private function get_local_license_expiration( $order = null ) {
			if ( ! $order ) $order = $this->_wc_order;

			$time = strtotime( $order->order_date );

			$timezone = date_default_timezone_get();

			if ( 'UTC' !== $timezone ) {
				// Temporary change time zone.
				date_default_timezone_set( 'UTC' );
			}

			// Expire 1 year after purchase
			$formatted_license_expiration = date( WP_FSM__LOG_DATETIME_FORMAT, $time + YEAR_IN_SECONDS );

			if ( 'UTC' !== $timezone ) {
				// Revert timezone.
				date_default_timezone_set( $timezone );
			}

			return $formatted_license_expiration;
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
		 * Get billing cycle in months.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return int
		 */
		private function get_local_billing_cycle_in_months() {
			switch ( $this->_wc_subscription->period ) {
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
		 * Get WC payment's transaction ID. If empty, use "edd_payment_{payment_id}".
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param WC_Order $order
		 *
		 * @return string
		 */
		private function get_payment_transaction_id( WC_Order $order = null ) {
			if ( ! $order ) {
				$order = $this->_wc_order;
			}
			return "wc_payment_{$order->id}";
		}

		/**
		 * Get payment data for API.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param WC_Order $order
		 *
		 * @return array
		 */
		private function get_payment_by_edd_for_api( WC_Order $order = null ) {
			if ( ! $order ) {
				$order = $this->_wc_order;
			}
			$payment                        = array();
			$payment['processed_at']        = $order->order_date;
			$payment['payment_external_id'] = $this->get_payment_transaction_id( $order );

			$payment = array_merge( $payment, $this->get_payment_gross_and_tax_for_api( $order ) );

			return $payment;
		}

		/**
		 * Generate site's URL based on how WC stores the URLs in the
		 * license post meta ('_edd_sl_sites').
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param string $url
		 *
		 * @return string
		 */
		private function get_edd_canonized_site_home_url( $url = '' ) {
			if ( empty( $url ) ) {
				$url = ! empty( $this->_edd_install_data['url'] ) ?
					$this->_edd_install_data['url'] :
					'';
			}

			if ( empty( $url ) ) {

				// Attempt to grab the URL from the user agent if no URL is specified
				$domain = array_map( 'trim', explode( ';', $_SERVER['HTTP_USER_AGENT'] ) );
				$url    = trim( $domain[1] );

			}

			return trailingslashit( self::$_edd_sl->clean_site_url( $url ) );
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
			$quota = (int) $this->ep->order->_api_activations_parent;

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
			$customer['email']                   = $this->ep->order->license_email;
			$customer['name']                    = $this->_wc_order->billing_first_name . ' ' . $this->_wc_order->billing_last_name;
			$customer['is_verified']             = true;
			$customer['send_verification_email'] = false; // Do NOT send verification emails.
			$customer['password']                = wp_generate_password( 8 ); // Generate random 8 char pass for FS.

			$ip = $this->_wc_order->customer_ip_address;
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

			$billing          = array();
			$billing['first'] = $this->_wc_order->billing_first_name;
			$billing['last']  = $this->_wc_order->billing_last_name;
			$billing['email'] = $this->_wc_order->billing_email;

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
			$install['url']                          = $this->_edd_install_data['site_url'];
			$install['version']                      = $this->_edd_install_data['plugin_version'];
			$install['is_premium']                   = $this->_edd_install_data['is_premium'];
			$install['is_active']                    = $this->_edd_install_data['is_active'];
			$install['is_uninstalled']               = $this->_edd_install_data['is_uninstalled'];
			$install['uid']                          = $this->_edd_install_data['site_uid'];
			$install['title']                        = $this->_edd_install_data['site_name'];
			$install['language']                     = $this->_edd_install_data['language'];
			$install['charset']                      = $this->_edd_install_data['charset'];
			$install['platform_version']             = $this->_edd_install_data['platform_version'];
			$install['programming_language_version'] = $this->_edd_install_data['php_version'];
			$install['license_id']                   = $license_id;

			$install_at = $this->ep->order->_purchase_time;

			if ( ! empty( $install_at ) ) {
				$install['installed_at'] = $install_at;
			}

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
			$purchase['payment_method']       = $this->_wc_order->payment_method;
			$purchase['customer_external_id'] = 'wc_customer_' . $this->ep->order->user_id;
			$purchase['license_key']          = substr( $this->ep->order->api_key, 6 );
			$purchase['license_quota']        = $this->get_license_quota(); // Preserve license activations limit.
			$purchase['processed_at']         = $this->_wc_order->order_date;
			$purchase['payment_external_id']  = $this->get_payment_transaction_id();

			// Set license expiration if not a lifetime license via a purchase.
			$license_expiration = $this->get_local_license_expiration();
			if ( null !== $license_expiration ) {
				$purchase['license_expires_at'] = $license_expiration;
			}

			if ( ! $purchase['payment_method'] ) {
				$purchase['payment_method'] = 'cc';
			}

			$purchase = array_merge( $purchase, $this->get_payment_gross_and_tax_for_api( $this->_wc_order ) );

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
		 * @return null
		egion Helper Methods		 */
		protected function get_subscription_for_api() {

			throw new Exception( 'Not implemented' );

			$subscription                             = array();
			$subscription['payment_method']           = $this->get_local_subscription_gateway();
			$subscription['billing_cycle']            = $this->get_local_billing_cycle_in_months();
			$subscription['subscription_external_id'] = ! empty( $this->_wc_subscription->profile_id ) ?
				$this->_wc_subscription->profile_id :
				'edd_subscription_' . $this->_wc_subscription->id;
			$subscription['customer_external_id']     = 'edd_customer_' . $this->_edd_customer->id;
			$subscription['next_payment']             = $this->_wc_subscription->get_expiration();
			$subscription['processed_at']             = $this->_wc_subscription->created;
			$subscription['license_key']              = self::$_edd_sl->get_license_key( $this->_edd_license->ID ); // Preserve the same keys.
			$subscription['license_quota']            = $this->get_license_quota(); // Preserve license activations limit.

			/**
			 * Set license expiration for cases when the subscription's next
			 * payment isn't matching the license expiration.
			 *
			 * Also allow migration of a lifetime license with a subscription.
			 */
			$subscription['license_expires_at'] = $this->get_local_license_expiration();

			// @todo Enrich API to accept is_cancelled as an optional argument during migration.
			if ( 'cancelled' === $this->_wc_subscription->get_status() ) {
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
				$transaction_id = $this->_wc_subscription->get_transaction_id();
			}

			if ( empty( $transaction_id ) ) {
				// Fallback to WC payment ID.
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
		 * @param int $index Renewal payment index.
		 *
		 * @return array
		 */
		protected function get_subscription_renewal_for_api( $index = 0 ) {
			throw new Exception( 'Not implemented' );

			$renewal = $this->get_payment_by_edd_for_api( $this->_edd_renewals[ $index ] );

			// Don't extend license on renewal.
			$renewal['is_extend_license'] = false;

			return $renewal;
		}

		#endregion
	}
