<?php
defined('MUDPUPPY') or die('Restricted');

class App {
	private static $instance = null;
	private static $dbo = null;
	private static $exited = false;
	private static $completionHandlers = array();
	/** @var Controller|PageController */
	private static $pageController = null;

	public function __construct() {
		if (self::$instance) {
			throw new Exception('App is a static class; cannot instantiate.');
		}
		self::$instance = $this;
	}

	public function __destruct() {
		if (!self::$exited) {
			App::cleanExit();
		}
	}

	/**
	 * Called by Mudpuppy when the application starts up.
	 */
	public static function initialize() {
		// Construct the single instance, which is necessary to automatically call App::cleanExit() on destruction
		new App();

		// Buffer the output (see App::cleanExit() for the reason)
		ob_start();

		// Start the session
		Session::start();

		// Setup the database if desired
		if (Config::$dbHost) {
			// Create database object
			self::$dbo = new Database();

			// Connect to database
			$connectSuccess = self::$dbo->connect(Config::$dbHost, Config::$dbDatabase, Config::$dbUser, Config::$dbPass);

			// Display log on failed connection
			if (!$connectSuccess) {
				if (Config::$debug) {
					Log::displayFullLog();
				} else {
					print 'Database Connection Error. Please contact your administrator or try again later.';
					die();
				}
			}

			// Refresh login, check for session expiration
			Security::refreshLogin();
		}

		// Do any application-specific startup tasks
		forward_static_call(array(Config::$appClass, 'initialize'));

		// Process the request
		self::$pageController = Controller::getController();
		self::$pageController->processRequest();
	}

	/**
	 * @return Controller|PageController
	 */
	public static function getPageController() {
		return self::$pageController;
	}

	/**
	 * Called from Security when a session has expired
	 */
	public static function sessionExpired() {
		Security::logout();
		self::redirect();
	}

	/**
	 * Add a message to the session
	 * @param string $title
	 * @param string $text
	 * @param string $type
	 */
	public static function addMessage($title, $text = '', $type = 'info') {
		$curMessages = & Session::get('messages', array());
		$curMessages[] = array('title' => $title, 'text' => $text, 'type' => $type);
	}

	/**
	 * Retrieve the messages from the session (also removes them from the session)
	 * @return mixed
	 */
	public static function readMessages() {
		return Session::extract('messages', array());
	}

	/**
	 * Add a handler to be called AFTER the connection and session are closed
	 * @param {function} $handler
	 */
	public static function addExitHandler($handler) {
		self::$completionHandlers[] = $handler;
	}

	/**
	 * Exit the app and write to the log if necessary
	 */
	public static function cleanExit() {
		// Make sure we only do this once, as it could potentially be triggered multiple times during termination
		if (!self::$exited) {
			self::$exited = true;

			// If in debug mode and we don't have a database connection, display the log if necessary. Needs to happen
			// here before the connection is closed.
			if (Config::$debug && !Config::$dbHost) {
				Log::write();
			}

			// Flush and close connection
			$size = ob_get_length();
			header('Content-Encoding: none');
			header('Content-Length: ' . $size);
			header('Connection: close');
			ob_end_flush();
			flush();

			// close session
			if (session_id()) {
				session_write_close();
			}

			// perform registered callbacks
			foreach (self::$completionHandlers as $handler) {
				$handler();
			}

			// Record errors to database (or S3 or local file, depending on configuration)
			Log::write();
		}
		// Then terminate execution
		exit();
	}

	/**
	 * Get the static database object
	 * @return Database
	 */
	public static function getDBO() {
		return self::$dbo;
	}

	/**
	 * Performs an HTTP header redirect to the specified URL.
	 *
	 * @param string $absLocation can be a fully qualified URL or an absolute path on the server
	 * @param int $statusCode
	 */
	public static function redirect($absLocation = '', $statusCode = 302) {
		http_response_code($statusCode);

		if (substr($absLocation, 0, 1) == '/') {
			$absLocation = substr($absLocation, 1);
		}
		if (preg_match('#^https?\:\/\/#i', $absLocation)) {
			header('Location: ' . $absLocation);
		} else {
			header('Location: ' . App::getBaseURL() . $absLocation);
		}
		App::cleanExit();
	}

	/**
	 * Get the fully qualified base url of the app
	 */
	public static function getBaseURL() {
		$url = 'http';
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] != 'off') {
			$url .= 's';
		}
		$url .= '://' . $_SERVER['HTTP_HOST'];
		if ($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) {
			$url .= ":$_SERVER[SERVER_PORT]";
		}
		$url .= '/';
		return $url;
	}

	public static function getCurrentUrl($includeParams = true) {
		$pageURL = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
		$uri = $_SERVER["REQUEST_URI"];
		if (!$includeParams) {
			$index = strpos($_SERVER["REQUEST_URI"], '?');
			if ($index !== false) {
				$uri = substr($_SERVER['REQUEST_URI'], 0, $index);
			}
		}

		if ($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443") {
			$pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $uri;
		} else {
			$pageURL .= $_SERVER["SERVER_NAME"] . $uri;
		}
		return $pageURL;
	}

	public static function abort($statusCode) {
		http_response_code($statusCode);
		if (file_exists("html/$statusCode.html")) {
			require_once("html/$statusCode.html");
		} else {
			require_once('html/500.html');
		}
		App::cleanExit();
	}

}

?>