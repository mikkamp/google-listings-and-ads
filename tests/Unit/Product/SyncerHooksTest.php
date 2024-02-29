<?php
declare( strict_types=1 );

namespace Automattic\WooCommerce\GoogleListingsAndAds\Tests\Unit\Product;

use Automattic\WooCommerce\GoogleListingsAndAds\Google\NotificationsService;
use Automattic\WooCommerce\GoogleListingsAndAds\Jobs\DeleteProducts;
use Automattic\WooCommerce\GoogleListingsAndAds\Jobs\JobRepository;
use Automattic\WooCommerce\GoogleListingsAndAds\Jobs\Notifications\ProductNotificationJob;
use Automattic\WooCommerce\GoogleListingsAndAds\Jobs\UpdateProducts;
use Automattic\WooCommerce\GoogleListingsAndAds\MerchantCenter\MerchantCenterService;
use Automattic\WooCommerce\GoogleListingsAndAds\Product\BatchProductHelper;
use Automattic\WooCommerce\GoogleListingsAndAds\Product\ProductHelper;
use Automattic\WooCommerce\GoogleListingsAndAds\Product\SyncerHooks;
use Automattic\WooCommerce\GoogleListingsAndAds\Proxies\WC;
use Automattic\WooCommerce\GoogleListingsAndAds\Tests\Framework\ContainerAwareUnitTest;
use Automattic\WooCommerce\GoogleListingsAndAds\Tests\Tools\HelperTrait\ProductTrait;
use Automattic\WooCommerce\GoogleListingsAndAds\Value\ChannelVisibility;
use Automattic\WooCommerce\GoogleListingsAndAds\Value\NotificationStatus;
use PHPUnit\Framework\MockObject\MockObject;
use WC_Helper_Product;

/**
 * Class SyncerHooksTest
 *
 * @package Automattic\WooCommerce\GoogleListingsAndAds\Tests\Unit\Product
 */
class SyncerHooksTest extends ContainerAwareUnitTest {

	use ProductTrait;

	/*** @var MockObject|MerchantCenterService $merchant_center */
	protected $merchant_center;

	/** @var BatchProductHelper $batch_helper */
	protected $batch_helper;

	/** @var ProductHelper $product_helper */
	protected $product_helper;

	/** @var MockObject|JobRepository $job_repository */
	protected $job_repository;

	/** @var MockObject|UpdateProducts $update_products_job */
	protected $update_products_job;

	/** @var MockObject|DeleteProducts $delete_products_job */
	protected $delete_products_job;

	/**
	 * @var MockObject|NotificationsService
	 */
	protected $notification_service;

	/**
	 * @var MockObject|ProductNotificationJob
	 */
	protected $product_notification_job;

	/** @var WC $wc */
	protected $wc;

	/** @var SyncerHooks $syncer_hooks */
	protected $syncer_hooks;

	public function test_create_new_simple_product_schedules_update_job() {
		$this->update_products_job->expects( $this->once() )
			->method( 'schedule' );

		WC_Helper_Product::create_simple_product( true, [ 'status' => 'publish' ] );
	}

	public function test_update_simple_product_schedules_update_job() {
		$product = WC_Helper_Product::create_simple_product( true, [ 'status' => 'draft' ] );

		$this->update_products_job->expects( $this->once() )
			->method( 'schedule' )
			->with( $this->equalTo( [ [ $product->get_id() ] ] ) );

		$product->set_status( 'publish' );
		$product->save();
	}

	public function test_multiple_update_events_for_same_product_only_schedules_update_job_once() {
		$product = WC_Helper_Product::create_simple_product( true, [ 'status' => 'draft' ] );

		$this->update_products_job->expects( $this->once() )
			->method( 'schedule' )
			->with( $this->equalTo( [ [ $product->get_id() ] ] ) );

		// update #1
		$product->set_status( 'publish' );
		$product->save();

		// update #2
		$product->set_description( 'Sample description' );
		$product->save();
	}

	public function test_create_variable_product_schedules_update_job_for_all_variations() {
		$variable_product = $this->create_variation_product( null, [ 'status' => 'draft' ] );
		$variation_ids    = $variable_product->get_children();

		$this->update_products_job->expects( $this->once() )
			->method( 'schedule' )
			->with( $this->equalTo( [ $variation_ids ] ) );

		$variable_product->set_status( 'publish' );
		$variable_product->save();
	}

	public function test_adding_variation_schedules_update_job() {
		$variable_product = $this->create_variation_product();

		$this->update_products_job->expects( $this->once() )
			->method( 'schedule' );

		$this->create_product_variation_object(
			$variable_product->get_id(),
			'DUMMY SKU VARIABLE SMALL BLUE 2',
			10,
			[
				'pa_size'   => 'small',
				'pa_colour' => 'blue',
				'pa_number' => '2',
			]
		);
	}

