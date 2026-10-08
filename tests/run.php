<?php
/** Run: php tests/run.php /path/to/wordpress (no database or site required). */
error_reporting( E_ALL );
set_error_handler( static function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );
define( 'ABSPATH', rtrim( $argv[1] ?? '', '/' ) . '/' );
define( 'WPINC', 'wp-includes' );
require ABSPATH . WPINC . '/compat.php';
require ABSPATH . WPINC . '/load.php';
require ABSPATH . WPINC . '/plugin.php';
require ABSPATH . WPINC . '/functions.php';
require ABSPATH . WPINC . '/formatting.php';
require ABSPATH . WPINC . '/shortcodes.php';
require ABSPATH . WPINC . '/kses.php';
if ( file_exists( ABSPATH . WPINC . '/utf8.php' ) ) {
	require ABSPATH . WPINC . '/utf8.php';
}
add_filter( 'pre_option_blog_charset', static function () { return 'UTF-8'; } );
function wp_salt( $scheme = 'auth' ) { return 'isolated-test-salt-not-a-site-secret'; }
require ABSPATH . WPINC . '/class-wp-token-map.php';
require ABSPATH . WPINC . '/html-api/html5-named-character-references.php';
foreach ( array( 'span', 'text-replacement', 'attribute-token', 'decoder', 'tag-processor' ) as $class ) {
	require ABSPATH . WPINC . '/html-api/class-wp-html-' . $class . '.php';
}
require dirname( __DIR__ ) . '/ko-gf-submit-label.php';

$checks = 0;
function check( $condition, $message ) {
	global $checks;
	++$checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}
function attribute( $html, $tag, $key, $value, $attribute ) {
	$p = new WP_HTML_Tag_Processor( $html );
	while ( $p->next_tag( $tag ) ) {
		if ( $value === $p->get_attribute( $key ) ) {
			return $p->get_attribute( $attribute );
		}
	}
	return null;
}
function fixture( $id = 9, $button = null ) {
	$button = $button ?? '<input type="submit" id="gform_submit_button_' . $id . '" class="gform_button button" value="Submit" onclick="return submitForm(this);" data-submission-type="submit" tabindex="4" />';
	return '<div class="gform_wrapper"><form method="post" id="gform_' . $id . '" action="/?a=1&amp;b=2" data-theme="orbital"><label for="input_' . $id . '_1">Name</label><input id="input_' . $id . '_1" name="input_1" required value="" /><input type="hidden" name="gform_submit" value="' . $id . '" /><input type="button" id="gform_next_button_' . $id . '_1" value="Next" />' . $button . '</form></div><script>window.test = "<button id=\"gform_submit_button_' . $id . '\">Submit</button>";</script>';
}
function render_label( $label, $html = null, $id = 9 ) {
	return apply_filters( 'gform_shortcode_form', $html ?? fixture( $id ), array( 'id' => $id, 'submit_text' => $label ) );
}
function token( $html ) {
	return attribute( $html, 'INPUT', 'name', KO_GF_Submit_Label::FIELD, 'value' );
}
function value( $html, $id = 9 ) {
	return attribute( $html, 'INPUT', 'id', 'gform_submit_button_' . $id, 'value' );
}

