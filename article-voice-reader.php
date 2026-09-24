<?php
/**
 * Plugin Name: Article Voice Reader
 * Description: Adds a floating "Listen to this article" widget to your posts using the visitor's browser built-in text-to-speech. No API keys, no external services, no ongoing cost.
 * Version: 1.0.0
 * Author: Pathmaka Galappaththi
 * License: GPL v2 or later
 * Text Domain: article-voice-reader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

class AVR_Voice_Reader {

	const OPTION_NAME    = 'avr_settings';
	const SETTINGS_SLUG  = 'avr-voice-reader';
	const PLUGIN_VERSION = '1.0.0';

	private static $valid_positions = array(
		'top-left',
		'top-right',
		'middle-left',
		'middle-right',
		'bottom-left',
		'bottom-right',
	);

	private $settings_hook = '';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_footer', array( $this, 'maybe_render_widget' ) );
		add_shortcode( 'voice_reader', array( $this, 'shortcode_render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ) );
	}

	/* ------------------------------------------------------------------ */
	/*  Settings                                                           */
	/* ------------------------------------------------------------------ */

	private function get_defaults() {
		return array(
			'enable_posts'    => 1,
			'enable_pages'    => 0,
			'auto_insert'     => 1,
			'widget_position' => 'middle-right',
			'widget_color'    => '#007f96',
			'default_rate'    => '1',
			'widget_title'    => __( 'Listen to this article', 'article-voice-reader' ),
		);
	}

	private function get_settings() {
		$saved = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( $saved, $this->get_defaults() );
	}

	public function register_settings_page() {
		$this->settings_hook = add_options_page(
			__( 'Article Voice Reader Settings', 'article-voice-reader' ),
			__( 'Article Voice Reader', 'article-voice-reader' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this, 'render_settings_page' )
		);

		add_action( 'load-' . $this->settings_hook, array( $this, 'maybe_handle_reset' ) );
	}

	/**
	 * Handles the "Reset to Defaults" form. Hooked to load-{page}, which runs
	 * before any output, so a redirect afterward is safe (avoids a resubmission
	 * prompt if the admin refreshes the page).
	 */
	public function maybe_handle_reset() {
		if ( empty( $_POST['avr_reset_defaults'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'avr_reset_defaults_action', 'avr_reset_nonce' );

		delete_option( self::OPTION_NAME );

		wp_safe_redirect( add_query_arg(
			array(
				'page'      => self::SETTINGS_SLUG,
				'avr-reset' => '1',
			),
			admin_url( 'options-general.php' )
		) );
		exit;
	}

	public function register_settings() {
		register_setting( self::SETTINGS_SLUG, self::OPTION_NAME, array( $this, 'sanitize_settings' ) );

		add_settings_section( 'avr_main', '', '__return_false', self::SETTINGS_SLUG );

		add_settings_field( 'enable_on', __( 'Enable on', 'article-voice-reader' ), array( $this, 'field_enable_on' ), self::SETTINGS_SLUG, 'avr_main' );
		add_settings_field( 'auto_insert', __( 'Widget', 'article-voice-reader' ), array( $this, 'field_auto_insert' ), self::SETTINGS_SLUG, 'avr_main' );
		add_settings_field( 'widget_position', __( 'Position', 'article-voice-reader' ), array( $this, 'field_position' ), self::SETTINGS_SLUG, 'avr_main' );
		add_settings_field( 'widget_color', __( 'Icon color', 'article-voice-reader' ), array( $this, 'field_color' ), self::SETTINGS_SLUG, 'avr_main' );
		add_settings_field( 'default_rate', __( 'Default speed', 'article-voice-reader' ), array( $this, 'field_rate' ), self::SETTINGS_SLUG, 'avr_main' );
		add_settings_field( 'widget_title', __( 'Widget title', 'article-voice-reader' ), array( $this, 'field_title' ), self::SETTINGS_SLUG, 'avr_main' );
	}

	public function sanitize_settings( $input ) {
		$defaults = $this->get_defaults();
		$output   = array();

		$output['enable_posts'] = ! empty( $input['enable_posts'] ) ? 1 : 0;
		$output['enable_pages'] = ! empty( $input['enable_pages'] ) ? 1 : 0;
		$output['auto_insert']  = ! empty( $input['auto_insert'] ) ? 1 : 0;

		$output['widget_position'] = ( isset( $input['widget_position'] ) && in_array( $input['widget_position'], self::$valid_positions, true ) )
			? $input['widget_position']
			: $defaults['widget_position'];

		$sanitized_color        = isset( $input['widget_color'] ) ? sanitize_hex_color( $input['widget_color'] ) : '';
		$output['widget_color'] = $sanitized_color ? $sanitized_color : $defaults['widget_color'];

		$rate                   = isset( $input['default_rate'] ) ? floatval( $input['default_rate'] ) : 1;
		$output['default_rate'] = (string) max( 0.5, min( 2, $rate ) );

		$output['widget_title'] = isset( $input['widget_title'] ) && '' !== trim( $input['widget_title'] )
			? sanitize_text_field( $input['widget_title'] )
			: $defaults['widget_title'];

		return $output;
	}

	public function field_enable_on() {
		$s = $this->get_settings();
		echo '<label><input type="checkbox" name="' . esc_attr( self::OPTION_NAME ) . '[enable_posts]" value="1" ' . checked( 1, $s['enable_posts'], false ) . '> ' . esc_html__( 'Posts', 'article-voice-reader' ) . '</label><br>';
		echo '<label><input type="checkbox" name="' . esc_attr( self::OPTION_NAME ) . '[enable_pages]" value="1" ' . checked( 1, $s['enable_pages'], false ) . '> ' . esc_html__( 'Pages', 'article-voice-reader' ) . '</label>';
	}

	public function field_auto_insert() {
		$s = $this->get_settings();
		echo '<label><input type="checkbox" name="' . esc_attr( self::OPTION_NAME ) . '[auto_insert]" value="1" ' . checked( 1, $s['auto_insert'], false ) . '> ' . esc_html__( 'Automatically show the floating widget', 'article-voice-reader' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'If disabled, place it manually with the [voice_reader] shortcode. It always floats at the position chosen below, regardless of where the shortcode is placed.', 'article-voice-reader' ) . '</p>';
	}

	public function field_position() {
		$s = $this->get_settings();
		$options = array(
			'top-left'      => __( 'Top left', 'article-voice-reader' ),
			'top-right'     => __( 'Top right', 'article-voice-reader' ),
			'middle-left'   => __( 'Middle left', 'article-voice-reader' ),
			'middle-right'  => __( 'Middle right', 'article-voice-reader' ),
			'bottom-left'   => __( 'Bottom left', 'article-voice-reader' ),
			'bottom-right'  => __( 'Bottom right', 'article-voice-reader' ),
		);
		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[widget_position]">';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $value, $s['widget_position'], false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public function field_color() {
		$s = $this->get_settings();
		echo '<input type="text" class="avr-color-field" name="' . esc_attr( self::OPTION_NAME ) . '[widget_color]" value="' . esc_attr( $s['widget_color'] ) . '" data-default-color="#007f96" />';
	}

	public function field_rate() {
		$s = $this->get_settings();
		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[default_rate]">';
		foreach ( array(
			'0.75' => '0.75x',
			'1'    => '1x (' . __( 'normal', 'article-voice-reader' ) . ')',
			'1.25' => '1.25x',
			'1.5'  => '1.5x',
			'2'    => '2x',
		) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $value, $s['default_rate'], false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public function field_title() {
		$s = $this->get_settings();
		echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_NAME ) . '[widget_title]" value="' . esc_attr( $s['widget_title'] ) . '">';
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'article-voice-reader' ) );
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Article Voice Reader Settings', 'article-voice-reader' ) . '</h1>';

		// This flag is purely cosmetic — it only decides whether a "reset done" notice is
		// shown. The reset itself already happened, nonce-verified, in maybe_handle_reset()
		// before this redirect; nothing changes based on the flag itself.
		if ( isset( $_GET['avr-reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Article Voice Reader settings have been reset to defaults.', 'article-voice-reader' ) . '</p></div>';
		}

		echo '<p>' . esc_html__( 'Uses the visitor\'s own browser for text-to-speech — no API keys, no external services, no ongoing cost. Voice quality and the number of available voices depend entirely on the visitor\'s browser and device; if only one voice is available, the voice dropdown is hidden automatically.', 'article-voice-reader' ) . '</p>';
		echo '<form method="post" action="options.php">';
		settings_fields( self::SETTINGS_SLUG );
		do_settings_sections( self::SETTINGS_SLUG );
		submit_button();
		echo '</form>';

		echo '<hr style="margin:24px 0;">';
		echo '<h2>' . esc_html__( 'Reset', 'article-voice-reader' ) . '</h2>';
		echo '<p>' . esc_html__( 'Restores every setting above to its original default. This cannot be undone.', 'article-voice-reader' ) . '</p>';
		echo '<form method="post" onsubmit="return confirm(\'' . esc_js( __( 'Reset all Article Voice Reader settings to their defaults? This cannot be undone.', 'article-voice-reader' ) ) . '\');">';
		wp_nonce_field( 'avr_reset_defaults_action', 'avr_reset_nonce' );
		echo '<input type="hidden" name="avr_reset_defaults" value="1">';
		submit_button( __( 'Reset to Defaults', 'article-voice-reader' ), 'secondary', 'avr_reset_submit', false );
		echo '</form>';

		echo '</div>';
	}

	public function enqueue_admin_assets( $hook ) {
		if ( $hook !== $this->settings_hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script( 'wp-color-picker', "jQuery(function ($) { $('.avr-color-field').wpColorPicker(); });" );
	}

	/* ------------------------------------------------------------------ */
	/*  Front-end widget                                                   */
	/* ------------------------------------------------------------------ */

	/**
	 * Always re-extracts plain text directly from the raw post content rather
	 * than reusing filtered output, so the shortcode can never trigger
	 * `the_content` recursively.
	 */
	private function get_plain_text_for_speech( $post_id ) {
		$raw = get_post_field( 'post_content', $post_id );
		$raw = strip_shortcodes( $raw );
		$raw = wp_strip_all_tags( $raw );
		$raw = html_entity_decode( $raw, ENT_QUOTES, 'UTF-8' );
		$raw = preg_replace( '/\s+/', ' ', $raw );
		return trim( $raw );
	}

	private function build_widget_html( $post_id ) {
		static $instance = 0;
		$instance++;

		$settings = $this->get_settings();
		$text     = $this->get_plain_text_for_speech( $post_id );

		if ( '' === $text ) {
			return '';
		}

		$id       = 'avr-widget-' . $instance;
		$position = in_array( $settings['widget_position'], self::$valid_positions, true ) ? $settings['widget_position'] : 'middle-right';
		$pos_class = 'avr-pos-' . $position;

		$html  = '<div class="avr-widget ' . esc_attr( $pos_class ) . '" id="' . esc_attr( $id ) . '" style="--avr-color: ' . esc_attr( $settings['widget_color'] ) . ';" data-text="' . esc_attr( wp_json_encode( $text ) ) . '">';

		$html .= '<button type="button" class="avr-toggle-btn" aria-label="' . esc_attr__( 'Open voice reader', 'article-voice-reader' ) . '" title="' . esc_attr( $settings['widget_title'] ) . '">'
			. '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="45" height="45" aria-hidden="true">'
			. '<path d="M8.5 4.5c-2.6 0-4.7 2.2-4.7 5 0 1.9 1 3.5 2.5 4.4v2.1c0 .7.5 1.2 1.2 1.2h1.4v-1.8c.3 0 .6.1.9.1 2.9 0 5.2-2.5 5.2-5.6s-2.9-5.3-6.5-5.3z"/>'
			. '<circle cx="10.3" cy="8.6" r="0.6" fill="currentColor" stroke="none"/>'
			. '<path d="M16.5 9c1 .8 1 3 0 3.8"/>'
			. '<path d="M18.7 7.3c1.9 1.7 1.9 6 0 7.7"/>'
			. '</svg>'
			. '</button>';

		$html .= '<div class="avr-panel">';
		$html .= '<button type="button" class="avr-close-btn" aria-label="' . esc_attr__( 'Close', 'article-voice-reader' ) . '">&times;</button>';
		$html .= '<div class="avr-widget-title">' . esc_html( $settings['widget_title'] ) . '</div>';
		$html .= '<button type="button" class="avr-play-btn" aria-label="' . esc_attr__( 'Play', 'article-voice-reader' ) . '">&#9654;</button>';

		$html .= '<div class="avr-speed-control"><span class="avr-speed-label-text">' . esc_html__( 'Speed', 'article-voice-reader' ) . '</span><input type="range" class="avr-speed-slider" min="0.5" max="2" step="0.25" value="' . esc_attr( $settings['default_rate'] ) . '"><span class="avr-speed-value">' . esc_html( rtrim( rtrim( $settings['default_rate'], '0' ), '.' ) ) . 'x</span></div>';

		$html .= '<label class="avr-voice-label"><span>' . esc_html__( 'Voice', 'article-voice-reader' ) . '</span><select class="avr-voice-select"><option value="">' . esc_html__( 'Default', 'article-voice-reader' ) . '</option></select></label>';

		$html .= '<span class="avr-status" aria-live="polite"></span>';
		$html .= '</div>'; // .avr-panel

		$html .= '</div>'; // .avr-widget

		return $html;
	}

	public function maybe_render_widget() {
		if ( ! is_singular() ) {
			return;
		}

		$settings = $this->get_settings();
		if ( empty( $settings['auto_insert'] ) ) {
			return;
		}

		$post_type = get_post_type();
		if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
			return;
		}
		if ( 'post' === $post_type && empty( $settings['enable_posts'] ) ) {
			return;
		}
		if ( 'page' === $post_type && empty( $settings['enable_pages'] ) ) {
			return;
		}

		echo $this->build_widget_html( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built entirely from escaped pieces above.
	}

	public function shortcode_render( $atts ) {
		if ( ! is_singular() ) {
			return '';
		}
		return $this->build_widget_html( get_the_ID() );
	}

	public function enqueue_front_assets() {
		if ( ! is_singular() ) {
			return;
		}

		$css_path = plugin_dir_path( __FILE__ ) . 'assets/css/avr-player.css';
		$js_path  = plugin_dir_path( __FILE__ ) . 'assets/js/avr-player.js';

		wp_enqueue_style(
			'avr-player-style',
			plugins_url( 'assets/css/avr-player.css', __FILE__ ),
			array(),
			file_exists( $css_path ) ? filemtime( $css_path ) : self::PLUGIN_VERSION
		);

		wp_enqueue_script(
			'avr-player',
			plugins_url( 'assets/js/avr-player.js', __FILE__ ),
			array(),
			file_exists( $js_path ) ? filemtime( $js_path ) : self::PLUGIN_VERSION,
			true
		);

		wp_localize_script( 'avr-player', 'avrPlayerData', array(
			'notSupported' => __( 'Voice reading is not supported in this browser.', 'article-voice-reader' ),
		) );
	}
}

new AVR_Voice_Reader();
