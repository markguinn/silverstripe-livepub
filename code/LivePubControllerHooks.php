<?php
/**
 * Extension for Controller class to give you some hooks for livepub in the template.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 04.23.2013
 * @package livepub
 */
class LivePubControllerHooks extends Extension {


	/**
	 * allows you to include php templates that work even when static publishing is enabled
	 */
	function IncludePHP($tpl){
		return LivePubHelper::include_php($tpl);
	}


	/**
	 * returns a viewable wrapper around the session
	 */
	function WrappedSession(){
		LivePubHelper::require_session();
		$obj = LivePubHelper::wrap($_SESSION);
		$obj->setVar('_SESSION');
		return $obj;
	}

	function LPH_Session(){
		return $this->WrappedSession();
	}


	/**
	 * returns a viewable wrapper around the request
	 */
	function WrappedRequest(){
		$obj = LivePubHelper::wrap($_REQUEST);
		$obj->setVar('_REQUEST');
		return $obj;
	}

	function LPH_Request(){
		return $this->WrappedRequest();
	}


	/**
	 * are we currently publishing?
	 */
	function LPH_IsPublishing(){
		return LivePubHelper::is_publishing();
	}

	function LPH_NotPublishing(){
		return !LivePubHelper::is_publishing();
	}


	/**
	 * if we're publishing, outputs an if statement
	 * $func should be something on the controller that
	 * is livepub sensitive (either uses eval_php or
	 * some other method to return code)
	 */
	function LPH_If($func){
		if (LivePubHelper::is_publishing()) {
			LivePubHelper::$context = 'php';
 			$str = $this->getOwner()->$func();
 			LivePubHelper::$context = 'html';
 			return '<?php if (' . $str . '): ?>';
		}
	}


	/**
	 * is this an ajax request? NOTE: this will not actually function as an IF
	 * statement in a live silverstripe template. You have to wrap them together
	 * like this for it to work in both live and published modes:
	 * <% if LPH_NotPublishing && isAjax %><% else %>$LPH_IfNotAjax
	 * $LPH_EndIf<% end_if %>
	 */
	function LPH_IfAjax(){
		if (LivePubHelper::is_publishing()) {
			return '<?php if ($isAjax): ?>';
		}
	}

	function LPH_IfNotAjax(){
		if (LivePubHelper::is_publishing()) {
			return '<?php if (!$isAjax): ?>';
		}
	}


	/**
	 * output generic php closing the above functions
	 */
	function LPH_Else(){
		if (LivePubHelper::is_publishing()) {
			return '<?php else: ?>';
		}
	}

	function LPH_EndIf(){
		if (LivePubHelper::is_publishing()) {
			return '<?php endif; ?>';
		}
	}

}
