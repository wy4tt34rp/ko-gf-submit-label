<?php
/**
 * Plugin Name: KO: Gravity Forms Submit Label
 * Description: Adds an embed-specific submit_text attribute to the Gravity Forms shortcode. Example: [gravityform id="1" title="false" description="false" ajax="true" submit_text="Send Request"]
 * Version: 1.0.3
 * Author: KO
 * Author URI: mailto:ko@velaagency.com
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Requires Plugins: gravityforms
 * License: GPL-2.0-or-later
 * Text Domain: ko-gf-submit-label
 */

defined( 'ABSPATH' ) || exit;

define( 'KO_GF_SUBMIT_LABEL_VERSION', '1.0.3' );

// Fail harmlessly if an older WordPress installation bypasses the requirement.
if ( ! method_exists( 'WP_HTML_Tag_Processor', 'set_modifiable_text' ) ) {
	return;
}

require_once __DIR__ . '/includes/class-ko-gf-submit-label-markup.php';

final class KO_GF_Submit_Label {
	const FIELD = 'ko_gf_submit_label';

	public static function init() {
		add_filter( 'gform_shortcode_form', array( __CLASS__, 'shortcode' ), 20, 2 );
		add_filter( 'gform_ajax_submission_result', array( __CLASS__, 'ajax_result' ), 20 );
		add_filter( 'gform_ajax_iframe_content', array( __CLASS__, 'iframe_result' ), 20 );
	}

	/** Legacy iframe AJAX also bypasses the original shortcode output. */
	public static function iframe_result( $html ) {
		if ( ! is_string( $html ) ) {
			return $html;
		}
		// This signed cosmetic token applies only to the submitted form response.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id = isset( $_POST['gform_submit'] ) && is_scalar( $_POST['gform_submit'] ) ? absint( $_POST['gform_submit'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$token = isset( $_POST[ self::FIELD ] ) && is_string( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : '';
		$label = $id ? self::verify( $token, $id ) : '';
		return '' === $label ? $html : KO_GF_Submit_Label_Markup::rewrite( $html, $id, $label, $token );
	}

	/** Modify the completed shortcode output, never the shared form object. */
	public static function shortcode( $html, $attributes ) {
		if ( ! is_string( $html ) || ! is_array( $attributes ) ) {
			return $html;
		}
		$label = self::clean_label( $attributes['submit_text'] ?? '' );
		if ( '' === $label ) {
			return $html;
		}
		$id = $attributes['id'] ?? 0;
		if ( ! is_scalar( $id ) ) {
			return $html;
		}
		if ( ! is_numeric( $id ) || ! (int) $id ) {
			$name = $attributes['name'] ?? $id;
			$id   = is_string( $name ) && class_exists( 'GFFormsModel' ) ? GFFormsModel::get_form_id( $name ) : 0;
		}
		$id = absint( $id );
		if ( ! $id ) {
			return $html;
		}
		return KO_GF_Submit_Label_Markup::rewrite( $html, $id, $label, self::sign( $id, $label ) );
	}

	/** New AJAX bypasses shortcodes. Touch only this submission's response. */
	public static function ajax_result( $result ) {
		if ( ! is_array( $result ) || empty( $result['form']['id'] ) ) {
			return $result;
		}
		$id = absint( $result['form']['id'] );
		// Cosmetic state, authenticated below; this does not process a submission.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$token = isset( $_POST[ self::FIELD ] ) && is_string( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : '';
		$label = self::verify( $token, $id );
		if ( '' === $label ) {
			return $result;
		}
		foreach ( array( 'form_markup', 'page_markup' ) as $key ) {
			if ( isset( $result[ $key ] ) && is_string( $result[ $key ] ) ) {
				$result[ $key ] = KO_GF_Submit_Label_Markup::rewrite( $result[ $key ], $id, $label, $token, 'page_markup' === $key );
			}
		}
		return $result;
	}

	private static function clean_label( $value ) {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return '';
		}
		$label = trim( sanitize_text_field( html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		// Keep the cosmetic transport bounded. Oversized attributes are ignored.
		return strlen( $label ) <= 2000 ? $label : '';
	}

	private static function sign( $id, $label ) {
		$payload = base64_encode( wp_json_encode( array( 'id' => $id, 'label' => $label ) ) );
		return $payload . '.' . hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
	}

	private static function verify( $token, $id ) {
		if ( strlen( $token ) > 18000 || 1 !== substr_count( $token, '.' ) ) {
			return '';
		}
		list( $payload, $signature ) = explode( '.', $token, 2 );
		if ( ! hash_equals( hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) ), $signature ) ) {
			return '';
		}
		$data = json_decode( (string) base64_decode( $payload, true ), true );
		if ( ! is_array( $data ) || ( $data['id'] ?? null ) !== $id || ! is_string( $data['label'] ?? null ) ) {
			return '';
		}
		// Already sanitized before signing. Do not decode entities a second time.
		return $data['label'];
	}
}

KO_GF_Submit_Label::init();
