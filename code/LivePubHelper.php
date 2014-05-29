<?php
/**
 * Helper class to facilitate dynamic content inside staticpublisher produced php files.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 8.5.10
 */
class LivePubHelper extends Object
{
	protected static $is_publishing = false;

	protected static $silverstripe_db_included = false;


	/**
	 * @var $vars array - each key is a variable name and value is php code to initialize it
	 */	
	public static $vars = array();
	
	/**
	 * @var $init_code array - each entry is a separate block of code, reset for each page
	 */
	public static $init_code = array();
	
	/**
	 * @var $base_init_code string - constant code that is added for all pages
	 */
	public static $base_init_code = '<?php
		$isAjax = (isset($_REQUEST["ajax"]) || (isset($_SERVER["HTTP_X_REQUESTED_WITH"]) && $_SERVER["HTTP_X_REQUESTED_WITH"] == "XMLHttpRequest"));
	?>';

	/**
	 * @var $context string - php|html - tells eval_php whether to wrap code in <?php ?> tag
	 */
 	public static $context = 'html';

	/**
	 * @var $template_path array - where to look for php templates. initially contains /templates/php in project and theme
	 */
	protected static $template_path = array();



	/**
	 * this is only to be called when static publishing is starting
	 */
	static function init_pub() {
		global $project;		

		self::$is_publishing = true;

		// if we've set up a global static configuration, add that in
		$file = BASE_PATH . '/' . $project . '/_config_static.php';
		if (file_exists($file) && strpos(self::$base_init_code, $file) === false) {
			self::$base_init_code .= "\n<?php include_once('$file') ?>\n";
		}
	}


	/**
	 * called when publishing is done
	 */
	static function stop_pub() {
		self::$is_publishing = false;
	}
	

	/**
	 * resets the init code to the base config file
	 */
	static function clear_init_code() {
		self::$init_code = array();
		self::$vars = array();
		self::$silverstripe_db_included = false;
		if (self::$base_init_code) array_unshift(self::$init_code, self::$base_init_code);
	}
	
	
	/**
	 * Outputs the static config code if needed. This should be placed in a function in Page_Controller and called from template.
	 */
	static function get_init_code() {
		if (self::is_publishing()) {
			$code = "";

			// if objects have set up initialization code, add that in
			foreach (self::$init_code as $block){
				$block = trim($block);
				if (strlen($block) == 0) continue;
				if (substr($block, 0, 5) != '<?php') $code .= '<?php ';
				$code .= $block;
				if (substr($block, -2) != '?>') $code .= ' ?>';
				$code .= "\n";
			}
			
			// if there are variables to initialize, add that in
			if (count(self::$vars) > 0) {
				$code .= "\n<?php\n";
				foreach (self::$vars as $var => $valCode) {
					if ($valCode === true) continue; // this means it was already set in the initialization
					$code .= '$' . $var . ' = ' . $valCode . ";\n";
				}
				$code .= "\n?>\n";
			}
			
			return $code;
		}
	}


	/**
	 * @param string $code
	 * @param string $id [optional] - if present, this prevents the same code from being added twice
	 */
	static function add_init_code($code, $id = null) {
		if ($id) {
			self::$init_code[$id] = $code;
		} else {
			self::$init_code[] = $code;
		}
	}


	/**
	 * @return string
	 */
	static function get_init_code_and_clear() {
		$code = self::get_init_code();
		self::clear_init_code();
		return $code;
	}

	/**
	 * returns true if we are currently publishing
	 * @return boolean
	 */
	static function is_publishing() {
		// this will break if you ever turned off $echo_progress on purpose
		//return class_exists('StaticPublisher') && StaticPublisher::$echo_progress;
		return self::$is_publishing;
	}


	/**
	 * evaluates the given php code unless we're currently publishing, in which case it
	 * returns php code that will echo the return value of the eval'd code.
	 *
	 * @param string $code
	 * @return string
	 */
	static function eval_php($code) {
		if (self::is_publishing()) {
			return self::$context=='html'
				? '<?php echo eval(\'' . addcslashes($code, "'") . '\'); ?>'
				: 'eval(\'' . addcslashes($code, "'") . '\')';
		} else {
			return eval($code);
		}
	}
	
	
	/**
	 * evaluates the given php code unless we're currently publishing, in which case it
	 * adds the php code to the initialization code.
	 * NOTE: this will not return or output the result
	 *
	 * @param string $code
	 * @return none
	 */
	static function exec_php($code, $alwaysExec=false) {
		if (self::is_publishing()) {
			self::$init_code[] = $code;
			if ($alwaysExec) eval($code);
		} else {
			eval($code);
		}
	}
	

