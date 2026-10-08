<?php
/** Minimal edits using WordPress's HTML tokenizer, preserving unrelated bytes. */
defined( 'ABSPATH' ) || exit;

final class KO_GF_Submit_Label_Markup extends WP_HTML_Tag_Processor {
	private $insertions = array();

	/**
	 * The protected bookmark spans are used only for inserting hidden transport
	 * and for empty button text. Core updates their offsets after attribute edits.
	 * Keep this small adapter covered when raising supported WordPress versions.
	 */
	private function insert_at_token( $html, $after = false ) {
		$name = 'ko_label_' . count( $this->insertions );
		if ( $this->set_bookmark( $name ) ) {
			$this->insertions[ $name ] = array( $html, $after );
		}
	}

	private function finish() {
		$html  = $this->get_updated_html();
		$edits = array();
		foreach ( $this->insertions as $name => $insertion ) {
			$span    = $this->bookmarks[ $name ];
			$edits[] = array( $span->start + ( $insertion[1] ? $span->length : 0 ), $insertion[0] );
		}
		usort( $edits, static function ( $a, $b ) { return $b[0] <=> $a[0]; } );
		foreach ( $edits as $edit ) {
			$html = substr_replace( $html, $edit[1], $edit[0], 0 );
		}
		return $html;
	}

	public static function rewrite( $html, $id, $label, $token, $fragment = false ) {
		$p          = new self( $html );
		$inside     = $fragment;
		$button     = false;
		$has_text   = false;
		$image      = false;
		$skip_stack = array();
		$form_seen  = false;
		$transport_seen = false;
		$void_tags  = array( 'AREA', 'BASE', 'BR', 'COL', 'EMBED', 'HR', 'IMG', 'INPUT', 'LINK', 'META', 'PARAM', 'SOURCE', 'TRACK', 'WBR' );

		while ( $p->next_token() ) {
			$tag = $p->get_tag();
			if ( 'FORM' === $tag ) {
				if ( $p->is_tag_closer() ) {
					if ( $inside && ! $fragment ) {
						if ( ! $transport_seen ) {
							$p->insert_at_token( '<input type="hidden" name="' . KO_GF_Submit_Label::FIELD . '" value="' . esc_attr( $token ) . '" />' );
						}
						break;
					}
				} elseif ( ! $fragment && ! $form_seen && 'gform_' . $id === $p->get_attribute( 'id' ) ) {
					$inside    = true;
					$form_seen = true;
				} else {
					// A fragment must not rewrite a different embedded form.
					$inside = false;
				}
				continue;
			}
			if ( ! $inside ) {
				continue;
			}
			if ( 'INPUT' === $tag && KO_GF_Submit_Label::FIELD === $p->get_attribute( 'name' ) && 'hidden' === $p->get_attribute( 'type' ) ) {
				$p->set_attribute( 'value', $token );
				$transport_seen = true;
				continue;
			}
			if ( ! $button && in_array( $tag, array( 'INPUT', 'BUTTON' ), true ) && ! $p->is_tag_closer() && 'gform_submit_button_' . $id === $p->get_attribute( 'id' ) ) {
				$type = strtolower( (string) $p->get_attribute( 'type' ) );
				if ( 'INPUT' === $tag && ! in_array( $type, array( 'submit', 'image' ), true ) ) {
					continue;
				}
				// Explicit names must agree with the new visible text.
				if ( null !== $p->get_attribute( 'aria-label' ) || null !== $p->get_attribute( 'aria-labelledby' ) ) {
					$p->set_attribute( 'aria-label', $label );
					$p->remove_attribute( 'aria-labelledby' );
				}
				if ( 'INPUT' === $tag ) {
					$p->set_attribute( 'image' === $type ? 'alt' : 'value', $label );
					continue;
				}
				$button     = true;
				$has_text   = false;
				$image      = $p->has_class( 'gform_image_button' );
				$skip_stack = array();
				continue;
			}
			if ( ! $button ) {
				continue;
			}
			if ( 'BUTTON' === $tag && $p->is_tag_closer() ) {
				if ( ! $has_text && ! $image ) {
					$p->insert_at_token( esc_html( $label ) );
				}
				$button = false;
				continue;
			}
			if ( 'IMG' === $tag && $image ) {
				$p->set_attribute( 'alt', $label );
			}
			if ( null !== $tag ) {
				if ( $p->is_tag_closer() ) {
					array_pop( $skip_stack );
				} elseif ( ! in_array( $tag, $void_tags, true ) && ! in_array( $tag, array( 'SCRIPT', 'STYLE', 'TEXTAREA', 'TITLE' ), true ) ) {
					$skip_stack[] = (bool) end( $skip_stack ) || 'SVG' === $tag || 'true' === $p->get_attribute( 'aria-hidden' ) || null !== $p->get_attribute( 'hidden' );
				}
				continue;
			}
			if ( ! $image && ! end( $skip_stack ) && '#text' === $p->get_token_type() && '' !== trim( $p->get_modifiable_text() ) ) {
				$p->set_modifiable_text( $has_text ? '' : $label );
				$has_text = true;
			}
		}
		return $p->finish();
	}
}
