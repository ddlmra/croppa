<?php namespace Bkwld\Croppa;

class ServiceProviderLaravel5 extends \Illuminate\Support\ServiceProvider {

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register() {
		// merge default config
		if (app()->environment('local')) {
			$config_file = __DIR__.'/../../config/local/config.php';
			$this->mergeConfigFrom($config_file, 'croppa');
		}
		$this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'croppa');

		// Bind a new singleton instance of Croppa to the app
		$this->app->singleton('croppa', function($app) {
			// Inject dependencies
			return new Croppa(array_merge($app['config']->get('croppa'), array(
				'host' => '//'.$app->make('request')->getHttpHost(),
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

		$this->publishes(array(
			__DIR__.'/../../config/config.php' => config_path('croppa.php')
		));

		// Listen for Cropa style URLs, these are how Croppa gets triggered
		$this->app->make('router')
			->get('{path}', 'Bkwld\Croppa\Handler@handle')
			->where('path', $this->app['croppa']->directoryPattern());
	}
}
