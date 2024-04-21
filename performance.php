<?php
/**
 * Plugin Name: Performance Lab Monorepo (not a real plugin)
 * Plugin URI: https://github.com/WordPress/performance
 * Description: The Performance Monorepo is not a plugin rather a collection of performance features as plugins. Download <a href="https://wordpress.org/plugins/performance-lab/" to install performance features instead.
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * Text Domain: performance
 *
 * @package performance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Notify the admin that the Performance Monorepo is not a plugin,
 * if they tried to install it as one.
 */
function perflab_monorepo_is_not_a_plugin() {
	echo '<div class="notice notice-error"><p>';
	printf(
		wp_kses(
			/* translators: Link to the Performance Lab plugin on WordPress.org */
			__( 'The Performance Monorepo is not a plugin, and should not be installed as one. Download the <a href="%s">Performance Lab</a> plugin to install performance features instead.', 'performance' ),
			array(
				'a' => array( 'href' => array() ),
			)
		),
		esc_url( 'https://wordpress.org/plugins/performance-lab/' )
	);
	echo "</p></div>\n";
}

add_action( 'admin_notices', 'perflab_monorepo_is_not_a_plugin' );
