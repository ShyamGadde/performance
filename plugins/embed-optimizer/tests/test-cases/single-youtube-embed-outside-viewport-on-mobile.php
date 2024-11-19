<?php
return array(
	'set_up'   => static function ( Test_Embed_Optimizer_Optimization_Detective $test_case ): void {
		foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
			$elements = array(
				array(
					'xpath'                     => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]/*[1][self::DIV]',
					'isLCP'                     => true,
					'resizedBoundingClientRect' => array_merge( $test_case->get_sample_dom_rect(), array( 'height' => 500 ) ),
				),
			);

			// Embed not visible on mobile.
			if ( 480 === $viewport_width ) {
				$elements[0]['intersectionRatio'] = 0;
				$elements[0]['isLCP']             = false;
			}

			$sample_size = od_get_url_metrics_breakpoint_sample_size();
			for ( $i = 0; $i < $sample_size; $i++ ) {
				OD_URL_Metrics_Post_Type::store_url_metric(
					od_get_url_metrics_slug( od_get_normalized_query_vars() ),
					$test_case->get_sample_url_metric(
						array(
							'viewport_width' => $viewport_width,
							'elements'       => $elements,
						)
					)
				);
			}
		}
	},
	'buffer'   => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio">
					<div class="wp-block-embed__wrapper">
						<iframe title="Matt Mullenweg: State of the Word 2023" width="750" height="422" src="https://www.youtube.com/embed/c7M4mBVgP3Y?feature=oembed" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
					</div>
				</figure>
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<style>
				@media (max-width: 480px) { #embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8 { min-height: 500px; } }
				@media (min-width: 481px) and (max-width: 600px) { #embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8 { min-height: 500px; } }
				@media (min-width: 601px) and (max-width: 782px) { #embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8 { min-height: 500px; } }
				@media (min-width: 783px) { #embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8 { min-height: 500px; } }
				</style>
				<link data-od-added-tag rel="preconnect" href="https://i.ytimg.com" media="(min-width: 481px)">
				<link data-od-added-tag rel="preconnect" href="https://www.youtube.com" media="(min-width: 481px)">
			</head>
			<body>
				<figure data-od-added-id id="embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8" class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio">
					<div class="wp-block-embed__wrapper">
						<iframe title="Matt Mullenweg: State of the Word 2023" width="750" height="422" src="https://www.youtube.com/embed/c7M4mBVgP3Y?feature=oembed" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
					</div>
				</figure>
			</body>
		</html>
	',
);