	/**
	 * loads a php template from the templates/php folder (allowing for themes)
	 * returns either the result of executing the code, or the code itself,
	 * depending on whether we're staticpublishing or not
	 *
	 * @param string $filename
	 * @return string
	 */
	static function include_php($filename) {
		// set up default template paths if needed
		if (count(self::$template_path) == 0) {
			self::init_template_paths();
		}
		
		// check all the possible paths we've accumulated		
		$tpl = false;
		foreach (self::$template_path as $path){
			$checkPath = $path . '/' . $filename . '.php';
			
			if (file_exists($checkPath)) {
				$tpl = $checkPath;
				break;
			}
		}

		if (!$tpl) {
			throw new Exception("Unable to locate PHP template: $filename (paths=".implode(':', self::$template_path).")");
		}
		
		// load it up
		if (self::is_publishing()) {
			//return file_get_contents($tpl);
			return '<?php include "' . $tpl . '"; ?>';
		} else {
			ob_start();
			include $tpl;
			$html = ob_get_contents();
			ob_end_clean();
			//return '<!-- php template -->' . $html . '<!-- end php template -->';
			return $html;
		}
	}


	/**
	 * Sets up the default template paths
	 */
	protected static function init_template_paths() {
		global $project;
		self::$template_path = array();
		self::$template_path[] = BASE_PATH . '/' . SSViewer::get_theme_folder() . '/templates/php';
		self::$template_path[] = BASE_PATH . '/' . $project . '/templates/php';
	}


	/**
	 * @param array $paths
	 */
	static function set_template_paths(array $paths) {
		self::$template_path = $paths;
	}


	/**
	 * Path should be relative to BASE_PATH
	 * @param string $path
	 */
	static function add_template_path($path) {
		if (!isset(self::$template_path) || empty(self::$template_path)) self::init_template_paths();
		if ($path[0] != '/') $path = BASE_PATH . '/' . $path;
		self::$template_path[] = rtrim($path, '/');
	}
	
	
	/**
	 * factory method to create a new wrapper object. if we're
	 * static publishing and an appropriate helper class is 
	 * available it will use that instead. A helper class shouldn't
	 * be needed very often but would be used if you wanted a totally
	 * different class for publishing vs normal mode
	 *
	 * @param object|array $srcdata
	 * @param string $class - what class to wrap it in
	 * @param boolean $add_init_code [optional] - if true, the classes default static init code will be added automatically
	 * @return ViewableWrapper
	 */
	static function wrap($object, $class = 'ViewableWrapper', $add_init_code=true) {
		if (self::is_publishing()) {
			$class2 = "{$class}_LivePub";
			if (class_exists($class2, true)) $class = $class2;

			$obj = new $class($object);
			if ($add_init_code && ($init = $obj->getStaticInit())) self::$init_code[] = $init;
		} else {
			$obj = new $class($object);
		}

		return $obj;
	}


	/**
	 * Takes an array of objects or arrays and creates a dataobjectset,
	 * calling {@link LivePubHelper::wrap} on each item in the array.
	 *
	 * @static
	 * @param array $list
	 * @param string $class [optional] - default is 'ViewableWrapper'
	 * @param string $exclude [optional] - filter set by excluding these values
	 * @param string $exclude_field [optional] - field to filter by
	 * @return DataObjectSet
	 */
	static function wrap_set(array $list, $class = 'ViewableWrapper', $exclude=null, $exclude_field=null) {
		$set = new DataObjectSet();

		foreach ($list as $r) {
			$obj = self::wrap($r, $class);
			if ($exclude && in_array($obj->getField($exclude_field), $exclude)) continue;
			$set->push($obj);
		}

		return $set;
	}
	
	
	/**
	 * if this is called, the published version of the page
	 * will include and initialize the DB::query stub
	 * and connect to the main silverstripe database.
	 * This allows limited use of DB::query() in both a live
	 * and published context.
	 */
	static function require_silverstripe_db() {
		global $databaseConfig;
		if (self::is_publishing() && !self::$silverstripe_db_included) {
			self::$silverstripe_db_included = true;
			self::$init_code[] = '
				$databaseConfig = '.var_export($databaseConfig, true).';
				require_once "'.dirname(dirname(__FILE__)).'/dummy_classes/LivePubDB.php";
				DB::init();
			';
		}
	}


	/**
	 * Ensures that the session will be available on the published page.
	 */
	static function require_session() {
		self::add_init_code('if (!session_id()) session_start();', 'require_session');
	}
}

