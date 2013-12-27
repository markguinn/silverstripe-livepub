<?php
/**
 * Drop-in replacement for SecurityToken class that enables CSRF tokens to
 * remain active even when publishing.
 *
 * This is automatically turned on
 *
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 12.26.2013
 * @package livepub
 */
class LiveSecurityToken extends SecurityToken
{
	/**
	 * @var string - you can change this if for some reason you need to. probably wise not to include
	 *               any characters that might get encoded in url or html contexts though
	 */
	private static $token_placeholder = 'LIVESECURITYTOKENGOESHERE';


	/**
	 * @var bool - won't be automatically turned on if you set this to true in config
	 */
	private static $disabled = false;


	/**
	 * Gets a global token (or creates one if it doesn't exist already).
	 * @return LiveSecurityToken
	 */
	public static function inst() {
		if (Config::inst()->get('LiveSecurityToken', 'disabled')) return null;
		if (!self::$inst) self::$inst = new LiveSecurityToken();
		return self::$inst;
	}


	/**
	 * In a normal case just returns a security token via the parent
	 * class. If we're publishing, it adds some init code and returns
	 * a string that we can replace with real code later (since we
	 * don't have control over escaping after this point)
	 *
	 * @return String
	 */
	public function getValue() {
		if (LivePubHelper::is_publishing()) {
			$sessionKey = $this->getName();

			// This duplicates the SessionToken functionality exactly
			// But only requires the one class from Silverstripe
			LivePubHelper::add_init_code('
				function new_security_token() {
					require_once("'.BASE_PATH.'/framework/security/RandomGenerator.php");
					$generator = new RandomGenerator();
					return $generator->randomToken("sha1");
				}
				$security_token = isset($_SESSION["'.$sessionKey.'"]) ? $_SESSION["'.$sessionKey.'"] : new_security_token();
			', 'LiveSecurityToken');

			return self::get_token_placeholder();
		} else {
			return parent::getValue();
		}
	}


	/**
	 * @return String
	 */
	public static function get_token_placeholder() {
		return Config::inst()->get('LiveSecurityToken', 'token_placeholder');
	}


	/**
	 * @return string
	 */
	public static function get_token_published_code() {
		return '<' . '?php echo $security_token; ?' . '>';
	}
}
