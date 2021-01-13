<?php
declare( strict_types=1 );

namespace Automattic\WooCommerce\GoogleListingsAndAds\Internal\DependencyManagement;

use Automattic\Jetpack\Connection\Manager;
use Automattic\WooCommerce\GoogleListingsAndAds\API\Google\Connection;
use Automattic\WooCommerce\GoogleListingsAndAds\API\Site\Controllers\MerchantCenter\ConnectionController;
use Automattic\WooCommerce\GoogleListingsAndAds\API\Site\Controllers\MerchantCenter\SettingsController;
use Automattic\WooCommerce\GoogleListingsAndAds\API\Site\Controllers\MerchantCenter\ShippingRateController;
use Automattic\WooCommerce\GoogleListingsAndAds\API\Site\Controllers\Onboarding\JetpackConnectController;
use Automattic\WooCommerce\GoogleListingsAndAds\Options\OptionsInterface;
use Automattic\WooCommerce\GoogleListingsAndAds\Proxies\RESTServer;
use Automattic\WooCommerce\GoogleListingsAndAds\Vendor\League\Container\Definition\DefinitionInterface;
use Automattic\WooCommerce\GoogleListingsAndAds\Vendor\League\ISO3166\ISO3166DataProvider;

/**
 * Class RESTServiceProvider
 *
 * @package Automattic\WooCommerce\GoogleListingsAndAds\Internal\DependencyManagement
 */
class RESTServiceProvider extends AbstractServiceProvider {

	/**
	 * Returns a boolean if checking whether this provider provides a specific
	 * service or returns an array of provided services if no argument passed.
	 *
	 * @param string $service
	 *
	 * @return boolean
	 */
	public function provides( string $service ): bool {
		return 'rest_controller' === $service;
	}

	/**
	 * Use the register method to register items with the container via the
	 * protected $this->leagueContainer property or the `getLeagueContainer` method
	 * from the ContainerAwareTrait.
	 *
	 * @return void
	 */
	public function register() {
		$this->share( JetpackConnectController::class, Manager::class );
		$this->share_with_options( SettingsController::class );
		$this->share( ConnectionController::class, Connection::class );
		$this->share_with_options( ShippingRateController::class, ISO3166DataProvider::class );
	}

	/**
	 * Share a class.
	 *
	 * Overridden to include the RESTServer proxy with all classes.
	 *
	 * @param string $class        The class name to add.
	 * @param mixed  ...$arguments Constructor arguments for the class.
	 *
	 * @return DefinitionInterface
	 */
	protected function share( string $class, ...$arguments ): DefinitionInterface {
		return parent::share( $class, RESTServer::class, ...$arguments )->addTag( 'rest_controller' );
	}

	/**
	 * Share a class with the OptionsInterface object provided.
	 *
	 * @param string $class        The class name to add.
	 * @param mixed  ...$arguments Constructor arguments for the class.
	 *
	 * @return DefinitionInterface
	 */
	protected function share_with_options( string $class, ...$arguments ): DefinitionInterface {
		return $this->share( $class, OptionsInterface::class, ...$arguments );
	}
}
