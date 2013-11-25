<?php
defined('MUDPUPPY') or die('Restricted');

define('APP_VERSION', '1.0.0');

// Base configuration
class Config {
	// Database settings
	public static $dbProtocol = 'mysql:host=%s;dbname=%s';
	public static $dbHost = 'localhost';
	public static $dbDatabase = 'mudpuppy_sample';
	public static $dbUser = 'root';
	public static $dbPass = 'root';

	// Authentication
	public static $noActivityTimeout = 3600;

	// Site configuration
	public static $appTitle = 'Mudpuppy Sample';
	public static $timezone = 'America/New_York';
	public static $dateFormat = 'd M Y H:i:s \\G\\M\\TO';

	// Application configuration
	public static $appClass = 'app\\SampleApp';
	public static $autoloadFolders = array(); // array, must include trailing /
	public static $randomSeedOffset = 0x5C7474D1;

	// Debugging
	public static $debug = true;
	public static $logQueries = true;
	public static $logLevel = LOG_LEVEL_ALWAYS;

	// AWS configuration
	public static $awsConfig = '';
}

// Production-specific overrides
if (isset($_SERVER['SERVER_NAME']) && strcasecmp($_SERVER['SERVER_NAME'], 'localhost') != 0 && $_SERVER['SERVER_ADDR'] != '127.0.0.1' && $_SERVER['SERVER_ADDR'] != '::1') {
	Config::$dbHost = '';
	Config::$dbUser = '';
	Config::$dbPass = '';
	Config::$debug = false;
	Config::$logQueries = false;
}
?>