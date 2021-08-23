<?php
declare( strict_types=1 );

namespace Automattic\WooCommerce\GoogleListingsAndAds\Tests\Unit\Coupon;

use Automattic\WooCommerce\GoogleListingsAndAds\Coupon\CouponHelper;
use Automattic\WooCommerce\GoogleListingsAndAds\Coupon\SyncerHooks;
use Automattic\WooCommerce\GoogleListingsAndAds\Jobs\DeleteCoupon;
use Automattic\WooCommerce\GoogleListingsAndAds\Jobs\UpdateCoupon;
use Automattic\WooCommerce\GoogleListingsAndAds\Jobs\JobRepository;
use Automattic\WooCommerce\GoogleListingsAndAds\MerchantCenter\MerchantCenterService;
use Automattic\WooCommerce\GoogleListingsAndAds\Proxies\WC;
use Automattic\WooCommerce\GoogleListingsAndAds\Tests\Framework\ContainerAwareUnitTest;
use Automattic\WooCommerce\GoogleListingsAndAds\Tests\Tools\HelperTrait\ProductTrait;
use PHPUnit\Framework\MockObject\MockObject;
use \WC_Coupon;

/**
 * Class SyncerHooksTest
 *
 * @package Automattic\WooCommerce\GoogleListingsAndAds\Tests\Unit\Product
 *
 * @property MockObject|MerchantCenterService $merchant_center
 * @property MockObject|JobRepository         $job_repository
 * @property MockObject|UpdateCoupon          $update_coupon_job
 * @property WC                               $wc
 * @property SyncerHooks                      $syncer_hooks
 */
class SyncerHooksTest extends ContainerAwareUnitTest {

	public function test_create_new_simple_coupon_schedules_update_job() {
		$this->update_coupon_job->expects( $this->once() )
								->method( 'schedule' );
	   $string_code = 'test_coupon_codes';
       $coupon = new WC_Coupon();
       $this->coupon_helper->mark_as_synced($coupon, 'fake_google_id', 'US');
       $coupon->set_code( $string_code );
	   $coupon->save();
	}

	public function test_update_simple_coupon_schedules_update_job() {
	    $string_code = 'test_coupon_codes';
	    $coupon = new WC_Coupon();
	    $this->coupon_helper->mark_as_synced($coupon, 'fake_google_id', 'US');
	    $coupon->set_code( $string_code );
	    $coupon->save();

		$this->update_coupon_job->expects( $this->once() )
								->method( 'schedule' )
								->with( $this->equalTo( [ $coupon->get_id() ] ) );
		$coupon->add_meta_data('test_coupon_field', 'testing', true);
		$coupon->save();
	}
	
	public function test_update_invisible_coupon_does_not_schedule_update_job() {
	    $string_code = 'test_coupon_codes';
	    $coupon = new WC_Coupon();
	    $coupon->update_meta_data('_wc_gla_visibility', 'dont-sync-and-show');
	    $coupon->save_meta_data();
	    $coupon->set_code( $string_code );
	    $coupon->save();
	    
	    $this->update_coupon_job->expects( $this->never() )->method( 'schedule' );
	    $coupon->add_meta_data('test_coupon_field', 'testing', true);
	    $coupon->save();
	}

	public function test_trash_simple_coupon_schedules_delete_job() {
	    $string_code = 'test_coupon_codes';
	    $coupon = new WC_Coupon();
	    $coupon->set_code( $string_code );
	    $coupon->save();
	    
	    $this->delete_coupon_job->expects( $this->once() )
	                            ->method( 'schedule' )
	                            ->with( $this->equalTo( [ $coupon->get_id() ] ) );
	    $coupon->delete();
	    $coupon->save();
	}

	public function test_delete_simple_coupon_schedules_delete_job() {
	    $string_code = 'test_coupon_codes';
	    $coupon = new WC_Coupon();
	    $coupon->set_code( $string_code );
	    $coupon->save();
	    
	    $this->delete_coupon_job->expects( $this->once() )
	                            ->method( 'schedule' )
	                            ->with( $this->equalTo( [ $coupon->get_id() ] ) );
	    $coupon->delete( array( 'force_delete' => true ) );
	    $coupon->save();
	}
	
	public function test_untrash_simple_coupon_schedules_update_job() {
	    $string_code = 'test_coupon_codes';
	    $coupon = new WC_Coupon();
	    $coupon->set_code( $string_code );
	    $coupon->save();
	    $coupon_id = $coupon->get_id();
	    $coupon->delete();
	   
	    $this->update_coupon_job->expects( $this->once() )
	                            ->method( 'schedule' )
	                            ->with( $this->equalTo( [ $coupon_id ] ) );   
	    // untrash coupon
	    wp_untrash_post( $coupon_id );
	}
	
	public function test_modify_post_does_not_schedule_update_job() {  
	    $this->update_coupon_job->expects( $this->never() )
	                            ->method( 'schedule' );
	    
	    $post = $this->factory()->post->create_and_get(); 
	    // update post
	    $this->factory()->post->update_object( $post->ID, [ 'post_title' => 'Sample title' ] );
	    // trash post
	    wp_trash_post( $post->ID );
	    // un-trash post
	    wp_untrash_post( $post->ID );
	}
	
	/**
	 * Runs before each test is executed.
	 */
	public function setUp() {
		parent::setUp();

		$this->login_as_administrator();

		$this->merchant_center = $this->createMock( MerchantCenterService::class );
		$this->merchant_center->expects( $this->any() )
							  ->method( 'is_connected' )
							  ->willReturn( true );

		$this->update_coupon_job   = $this->createMock( UpdateCoupon::class );
		$this->delete_coupon_job   = $this->createMock( DeleteCoupon::class );
		$this->job_repository      = $this->createMock( JobRepository::class );
		$this->job_repository->expects( $this->any() )
							 ->method( 'get' )
							 ->willReturnMap(
								 [
									 [ DeleteCoupon::class, $this->delete_coupon_job ],
								     [ UpdateCoupon::class, $this->update_coupon_job ],
								 ]
							 );

		$this->wc             = $this->container->get( WC::class );
		$this->coupon_helper  = $this->container->get( CouponHelper::class);
		$this->syncer_hooks   = new SyncerHooks(
		    $this->coupon_helper,
		    $this->job_repository,
		    $this->merchant_center,
		    $this->wc
		);

		$this->syncer_hooks->register();
	}
}
