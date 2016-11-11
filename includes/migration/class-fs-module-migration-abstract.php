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
	 * IMPORTANT Assumptions:
	 *  - Product do NOT have more than one paid plan (multiple price levels by licenses does not count as a different
	 *  plan).
	 *
	 * Class FS_Module_Migration_Abstract
	 */
	abstract class FS_Module_Migration_Abstract {
		/**
		 * @var FS_Entity
		 */
		private $_api_scope;
		/**
		 * @var FS_Developer
		 */
		protected $_developer;

		/**
		 * @var FS_Plugin
		 */
		protected $_module;

		/**
		 * @var string
		 */
		protected $_namespace;

		/**
		 * @var FS_Api
		 */
		private $_api;

		/**
		 * @var FS_Plan[]
		 */
		private $_plans;

		/**
		 * @var FS_Plan
		 */
		protected $_free_plan;

		/**
		 * @var FS_Plan
		 */
		protected $_paid_plan;

		/**
		 * @var FS_Entity_Mapper
		 */
		protected $_entity_mapper;

		/**
		 * @since 1.0.0
		 *
		 * @var FS_Logger
		 */
		private $_logger;


		#--------------------------------------------------------------------------------
		#region Init
		#--------------------------------------------------------------------------------

		protected function init( $namespace, FS_Developer $developer, $module ) {
			$this->_namespace = $namespace;
			$this->_module    = $module;
			$this->_developer = $developer;

			$this->_logger = FS_Logger::get_logger( WP_FSM__SLUG, WP_FS__DEBUG_SDK, WP_FS__ECHO_DEBUG_SDK );

			if ( ! is_null( $module ) ) {
				$this->process_pricing();
			}
		}

		#endregion

		/**
		 * The main module synchronization method.
		 *
		 * @author   Vova Feldman (@svovaf)
		 * @since    1.0.0
		 *
		 * @param bool $flush If true, ignore cached entities map.
		 *
		 * @return bool
		 */
		function do_sync( $flush = false ) {
			$this->log( "Starting to sync {$this->_module->title}...\n------------------------------------------------------------------------------------\n" );

			$result = $this->sync_module( $flush );

			if ( false === $result ) {
				$this->log_failure( "Failed to sync module to Freemius. Skipping..." );

				return false;
			}

			if ( $result instanceof FS_Plugin ) {
				/**
				 * Module was created in the current execution,
				 * therefore, if a free plan is locally exist,
				 * it was already created with the module creation.
				 */
			} else {
				if ( false === $this->sync_free_plan( $flush ) ) {
					$this->log_failure( "Failed to sync module's free plan to Freemius. Skipping..." );

					return false;
				}
			}

			if ( false === $this->sync_paid_plan( $flush ) ) {
				$this->log_failure( "Failed to sync module's paid plan to Freemius. Skipping..." );

				return false;
			}

			return true;
		}

		#--------------------------------------------------------------------------------
		#region Logging
		#--------------------------------------------------------------------------------

		/**
		 * Log message.
		 *
		 * @author   Vova Feldman (@svovaf)
		 * @since    1.0.0
		 *
		 * @param string $message
		 */
		protected function log( $message ) {
			$this->_logger->log( $message );
		}

		/**
		 * Log failure message.
		 *
		 * @author   Vova Feldman (@svovaf)
		 * @since    1.0.0
		 *
		 * @param string $message
		 */
		protected function log_failure( $message ) {
			$this->log( "[FAILURE] {$message}" );
		}

		/**
		 * Log success message.
		 *
		 * @author   Vova Feldman (@svovaf)
		 * @since    1.0.0
		 *
		 * @param string $message
		 */
		protected function log_success( $message ) {
			$this->log( "[SUCCESS] {$message}" );
		}

		/**
		 * If API resulted an error, log it as a failure and return TRUE.
		 *
		 * @author   Vova Feldman (@svovaf)
		 * @since    1.0.0
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
			return FS_Entity_Mapper::instance( $this->_namespace )->get_remote_id( $type, $local_id );
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
			return FS_Entity_Mapper::instance( $this->_namespace )->link(
				$entity,
				$local_entity_id
			);
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param FS_Plan    $plan
		 * @param FS_Pricing $pricing
		 * @param string     $local_pricing_index
		 */
		private function link_local_paid_plan_pricing(
			FS_Plan $plan,
			FS_Pricing $pricing,
			$local_pricing_index
		) {
			$local_pricing_ids = $this->get_local_paid_plan_pricing_ids( $local_pricing_index );

			foreach ( $local_pricing_ids as $local_pricing_id ) {
				// Link pricing to plan.
				$this->link_entity( $plan, $local_pricing_id );

				// Link pricing.
				$this->link_entity( $pricing, $local_pricing_id );
			}
		}

		#endregion

		#--------------------------------------------------------------------------------
		#region Local Data Getters
		#--------------------------------------------------------------------------------

		/**
		 * Get local module ID.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return bool
		 */
		abstract protected function get_local_module_id();

		/**
		 * Check if local product has a free plan.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return bool
		 */
		abstract protected function local_has_free_plan();

		/**
		 * Get local free plan ID.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return bool
		 */
		abstract protected function get_local_free_plan_id();

		/**
		 * Check if local product has any paid plans.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return bool
		 */
		abstract protected function local_has_paid_plan();

		/**
		 * Get local paid plan associated pricing objects count.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return bool
		 */
		abstract protected function get_local_paid_plan_pricing_count();

		/**
		 * Get local paid plan pricing unique IDs.
		 *
		 * This case is relevant when there are different pricing objects for the
		 * same feature-set but different license activations limit like in WC.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param int $index
		 *
		 * @return string[]
		 */
		abstract protected function get_local_paid_plan_pricing_ids( $index );

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param int|null $licenses
		 *
		 * @return int|false
		 */
		abstract protected function get_local_paid_plan_pricing_index_by_licenses( $licenses );

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param int $index
		 *
		 * @return int|null
		 */
		abstract protected function get_local_paid_plan_pricing_licenses( $index );

		/**
		 * Get local module business model (free, freemium or premium).
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return string
		 */
		protected function get_local_business_model() {
			if ( ! $this->local_has_paid_plan() ) {
				return 'free';
			}

			if ( $this->local_has_free_plan() ) {
				return 'freemium';
			}

			return 'premium';
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
		abstract protected function get_module_for_api();

		/**
		 * Generate free plan details from local data for API.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return array
		 */
		abstract protected function get_free_plan_for_api();

		/**
		 * Generate paid plan details from local data for API.
		 *
		 * @author Vova Feldman
		 * @since  1.0.0
		 *
		 * @return array
		 */
		abstract protected function get_paid_plan_for_api();

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
		abstract protected function get_paid_plan_pricing_for_api( $index );

		#endregion

		#--------------------------------------------------------------------------------
		#region Freemius API
		#--------------------------------------------------------------------------------

		/**
		 * Lazy load of the module's scope API.
		 *
		 * If module is set, use 'plugin' scope, otherwise, developer's scope.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return FS_Api
		 */
		private function get_api() {
			if ( ! isset( $this->_api ) ) {
				if ( ! class_exists( 'FS_Api' ) ) {
					require_once WP_FSM__DIR_INCLUDES . '/class-fs-api.php';
				}

				// If module do not exist on Freemius we need to run with
				// developer's scope to create a new module.
				$this->_api_scope = is_null( $this->_module ) ?
					$this->_developer :
					$this->_module;

				$this->_api = FS_Api::instance(
					WP_FSM__SLUG,
					$this->_api_scope->get_type(),
					$this->_api_scope->id,
					$this->_api_scope->public_key,
					false,
					$this->_api_scope->secret_key
				);
			}

			return $this->_api;
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
		protected function api_call( $path, $method = 'GET', $params = array() ) {
			$method = strtoupper( $method );

			if ( $this->_api_scope instanceof FS_Developer ) {
				/**
				 * When running in developer's scope prepend the context module
				 * details to the request with one exception - when the request
				 * is about creating a new module.
				 */
				if ( 'GET' === $method || 'plugins.json' !== trim( $path, '/' ) ) {
					$path = "/plugins/{$this->_module->id}/" . ltrim( $path, '/' );
				}
			}

			if ( 'GET' !== $method ) {
				// Hint that API that it's a migration mode.
				$params['is_migration'] = true;
				$params['source']       = $this->_namespace;
			}

			return self::get_api()->call( $path, $method, $params );
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

		#endregion

		#--------------------------------------------------------------------------------
		#region Entities Sync
		#--------------------------------------------------------------------------------

		/**
		 * - If module already migrated, return the module ID.
		 * - If module not yet migrated, return the module object.
		 * - If fails, return false.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param bool $flush If true, ignore cached entities map.
		 *
		 * @return false|number|\FS_Plugin
		 */
		protected function sync_module( $flush = false ) {
			$local_module_id = $this->get_local_module_id();

			$module_id = ! $flush && $this->get_remote_id(
					FS_Plugin::get_type(),
					$local_module_id
				);

			if ( FS_Entity::is_valid_id( $module_id ) ) {
				$this->log( "Module ({$local_module_id}) already associated with a Freemius module ({$module_id})." );
			} else {

				if ( is_null( $this->_module ) ) {
					$this->log( "Module ({$local_module_id}) do not exist on Freemius, starting to create one..." );

					$module = $this->create_module( $this->get_module_for_api() );

					if ( $this->log_on_error( $module, "Failed creating module ({$local_module_id}) on Freemius." ) ) {
						return false;
					}

					$this->_module = $module;
					$module_id     = $module->id;

					$this->log_success( "Successfully created module ({$local_module_id}) on Freemius ({$module_id})." );
				}

				$this->link_entity( $this->_module, $local_module_id );

				$module_id = $this->_module->id;

				$this->log_success( "Successfully linked local module ({$local_module_id}) to Freemius module ({$module_id})." );

				if ( $this->_api_scope instanceof FS_Developer ) {
					// Module created.
					return $this->_module;
				}
			}

			return $module_id;
		}

		/**
		 * - If module do NOT have a free plan:
		 *      - Try to delete remotely on Freemius.
		 *      - If not exist on Freemius or successfully - return TRUE.
		 *      - If failed to delete remotely, return FALSE.
		 * - If free plan already migrated, return the plan ID.
		 * - If free plan not yet migrated, return the plan object.
		 * - If fails, return false.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param bool $flush If true, ignore cached entities map.
		 *
		 * @return bool|number|FS_Plan
		 */
		protected function sync_free_plan( $flush = false ) {
			if ( ! $this->local_has_free_plan() ) {
				$this->log( "Module doesn't have a free plan. Checking if there's a free plan on Freemius..." );

				if ( ! $this->has_free_plan() ) {
					$this->log( "Module doesn't have a free plan on Freemius as well. So there's no free plan to sync." );
				} else {
					// Delete free plan from Freemius.
					if ( $this->delete_free_plan() ) {
						$this->log_success( "Module's free plan was successfully deleted from Freemius." );
					} else {
						$this->log_failure( "Module does have a free plan on Freemius but failed to delete it. Try to delete manually from the dashboard." );

						return false;
					}
				}

				return true;
			}

			$local_free_plan_id = $this->get_local_free_plan_id();

			$free_plan_id = ! $flush && $this->get_remote_id(
					FS_Plan::get_type(),
					$local_free_plan_id
				);

			if ( FS_Entity::is_valid_id( $free_plan_id ) ) {
				$this->log( "Free plan ({$local_free_plan_id}) already associated with a Freemius plan ({$free_plan_id})." );

				return $free_plan_id;
			}

			if ( ! $this->has_free_plan() ) {
				$this->log( "Free plan not exist on Freemius, try to create one." );

				$plan = $this->create_plan( $this->get_free_plan_for_api() );

				if ( $this->log_on_error( $plan,
					"Failed migrating free plan ({$local_free_plan_id}) on to Freemius." )
				) {
					return false;
				}
			} else {
				$plan = $this->_free_plan;

				$this->log( "Free plan already exist on Freemius ({$plan->id}), just link it with the local one ({$local_free_plan_id})." );
			}

			$free_plan_id = $plan->id;

			$this->link_entity( $plan, $local_free_plan_id );

			$this->log( "Successfully linked free plan ({$local_free_plan_id}) to Freemius free plan ({$plan->id})." );

			return $free_plan_id;
		}

		/**
		 * - If module do NOT have a paid plan return TRUE.
		 * - If fails, return false.
		 * - Otherwise, return the paid plan object.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param bool $flush If true, ignore cached entities map.
		 *
		 * @return bool|FS_Plan
		 */
		protected function sync_paid_plan( $flush = false ) {
			if ( ! $this->local_has_paid_plan() ) {
				$this->log( "Module doesn't have a paid plan. Checking if there's a free plan on Freemius..." );

				if ( ! $this->has_paid_plan() ) {
					$this->log( "Module doesn't have a paid plan on Freemius as well. So there's no free plan to sync." );

				} else {
					$this->log( "Module DOES have a paid plan on Freemius, but currently we don't want to delete plans only enrich them. So just skip." );
					// Delete all paid plans from Freemius.
//				$this->delete_paid_plans();
				}

				return true;
			}

			$local_pricing_ids = $this->get_local_paid_plan_pricing_ids( 0 );

			$plan_id = ! $flush && $this->get_remote_id(
					FS_Plan::get_type(),
					$local_pricing_ids[0]
				);

			if ( FS_Entity::is_valid_id( $plan_id ) ) {
				$this->log( "Paid plan already associated with a Freemius plan ({$plan_id})." );
			} else if ( false === $plan_id ) {
				// Plan is not linked.
				if ( ! $this->has_paid_plan() ) {
					$this->log( "There's no paid plan on Freemius, syncing..." );

					// Create paid plan.
					$plan = $this->create_plan( $this->get_paid_plan_for_api() );

					if ( $this->log_on_error( $plan, "Failed creating paid plan on Freemius." ) ) {
						return false;
					}

					for ( $i = 0, $len = $this->get_local_paid_plan_pricing_count(); $i < $len; $i ++ ) {
						$pricing = $this->create_pricing( $plan->id, $this->get_paid_plan_pricing_for_api( $i ) );

						if ( $this->log_on_error( $plan, "Failed creating plan pricing on Freemius." ) ) {
							return false;
						}

						$this->link_local_paid_plan_pricing( $plan, $pricing, $i );

						$this->log_success( "Successfully created and linked paid plan with its pricing on Freemius ({$plan->id})." );
					}

					return $plan;
				} else {
					$plan_id = $this->_paid_plan->id;

					$this->log( "There's already a paid plan on Freemius, trying to sync..." );
				}
			}

			$paid_plan = $this->get_plan_by_id( $plan_id );

			$is_paid_plan_id_changed = ! is_object( $paid_plan );

			/**
			 * If there's already a paid plan, only enrich it
			 * with multi-site licensing pricing that are not yet set.
			 *
			 * - Do NOT change the prices.
			 * - Do NOT remove multi-site licenses that are missing locally.
			 */

			if ( $is_paid_plan_id_changed ) {
				$paid_plan = $this->_paid_plan;
			}

			// Load plan's pricing.
			$pricing = $this->get_plan_pricing( $paid_plan->id );

			/**
			 * @var array<string,bool> $matched_pricing
			 */
			$matched_pricing = array();

			foreach ( $pricing as $p ) {
				$index = $this->get_local_paid_plan_pricing_index_by_licenses( $p->licenses );

				if ( false === $index ) {
					// Price for the specified licenses doesn't exist locally.
				} else {
					$this->link_local_paid_plan_pricing( $paid_plan, $p, $index );

					$licenses_key = is_numeric( $p->licenses ) ? $p->licenses : 'unlimited';

					$matched_pricing[ 'licenses:' . $licenses_key ] = true;

					$this->log_success( "Successfully linked the plan's {$licenses_key}-site licenses pricing to the one on Freemius ({$p->id})." );
				}

			}

			if ( count( $matched_pricing ) < $this->get_local_paid_plan_pricing_count() ) {
				// Enrich multi-site license pricing that are not exist on Freemius.
				for ( $i = 0, $len = $this->get_local_paid_plan_pricing_count(); $i < $len; $i ++ ) {
					$licenses = $this->get_local_paid_plan_pricing_licenses( $i );

					$licenses_key = is_numeric( $licenses ) ? $licenses : 'unlimited';

					if ( ! isset( $matched_pricing[ 'licenses:' . $licenses_key ] ) ) {
						// Only _enrich_ with pricing that doesn't exist on Freemius.
						$pricing = $this->create_pricing(
							$paid_plan->id,
							$this->get_paid_plan_pricing_for_api( $i )
						);

						if ( $this->log_on_error( $pricing,
							"Failed creating {$licenses_key}-site licenses pricing for plan ({$paid_plan->id}) on Freemius." )
						) {
							$this->log( 'Continue anyway...' );
							continue;
						}

						$this->link_local_paid_plan_pricing( $paid_plan, $pricing, $i );

						$this->log_success( "Successfully created and linked the plan's {$licenses_key}-site licenses pricing to the one on Freemius ({$pricing->id})." );

					}
				}

			}

			return $paid_plan;
		}

		#endregion

		#--------------------------------------------------------------------------------
		#region Helper Methods
		#--------------------------------------------------------------------------------

		/**
		 * Pre-load plans from Freemius and do some processing
		 * to find the free plan and the 1st paid plan.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return FS_Plan[]
		 */
		private function process_pricing() {
			if ( ! isset( $this->_plans ) ) {
				$this->_plans = array();

				$result = $this->api_call( "/plans.json" );

				// @todo handle api error

				foreach ( $result->plans as $plan ) {
					$plan = new FS_Plan( $plan );

					if ( $plan->is_free() ) {
						if ( ! isset( $this->_free_plan ) ) {
							// Store 1st free plan.
							$this->_free_plan = $plan;
						}
					} else {
						if ( ! isset( $this->_paid_plan ) ) {
							// Store 1st paid plan.
							$this->_paid_plan = $plan;
						}
					}

					$this->_plans[] = $plan;
				}
			}

			return $this->_plans;
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param $plan_id
		 *
		 * @return FS_Pricing[]
		 */
		private function get_plan_pricing( $plan_id ) {
			$result = $this->api_call( "plans/{$plan_id}/pricing.json" );

			if ( $this->is_api_error( $result ) ) {
				return $result;
			}

			$pricing = array();

			foreach ( $result->pricing as $p ) {
				$pricing[] = new FS_Pricing( $p );
			}

			return $pricing;
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param number $plan_id
		 *
		 * @return false|FS_Plan
		 */
		private function get_plan_by_id( $plan_id ) {
			foreach ( $this->_plans as $plan ) {
				if ( $plan_id == $plan->id ) {
					return $plan;
				}
			}

			return false;
		}

		/**
		 * Delete the Free plan from Freemius.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return bool Is successfully deleted.
		 */
		protected function delete_free_plan() {
			return $this->delete_plan( $this->_free_plan->id );
		}

		/**
		 * Delete all paid plans from Freemius.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return bool Is successfully deleted.
		 */
		protected function delete_paid_plans() {
			foreach ( $this->_plans as $plan ) {
				if ( $this->_free_plan->id == $plan->id ) {
					// Skip free plan.
					continue;
				}

				$this->delete_plan( $plan->id );
			}
		}

		/**
		 * Check if module have a free plan on Freemius.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return bool
		 */
		protected function has_free_plan() {
			return isset( $this->_free_plan );
		}

		/**
		 * Check if module have a paid plan on Freemius.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @return bool
		 */
		protected function has_paid_plan() {
			return isset( $this->_paid_plan );
		}


		#endregion

		#--------------------------------------------------------------------------------
		#region API Write Calls
		#--------------------------------------------------------------------------------


		/**
		 * Create module on Freemius.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param array $module
		 *
		 * @return FS_Plugin|mixed
		 */
		private function create_module( array $module ) {
			$result = $this->api_call( '/plugins.json', 'post', $module );

			if ( $this->is_api_error( $result ) ) {
				return $result;
			}

			return new FS_Plugin( $result );
		}

		/**
		 * Create plan on Freemius.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param array $plan
		 *
		 * @return FS_Plan|mixed
		 */
		private function create_plan( array $plan ) {
			$result = $this->api_call( '/plans.json', 'post', $plan );

			if ( $this->is_api_error( $result ) ) {
				return $result;
			}

			return new FS_Plan( $result );
		}

		/**
		 * Create plan pricing on Freemius.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 *
		 * @param number $plan_id
		 * @param array  $pricing
		 *
		 * @return FS_Pricing|mixed
		 */
		private function create_pricing( $plan_id, array $pricing ) {
			$result = $this->api_call( "/plans/{$plan_id}/pricing.json", 'post', $pricing );

			if ( $this->is_api_error( $result ) ) {
				return $result;
			}

			return new FS_Pricing( $result );
		}

		/**
		 * Delete plan from Freemius.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.0
		 *
		 * @param number $plan_id
		 *
		 * @return bool Is successfully deleted.
		 */
		private function delete_plan( $plan_id ) {
			$result = $this->api_call( "/plans/{$plan_id}.json", 'delete' );

			return is_object( $result ) &&
			       isset( $result->error ) &&
			       isset( $result->error->http ) &&
			       204 == $result->error->http;
		}

		#endregion
	}