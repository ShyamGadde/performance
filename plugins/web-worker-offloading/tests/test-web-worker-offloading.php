<?php
/**
 * Test cases for Web Worker Offloading.
 *
 * @package web-worker-offloading
 */

class Test_Web_Worker_Offloading extends WP_UnitTestCase {

	/**
	 * Set up the test case.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->reset_wp_dependencies();
		add_theme_support( 'html5', array( 'script' ) );
	}

	/**
	 * Tear down the test case.
	 */
	public function tear_down(): void {
		parent::tear_down();
		$this->reset_wp_dependencies();
	}

	/**
	 * @covers ::wwo_get_configuration
	 */
	public function test_wwo_get_configuration(): void {
		$wp_content_dir        = WP_CONTENT_DIR;
		$partytown_assets_path = 'web-worker-offloading/build/';
		$config                = wwo_get_configuration();

		$this->assertArrayHasKey( 'lib', $config );
		$this->assertStringStartsWith( '/' . basename( $wp_content_dir ), $config['lib'] );
		$this->assertStringEndsWith( $partytown_assets_path, $config['lib'] );

		// Test `wwo_configuration` filter.
		add_filter(
			'wwo_configuration',
			static function ( $config ) {
				$config['forward'] = array( 'datalayer.push' );
				$config['debug']   = true;
				return $config;
			}
		);

		$config = wwo_get_configuration();

		$this->assertArrayHasKey( 'forward', $config );
		$this->assertArrayHasKey( 'debug', $config );
		$this->assertNotEmpty( $config['forward'] );
		$this->assertIsArray( $config['forward'] );
		$this->assertTrue( $config['debug'] );
		$this->assertContains( 'datalayer.push', $config['forward'] );
	}

	/**
	 * @covers ::wwo_register_default_scripts
	 */
	public function test_wwo_register_default_scripts(): void {
		$this->assertEquals( 10, has_action( 'wp_default_scripts', 'wwo_register_default_scripts' ) );

		// Register scripts.
		wp_scripts();

		$wp_content_dir   = WP_CONTENT_DIR;
		$partytown_config = wwo_get_configuration();
		$partytown_lib    = dirname( $wp_content_dir ) . $partytown_config['lib'];
		$before_data      = wp_scripts()->get_inline_script_data( 'web-worker-offloading', 'before' );
		$after_data       = wp_scripts()->get_inline_script_data( 'web-worker-offloading', 'after' );

		$this->assertTrue( wp_script_is( 'web-worker-offloading', 'registered' ) );
		$this->assertNotEmpty( $before_data );
		$this->assertNotEmpty( $after_data );
		$this->assertStringContainsString(
			'window.partytown',
			$before_data
		);
		$this->assertStringContainsString(
			wp_json_encode( $partytown_config ),
			$before_data
		);
		$this->assertEquals( file_get_contents( $partytown_lib . 'partytown.js' ), $after_data );
		$this->assertTrue( wp_script_is( 'web-worker-offloading', 'registered' ) );
	}

