<?php
/**
 * This module provides Javascript obfuscation of email addresses. It also 
 * degrades gracefully to an URL-based solution when Javascript is not enabled.
 * 
 * This module used code from the (unreleased) Hide Mailto module as a starting point 
 * (http://www.silverstripe.org/hide-mail-to-module/).
 *
 * Full documentation for this module has not yet been completed.
 * 
 * @package hideemail
 * @author Simon Wade <simon.wade@dimension27.com>
 */

/**
 * Generates obfusticated links, and also holds the method called when /mailto/
 * is called via the URL. As noted above, take a look at the _config.php file to
 * see how mailto/ maps to this class.
 */
class HideEmail_Controller extends ContentController {
	/**
	 * The list of allowed domains to create a mailto: link to. By default, allow
	 * all domains.
	 *
	 * TODO Maybe the default should be to allow the current domain only?
	 */
	static $allowed_domains = '*';

	/**
	 * @param mixed $domains Either an array of domains to allow, or the string
	 * '*' to allow all domains.
	 */
	static function set_allowed_domains($domains) {
		self::$allowed_domains = $domains;
	}

	/**
	 * This is called by default when this controller is executed.
	 */
	function handleAction($request) {
		// We have two situations to deal with, where urlParams['Action'] is an int (assume Member ID), or a string (assume username)
		if(is_numeric($this->urlParams['User'])) {
			// User is numeric, assume it's a member ID and optional parameter is the email subject
			$member = DataObject::get_by_id('Member', (int)$this->urlParams['User']);
			if(!$member) user_error("No member found with ID #" . $this->urlParams['User'], E_USER_ERROR); // No member found with this ID, perhaps we could redirect a user back instead of giving them a 500 error?
			list($user, $domain) = explode('@', $member->Email);
			$subject = $this->urlParams['Domain'];
		}
		else {
			// User is not numeric, assume that User is the username, Domain is the domain and optional Subject is the email subject
			$user = $this->urlParams['User'];
			$domain = $this->urlParams['Domain'];
			// HTTPRequest strips off the final part of the domain name thinking it's a file extension
			if( $extension = $request->getExtension() ) $domain .= '.'.$request->getExtension();
			$subject = $this->urlParams['Subject'];
		}

		// Make sure the domain is in the allowed domains
		if((is_string(self::$allowed_domains) && self::$allowed_domains == '*') || in_array($domain, self::$allowed_domains)) {
			if( !$subject ) {
				$subject = $request->requestVar('subject');
			}
			// Create the redirect
			$target = 'mailto:' . $user . '@' . $domain;
			if(isset($subject)) $target .= '?subject=' . Convert::raw2xml($subject);
			header("Location: " . $target);
			
			// This is a bit hacky and can probably be tidied up a lot...
			$url = @$_SERVER['HTTP_REFERER'];
			if(!$url) $url = Director::absoluteBaseURL();
			echo"<p>Redirecting to <a href=\"$url\" title=\"Please click this link if your browser does not redirect you\">$url</a></p>
					<meta http-equiv=\"refresh\" content=\"0; url=$url\" />
					<script type=\"text/javascript\">setTimeout('window.location.href = \"$url\"', 100);</script>";
			exit;
		}
		else {
			// Not allowed to redirect to this domain
			user_error("We're not allowed to redirect to the domain '$domain', because it's not listed in the _config.php file", E_USER_ERROR);
		}
	}

	/**
	 * To satisfy pageless-controller logic.
	 * TODO Extend PagelessController once that's merged into the core
	 */
	function __construct($dataRecord = null) {
		parent::__construct($dataRecord);
	}

}

class HideEmail {

	public static $jsAdded = false;
	static function obfuscateEmails( $content ) {
		$search = array(
				'/<a (.*)href=([\'"])\s*mailto:\s*(\S+)@(\S+)([\'"])(.*)>(.*)<\/a>/siUe', // (\?[^\'"]*subject=([^&]+))?
				'/[^>]+@\S+/e'
		);
		$replace = array(
				'"<script>document.write(deobfuscate(".HideEmail::obfuscate("$0")."))</script>'."\n"
						.'<noscript><a $1href=$2'.Director::baseURL()
						.'mailto/$3/$4$5$6>".HideEmail::replaceEmails("$7")."</a></noscript>"',
				'"<script>document.write(deobfuscate(".HideEmail::obfuscate("$0")."))</script>'
						.'<noscript>".HideEmail::replaceEmails("$0")."</noscript>"'
		);
		$count = 0;
		$content = preg_replace($search, $replace, $content, -1, $count);
		if( $count > 0 && !self::$jsAdded ) {
			$content =<<<EOB
<script type="text/javascript">function deobfuscate(s) { var o = s[0], i, r = ''; for( i = 1; i < s.length; i++ ) { r += String.fromCharCode(s[i] - o); } return r; }</script>
$content
EOB;
			self::$jsAdded = true;
		}
		return $content;
	}

	static function obfuscate( $str ) {
		$offset = rand(100, 999);
		$rv = array($offset);
		for( $i = 0; $i < strlen($str); $i++ ) {
			$rv[] = ord($str[$i]) + $offset;
		}
		$rv = '['.implode(',', $rv).']';
		return $rv;
	}

	static function replaceEmails( $str ) {
		return preg_replace('/\S+\@\S+/', '[email hidden]', $str);
	}

	private function __construct() {}

}

class HideEmail_PageDecorator extends SiteTreeDecorator {

	function Content() {
		$content = $this->owner->getField('Content');
		return HideEmail::obfuscateEmails($content);
	}

}

/**
 * The DataObjectDecorator that adds $HideEmailLink functionality to the Member
 * class.
 */
class HideEmail_Role extends DataObjectDecorator {
	function HideEmailLink() {
		return "mailto/" . $this->owner->ID;
	}
}

?>