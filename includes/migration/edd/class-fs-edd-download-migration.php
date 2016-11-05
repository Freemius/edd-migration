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
	 * Assumptions:
	 *  - Currently assumes that the download is a PLUGIN, not a THEME.
	 *
	 * Class FS_EDD_Download_Migration
	 */
	class FS_EDD_Download_Migration extends FS_Module_Migration_Abstract {
		/**
		 * @var EDD_Download
		 */
		private $_edd_download;

		/**
		 * @var array<int,array<string,mixed>>
		 */
		private $_edd_prices = array();

		/**
		 * @var int
		 */
		private $_edd_free_price_id;

		/**
		 * @var bool
		 */
		private $_edd_has_paid_plan = false;

		/**
		 * @var array
		 */
		private $_edd_paid_plan_pricing = array();

		#--------------------------------------------------------------------------------
		#region Init
		#--------------------------------------------------------------------------------

		function __construct(
			FS_Developer $developer,
			$module,
			EDD_Download $download
		) {
			$this->init( WP_FS__NAMESPACE_EDD, $developer, $module );

			$this->_edd_download = $download;

			if ( $download->has_variable_prices() ) {
				$this->_edd_prices = $download->get_prices();
			} else {
				if ( class_exists( 'EDD_Recurring' ) ) {
					$recurring = EDD_Recurring::is_recurring( $download->ID );
					$period    = EDD_Recurring::get_period_single( $download->ID );
				} else {
					$recurring = false;
					$period    = 'never';
				}

				$license_limit = get_post_meta( $download->ID, '_edd_sl_limit', true );

				$this->_edd_prices = array(
					// Set the EDD price ID as ZERO when the download doesn't have variable prices.
					0 => array(
						'recurring'     => $recurring ? 'yes' : 'no',
						'period'        => $period,
						'license_limit' => is_numeric( $license_limit ) ? $license_limit : 0,
						'amount'        => $download->get_price(),
					)
				);
			}

			$this->process_local_pricing();
		}

		#endregion

		#--------------------------------------------------------------------------------
		#region Helper Methods
		#--------------------------------------------------------------------------------

		/**
		 * Get billing cycle out of the variable price.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param array $edd_price
		 *
		 * @return string
		 */
		private function get_billing_cycle( $edd_price ) {
			$billing_cycle = 'lifetime';

			if ( ! empty( $edd_price['recurring'] ) &&
			     'yes' === $edd_price['recurring'] &&
			     ! empty( $edd_price['period'] ) &&
			     'never' !== $edd_price['period']
			) {
				switch ( $edd_price['period'] ) {
					case 'year':
						$billing_cycle = 'annual';
						break;
					case 'month':
						$billing_cycle = 'monthly';
						break;
					default:
						// @todo Throw an error when billing cycle is not supported.
						$billing_cycle = 'monthly';
						break;
				}
			}

			return $billing_cycle;
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param number $id
		 *
		 * @return string
		 */
		private function get_edd_unique_price_id( $id ) {
			return $this->_edd_download->ID . ':' . $id;
		}

		/**
		 * Aggregate EDD prices based on license limits.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 */
		private function process_local_pricing() {
			$edd_paid_plan_pricing_by_licenses = array();

			foreach ( $this->_edd_prices as $id => &$edd_price ) {
				$licenses = (int) $edd_price['license_limit'];

				// Add price ID to data.
				$edd_price['_id'] = $id;

				// Check if free plan.
				$amount = floatval( $edd_price['amount'] );

				if ( .0 >= $amount ) {
					$this->_edd_free_price_id = $this->get_edd_unique_price_id( $id );
					continue;
				}

				if ( ! isset( $edd_paid_plan_pricing_by_licenses[ $licenses ] ) ) {
					$edd_paid_plan_pricing_by_licenses[ $licenses ] = array();
				}

				// Paid plan.
				$edd_paid_plan_pricing_by_licenses[ $licenses ][] = $edd_price;

				$this->_edd_has_paid_plan = true;
			}

			foreach ( $edd_paid_plan_pricing_by_licenses as $licenses => $edd_prices ) {
				$pricing = array(
					'edd_prices_ids' => array()
				);

				$pricing['licenses'] = ( $licenses > 0 ) ? $licenses : null;

				foreach ( $edd_prices as $edd_price ) {
					$amount = floatval( $edd_price['amount'] );

					$billing_cycle                     = $this->get_billing_cycle( $edd_price );
					$pricing["{$billing_cycle}_price"] = $amount;

					// We need to store EDD price IDs list to link them with the pricing.
					$pricing['edd_prices_ids'][] = $this->get_edd_unique_price_id( $edd_price['_id'] );
				}

				$this->_edd_paid_plan_pricing[] = $pricing;
			}
		}

		#endregion

		#--------------------------------------------------------------------------------
		#region EDD Required Data Getters
		#--------------------------------------------------------------------------------

		/**
		 * Get local module ID.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return bool
		 */
		protected function get_local_module_id() {
			return $this->_edd_download->ID;
		}

		/**
		 * Check if download has a free plan.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return bool
		 */
		protected function local_has_free_plan() {
			return isset( $this->_edd_free_price_id );
		}

		/**
		 * Get free price ID as the plan ID since EDD doesn't have a concept of plans.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return bool
		 */
		protected function get_local_free_plan_id() {
			return $this->_edd_download->ID . ':' . $this->_edd_free_price_id . 'free';
		}

		/**
		 * Check download has any price that is not free.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return bool
		 */
		protected function local_has_paid_plan() {
			return $this->_edd_has_paid_plan;
		}

		/**
		 * Return all the prices IDs that are paid and belong to the
		 * same plan (identical feature-set).
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param int $index
		 *
		 * @return string[]
		 */
		protected function get_local_paid_plan_pricing_ids( $index ) {
			return $this->_edd_paid_plan_pricing[ $index ]['edd_prices_ids'];
		}

		/**
		 * Get paid plan associated pricing objects count.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return bool
		 */
		protected function get_local_paid_plan_pricing_count() {
			return count( $this->_edd_paid_plan_pricing );
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param int|null $licenses
		 *
		 * @return int|false
		 */
		protected function get_local_paid_plan_pricing_index_by_licenses( $licenses ) {
			$index = 0;

			foreach ( $this->_edd_paid_plan_pricing as $edd_pricing ) {
				if ( $licenses === $edd_pricing['licenses'] ) {
					return $index;
				}

				$index ++;
			}

			return false;
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param int $index
		 *
		 * @return int|null
		 */
		protected function get_local_paid_plan_pricing_licenses( $index ) {
			return $this->_edd_paid_plan_pricing[ $index ]['licenses'];
		}

		#endregion

		#--------------------------------------------------------------------------------
		#region Data Mapping for API
		#--------------------------------------------------------------------------------

		/**
		 * Generate module details from local data for API.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return array
		 */
		protected function get_module_for_api() {
			$download_post = WP_Post::get_instance( $this->_edd_download->get_ID() );

			return array(
				'slug'           => $download_post->post_name,
				'title'          => $this->_edd_download->get_name(),
				'type'           => 'plugin',
				'business_model' => $this->get_local_business_model(),
				'created_at'     => $download_post->post_date_gmt,
			);
		}

		/**
		 * Generate free plan details from local data for API.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return array
		 */
		protected function get_free_plan_for_api() {
			return array(
				'name'  => 'free',
				'title' => 'Free',
			);
		}

		/**
		 * Generate paid plan details from local data for API.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return array
		 */
		protected function get_paid_plan_for_api() {
			return array(
				'name'              => 'pro',
				'title'             => 'Pro',
				// By default create non-blocking plan.
				'is_block_features' => false,
				'is_https_support'  => true,
				// By default allow localhost activations.
				'is_free_localhost' => true,
				// Set paid plan as featured.
				'is_featured'       => true,
			);
		}

		/**
		 * Generate paid plan pricing details from local data for API.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @param int $index
		 *
		 * @return array
		 */
		protected function get_paid_plan_pricing_for_api( $index ) {
			// Clone.
			$pricing = $this->_edd_paid_plan_pricing[ $index ];

			unset( $pricing['edd_prices_ids'] );

			return $pricing;
		}

		#endregion
	}