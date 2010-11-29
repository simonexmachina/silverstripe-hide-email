<?php

/**
 * Specify the domains you're allowed to send to. This can be either the
 * string '*' (for creating a link for any domain), or an array of domains to
 * limit to certain domains only.
 * This should be set to your own domain(s) in mysite/_config.php.
 */
HideEmail_Controller::set_allowed_domains('*');

/**
 * You can comment out/remove this line if you don't want to use $HideEmailLink
 * on Member objects in the system (it does add some extra processing time to
 * viewing Member objects if you have these)
 */
Object::add_extension('Member', 'HideEmail_Role');

/**
 * You can comment out/remove this line if you don't want to hide emails in 
 * Page content.
 */
Object::add_extension('Page', 'HideEmail_PageDecorator');

/**
 * Sets up the route to handle the mailto links
 */
Director::addRules(50, array(
	'mailto/$User/$Domain/$Subject' => 'HideEmail_Controller'
));
?>