	public function test_trashing_product_does_not_schedules_delete_job_if_product_is_not_synced() {
		$product = WC_Helper_Product::create_simple_product();

		$this->delete_products_job->expects( $this->never() )
			->method( 'schedule' )
			->with( $this->equalTo( [ [ $product->get_id() ] ] ) );

		$product->delete();
	}

	public function test_trashing_synced_product_schedules_delete_job() {
		$product = WC_Helper_Product::create_simple_product();
		$this->product_helper->mark_as_synced( $product, $this->generate_google_product_mock( 'online:en:US:gla_1' ) );

		$this->delete_products_job->expects( $this->once() )
			->method( 'schedule' )
			->with( $this->equalTo( [ [ 'online:en:US:gla_1' => $product->get_id() ] ] ) );

		$product->delete();
	}

	public function test_force_deleting_synced_product_schedules_delete_job() {
		$product = WC_Helper_Product::create_simple_product();
		$this->product_helper->mark_as_synced( $product, $this->generate_google_product_mock( 'online:en:US:gla_1' ) );

		$this->delete_products_job->expects( $this->once() )
			->method( 'schedule' )
			->with( $this->equalTo( [ [ 'online:en:US:gla_1' => $product->get_id() ] ] ) );

		$product->delete( true );
	}

	public function test_trashing_synced_variable_schedules_delete_job_for_all_variations() {
		$variable_product = $this->create_variation_product();
		foreach ( $variable_product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			$this->product_helper->mark_as_synced( $variation, $this->generate_google_product_mock() );
		}

		$this->delete_products_job->expects( $this->exactly( count( $variable_product->get_children() ) ) )
			->method( 'schedule' );

		$variable_product->delete();
	}

	public function test_force_deleting_synced_variable_schedules_delete_job_for_all_variations() {
		$variable_product = $this->create_variation_product();
		foreach ( $variable_product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			$this->product_helper->mark_as_synced( $variation, $this->generate_google_product_mock() );
		}

		$this->delete_products_job->expects( $this->exactly( count( $variable_product->get_children() ) ) )
			->method( 'schedule' );

		$variable_product->delete( true );
	}

	public function test_trashing_synced_variation_schedules_delete_job() {
		$variable_product = $this->create_variation_product();
		foreach ( $variable_product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			$this->product_helper->mark_as_synced( $variation, $this->generate_google_product_mock( 'online:en:US:gla_' . $variation_id ) );
		}
		$variation_to_delete = wc_get_product( $variable_product->get_children()[0] );

		$this->delete_products_job->expects( $this->once() )
			->method( 'schedule' )
			->with( $this->equalTo( [ [ 'online:en:US:gla_' . $variation_to_delete->get_id() => $variation_to_delete->get_id() ] ] ) );

		$variation_to_delete->delete();
	}


	public function test_force_deleting_synced_variation_schedules_delete_job() {
		$variable_product = $this->create_variation_product();
		foreach ( $variable_product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			$this->product_helper->mark_as_synced( $variation, $this->generate_google_product_mock( 'online:en:US:gla_' . $variation_id ) );
		}
		$variation_to_delete = wc_get_product( $variable_product->get_children()[0] );

		$this->delete_products_job->expects( $this->once() )
			->method( 'schedule' )
			->with( $this->equalTo( [ [ 'online:en:US:gla_' . $variation_to_delete->get_id() => $variation_to_delete->get_id() ] ] ) );

		$variation_to_delete->delete( true );
	}

	public function test_saving_synced_but_not_sync_ready_product_schedules_delete_job() {
		$product = WC_Helper_Product::create_simple_product( true, [ 'status' => 'draft' ] );
		$this->product_helper->mark_as_synced( $product, $this->generate_google_product_mock( 'online:en:US:gla_1' ) );

		$this->delete_products_job->expects( $this->once() )
			->method( 'schedule' )
			->with( $this->equalTo( [ [ 'online:en:US:gla_1' => $product->get_id() ] ] ) );

		$product->save();
	}

	public function test_trashing_synced_product_wp_post_schedules_delete_joby() {
		$product = WC_Helper_Product::create_simple_product();
		$this->product_helper->mark_as_synced( $product, $this->generate_google_product_mock( 'online:en:US:gla_1' ) );

		$this->delete_products_job->expects( $this->once() )
			->method( 'schedule' )
			->with( $this->equalTo( [ [ 'online:en:US:gla_1' => $product->get_id() ] ] ) );

		wp_trash_post( $product->get_id() );
	}

