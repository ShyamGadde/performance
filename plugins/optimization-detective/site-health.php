<?php
/**
 * Site Health checks.
 *
 * @package optimization-detective
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Adds the Optimization Detective REST API check to site health tests.
 *
 * @since n.e.x.t
 *
 * @param array{direct: array<string, array{label: string, test: string}>} $tests Site Health Tests.
 * @return array{direct: array<string, array{label: string, test: string}>} Amended tests.
 */
function od_optimization_detective_add_rest_api_test( array $tests ): array {
	$tests['direct']['optimization_detective_rest_api'] = array(
		'label' => __( 'Optimization Detective REST API Endpoint Availability', 'optimization-detective' ),
		'test'  => 'od_optimization_detective_rest_api_test',
	);

	return $tests;
}

/**
 * Tests availability of the Optimization Detective REST API endpoint.
 *
 * @since n.e.x.t
 *
 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string} Result.
 */
function od_optimization_detective_rest_api_test(): array {
	$common_description_html = '<p>' . wp_kses(
		sprintf(
			/* translators: %s is the REST API endpoint */
			__( 'To collect URL Metrics from visitors the REST API must be accessible to unauthenticated users. Specifically, visitors must be able to perform a <code>POST</code> request to the <code>%s</code> endpoint.', 'optimization-detective' ),
			'/' . OD_REST_API_NAMESPACE . OD_URL_METRICS_ROUTE
		),
		array( 'code' => array() )
	) . '</p>';

	$result = array(
		'label'       => __( 'Optimization Detective\'s REST API endpoint is accessible', 'optimization-detective' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Optimization Detective', 'optimization-detective' ),
			'color' => 'blue',
		),
		'description' => $common_description_html . '<p><strong>' . esc_html__( 'This appears to be working properly.', 'optimization-detective' ) . '</strong></p>',
		'actions'     => '',
		'test'        => 'optimization_detective_rest_api',
	);

	$rest_url = get_rest_url( null, OD_REST_API_NAMESPACE . OD_URL_METRICS_ROUTE );
	$response = wp_remote_post(
		$rest_url,
		array(
			'headers'   => array( 'Content-Type' => 'application/json' ),
			'sslverify' => false,
		)
	);

	$error_label            = __( 'Optimization Detective\'s REST API endpoint is inaccessible', 'optimization-detective' );
	$error_description_html = '<p>' . esc_html__( 'You may have a plugin active or server configuration which restricts access to logged-in users. Unauthenticated access must be restored in order for Optimization Detective to work.', 'optimization-detective' ) . '</p>';

	if ( is_wp_error( $response ) ) {
		$result['status']      = 'recommended';
		$result['label']       = $error_label;
		$result['description'] = $common_description_html . $error_description_html . '<p>' . wp_kses(
			sprintf(
				/* translators: 1: the error code, 2: the error message */
				__( 'The REST API responded with the error code <code>%1$s</code> and this error message: %2$s.', 'optimization-detective' ),
				esc_html( (string) $response->get_error_code() ),
				esc_html( rtrim( $response->get_error_message(), '.' ) )
			),
			array( 'code' => array() )
		) . '</p>';

		$info = array(
			'error_message' => $result['description'],
			'error_code'    => $response->get_error_code(),
			'available'     => false,
		);
	} else {
		$status_code = wp_remote_retrieve_response_code( $response );
		$data        = json_decode( wp_remote_retrieve_body( $response ), true );

		if (
			400 === $status_code &&
			isset( $data['data']['params'] ) &&
			is_array( $data['data']['params'] ) &&
			count( $data['data']['params'] ) > 0
		) {
			// The REST API endpoint is available.
			$info = array(
				'status_code'   => $status_code,
				'available'     => true,
				'error_message' => '',
			);
		} else {
			$result['status']      = 'recommended';
			$result['label']       = __( 'The Optimization Detective REST API endpoint is inaccessible to logged-out users', 'optimization-detective' );
			$result['description'] = $common_description_html . $error_description_html . '<p>' . wp_kses(
				sprintf(
					/* translators: %d is the HTTP status code, %s is the status header description */
					__( 'The REST API returned with an HTTP status of <code>%1$d %2$s</code>.', 'optimization-detective' ),
					$status_code,
					get_status_header_desc( (int) $status_code )
				),
				array( 'code' => array() )
			) . '</p>';

			$info = array(
				'status_code'   => $status_code,
				'available'     => false,
				'error_message' => $result['description'],
			);
		}
	}

	update_option( 'od_rest_api_info', $info );
	return $result;
}

/**
 * Renders an admin notice if the REST API health check fails.
 *
 * @since n.e.x.t
 *
 * @param array<string> $additional_classes Additional classes to add to the notice.
 */
function od_render_rest_api_health_check_notice( array $additional_classes = array() ): void {
	$rest_api_info = get_option( 'od_rest_api_info', array() );
	if (
		isset( $rest_api_info['available'] ) &&
		false === $rest_api_info['available'] &&
		isset( $rest_api_info['error_message'] ) &&
		'' !== $rest_api_info['error_message']
	) {
		wp_admin_notice(
			wp_kses( $rest_api_info['error_message'], array_fill_keys( array( 'p', 'code' ), array() ) ),
			array(
				'type'               => 'warning',
				'additional_classes' => $additional_classes,
			)
		);
	}
}

/**
 * Displays an admin notice on the plugin row if the REST API health check fails.
 *
 * @since n.e.x.t
 *
 * @param string $plugin_file Plugin file.
 */
function od_rest_api_health_check_admin_notice( string $plugin_file ): void {
	if ( 'optimization-detective/load.php' !== $plugin_file ) {
		return;
	}
	od_render_rest_api_health_check_notice( array( 'inline', 'notice-alt' ) );
}

/**
 * Runs the REST API health check if it hasn't been run yet.
 *
 * This happens at the `admin_init` action to avoid running the check on the frontend. This will run on the first admin
 * page load after the plugin has been activated. This allows for this function to add an action at `admin_notices` so
 * that an error message can be displayed after performing that plugin activation request. Note that a plugin activation
 * hook cannot be used for this purpose due to not being compatible with multisite. While the site health notice is
 * shown at the `admin_notices` action once, the notice will only be displayed inline with the plugin row thereafter
 * via {@see od_rest_api_health_check_admin_notice()}.
 *
 * @since n.e.x.t
 */
function od_maybe_run_rest_api_health_check(): void {
	// If the option already exists, then the REST API health check has already been performed.
	if ( false !== get_option( 'od_rest_api_info' ) ) {
		return;
	}

	// This will populate the od_rest_api_info option so that the function won't execute on the next page load.
	od_optimization_detective_rest_api_test();

	// Show any notice in the main admin notices area for the first page load (e.g. after plugin activation).
	add_action(
		'admin_notices',
		static function (): void {
			od_render_rest_api_health_check_notice();
		}
	);
}