$original = fixture();
foreach ( array( '', '   ', null, array(), '<script>alert(1)</script>', str_repeat( 'a', 2001 ) ) as $empty ) {
	check( render_label( $empty ) === $original, 'Empty/invalid labels leave output byte-identical.' );
}
check( apply_filters( 'gform_shortcode_form', $original, array( 'id' => 9 ) ) === $original, 'Omitted label is unchanged.' );
$a = render_label( 'Send Request' );
$b = render_label( 'Book Tour' );
check( value( $a ) === 'Send Request' && value( $b ) === 'Book Tour', 'Same form embeds keep distinct labels.' );
check( token( $a ) !== token( $b ), 'Transport belongs to the label of each embed.' );
check( render_label( 'Send Request', $a ) === $a, 'Repeated processing is idempotent.' );
check( substr_count( render_label( 'New Label', $a ), 'name="ko_gf_submit_label"' ) === 1, 'Reprocessing updates transport without duplicates.' );
check( value( render_label( '' ) ) === 'Submit', 'No override leaks after labeled embeds.' );
check( value( render_label( 'Contact', null, 12 ), 12 ) === 'Contact', 'Another form is independently labeled.' );
check( render_label( '0' ) !== $original && value( render_label( '0' ) ) === '0', 'Zero is not treated as empty.' );
check( strpos( $a, 'onclick="return submitForm(this);" data-submission-type="submit" tabindex="4"' ) !== false, 'Behavior attributes retained.' );
check( strpos( $a, '<input type="button" id="gform_next_button_9_1" value="Next" />' ) !== false, 'Next button untouched.' );
check( substr( $a, strpos( $a, '<script>' ) ) === substr( $original, strpos( $original, '<script>' ) ), 'Script text untouched.' );
check( render_label( 'Send', '<div id="gform_confirmation_wrapper_9">Thank you.</div>' ) === '<div id="gform_confirmation_wrapper_9">Thank you.</div>', 'Confirmation untouched.' );
check( render_label( 'Send', fixture( 12 ) ) === fixture( 12 ), 'Different form output is untouched.' );
$combined = render_label( 'Send Request', fixture( 9 ) . fixture( 9 ) );
check( substr_count( $combined, 'value="Send Request"' ) === 1, 'Only the first matching form in an embed is changed.' );

foreach ( array( 'R&D "Now"', 'Envoyer ✓', '&amp; welcome', '&amp;amp;', '<b>Send</b><script>alert(1)</script>' ) as $label ) {
	$out = render_label( $label );
	check( strpos( $out, '<b>' ) === false && strpos( $out, 'alert(1)' ) === false, 'Labels contain no injected markup.' );
	$_POST = array( KO_GF_Submit_Label::FIELD => token( $out ) );
	$result = array( 'form' => array( 'id' => 9 ), 'form_markup' => fixture(), 'is_valid' => false, 'validation_messages' => array( 1 => 'Required' ) );
	$ajax = apply_filters( 'gform_ajax_submission_result', $result );
	check( value( $ajax['form_markup'] ) === value( $out ), 'AJAX label survives without double decoding.' );
	check( $ajax['validation_messages'] === $result['validation_messages'] && $ajax['form'] === $result['form'], 'AJAX validation and form object unchanged.' );
}
$button = '<button type="submit" id="gform_submit_button_9" class="gform_button button" aria-label="Old" aria-labelledby="old_label" data-js="submit" onclick="go(this)"><span class="icon" aria-hidden="true">+</span><span class="label">Submit</span><svg aria-hidden="true"><title>Icon</title><path d="M1 1" /></svg></button>';
$out = render_label( 'Send & Go', fixture( 9, $button ) );
check( strpos( $out, '<span class="label">Send &amp; Go</span>' ) !== false, 'Button label span replaced and escaped.' );
check( strpos( $out, '<span class="icon" aria-hidden="true">+</span>' ) !== false && strpos( $out, '<title>Icon</title>' ) !== false, 'Decorative icon markup preserved.' );
check( attribute( $out, 'BUTTON', 'id', 'gform_submit_button_9', 'aria-label' ) === 'Send & Go', 'Accessible label matches.' );
check( attribute( $out, 'BUTTON', 'id', 'gform_submit_button_9', 'aria-labelledby' ) === null, 'Stale external label cannot override accessible name.' );
check( strpos( render_label( 'Send', fixture( 9, '<button id="gform_submit_button_9"></button>' ) ), '>Send</button>' ) !== false, 'Empty buttons gain text.' );
check( strpos( render_label( 'Send', fixture( 9, '<button id="gform_submit_button_9">Submit</button>' ) ), '>Send</button>' ) !== false, 'Native button text changes.' );
check( strpos( render_label( 'Send', fixture( 9, '<button id="gform_submit_button_9"><span>Submit</span><span> now</span></button>' ) ), '<span>Send</span><span></span>' ) !== false, 'Multiple label text nodes do not leave stale words.' );
$image = render_label( 'Send', fixture( 9, '<input type="image" id="gform_submit_button_9" src="submit.png" alt="Submit" />' ) );
check( attribute( $image, 'INPUT', 'id', 'gform_submit_button_9', 'alt' ) === 'Send' && strpos( $image, 'src="submit.png"' ) !== false, 'Input image source preserved; alt updated.' );
$image = render_label( 'Send', fixture( 9, '<button id="gform_submit_button_9" class="gform_image_button"><img src="submit.png" alt="Submit" /></button>' ) );
check( strpos( $image, 'alt="Send"' ) !== false && strpos( $image, '>Send</button>' ) === false, 'Image button remains an image button.' );