	public function test_force_deleting_synced_product_wp_post_schedules_delete_job() {
		$product = WC_Helper_Product::create_simple_product();
		$this->product_helper->mark_as_synced( $product, $this->generate_google_product_mock( 'online:en:US:gla_1' ) );

		$this->delete_products_job->expects( $this->once() )
			->method( 'schedule' )
			->with( $this->equalTo( [ [ 'online:en:US:gla_1' => $product->get_id() ] ] ) );

		wp_delete_post( $product->get_id(), true );
	}

	public function test_creating_and_updating_post_does_not_schedule_update_job() {
		$this->update_products_job->expects( $this->never() )
			->method( 'schedule' );

		$post = $this->factory()->post->create_and_get();

		// update post
		$this->factory()->post->update_object( $post->ID, [ 'post_title' => 'Sample title' ] );

		// trash post
		wp_trash_post( $post->ID );

		// un-trash post
		wp_untrash_post( $post->ID );
	}

	public function test_trashing_and_deleting_post_does_not_schedule_delete_job() {
		$this->delete_products_job->expects( $this->never() )
			->method( 'schedule' );

		$post = $this->factory()->post->create_and_get();

		// trash post
		wp_trash_post( $post->ID );

		// force delete post
		wp_delete_post( $post->ID, true );
	}

	public function test_create_product_triggers_notification_created() {
		$product = WC_Helper_Product::create_simple_product( true, [ 'status' => 'draft' ] );
		$this->notification_service->expects( $this->once() )->method( 'is_enabled' )->willReturn( true );
		$this->product_notification_job->expects( $this->once() )
			->method( 'schedule' )->with(
				$this->equalTo(
					[
						'item_id' => $product->get_id(),
						'topic'   => NotificationsService::TOPIC_PRODUCT_CREATED,
					]
				)
			);
		$product->set_status( 'publish' );
		$product->save();
	}

	public function test_create_product_triggers_notification_updated() {
		$product = WC_Helper_Product::create_simple_product( true, [ 'status' => 'draft' ] );
		$this->notification_service->expects( $this->once() )->method( 'is_enabled' )->willReturn( true );
		$this->product_notification_job->expects( $this->once() )
			->method( 'schedule' )->with(
				$this->equalTo(
					[
						'item_id' => $product->get_id(),
						'topic'   => NotificationsService::TOPIC_PRODUCT_UPDATED,
					]
				)
			);
		$product->set_status( 'publish' );
		$this->product_helper->set_notification_status( $product, NotificationStatus::NOTIFICATION_CREATED );
		$product->save();
	}

	public function test_create_product_triggers_notification_delete() {
		$product = WC_Helper_Product::create_simple_product( true, [ 'status' => 'draft' ] );
		$this->notification_service->expects( $this->once() )->method( 'is_enabled' )->willReturn( true );
		$this->product_notification_job->expects( $this->once() )
			->method( 'schedule' )->with(
				$this->equalTo(
					[
						'item_id' => $product->get_id(),
						'topic'   => NotificationsService::TOPIC_PRODUCT_DELETED,
					]
				)
			);
		$product->set_status( 'publish' );
		$product->add_meta_data( '_wc_gla_visibility', ChannelVisibility::DONT_SYNC_AND_SHOW, true );
		$this->product_helper->set_notification_status( $product, NotificationStatus::NOTIFICATION_CREATED );
		$product->save();
	}


	/**
	 * Runs before each test is executed.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->login_as_administrator();

		$this->merchant_center = $this->createMock( MerchantCenterService::class );
		$this->merchant_center->expects( $this->any() )
			->method( 'is_ready_for_syncing' )
			->willReturn( true );

		$this->update_products_job      = $this->createMock( UpdateProducts::class );
		$this->delete_products_job      = $this->createMock( DeleteProducts::class );
		$this->product_notification_job = $this->createMock( ProductNotificationJob::class );
		$this->job_repository           = $this->createMock( JobRepository::class );
		$this->notification_service     = $this->createMock( NotificationsService::class );

		$this->job_repository->expects( $this->any() )
			->method( 'get' )
			->willReturnMap(
				[
					[ UpdateProducts::class, $this->update_products_job ],
					[ DeleteProducts::class, $this->delete_products_job ],
					[ ProductNotificationJob::class, $this->product_notification_job ],
				]
			);

		$this->batch_helper   = $this->container->get( BatchProductHelper::class );
		$this->product_helper = $this->container->get( ProductHelper::class );
		$this->wc             = $this->container->get( WC::class );
		$this->syncer_hooks   = new SyncerHooks( $this->batch_helper, $this->product_helper, $this->job_repository, $this->merchant_center, $this->notification_service, $this->wc );

		add_filter( 'woocommerce_gla_notifications_enabled', '__return_false' );
		$this->syncer_hooks->register();
	}
}
