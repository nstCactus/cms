<?php
/**
 * Craft bootstrap file.
 *
 * @link http://buildwithcraft.com/
 * @copyright Copyright (c) 2013 Pixel & Tonic, Inc.
 * @license http://buildwithcraft.com/license
 */

use craft\app\helpers\ArrayHelper;

// Setup
// -----------------------------------------------------------------------------

// Determine what type of application we're loading
if (!isset($appType) || ($appType !== 'web' && $appType !== 'console'))
{
	$appType = 'web';
}

$getArg = function($param, $unset = true)
{
	if (isset($_SERVER['argv']))
	{
		foreach ($_SERVER['argv'] as $key => $arg)
		{
			if (strpos($arg, "--{$param}=") !== false)
			{
				$parts = explode('=', $arg);
				$value = $parts[1];

				if ($unset)
				{
					unset($_SERVER['argv'][$key]);
				}

				return $value;
			}
		}
	}
};

$createFolder = function($path)
{
	// Code borrowed from IOHelper...
	if (!is_dir($path))
	{
		$oldumask = umask(0);

		if (!mkdir($path, 0755, true))
		{
			// Set a 503 response header so things like Varnish won't cache a bad page.
			http_response_code(503);

			exit('Tried to create a folder at '.$path.', but could not.');
		}

		// Because setting permission with mkdir is a crapshoot.
		chmod($path, 0755);
		umask($oldumask);
	}
};

$ensureFolderIsReadable = function($path, $writableToo = false)
{
	$realPath = realpath($path);

	// !@file_exists('/.') is a workaround for the terrible is_executable()
	if ($realPath === false || !is_dir($realPath) || !@file_exists($realPath.'/.'))
	{
		// Set a 503 response header so things like Varnish won't cache a bad page.
		http_response_code(503);

		exit(($realPath !== false ? $realPath : $path).' doesn\'t exist or isn\'t writable by PHP. Please fix that.');
	}

	if ($writableToo)
	{
		if (!is_writable($realPath))
		{
			// Set a 503 response header so things like Varnish won't cache a bad page.
			http_response_code(503);

			exit($realPath.' isn\'t writable by PHP. Please fix that.');
		}
	}
};

// Determine the paths
// -----------------------------------------------------------------------------

// App folder, we are already in you.
$appPath = __DIR__;

// By default the craft/ folder will be one level up
$craftPath = realpath(defined('CRAFT_BASE_PATH') ? CRAFT_BASE_PATH : $getArg('basePath') ?: dirname($appPath));

// By default the remaining folders will be in craft/
$configPath = realpath(defined('CRAFT_CONFIG_PATH') ? CRAFT_CONFIG_PATH : $getArg('configPath') ?: $craftPath.'/config');
$pluginsPath = realpath(defined('CRAFT_PLUGINS_PATH') ? CRAFT_PLUGINS_PATH : $getArg('pluginsPath') ?: $craftPath.'/plugins');
$storagePath = realpath(defined('CRAFT_STORAGE_PATH') ? CRAFT_STORAGE_PATH : $getArg('storagePath') ?: $craftPath.'/storage');
$templatesPath = realpath(defined('CRAFT_TEMPLATES_PATH') ? CRAFT_TEMPLATES_PATH : $getArg('templatesPath') ?: $craftPath.'/templates');
$translationsPath = realpath(defined('CRAFT_TRANSLATIONS_PATH') ? CRAFT_TRANSLATIONS_PATH : $getArg('translationsPath') ?: $craftPath.'/translations');

// Validate the paths
// -----------------------------------------------------------------------------

// Validate permissions on craft/config/ and craft/storage/
$ensureFolderIsReadable($configPath);

// If license.key doesn't exist yet, make sure the config folder is writable.
if (!file_exists($configPath.'/license.key'))
{
	$ensureFolderIsReadable($configPath, true);
}

$ensureFolderIsReadable($storagePath, true);