$_POST = array( KO_GF_Submit_Label::FIELD => token( $a ) );
$result = array( 'form' => array( 'id' => 9 ), 'form_markup' => fixture(), 'is_valid' => false );
$ajax = apply_filters( 'gform_ajax_submission_result', $result );
check( value( $ajax['form_markup'] ) === 'Send Request' && token( $ajax['form_markup'] ) === token( $a ), 'Full AJAX re-render retains signed transport.' );
$page = array( 'form' => array( 'id' => 9 ), 'page_markup' => '<div class="gform_page"><input type="submit" id="gform_submit_button_9" value="Submit" /></div>', 'form_unique_id' => 'original-uuid' );
$ajax = apply_filters( 'gform_ajax_submission_result', $page );
check( value( $ajax['page_markup'] ) === 'Send Request' && $ajax['form_unique_id'] === 'original-uuid', 'Multipage AJAX label and UUID retained.' );
check( strpos( $ajax['page_markup'], KO_GF_Submit_Label::FIELD ) === false, 'Page-only replacement keeps existing form transport.' );
check( apply_filters( 'gform_shortcode_form', $original, array( 'id' => 9 ) ) === $original, 'Posted label cannot affect an unlabeled embed.' );
check( value( render_label( 'Book Tour' ) ) === 'Book Tour', 'Posted label cannot affect a differently labeled embed.' );
foreach ( array( '', token( $a ) . 'tampered', array( 'bad' ), token( render_label( 'Other', null, 12 ) ) ) as $bad ) {
	$_POST = array( KO_GF_Submit_Label::FIELD => $bad );
	check( apply_filters( 'gform_ajax_submission_result', $result ) === $result, 'Invalid or other-form transport is ignored.' );
}
$_POST = array();
check( apply_filters( 'gform_ajax_submission_result', $result ) === $result, 'AJAX no-override response is unchanged.' );
$_POST = array( KO_GF_Submit_Label::FIELD => token( $a ) );
$confirmation = array( 'form' => array( 'id' => 9 ), 'confirmation_markup' => fixture( 12 ), 'is_valid' => true );
check( apply_filters( 'gform_ajax_submission_result', $confirmation ) === $confirmation, 'AJAX confirmation and chained forms untouched.' );

// Exercise the real WordPress shortcode parser and original GF hook contract.
add_shortcode( 'gravityform', static function ( $attributes ) {
	$id = (int) ( $attributes['id'] ?? 9 );
	return apply_filters( 'gform_shortcode_form', fixture( $id ), $attributes );
} );
$page = do_shortcode( '[gravityform id="9" ajax="true" submit_text="Send Request"][gravityform id="9" submit_text="Book Tour"][gravityform id="9"][gravityform id="12" submit_text="Contact"]' );
check( substr_count( $page, 'value="Send Request"' ) === 1 && substr_count( $page, 'value="Book Tour"' ) === 1 && substr_count( $page, 'value="Submit"' ) === 1 && substr_count( $page, 'value="Contact"' ) === 1, 'WordPress shortcode pipeline isolates four embeds.' );
echo "PASS: {$checks} assertions; WordPress core HTML parser, sanitization and shortcode hooks.\n";
