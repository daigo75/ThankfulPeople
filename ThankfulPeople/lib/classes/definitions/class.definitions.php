<?php
namespace Aelia\Plugins\ThankfulPeople;
if(!defined('APPLICATION')) exit();

/**
 * Constants used by ThankfulPeople plugin.
 */
class Definitions {
	// @var array Holds a list of the plugin paths
	protected static $Paths = array();

	// @var array Holds a list of the plugin URLs
	protected static $URLs = array();

	// TODO Add error codes as constants

	// TODO Add URL arguments as constants

	/**
	 * Returns the full path corresponding to the specified key.
	 *
	 * @param key The path key.
	 * @return string
	 */
	public static function Path($key) {
		return GetValue($key, self::$Paths, '');
	}

	/**
	 * Builds and stores the paths used by the plugin.
	 */
	protected static function SetPaths() {
		self::$Paths['plugin'] = PATH_PLUGINS . '/ThankfulPeople';
		self::$Paths['lib'] = self::Path('plugin') . '/lib';
		self::$Paths['views'] = self::Path('plugin') . '/views';
		self::$Paths['admin_views'] = self::Path('views') . '/admin';
		self::$Paths['classes'] = self::Path('lib') . '/classes';
		self::$Paths['vendor'] = self::Path('plugin') . '/vendor';

		self::$Paths['design'] = self::Path('plugin') . '/design';
		self::$Paths['css'] = self::Path('design');
		self::$Paths['images'] = self::Path('design') . '/images';

		self::$Paths['js'] = self::Path('plugin') . '/js';
		self::$Paths['js_admin'] = self::Path('js') . '/admin';
		self::$Paths['js_frontend'] = self::Path('js') . '/frontend';
	}

	/**
	 * Builds and stores the paths specific to this plugin.
	 */
	protected static function SetPluginPaths() {
	}

	/**
	 * Builds and stores the URLs used by the plugin.
	 */
	protected static function SetBaseURLs() {
		self::$URLs['plugin'] =  'plugin/ThankfulPeople';

		self::$URLs['design'] = self::URL('plugin') . '/design';
		self::$URLs['css'] = self::URL('design');
		self::$URLs['images'] = self::URL('design') . '/images';
		self::$URLs['js'] = self::URL('plugin') . '/js';
		self::$URLs['js_admin'] = self::URL('js') . '/admin';
		self::$URLs['js_frontend'] = self::URL('js') . '/frontend';
	}

	// TODO Add additional URLs needed by the plugin
	protected static function SetPluginURLs() {
		self::$URLs['settings_general'] =  'settings';
	}

	/**
	 * Returns the URL corresponding to the specified key.
	 *
	 * @param key The URL key.
	 * @return string
	 */
	public function URL($key) {
		return GetValue($key, self::$URLs, '');
	}

	public function Initialize() {
		self::SetPaths();
		self::SetPluginPaths();

		self::SetBaseURLs();
		self::SetPluginURLs();
	}

	// @var int The default interval to recalculate the thanks, in hours
	const DEFAULT_RECALC_INTERVAL = 24;
}

Definitions::Initialize();
