# KO: Gravity Forms Submit Label

Version 1.0.1. Author: KO, <mailto:ko@velaagency.com>.

```text
[gravityform id="9" title="false" description="false" ajax="true" submit_text="Send Request"]
```

See [readme.txt](readme.txt) for installation, behavior, and compatibility limits.

## Design

`gform_shortcode_form` changes completed output, so overrides never enter a shared form object or a request-wide label map. The HTML tokenizer limits changes to the matching form and submit element. A small subclass uses core's protected bookmark spans to insert transport or empty-button text without reserializing the surrounding form. Keep the parser regression suite in the release checks.

`gform_ajax_submission_result` reads the signed label carried by the submitted form. It verifies the signature and form ID before changing only `form_markup` or `page_markup`. Ordinary shortcode rendering never reads posted label state, so a submission cannot change another embed's label. Confirmation responses are untouched. No database writes or frontend assets are involved.

## Tests

Download a WordPress distribution outside this repository, then run:

```sh
php tests/run.php /path/to/wordpress
```

This uses actual WordPress core functions without a database. Only the site salt is stubbed. Gravity Forms rendering and AJAX result structures are fixtures based on its hook contract; this is not a full Gravity Forms integration test. The repeated same-ID fixture checks label isolation only, not Gravity Forms support for duplicate DOM IDs.

The release ZIP includes only runtime PHP, readme, changelog and license. Tests and Git metadata stay in the source repository.

## References

- [Gravity Forms shortcode hook](https://docs.gravityforms.com/gform_shortcode_form_property/)
- [Gravity Forms AJAX methods](https://docs.gravityforms.com/ajax-form-submission/)
- [Gravity Forms native button markup](https://docs.gravityforms.com/submit-button-css-selectors/)
- [Gravity Forms duplicate embed limitation](https://docs.gravityforms.com/embedding-one-form-multiple-times-per-page/)
- [WordPress HTML text editing](https://developer.wordpress.org/reference/classes/wp_html_tag_processor/set_modifiable_text/)
