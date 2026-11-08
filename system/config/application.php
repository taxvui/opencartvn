<?php
// Site
$_['site_url']           = HTTP_SERVER;
$_['site_ssl']           = HTTPS_SERVER;

// Url
$_['url_autostart']      = false;

// Database
$_['db_autostart']       = true;
$_['db_engine']          = DB_DRIVER; // mpdo, mysqli or pgsql
$_['db_hostname']        = DB_HOSTNAME;
$_['db_username']        = DB_USERNAME;
$_['db_password']        = DB_PASSWORD;
$_['db_database']        = DB_DATABASE;
$_['db_port']            = DB_PORT;

// Session
$_['session_autostart']  = false;
$_['session_engine']     = 'db';
$_['session_name']       = 'OCSESSID';

// Template
$_['template_engine']    = 'twig';
$_['template_directory'] = '';
$_['template_cache']     = false;

// Autoload Libraries
$_['library_autoload']   = array();

// Actions
$_['action_pre_action']  = array(
	'startup/session',
	'startup/startup',
	'startup/error',
	'startup/event',
	'startup/maintenance',
	'startup/seo_url',
	'startup/csrf'
);

// Action Events
$_['action_event'] = array(
	'controller/*/before' => array(
		'event/language/before'
	),
	'controller/*/after' => array(
		'event/language/after'
	),
	'view/*/before' => array(
		998 => 'event/language'
	),
	'language/*/after' => array(
		'event/translation'
	)
);

// Mutating actions reviewed against the actual frontend controllers. Keep the
// same list available to the client so legacy links/AJAX submit POST instead.
$_['csrf_post_routes'] = array(
    'account/extension/delete', 'account/extension/deletecomment', 'account/extension/upload',
    'account/store/delete', 'account/store/secret', 'account/showcase/delete',
    'account/payment/add', 'account/payment/makedefault', 'account/payment/delete',
    'account/subscription/update', 'account/subscription/cancelsubscription',
    'account/subscription/renewsubscription', 'account/subscription/restartsubscription',
    'account/subscription/createbackup', 'account/subscription/restorebackup', 'account/subscription/removebackup',
    'account/subscription/createdatabasebackup', 'account/subscription/restoredatabasebackup', 'account/subscription/removedatabasebackup',
    'account/subscription/adddomain', 'account/subscription/deletedomain',
    'account/subscription/pointdomain', 'account/subscription/unpointdomain',
    'account/logout/index', 'account/security/reset', 'checkout/payment/prepare', 'checkout/payment/confirm',
    'cms/blog/addcomment', 'cms/blog/addreply', 'cms/blog/removecomment',
    'marketplace/extension/addcomment', 'marketplace/extension/addreply',
    'marketplace/extension/removecomment', 'marketplace/extension/removereply'
);
// These actions redirect after successful writes; other mutating endpoints
// already have AJAX handlers and keep their JSON responses.
$_['csrf_navigation_routes'] = array(
    'account/extension/delete', 'account/store/delete', 'account/showcase/delete',
    'account/subscription/deletedomain', 'account/subscription/pointdomain',
    'account/subscription/unpointdomain', 'account/logout/index'
);
$_['csrf_read_routes'] = array(
    'account/account/index', 'account/extension/index', 'account/store/index',
    'account/subscription/index', 'account/payment/index'
);
