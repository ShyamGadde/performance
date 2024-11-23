<?php
/**
 * Helper functions for Image Prioritizer.
 *
 * @package image-prioritizer
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Initializes Image Prioritizer when Optimization Detective has loaded.
 *
 * @since 0.2.0
 *
 * @param string $optimization_detective_version Current version of the optimization detective plugin.
 */
function image_prioritizer_init( string $optimization_detective_version ): void {
	$required_od_version = '0.7.0';
	if ( ! version_compare( (string) strtok( $optimization_detective_version, '-' ), $required_od_version, '>=' ) ) {
		add_action(
			'admin_notices',
			static function (): void {
				global $pagenow;
				if ( ! in_array( $pagenow, array( 'index.php', 'plugins.php' ), true ) ) {
					return;
				}
				wp_admin_notice(
					esc_html__( 'The Image Prioritizer plugin requires a newer version of the Optimization Detective plugin. Please update your plugins.', 'image-prioritizer' ),
					array( 'type' => 'warning' )
				);
			}
		);
		return;
	}

	// Classes are required here because only here do we know the expected version of Optimization Detective is active.
	require_once __DIR__ . '/class-image-prioritizer-tag-visitor.php';
	require_once __DIR__ . '/class-image-prioritizer-img-tag-visitor.php';
	require_once __DIR__ . '/class-image-prioritizer-background-image-styled-tag-visitor.php';
	require_once __DIR__ . '/class-image-prioritizer-video-tag-visitor.php';

	add_action( 'wp_head', 'image_prioritizer_render_generator_meta_tag' );
	add_action( 'od_register_tag_visitors', 'image_prioritizer_register_tag_visitors' );
}

/**
 * Displays the HTML generator meta tag for the Image Prioritizer plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 0.1.0
 */
function image_prioritizer_render_generator_meta_tag(): void {
	// Use the plugin slug as it is immutable.
	echo '<meta name="generator" content="image-prioritizer ' . esc_attr( IMAGE_PRIORITIZER_VERSION ) . '">' . "\n";
}

/**
 * Registers tag visitors.
 *
 * @since 0.1.0
 *
 * @param OD_Tag_Visitor_Registry $registry Tag visitor registry.
 */
function image_prioritizer_register_tag_visitors( OD_Tag_Visitor_Registry $registry ): void {
	// Note: The class is invocable (it has an __invoke() method).
	$img_visitor = new Image_Prioritizer_Img_Tag_Visitor();
	$registry->register( 'image-prioritizer/img', $img_visitor );

	$bg_image_visitor = new Image_Prioritizer_Background_Image_Styled_Tag_Visitor();
	$registry->register( 'image-prioritizer/background-image', $bg_image_visitor );

	$video_visitor = new Image_Prioritizer_Video_Tag_Visitor();
	$registry->register( 'image-prioritizer/video', $video_visitor );
}

/**
 * Gets the script to lazy-load videos.
 *
 * Load a video and its poster image when it approaches the viewport using an IntersectionObserver.
 *
 * Handles 'autoplay' and 'preload' attributes accordingly.
 *
 * @since 0.2.0
 */
function image_prioritizer_get_lazy_load_script(): string {
	$script = file_get_contents( __DIR__ . sprintf( '/lazy-load%s.js', wp_scripts_get_suffix() ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- It's a local filesystem path not a remote request.

	if ( false === $script ) {
		return '';
	}

	return $script;
}

/**
 * Filters the list of Optimization Detective extension module URLs to include the extension for Image Prioritizer.
 *
 * @since n.e.x.t
 *
 * @param string[]|mixed $extension_module_urls Extension module URLs.
 * @return string[] Extension module URLs.
 */
function image_prioritizer_filter_extension_module_urls( $extension_module_urls ): array {
	if ( ! is_array( $extension_module_urls ) ) {
		$extension_module_urls = array();
	}
	$extension_module_urls[] = add_query_arg( 'ver', IMAGE_PRIORITIZER_VERSION, plugin_dir_url( __FILE__ ) . sprintf( 'detect%s.js', wp_scripts_get_suffix() ) );
	return $extension_module_urls;
}

/**
 * Filters additional properties for the element item schema for Optimization Detective.
 *
 * @since n.e.x.t
 *
 * @param array<string, array{type: string}> $additional_properties Additional properties.
 * @return array<string, array{type: string}> Additional properties.
 */
function image_prioritizer_add_element_item_schema_properties( array $additional_properties ): array {
	// TODO: Validation of the URL.
	$additional_properties['lcpElementExternalBackgroundImage'] = array(
		'type'       => 'object',
		'properties' => array(
			'url'   => array(
				'type'      => 'string',
				'format'    => 'uri',
				'required'  => true,
				'maxLength' => 500, // Image URLs can be quite long.
			),
			'tag'   => array(
				'type'      => 'string',
				'required'  => true,
				'minLength' => 1,
				'pattern'   => '^[a-zA-Z0-9\-]+$', // Technically emoji can be allowed in a custom element's tag name, but this is not supported here.
			),
			'id'    => array(
				'type'      => array( 'string', 'null' ),
				'maxLength' => 100, // A reasonable upper-bound length for a long ID. The client will must truncate anything longer.
				'required'  => true,
			),
			'class' => array(
				'type'      => array( 'string', 'null' ),
				'maxLength' => 500, // There can be a ton of class names on an element. The client will must truncate anything longer.
				'required'  => true,
			),
		),
	);
	return $additional_properties;
}