	/**
	 * Data provider for testing `wwo_update_script_type`.
	 *
	 * @return array<string, mixed> Data.
	 */
	public static function data_update_script_types(): array {
		return array(
			'add-script'                                 => array(
				'set_up'   => static function (): void {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), '1.0.0', true );
				},
				'expected' => '<script src="https://example.com/foo.js?ver=1.0.0" id="foo-js"></script>',
			),
			'add-inline-scripts'                         => array(
				'set_up'   => static function (): void {
					wp_register_script( 'foo', false, array(), '1.0.0' );
					wp_add_inline_script( 'foo', 'console.log("Hello, World!");', 'before' );
					wp_add_inline_script( 'foo', 'console.log("Hello, World!");', 'after' );
					wp_enqueue_script( 'foo' );
				},
				'expected' => '<script id="foo-js-before">console.log("Hello, World!");</script><script id="foo-js-after">console.log("Hello, World!");</script>',
			),
			'add-script-for-web-worker-offloading'       => array(
				'set_up'   => static function (): void {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), '1.0.0', true );
					wp_script_add_data( 'foo', 'worker', true );
				},
				'expected' => '{{ wwo_config }}{{ wwo_inline_script }}<script type="text/partytown" src="https://example.com/foo.js?ver=1.0.0" id="foo-js"></script>',
			),
			'add-defer-script-for-web-worker-offloading' => array(
				'set_up'   => static function (): void {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), '1.0.0', array( 'strategy' => 'defer' ) );
					wp_script_add_data( 'foo', 'worker', 1 );
				},
				'expected' => '{{ wwo_config }}{{ wwo_inline_script }}<script type="text/partytown" src="https://example.com/foo.js?ver=1.0.0" id="foo-js" defer data-wp-strategy="defer"></script>',
			),
			'add-async-script-for-web-worker-offloading' => array(
				'set_up'   => static function (): void {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), '1.0.0', array( 'strategy' => 'async' ) );
					wp_script_add_data( 'foo', 'worker', true );
				},
				'expected' => '{{ wwo_config }}{{ wwo_inline_script }}<script type="text/partytown" src="https://example.com/foo.js?ver=1.0.0" id="foo-js" async data-wp-strategy="async"></script>',
			),
			'add-script-for-web-worker-offloading-with-before-data' => array(
				'set_up'   => static function (): void {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), '1.0.0', true );
					wp_add_inline_script( 'foo', 'console.log("Hello, World!");', 'before' );
					wp_script_add_data( 'foo', 'worker', true );
				},
				'expected' => '{{ wwo_config }}{{ wwo_inline_script }}<script id="foo-js-before" type="text/partytown">console.log("Hello, World!");</script><script type="text/partytown" src="https://example.com/foo.js?ver=1.0.0" id="foo-js"></script>',
			),
			'add-script-for-web-worker-offloading-with-after-data' => array(
				'set_up'   => static function (): void {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), '1.0.0', true );
					wp_add_inline_script( 'foo', 'console.log("Hello, World!");', 'after' );
					wp_script_add_data( 'foo', 'worker', true );
				},
				'expected' => '{{ wwo_config }}{{ wwo_inline_script }}<script type="text/partytown" src="https://example.com/foo.js?ver=1.0.0" id="foo-js"></script><script id="foo-js-after" type="text/partytown">console.log("Hello, World!");</script>',
			),
			'add-script-for-web-worker-offloading-with-before-and-after-data' => array(
				'set_up'   => static function (): void {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), '1.0.0', true );
					wp_add_inline_script( 'foo', 'console.log("Hello, World!");', 'before' );
					wp_add_inline_script( 'foo', 'console.log("Hello, World!");', 'after' );
					wp_script_add_data( 'foo', 'worker', true );
				},
				'expected' => '{{ wwo_config }}{{ wwo_inline_script }}<script id="foo-js-before" type="text/partytown">console.log("Hello, World!");</script><script type="text/partytown" src="https://example.com/foo.js?ver=1.0.0" id="foo-js"></script><script id="foo-js-after" type="text/partytown">console.log("Hello, World!");</script>',
			),
			'add-async-script-for-web-worker-offloading-with-before-and-after-data' => array(
				'set_up'   => static function (): void {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), '1.0.0', array( 'strategy' => 'async' ) );
					wp_add_inline_script( 'foo', 'console.log("Hello, World!");', 'before' );
					wp_add_inline_script( 'foo', 'console.log("Hello, World!");', 'after' );
					wp_script_add_data( 'foo', 'worker', true );
				},
				'expected' => '{{ wwo_config }}{{ wwo_inline_script }}<script id="foo-js-before" type="text/partytown">console.log("Hello, World!");</script><script type="text/partytown" src="https://example.com/foo.js?ver=1.0.0" id="foo-js" data-wp-strategy="async"></script><script id="foo-js-after" type="text/partytown">console.log("Hello, World!");</script>',
			),
			'add-defer-script-for-web-worker-offloading-with-before-and-after-data' => array(
				'set_up'   => static function (): void {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), '1.0.0', array( 'strategy' => 'defer' ) );
					wp_add_inline_script( 'foo', 'console.log("Hello, World!");', 'before' );
					wp_add_inline_script( 'foo', 'console.log("Hello, World!");', 'after' );
					wp_script_add_data( 'foo', 'worker', true );
				},
				'expected' => '{{ wwo_config }}{{ wwo_inline_script }}<script id="foo-js-before" type="text/partytown">console.log("Hello, World!");</script><script type="text/partytown" src="https://example.com/foo.js?ver=1.0.0" id="foo-js" data-wp-strategy="defer"></script><script id="foo-js-after" type="text/partytown">console.log("Hello, World!");</script>',
			),
			'add-inline-script-offloaded-to-web-worker'  => array(
				'set_up'   => static function (): void {
					wp_register_script( 'foo', false, array(), '1.0.0' );
					wp_add_inline_script( 'foo', 'console.log("Hello, World!");', 'before' );
					wp_add_inline_script( 'foo', 'console.log("Hello, World!");', 'after' );
					wp_script_add_data( 'foo', 'worker', true );
					wp_enqueue_script( 'foo' );
				},
				'expected' => '{{ wwo_config }}{{ wwo_inline_script }}<script id="foo-js-before" type="text/partytown">console.log("Hello, World!");</script><script id="foo-js-after" type="text/partytown">console.log("Hello, World!");</script>',
			),
		);
	}

	/**
	 * Test `wwo_update_script_type`.
	 *
	 * @covers ::wwo_update_script_type
	 * @covers ::wwo_filter_print_scripts_array
	 * @cogers ::wwo_filter_inline_script_attributes
	 *
	 * @dataProvider data_update_script_types
	 *
	 * @param Closure $set_up   Closure to set up the test.
	 * @param string  $expected Expected output.
	 */
	public function test_update_script_types( Closure $set_up, string $expected ): void {
		$expected = $this->replace_placeholders( $expected );

		$set_up();

		$normalize = static function ( $html ) {
			$html = preg_replace( '/\r|\n/', '', $html );
			return trim( preg_replace( '#(?=<[^/])#', "\n", $html ) );
		};

		// Normalize the output.
		$actual   = $normalize( get_echo( 'wp_print_scripts' ) );
		$expected = $normalize( $expected );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Test head and footer scripts.
	 *
	 * @covers ::wwo_update_script_type
	 * @covers ::wwo_filter_print_scripts_array
	 */
	public function test_head_and_footer_scripts(): void {
		wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), '1.0.0', false );
		wp_script_add_data( 'foo', 'worker', true );

		$this->assertEquals(
			$this->replace_placeholders( '{{ wwo_config }}{{ wwo_inline_script }}<script type="text/partytown" src="https://example.com/foo.js?ver=1.0.0" id="foo-js"></script>' ),
			trim( get_echo( 'wp_print_head_scripts' ) )
		);

		wp_enqueue_script( 'bar', 'https://example.com/bar.js', array(), '1.0.0', true );
		wp_script_add_data( 'bar', 'worker', true );

		$this->assertEquals(
			$this->replace_placeholders( '<script type="text/partytown" src="https://example.com/bar.js?ver=1.0.0" id="bar-js"></script>' ),
			trim( get_echo( 'wp_print_footer_scripts' ) )
		);
	}

	/**
	 * Test only head script.
	 *
	 * @covers ::wwo_update_script_type
	 * @covers ::wwo_filter_print_scripts_array
	 */
	public function test_only_head_script(): void {
		wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), '1.0.0', false );
		wp_script_add_data( 'foo', 'worker', true );

		$this->assertEquals(
			$this->replace_placeholders( '{{ wwo_config }}{{ wwo_inline_script }}<script type="text/partytown" src="https://example.com/foo.js?ver=1.0.0" id="foo-js"></script>' ),
			trim( get_echo( 'wp_print_head_scripts' ) )
		);

		wp_enqueue_script( 'bar', 'https://example.com/bar.js', array(), '1.0.0', true );

		$this->assertEquals(
			$this->replace_placeholders( '<script src="https://example.com/bar.js?ver=1.0.0" id="bar-js"></script>' ),
			trim( get_echo( 'wp_print_footer_scripts' ) )
		);
	}

	/**
	 * Test only footer script.
	 *
	 * @covers ::wwo_update_script_type
	 * @covers ::wwo_filter_print_scripts_array
	 */
	public function test_only_footer_script(): void {
		wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), '1.0.0', false );

		$this->assertEquals(
			$this->replace_placeholders( '<script src="https://example.com/foo.js?ver=1.0.0" id="foo-js"></script>' ),
			trim( get_echo( 'wp_print_head_scripts' ) )
		);

		wp_enqueue_script( 'bar', 'https://example.com/bar.js', array(), '1.0.0', true );
		wp_script_add_data( 'bar', 'worker', true );

		$this->assertEquals(
			$this->replace_placeholders( '{{ wwo_config }}{{ wwo_inline_script }}<script type="text/partytown" src="https://example.com/bar.js?ver=1.0.0" id="bar-js"></script>' ),
			trim( get_echo( 'wp_print_footer_scripts' ) )
		);
	}

	/**
	 * Replace placeholders.
	 *
	 * @param string $template Template.
	 * @return string Template with placeholders replaced.
	 */
	private function replace_placeholders( string $template ): string {
		$wwo_config_data        = wp_scripts()->get_inline_script_data( 'web-worker-offloading', 'before' );
		$wwo_inline_script_data = wp_scripts()->get_inline_script_data( 'web-worker-offloading', 'after' );

		$template = str_replace(
			'{{ wwo_config }}',
			wp_get_inline_script_tag( $wwo_config_data, array( 'id' => 'web-worker-offloading-js-before' ) ),
			$template
		);
		return str_replace(
			'{{ wwo_inline_script }}',
			wp_get_inline_script_tag( $wwo_inline_script_data, array( 'id' => 'web-worker-offloading-js-after' ) ),
			$template
		);
	}

	/**
	 * Reset WP_Scripts and WP_Styles.
	 */
	private function reset_wp_dependencies(): void {
		$GLOBALS['wp_scripts'] = null;
	}
}
