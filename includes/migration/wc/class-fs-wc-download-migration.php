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
	 * Class FS_WC_Download_Migration
	 */
	class FS_WC_Download_Migration extends FS_Module_Migration_Abstract {
		/**
		 * @var WC_Product_Variable|WC_Product
		 */
		private $_product;

		/**
		 * @var array<int,array<string,mixed>>
		 */
		private $_variations = array();

		/**
		 * @var int
		 */
		private $_free_price_id;

		/**
		 * @var bool
		 */
		private $_has_paid_plan = false;

		/**
		 * @var array
		 */
		private $_plan_pricing = array();

		#--------------------------------------------------------------------------------
		#region Init
		#--------------------------------------------------------------------------------

		function __construct( FS_Developer $developer, $module, WC_Product $product ) {
			$this->_product = $product;

			$this->init( WP_FS__NAMESPACE_WC, $developer, $module );

			if ( $product->is_type('variable') ) {
				$this->_variations = $this->_product->get_available_variations();
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
		 * @todo Allow user to change this somehow
		 *
		 * @return string
		 */
		private function get_billing_cycle( $id ) {
			return 'annual';
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param number $id
		 *
		 * @return string
		 */
		private function get_wc_unique_price_id( $id ) {
			return $this->_product->id . ':' . $id;
		}

		/**
		 * Aggregate WC prices based on license limits.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 */
		private function process_local_pricing() {
			$plan_pricing = array();

			foreach ( $this->_variations as &$variation ) {
				$variation['_id'] = $id = $variation['variation_id'];

				$licenses = (int) get_post_meta( $id, '_api_activations', true );


				// Check if free plan.
				$amount = floatval( $variation['display_regular_price'] );
				if ( .0 >= $amount ) {
					$this->_free_price_id = $this->get_wc_unique_price_id( $id );
					continue;
				}

				if ( ! isset( $plan_pricing[ $licenses ] ) ) {
					$plan_pricing[ $licenses ] = array();
				}

				// Paid plan.
				$plan_pricing[ $licenses ][] = $variation;

				$this->_has_paid_plan = true;
			}

			foreach ( $plan_pricing as $licenses => $variations ) {
				$pricing = array(
					'wc_prices_ids' => array()
				);

				$pricing['licenses'] = ( $licenses > 0 ) ? $licenses : null;

				foreach ( $variations as $variation ) {
					$amount = floatval( $variation['display_regular_price'] );

					$billing_cycle                     = $this->get_billing_cycle( $variation['_id'] );
					$pricing["{$billing_cycle}_price"] = $amount;

					// We need to store WC price IDs list to link them with the pricing.
					$pricing['wc_prices_ids'][] = $this->get_wc_unique_price_id( $variation['_id'] );
				}

				$this->_plan_pricing[] = $pricing;
			}
		}

		#endregion

		#--------------------------------------------------------------------------------
		#region WC Required Data Getters
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
			return $this->_product->id;
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
			return isset( $this->_free_price_id );
		}

		/**
		 * Get free price ID as the plan ID since WC doesn't have a concept of plans.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return bool
		 */
		protected function get_local_free_plan_id() {
			return $this->_product->id . ':' . $this->_free_price_id . 'free';
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
			return $this->_has_paid_plan;
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
			return $this->_plan_pricing[ $index ]['wc_prices_ids'];
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
			return count( $this->_plan_pricing );
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

			foreach ( $this->_plan_pricing as $pricing ) {
				if ( $licenses === $pricing['licenses'] ) {
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
			return $this->_plan_pricing[ $index ]['licenses'];
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
			return array(
				'slug'           => $this->_product->post->post_name,
				'title'          => $this->_product->get_title(),
				'type'           => 'plugin',
				'business_model' => $this->get_local_business_model(),
				'created_at'     => $this->_product->post->post_date_gmt,
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
			$pricing = $this->_plan_pricing[ $index ];

			unset( $pricing['wc_prices_ids'] );

			return $pricing;
		}

		#endregion
	}