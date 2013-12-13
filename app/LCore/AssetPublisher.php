<?php

namespace LCore;

use Composer\Script\CommandEvent;

use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Foundation\Application;
use Illuminate\Workbench\Starter as Workbench;
use Illuminate\Config\Repository as Config;
use Illuminate\Support\ClassLoader;
use Patchwork\Utf8\Bootup;

class AssetPublisher
{
	protected $app;

	public static function createHelper(CommandEvent $event)
	{
		$package = $event->getComposer()->getPackage();
		$plugin_manager = $event->getComposer()->getPluginManager();
		$package_dir = $plugin_manager->getInstallPath($package);
		$base_dir = dirname(dirname(dirname($package_dir)));

		$script = "<?php\nrequire('vendor/autoload.php');\n\$asset_publisher = new LCore\AssetPublisher();\n\$asset_publisher->publish();";
		file_put_contents($base_dir . '/lcore.php', $script);
	}

	public function publish()
	{
		$i = -1;
		foreach ($GLOBALS['argv'] as $k=>$txt) {
			if ($txt == 'publish') {
				$i = $k + 1;
				break;
			}
		}
		if ($i < 0 || empty($GLOBALS['argv'][$i])) {
			echo "\nUsage:\n\n";
			echo '    php lcore.php publish "target_directory"';
			echo "\n\n";
			exit();
		}
		$base = $GLOBALS['argv'][$i];
		$packages = $this->findPackages();
		foreach ($packages as $package=>$source) {
			$target = $this->createPackageFolder($base, $package);
			$this->copy($source, $target);
		}
	}

	public function createPackageFolder($base, $package)
	{
		$target = rtrim($base, '/\\').'/packages/'.$package;
		if (!file_exists($target)) {
			mkdir($target, 0644, true);
		}
		return $target;
	}

	public function copy($source, $target)
	{
		$files = array_diff(scandir($source), ['.', '..']);
		foreach ($files as $file) {
			if (is_dir($source.'/'.$file)) {
				if (!file_exists($target.'/'.$file)) {
					mkdir($target.'/'.$file, 0644, true);
				}
				$this->copy($source.'/'.$file, $target.'/'.$file);
				continue;
			}
			copy($source.'/'.$file, $target.'/'.$file);
		}
	}

	public function findPackages()
	{
		Bootup::initMbstring();
		ClassLoader::register();
		$app = new Application;

		$env = $app->detectEnvironment(array(
			'local' => array('your-machine-name'),
		));
		$app->bindInstallPaths($this->getPath());

		Facade::clearResolvedInstances();

		Facade::setFacadeApplication($app);

		$config = new Config($app->getConfigLoader(), $env);

		$app->instance('config', $config);

		$packages = [];

		$providers = $app['config']['app']['providers'];
		foreach ($providers as $i => $provider) {
			if (strpos($provider, 'Illuminate') === 0) {
				continue;
			}

			$ref = new \ReflectionClass($provider);
			$filename = $ref->getFilename();
			$package_dir = dirname(strstr($filename, $provider, true));
			$public_dir = $package_dir.'/public';
			if (!file_exists($public_dir) || !is_dir($public_dir)) {
				continue;
			}

			$minus_two_dir = dirname(dirname($package_dir));
			$package_name = str_replace('\\', '/', substr($package_dir, strlen($minus_two_dir) + 1));
			$packages[$package_name] = $public_dir;
		}
		return $packages;
	}

	public function getPath()
	{
		return array(
			'app' => dirname(dirname(__DIR__)).'/app',
			'public' => dirname(dirname(__DIR__)).'/public',
			'base' => dirname(dirname(__DIR__)),
			'storage' => dirname(dirname(__DIR__)).'/app/storage',
		);
	}
}