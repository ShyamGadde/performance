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
 * @access private
 * @todo Add coverage.
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
 * @access private
 *
 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string} Result.
 */
function od_optimization_detective_rest_api_test(): array {
	$response        = od_get_rest_api_health_check_response( false );
	$result          = od_construct_site_health_result( $response );
	$is_inaccessible = 'good' !== $result['status'];
	update_option( 'od_rest_api_inaccessible', $is_inaccessible ? '1' : '0' );
	return $result;
}

/**
 * Checks whether the Optimization Detective REST API endpoint is inaccessible.
 *
 * This merely checks the database option what was previously computed in the Site Health test as done in {@see od_optimization_detective_rest_api_test()}.
 * This is to avoid checking for REST API accessibility during a frontend request. Note that when the plugin is first
 * installed, the 'od_rest_api_inaccessible' option will not be in the database, as the check has not been performed
 * yet. Once Site Health's weekly check happens or when a user accesses the admin so that the admin_init action fires,
 * then at this point the check will be performed at {@see od_maybe_run_rest_api_health_check()}. In practice, this will
 * happen immediately after the user activates a plugin since the user is redirected back to the plugin list table in
 * the admin. The reason for storing the negative inaccessible state as opposed to the positive accessible state is that
 * when an option does not exist then `get_option()` returns `false` which is the same falsy value as the stored `'0'`.
 *
 * @since n.e.x.t
 * @access private
 *
 * @return bool Whether inaccessible.
 */
function od_is_rest_api_inaccessible(): bool {
	return 1 === (int) get_option( 'od_rest_api_inaccessible', '0' );
}

/**
 * Tests availability of the Optimization Detective REST API endpoint.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param array<string, mixed>|WP_Error $response REST API response.
 *
 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string} Result.
 */
function od_construct_site_health_result( $response ): array {
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
	} else {
		$status_code = wp_remote_retrieve_response_code( $response );
		$data        = json_decode( wp_remote_retrieve_body( $response ), true );

		$is_expected = (
			400 === $status_code &&
			isset( $data['data']['params'] ) &&
			is_array( $data['data']['params'] ) &&
			count( $data['data']['params'] ) > 0
		);
		if ( ! $is_expected ) {
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
		}
	}
	return $result;
}

/**
 * Gets the response to an Optimization Detective REST API store request to confirm it is accessible to unauthenticated requests.
 *
 * @since n.e.x.t
 *
 * @param bool $use_cached Whether to use a previous response cached in a transient.
 * @return array{ response: array{ code: int, message: string }, body: string }|WP_Error Response.
 */
function od_get_rest_api_health_check_response( bool $use_cached ) {
	$transient_key = 'od_rest_api_health_check_response';
	$response      = $use_cached ? get_transient( $transient_key ) : null;
	if (
		(
			is_array( $response )
			&&
			isset( $response['response']['status'], $response['response']['message'], $response['body'] )
			&&
			is_int( $response['response']['status'] )
			&&
			is_string( $response['response']['message'] )
			&&
			is_string( $response['body'] )
		)
		||
		is_wp_error( $response )
	) {
		return $response;
	}
	$rest_url = get_rest_url( null, OD_REST_API_NAMESPACE . OD_URL_METRICS_ROUTE );
	$response = wp_remote_post(
		$rest_url,
		array(
			'headers'   => array( 'Content-Type' => 'application/json' ),
			'sslverify' => false,
		)
	);

	// This transient will be used when showing the admin notice with the plugin on the plugins screen.
	// The 1-day expiration allows for fresher content than the weekly check initiated by Site Health.
	set_transient( $transient_key, $response, DAY_IN_SECONDS );
	return $response;
}

/**
 * Renders an admin notice if the REST API health check fails.
 *
 * @since n.e.x.t
 * @access private
 * @todo Add coverage.
 *
 * @param array<string> $additional_classes Additional classes to add to the notice.
 */
function od_render_rest_api_health_check_notice( array $additional_classes = array() ): void {
	if ( ! od_is_rest_api_inaccessible() ) {
		return;
	}

	$response = od_get_rest_api_health_check_response( true );
	$result   = od_construct_site_health_result( $response );
	if ( 'good' === $result['status'] ) {
		// There's a slight chance the DB option is stale in the initial if statement.
		return;
	}

	wp_admin_notice(
		sprintf(
			'<details><summary>%s %s</summary>%s %s</details>',
			esc_html__( 'Warning:', 'optimization-detective' ),
			esc_html( $result['label'] ),
			wp_kses( $result['description'], array_fill_keys( array( 'p', 'code' ), array() ) ),
			'<p>' . esc_html__( 'Please visit Site Health to re-check this once you believe you have resolved the issue.', 'optimization-detective' ) . '</p>'
		),
		array(
			'type'               => 'warning',
			'additional_classes' => $additional_classes,
			'paragraph_wrap'     => false,
		)
	);
}

/**
 * Displays an admin notice on the plugin row if the REST API health check fails.
 *
 * @since n.e.x.t
 * @access private
 * @todo Add coverage.
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
 * @access private
 * @todo Add coverage.
 */
function od_maybe_run_rest_api_health_check(): void {
	// If the option already exists, then the REST API health check has already been performed.
	if ( false !== get_option( 'od_rest_api_inaccessible' ) ) {
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
