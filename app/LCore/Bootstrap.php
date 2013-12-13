<?php

namespace LCore;
use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Foundation\Application;
use Illuminate\Workbench\Starter as Workbench;
use Illuminate\Config\Repository as Config;
use Illuminate\Support\ClassLoader;
use Patchwork\Utf8\Bootup;

class Bootstrap
{
	public function __construct()
	{
		define('LARAVEL_START', microtime(true));
		if (file_exists($compiled = dirname(dirname(__DIR__)).'/bootstrap/compiled.php'))
		{
			require $compiled;
		}

		Bootup::initMbstring();

		ClassLoader::register();

		if (is_dir($workbench = dirname(dirname(__DIR__)).'/workbench'))
		{
			Workbench::start($workbench);
		}

		$app = $this->getApp();

		$app->run();
		$app->shutdown();
	}

	public function getApp()
	{
		$app = new Application;

		$app->redirectIfTrailingSlash();
		$env = $app->detectEnvironment(array(
			'local' => array('your-machine-name'),
		));
		$app->bindInstallPaths($this->getPath());



		error_reporting(-1);
		if ( ! extension_loaded('mcrypt'))
		{
			die('Laravel requires the Mcrypt PHP extension.'.PHP_EOL);

			exit(1);
		}

		$app->instance('app', $app);
		if (isset($unitTesting))
		{
			$app['env'] = $env = $testEnvironment;
		}

		Facade::clearResolvedInstances();

		Facade::setFacadeApplication($app);

		$config = new Config($app->getConfigLoader(), $env);

		$app->instance('config', $config);

		$app->startExceptionHandling();

		if ($env != 'testing') ini_set('display_errors', 'Off');

		if ($app->runningInConsole())
		{
			$app->setRequestForConsoleEnvironment();
		}

		$config = $app['config']['app'];

		date_default_timezone_set($config['timezone']);

		AliasLoader::getInstance($config['aliases'])->register();

		Request::enableHttpMethodParameterOverride();

		$providers = $config['providers'];

		$app->getProviderRepository()->load($app, $providers);

		$app->boot();

		$path = $app['path'].'/start/global.php';
		if (file_exists($path)) require $path;

		$path = $app['path']."/start/{$env}.php";
		if (file_exists($path)) require $path;
		if (file_exists($path = $app['path'].'/routes.php'))
		{
			require $path;
		}



		return $app;
	}

	public function getPath()
	{
		return array(

			/*
			|--------------------------------------------------------------------------
			| Application Path
			|--------------------------------------------------------------------------
			|
			| Here we just defined the path to the application directory. Most likely
			| you will never need to change this value as the default setup should
			| work perfectly fine for the vast majority of all our applications.
			|
			*/

			'app' => dirname(dirname(__DIR__)).'/app',

			/*
			|--------------------------------------------------------------------------
			| Public Path
			|--------------------------------------------------------------------------
			|
			| The public path contains the assets for your web application, such as
			| your JavaScript and CSS files, and also contains the primary entry
			| point for web requests into these applications from the outside.
			|
			*/

			'public' => dirname(dirname(__DIR__)).'/public',

			/*
			|--------------------------------------------------------------------------
			| Base Path
			|--------------------------------------------------------------------------
			|
			| The base path is the root of the Laravel installation. Most likely you
			| will not need to change this value. But, if for some wild reason it
			| is necessary you will do so here, just proceed with some caution.
			|
			*/

			'base' => dirname(dirname(__DIR__)),

			/*
			|--------------------------------------------------------------------------
			| Storage Path
			|--------------------------------------------------------------------------
			|
			| The storage path is used by Laravel to store cached Blade views, logs
			| and other pieces of information. You may modify the path here when
			| you want to change the location of this directory for your apps.
			|
			*/

			'storage' => dirname(dirname(__DIR__)).'/app/storage',

		);
	}

}