// Create the craft/storage/runtime/ folder if it doesn't already exist
$createFolder($storagePath.'/runtime');
$ensureFolderIsReadable($storagePath.'/runtime', true);

// Create the craft/storage/runtime/logs/ folder if it doesn't already exist
$createFolder($storagePath.'/runtime/logs');
$ensureFolderIsReadable($storagePath.'/runtime/logs', true);

// Log errors to craft/storage/runtime/logs/phperrors.log
ini_set('log_errors', 1);
ini_set('error_log', $storagePath.'/runtime/logs/phperrors.log');

// Determine if Craft is running in Dev Mode
// -----------------------------------------------------------------------------

// Set the environment
defined('CRAFT_ENVIRONMENT') || define('CRAFT_ENVIRONMENT', $_SERVER['SERVER_NAME']);

// We need to special case devMode in the config because YII_DEBUG has to be set as early as possible.
if ($appType === 'console')
{
	$devMode = true;
}
else
{
	$devMode = false;
	$generalConfigPath = $configPath.'/general.php';

	if (file_exists($generalConfigPath))
	{
		$generalConfig = require $generalConfigPath;

		if (is_array($generalConfig))
		{
			// Normalize it to a multi-environment config
			if (!array_key_exists('*', $generalConfig))
			{
				$generalConfig = ['*' => $generalConfig];
			}

			// Loop through all of the environment configs, figuring out what the final word is on Dev Mode
			foreach ($generalConfig as $env => $envConfig)
			{
				if ($env == '*' || strpos(CRAFT_ENVIRONMENT, $env) !== false)
				{
					if (isset($envConfig['devMode']))
					{
						$devMode = $envConfig['devMode'];
					}
				}
			}
		}
	}
}

if ($devMode)
{
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
	defined('YII_DEBUG') || define('YII_DEBUG', true);
	defined('YII_ENV') || define('YII_ENV', 'dev');
}
else
{
	error_reporting(0);
	ini_set('display_errors', 0);
	defined('YII_DEBUG') || define('YII_DEBUG', false);
	defined('YII_ENV') || define('YII_ENV', 'prod');
}

// Load the Composer dependencies and the app
// -----------------------------------------------------------------------------

// Guzzle makes use of these PHP constants, but they aren't actually defined in some compilations of PHP
// See: http://it.blog.adclick.pt/php/fixing-php-notice-use-of-undefined-constant-curlopt_timeout_ms-assumed-curlopt_timeout_ms/
defined('CURLOPT_TIMEOUT_MS')        || define('CURLOPT_TIMEOUT_MS',        155);
defined('CURLOPT_CONNECTTIMEOUT_MS') || define('CURLOPT_CONNECTTIMEOUT_MS', 156);

// Load the files
require $appPath.'/vendor/autoload.php';
require $appPath.'/vendor/yiisoft/yii2/Yii.php';
require $appPath.'/Craft.php';

// Set aliases
Craft::setAlias('@craft/app', $appPath);
Craft::setAlias('@config', $configPath);
Craft::setAlias('@plugins', $pluginsPath);
Craft::setAlias('@storage', $storagePath);
Craft::setAlias('@templates', $templatesPath);
Craft::setAlias('@translations', $translationsPath);

// Append Craft's class map to Yii's
Yii::$classMap = ArrayHelper::merge(
	Yii::$classMap,
	require $appPath.'/classes.php'
);

// Load the config
$config = ArrayHelper::merge(
	require $appPath.'/config/main.php',
	require $appPath.'/config/common.php',
	require $appPath.'/config/'.$appType.'.php'
);

if ($devMode)
{
	$config['bootstrap'][] = 'debug';
	$config['modules']['debug'] = 'yii\debug\Module';
}

// Initialize the application
$class = 'craft\\app\\'.$appType.'\\Application';
/* @var $app craft\app\web\Application|craft\app\console\Application */
$app = new $class($config);

return $app;
