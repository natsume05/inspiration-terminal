<?php
define( 'WP_CACHE', true );

 // Added by SpeedyCache

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'xjladhguxv_wo74cdf' );

/** Database username */
define( 'DB_USER', 'xjladhguxv_wo74cdf' );

/** Database password */
define( 'DB_PASSWORD', 'p]7FJ(Lp23!YS..f' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'bj3kv8ez2xlccolsyo2bbzuz9f4ngdrvwcd8btq6l7fan1jxqksvrwoeyhagnp82' );
define( 'SECURE_AUTH_KEY',  'zgisps26kdxpvgiszxe9okvonlfkzpr8nnrkb6gwsnkr8vgjxods3xniu9g3m8vs' );
define( 'LOGGED_IN_KEY',    '8akugu7dxt6r28uqipc0cq1yksdijljjd5usaxmihpkea3iqzlpxyukrouyt4f2c' );
define( 'NONCE_KEY',        'ernaz29keh0utpa9hwqaycyxluveuqp8vgyrysqypi2ndiaixuji485ouhbmu0ym' );
define( 'AUTH_SALT',        'z09oyc8i66dzucrlbcfnpcweq98t2fgx5q6riql2j9sogirjw1xyyz2mmotokk7a' );
define( 'SECURE_AUTH_SALT', 'i75ubcazxf5bwzq15okwomggrgd7nr4sobom603rkpqp2vrbvzfvuehz8lfyfbet' );
define( 'LOGGED_IN_SALT',   'hlj9b3qaaekmmfjkn5sdwaqqacwsusxzz4c3kvvvzkege7bhcx8fflvlzydbc9hb' );
define( 'NONCE_SALT',       'oj9odcc7wngz3uuk0yy1dgsfgidredep45wvjiynkbs1mi0kuxh9tlq6ni8t8bsx' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'soft_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
