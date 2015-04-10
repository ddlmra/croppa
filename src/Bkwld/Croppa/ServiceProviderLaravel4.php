<?php namespace Bkwld\Croppa;

class ServiceProviderLaravel4 extends \Illuminate\Support\ServiceProvider {

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register() {

		// Bind a new singleton instance of Croppa to the app
		$this->app->singleton('croppa', function($app) {

			// Inject dependencies
			return new Croppa(array_merge($app->make('config')->get('croppa::config'), array(
				'host' => '//'.$this->app->make('request')->getHttpHost(),
				'public' => $app->make('path.public'),
			)));
		});
	}

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot() {
		$this->package('bkwld/croppa');

		// Listen for Cropa style URLs, these are how Croppa gets triggered
		$this->app->make('router')
			->get('{path}', 'Bkwld\Croppa\Handler@handle')
			->where('path', $this->app['croppa']->directoryPattern());
	}

}
