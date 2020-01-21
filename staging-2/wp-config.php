<?php
/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, and ABSPATH. You can find more information by visiting
 * {@link https://codex.wordpress.org/Editing_wp-config.php Editing wp-config.php}
 * Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */
// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress 
 //Added by WP-Cache Manager
//define ('WPLANG', 'nl_NL');*/
function isSecure() {
  return true;
//    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
//    || $_SERVER['SERVER_PORT'] == 443;
}
 
//$web_site_url = 'www.shaversclub.nl/staging-2';
$web_site_url = 'localhost:8075/staging-2';
$protocol = 'http://';
 
if (isSecure())
{
        define('WP_HOME', $protocol . $web_site_url); // Changed by WP-Staging
        define('WP_SITEURL',$protocol . $web_site_url); // Changed by WP-Staging
}
else
{
        define('WP_HOME',$protocol . $web_site_url);// Changed by WP-Staging
        define('WP_SITEURL',$protocol . $web_site_url); // Changed by WP-Staging
}
 //Added by WP-Cache Manager
define('WP_CACHE', true); //Added by WP-Cache Manager
 //Added by WP-Cache Manager
$dir = '/home/admin/domains/shaversclub.nl';
//$dir = '/var/www';
define( 'WPCACHEHOME', $dir . '/wp-content/plugins/wp-super-cache/' ); //Added by WP-Cache Manager
//define('DB_NAME', 'admin_wp5');
define('DB_NAME', 'shaversclub');
/** MySQL database username */
//define('DB_USER', 'admin_wp5');
define('DB_USER', 'root');
/** MySQL database password */
//define('DB_PASSWORD', 'jg9zWQnbp');
define('DB_PASSWORD', 'root');
/** MySQL hostname */
define('DB_HOST', 'db2');
/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');
/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

define('API_URL', 'http://localhost:8073/api/cart');
/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'VUbJVfBMw6LlwbDiGmkaadqGppFByPBAEpuGewnk0FybLcL2nc0WDfjKr76JnAlb');
define('SECURE_AUTH_KEY',  'xzCzwntyfreBbaG08Diq2a6UGqJPE55sqFVCxjBSMnPf4BtnEBjn6XHRSfcRmeCV');
define('LOGGED_IN_KEY',    'tARvW0jEJ6RJ0UrwmhVFYxFQdAjRgtSPGBa6LvNyjrnrb4SrTma2UgBJp24eGVc4');
define('NONCE_KEY',        'cCHN0Q6XP9hF0gSQJTrE6UPgfIilkheiUvxsUDJ6R8u8i4dprqLdgP60ei74Fsby');
define('AUTH_SALT',        'DjJ7BH4ruk2cq0ilIRFJNZrnUKFAcYgDdUdOcNtsD75Qp8RgxACJN9TjYxQspgS9');
define('SECURE_AUTH_SALT', 'zZ0w04pfacaK4s3saiIcGyJF7xPDW2opAPfcmzgVKCRDIVDqMJqMk29OPn7bZQb4');
define('LOGGED_IN_SALT',   'yLR4GB2MGsZwuan8YqXphSPtBui9zj8elAz3QK8NcqKcvP9cntAvMPLxYrg5FnHq');
define('NONCE_SALT',       'IGR4vagkR6Df6rEHuIQdKOwq7WOMbJYpoaMcHGKAgstWaAEKExvhYazs85UIwg97');
/**
 * Other customizations.
 */
define('FS_METHOD','direct');define('FS_CHMOD_DIR',0777);define('FS_CHMOD_FILE',0666);
define('WP_TEMP_DIR',dirname(__FILE__).'/wp-content/uploads');
/**
 * Turn off automatic updates since these are managed upstream.
 */
define('AUTOMATIC_UPDATER_DISABLED', true);
/**#@-*/
/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
define('WP_DEBUG_LOG', true);
define('SCRIPT_DEBUG', false);
define('SAVEQUERIES', false);
$table_prefix = 'wpstg0_';//  = 'wp_';
//$table_prefix = 'test_wp_';
/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);
if ( ! defined( 'WCS_DEBUG' ) ) {
 	define( 'WCS_DEBUG', true );
}
define('DISABLE_WP_CRON', true);
define( 'WP_CRON_LOCK_TIMEOUT', 540 ); // de cron gaat elke 10 min, mocht om 1 of andere reden de cron eerder worden uitgevoerd, dan hoort deze dat setting dat iig de eerste 9 min tegen te houden
/* That's all, stop editing! Happy blogging. */
/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');
/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
