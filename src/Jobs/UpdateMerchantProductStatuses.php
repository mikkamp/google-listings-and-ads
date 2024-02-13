<?php
declare( strict_types=1 );

namespace Automattic\WooCommerce\GoogleListingsAndAds\Jobs;

use Automattic\WooCommerce\GoogleListingsAndAds\ActionScheduler\ActionSchedulerInterface;
use Automattic\WooCommerce\GoogleListingsAndAds\MerchantCenter\MerchantCenterService;
use Automattic\WooCommerce\GoogleListingsAndAds\API\Google\MerchantReport;
use Automattic\WooCommerce\GoogleListingsAndAds\MerchantCenter\MerchantStatuses;

defined( 'ABSPATH' ) || exit;

/**
 * Class UpdateMerchantProductStatuses
 *
 * Update Product Stats
 *
 * Note: The job will not start if it is already running or if the Google Merchant Center account is not connected.
 *
 * @package Automattic\WooCommerce\GoogleListingsAndAds\Jobs
 *
 * @since x.x.x
 */
class UpdateMerchantProductStatuses extends AbstractActionSchedulerJob {
	/**
	 * @var MerchantCenterService
	 */
	protected $merchant_center;

	/**
	 * @var MerchantReport
	 */
	protected $merchant_report;

	/**
	 * @var MerchantStatuses
	 */

	protected $merchant_statuses;

	/**
	 * UpdateShippingSettings constructor.
	 *
	 * @param ActionSchedulerInterface  $action_scheduler
	 * @param ActionSchedulerJobMonitor $monitor
	 * @param MerchantCenterService     $merchant_center
	 * @param MerchantReport            $merchant_report
	 * @param MerchantStatuses          $merchant_statuses
	 */
	public function __construct( ActionSchedulerInterface $action_scheduler, ActionSchedulerJobMonitor $monitor, MerchantCenterService $merchant_center, MerchantReport $merchant_report, MerchantStatuses $merchant_statuses ) {
		parent::__construct( $action_scheduler, $monitor );
		$this->merchant_center   = $merchant_center;
		$this->merchant_report   = $merchant_report;
		$this->merchant_statuses = $merchant_statuses;
	}

	/**
	 * Get the name of the job.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'update_merchant_product_statuses';
	}

	/**
	 * Can the job be scheduled.
	 *
	 * @param array|null $args
	 *
	 * @return bool Returns true if the job can be scheduled.
	 */
	public function can_schedule( $args = [] ): bool {
		return parent::can_schedule( $args ) && $this->merchant_center->is_connected();
	}

	/**
	 * Process the job.
	 *
	 * @param int[] $items An array of job arguments.
	 *
	 * @throws JobException If the shipping settings cannot be synced.
	 */
	public function process_items( array $items ) {
		$next_page_token = $items['next_page_token'] ?? null;

		// Clear the cache if we're starting from the beginning.
		if ( ! $next_page_token ) {
			$this->merchant_statuses->clear_cache();
			$this->merchant_statuses->delete_product_statuses_count_intermediate_data();
		}

		$results         = $this->merchant_report->get_product_view_report( $next_page_token );
		$next_page_token = $results['next_page_token'];

		$this->merchant_statuses->update_product_stats( $results['statuses'] );

		if ( $next_page_token ) {
			$this->schedule( [ [ 'next_page_token' => $next_page_token ] ] );
		} else {
			$this->merchant_statuses->handle_complete_mc_statuses_fetching();
		}
	}

	/**
	 * Schedule the job.
	 *
	 * @param array $args - arguments.
	 */
	public function schedule( array $args = [] ) {
		if ( $this->can_schedule( $args ) ) {
			$this->action_scheduler->schedule_immediate( $this->get_process_item_hook(), $args );
		}
	}

	/**
	 * The job is considered to be scheduled if the "process_item" action is currently pending or in-progress regardless of the arguments.
	 *
	 * @return bool
	 */
	public function is_scheduled(): bool {
		// We set 'args' to null so it matches any arguments. This is because it's possible to have multiple instances of the job running with different page tokens
		return $this->is_running( null );
	}
}
