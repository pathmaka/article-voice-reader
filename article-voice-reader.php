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
	const PLUGIN_SLUG    = 'article-voice-reader';
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

		// "View details" popup on the Plugins screen.
		add_filter( 'plugin_row_meta', array( $this, 'add_view_details_link' ), 10, 2 );
		add_filter( 'plugins_api', array( $this, 'provide_plugin_information' ), 10, 3 );
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
			__( 'Voice Reader Settings', 'article-voice-reader' ),
			__( 'Voice Reader', 'article-voice-reader' ),
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

		echo '<div class="wrap"><h1>' . esc_html__( 'Voice Reader Settings', 'article-voice-reader' ) . '</h1>';

		if ( isset( $_GET['avr-reset'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Voice Reader settings have been reset to defaults.', 'article-voice-reader' ) . '</p></div>';
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
		echo '<form method="post" onsubmit="return confirm(\'' . esc_js( __( 'Reset all Voice Reader settings to their defaults? This cannot be undone.', 'article-voice-reader' ) ) . '\');">';
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

		wp_register_script( 'avr-player', false, array(), self::PLUGIN_VERSION, true );
		wp_enqueue_script( 'avr-player' );
		wp_add_inline_script( 'avr-player', $this->get_inline_js() );

		wp_register_style( 'avr-player-style', false );
		wp_enqueue_style( 'avr-player-style' );
		wp_add_inline_style( 'avr-player-style', $this->get_inline_css() );
	}

	/**
	 * Adds a "View details" link under the plugin's row on the Plugins screen,
	 * matching the thickbox link WordPress.org-hosted plugins get automatically.
	 */
	public function add_view_details_link( $links, $file ) {
		if ( plugin_basename( __FILE__ ) !== $file ) {
			return $links;
		}

		$url = self_admin_url(
			'plugin-install.php?tab=plugin-information&plugin=' . self::PLUGIN_SLUG .
			'&TB_iframe=true&width=600&height=550'
		);

		$links[] = '<a href="' . esc_url( $url ) . '" class="thickbox open-plugin-details-modal" aria-label="' .
			esc_attr__( 'More information about Article Voice Reader', 'article-voice-reader' ) . '" data-title="' .
			esc_attr__( 'Article Voice Reader', 'article-voice-reader' ) . '">' .
			esc_html__( 'View details', 'article-voice-reader' ) . '</a>';

		return $links;
	}

	/**
	 * Supplies the content for the "View details" popup, since this plugin isn't
	 * hosted on WordPress.org and there's no directory entry to pull real data from.
	 */
	public function provide_plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::PLUGIN_SLUG !== $args->slug ) {
			return $result;
		}

		$info                     = new stdClass();
		$info->name               = 'Article Voice Reader';
		$info->slug               = self::PLUGIN_SLUG;
		$info->version            = self::PLUGIN_VERSION;
		$info->author             = '<a href="https://www.linkedin.com/in/pathmaka" target="_blank" rel="noopener noreferrer">Pathmaka Galappaththi</a>';
		$info->requires           = '5.8';
		$info->tested             = get_bloginfo( 'version' );
		$info->requires_php       = '7.4';
		$info->last_updated       = gmdate( 'Y-m-d' );
		$info->homepage           = '';
		$info->short_description  = __( 'A floating "Listen to this article" widget powered entirely by the visitor\'s own browser.', 'article-voice-reader' );
		$info->sections           = array(
			'description' => '<p>' . esc_html__( 'Article Voice Reader adds a floating text-to-speech widget to your posts and pages, using the browser\'s built-in Web Speech API — no API keys, no external services, no ongoing cost.', 'article-voice-reader' ) . '</p>'
				. '<ul>'
				. '<li>' . esc_html__( 'Play/Pause toggle and adjustable playback speed.', 'article-voice-reader' ) . '</li>'
				. '<li>' . esc_html__( 'Voice selector — shown only when the visitor\'s browser offers more than one voice.', 'article-voice-reader' ) . '</li>'
				. '<li>' . esc_html__( 'Configurable position: any of six corners/edges of the screen.', 'article-voice-reader' ) . '</li>'
				. '<li>' . esc_html__( '[voice_reader] shortcode for manual placement instead of auto-insertion.', 'article-voice-reader' ) . '</li>'
				. '</ul>'
				. '<p><strong>' . esc_html__( 'Author:', 'article-voice-reader' ) . '</strong> Pathmaka Galappaththi &mdash; <a href="https://www.linkedin.com/in/pathmaka" target="_blank" rel="noopener noreferrer">www.linkedin.com/in/pathmaka</a></p>',
			'changelog'   => '<p><strong>1.0.0</strong></p><ul>'
				. '<li>' . esc_html__( 'Initial release.', 'article-voice-reader' ) . '</li>'
				. '<li>' . esc_html__( 'Floating widget with Play/Pause toggle and speed slider.', 'article-voice-reader' ) . '</li>'
				. '<li>' . esc_html__( 'Optional voice selector when the browser offers more than one voice.', 'article-voice-reader' ) . '</li>'
				. '<li>' . esc_html__( 'Configurable position, icon color, and widget title.', 'article-voice-reader' ) . '</li>'
				. '<li>' . esc_html__( '[voice_reader] shortcode and Reset to Defaults option.', 'article-voice-reader' ) . '</li>'
				. '</ul>',
		);
		$info->download_link      = '';
		$info->banners            = array();
		$info->icons              = array();

		return $info;
	}

	private function get_inline_css() {
		return '
.avr-widget {
	position: fixed;
	z-index: 9999;
}
.avr-widget.avr-pos-top-left     { top: 20px; left: 20px; }
.avr-widget.avr-pos-top-right    { top: 20px; right: 20px; }
.avr-widget.avr-pos-middle-left  { top: 50%; left: 20px; transform: translateY(-50%); }
.avr-widget.avr-pos-middle-right { top: 50%; right: 20px; transform: translateY(-50%); }
.avr-widget.avr-pos-bottom-left  { bottom: 20px; left: 20px; }
.avr-widget.avr-pos-bottom-right { bottom: 20px; right: 20px; }

.avr-toggle-btn {
	width: 52px; height: 52px; border-radius: 50%;
	border: none; background: var(--avr-color, #007f96); color: #FAF9F6;
	font-size: 22px; line-height: 1; cursor: pointer;
	display: flex; align-items: center; justify-content: center;
	box-shadow: 0 4px 14px rgba(0,0,0,0.2);
}
.avr-toggle-btn:hover { filter: brightness(0.85); }
.avr-widget.avr-open .avr-toggle-btn { display: none; }

.avr-panel {
	display: none;
	position: relative;
	flex-direction: column;
	align-items: center;
	gap: 10px;
	width: 150px;
	padding: 16px 14px;
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 10px;
	box-shadow: 0 4px 14px rgba(0,0,0,0.15);
	font-size: 13px;
	text-align: center;
}
.avr-widget.avr-open .avr-panel { display: flex; }

.avr-close-btn {
	position: absolute; top: 4px; right: 8px;
	border: none; background: transparent; cursor: pointer;
	font-size: 20px; line-height: 1; color: #999; padding: 0;
}
.avr-close-btn:hover { color: #333; }

.avr-widget-title { font-weight: 600; line-height: 1.3; padding-right: 12px; }
.avr-play-btn {
	width: 52px; height: 52px; border-radius: 50%;
	border: none; background: var(--avr-color, #007f96); color: #fff;
	font-size: 18px; line-height: 1; cursor: pointer;
	display: flex; align-items: center; justify-content: center;
}
.avr-play-btn:hover { filter: brightness(0.85); }
.avr-voice-label {
	display: flex; flex-direction: column; gap: 4px;
	width: 100%; font-size: 12px; color: #444;
}
.avr-voice-label select { width: 100%; padding: 2px 4px; }
.avr-speed-control {
	display: flex; flex-direction: column; align-items: center; gap: 4px;
	width: 100%; font-size: 12px; color: #444;
}
.avr-speed-control input[type="range"] { width: 100%; accent-color: var(--avr-color, #007f96); }
.avr-speed-value { font-weight: 600; color: var(--avr-color, #007f96); }
.avr-status { font-style: italic; color: #666; min-height: 14px; }
@media (max-width: 782px) {
	.avr-panel { width: 128px; padding: 12px 10px; }
	.avr-widget.avr-pos-top-left, .avr-widget.avr-pos-middle-left, .avr-widget.avr-pos-bottom-left { left: 10px; }
	.avr-widget.avr-pos-top-right, .avr-widget.avr-pos-middle-right, .avr-widget.avr-pos-bottom-right { right: 10px; }
}
';
	}

	private function get_inline_js() {
		return <<<'JS'
(function () {
	if (!('speechSynthesis' in window)) {
		document.querySelectorAll('.avr-widget').forEach(function (el) {
			el.innerHTML = '<em>Voice reading is not supported in this browser.</em>';
		});
		return;
	}

	var voicesList = [];
	var voicesResolved = false;

	function loadVoices() {
		var currentList = window.speechSynthesis.getVoices();
		if (currentList.length > 0) {
			voicesList = currentList;
			voicesResolved = true;
		} else if (!voicesResolved) {
			return; // Not loaded yet — wait for onvoiceschanged or the fallback below.
		}
		document.querySelectorAll('.avr-widget').forEach(populateVoiceSelect);
	}

	function populateVoiceSelect(widget) {
		if (!voicesResolved) {
			return; // Avoid deciding to remove the dropdown before we truly know the count.
		}
		var label = widget.querySelector('.avr-voice-label');
		if (!label) {
			return;
		}
		if (voicesList.length <= 1) {
			label.remove();
			return;
		}
		var select = label.querySelector('.avr-voice-select');
		if (!select || select.dataset.populated) {
			return;
		}
		voicesList.forEach(function (voice, i) {
			var opt = document.createElement('option');
			opt.value = i;
			opt.textContent = voice.name + ' (' + voice.lang + ')';
			select.appendChild(opt);
		});
		select.dataset.populated = '1';
	}

	window.speechSynthesis.onvoiceschanged = loadVoices;
	loadVoices();

	// Fallback for browsers that never fire onvoiceschanged: give it one second,
	// then accept whatever getVoices() reports (even if that's still empty/one).
	setTimeout(function () {
		if (!voicesResolved) {
			voicesResolved = true;
			voicesList = window.speechSynthesis.getVoices();
			document.querySelectorAll('.avr-widget').forEach(populateVoiceSelect);
		}
	}, 1000);

	document.querySelectorAll('.avr-widget').forEach(function (widget) {
		var fullText = JSON.parse(widget.dataset.text || '""');
		var remainingText = fullText;
		var lastCharIndex = 0;
		var playBtn = widget.querySelector('.avr-play-btn');
		var toggleBtn = widget.querySelector('.avr-toggle-btn');
		var closeBtn = widget.querySelector('.avr-close-btn');
		var speedSlider = widget.querySelector('.avr-speed-slider');
		var speedValueDisplay = widget.querySelector('.avr-speed-value');
		var status = widget.querySelector('.avr-status');

		function getVoiceSelect() {
			return widget.querySelector('.avr-voice-select');
		}

		function setIcon(playing) {
			playBtn.innerHTML = playing ? '&#10074;&#10074;' : '&#9654;';
			playBtn.setAttribute('aria-label', playing ? 'Pause' : 'Play');
		}

		function makeUtterance(textToSpeak) {
			var u = new SpeechSynthesisUtterance(textToSpeak);
			u.rate = parseFloat(speedSlider.value) || 1;
			var voiceSelect = getVoiceSelect();
			if (voiceSelect && voiceSelect.value !== '' && voicesList[voiceSelect.value]) {
				u.voice = voicesList[voiceSelect.value];
			}
			u.onboundary = function (e) {
				lastCharIndex = e.charIndex;
			};
			u.onend = function () {
				setIcon(false);
				status.textContent = 'Finished';
				remainingText = fullText;
				lastCharIndex = 0;
			};
			u.onerror = function (e) {
				if (e.error === 'canceled' || e.error === 'interrupted') {
					return; // Expected — triggered by restarting on speed/voice change.
				}
				status.textContent = 'There was a problem playing the audio.';
			};
			return u;
		}

		// Cancels the current utterance and picks up from roughly where it left off,
		// instead of restarting the whole article. Position tracking relies on the
		// browser firing "boundary" events, which isn't supported by every voice —
		// when it isn't, this falls back to restarting from the beginning.
		function restartFromCurrentPosition() {
			window.speechSynthesis.cancel();
			remainingText = remainingText.slice(lastCharIndex);
			lastCharIndex = 0;

			if (remainingText.trim() === '') {
				setIcon(false);
				status.textContent = 'Finished';
				remainingText = fullText;
				return;
			}

			window.speechSynthesis.speak(makeUtterance(remainingText));
			setIcon(true);
			status.textContent = 'Playing…';
		}

		playBtn.addEventListener('click', function () {
			if (window.speechSynthesis.speaking && !window.speechSynthesis.paused) {
				window.speechSynthesis.pause();
				setIcon(false);
				status.textContent = 'Paused';
				return;
			}
			if (window.speechSynthesis.paused) {
				window.speechSynthesis.resume();
				setIcon(true);
				status.textContent = 'Playing…';
				return;
			}
			remainingText = fullText;
			lastCharIndex = 0;
			window.speechSynthesis.cancel();
			window.speechSynthesis.speak(makeUtterance(remainingText));
			setIcon(true);
			status.textContent = 'Playing…';
		});

		function formatSpeed(value) {
			var num = parseFloat(value);
			return (Math.round(num * 100) / 100).toString().replace(/\.?0+$/, '') + 'x';
		}

		speedSlider.addEventListener('input', function () {
			speedValueDisplay.textContent = formatSpeed(speedSlider.value);
		});

		speedSlider.addEventListener('change', function () {
			if (window.speechSynthesis.speaking) {
				restartFromCurrentPosition();
			}
		});

		widget.addEventListener('change', function (e) {
			if (e.target.classList.contains('avr-voice-select') && window.speechSynthesis.speaking) {
				restartFromCurrentPosition();
			}
		});

		toggleBtn.addEventListener('click', function () {
			widget.classList.add('avr-open');
		});

		closeBtn.addEventListener('click', function () {
			widget.classList.remove('avr-open');
		});
	});

	window.addEventListener('beforeunload', function () {
		window.speechSynthesis.cancel();
	});
})();
JS;
	}
}

new AVR_Voice_Reader();
