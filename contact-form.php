<?php
/*
Plugin Name: Lightweight Contact Form
Plugin URI: https://isabelcastillo.com/lightweight-wordpress-contact-form
Description: Light, barebones Contact Form shortcode with client-side and server-side validation.
Version: 1.4.1.alpha.3
Author: Isabel Castillo
Author URI: https://isabelcastillo.com
Text Domain: lightweight-contact-form
License: GNU GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Copyright 2015-2017 Isabel Castillo

Lightweight Contact Form is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Lightweight Contact Form is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Lightweight Contact Form. If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * Check for malicious input
 */
function lcf_malicious_input($input) {
	$maliciousness = false;
	$denied_inputs = array("\r", "\n", "mime-version", "content-type", "cc:", "to:");
	foreach($denied_inputs as $denied_input) {
		if(strpos(strtolower($input), strtolower($denied_input)) !== false) {
			$maliciousness = true;
			break;
		}
	}
	return $maliciousness;
}
/**
 * Block spam
 */
function lcf_spam_question($input) {
	$response = '2';
	$response = stripslashes(trim($response));
	return ($input == $response);
}
/**
 * Validate the input, server-side
 */
function lcf_input_filter() {
	if ( ! ( isset( $_POST['lcf_key'] ) ) ) {
		return false;
	}
	if ( 'process_form' != $_POST['lcf_key'] ) {
		return false;
	}

	global $lcf_strings;
	$pass  = true;

	$lcf_strings['value_name'] = isset($_POST['lcf_contactform_name']) ? sanitize_text_field($_POST['lcf_contactform_name']) : '';
	$lcf_strings['value_email'] = isset( $_POST['lcf_contactform_email'] ) ? sanitize_email( $_POST['lcf_contactform_email'] ) : '';
	$lcf_strings['value_message'] = isset($_POST['lcf_message']) ? sanitize_textarea_field($_POST['lcf_message']) : '';
	$lcf_strings['value_response'] = isset($_POST['lcf_response']) ? sanitize_text_field($_POST['lcf_response']) : '';

	if(empty($lcf_strings['value_name'])) {
		$pass = false;
		$fail = 'empty';
		$lcf_strings['name_error'] = true;
	}
	if(!is_email($lcf_strings['value_email'])) {
		$pass = false; 
		$fail = 'empty';
		$lcf_strings['email_error'] = true;
	}
	if (empty($lcf_strings['value_response'])) {
		$pass = false; 
		$fail = 'empty';
		$lcf_strings['response_error'] = true;
	}
	if (!lcf_spam_question($lcf_strings['value_response'])) {
		$pass = false;
		$fail = 'wrong';
		$lcf_strings['response_error'] = true;
	}
	if(empty($lcf_strings['value_message'])) {
		$pass = false; 
		$fail = 'empty';
		$lcf_strings['message_error'] = true;
	}
			
	if(lcf_malicious_input($lcf_strings['value_name']) || lcf_malicious_input($lcf_strings['value_email'])) {
		$pass = false; 
		$fail = 'malicious';
	}
	if($pass == true) {
		return true;
	} else {
		if($fail == 'malicious') {
			$lcf_strings['error'] = __( 'Please do not include any of the following in the Name or Email fields: linebreaks, or the phrases "mime-version", "content-type", "cc:" or "to:"', 'lightweight-contact-form' );
		} elseif($fail == 'empty') {
		
			$lcf_strings['error'] = __( 'Please complete the required fields.', 'lightweight-contact-form' );
		} elseif($fail == 'wrong') {
			$lcf_strings['error'] = __( 'Oops. Incorrect answer for the security question. Please try again. Hint: 1 + 1 = 2', 'lightweight-contact-form' );
		}
		return false;
	}
}

/**
 * Add the validation script to the footer on the contact form page.
 */
function lcf_form_validation() {
	?><script type='text/javascript'>var submitButton = document.getElementById('lcf_contact');
	submitButton.onclick = function() {
		var hasBlank = false;
		var emailBlank = false;
		var mathBlank = false;
		
		// hide previous errors
		[].forEach.call(document.querySelectorAll('.error'), function (el) {
		  el.style.display = 'none';
		});

		// Check for blank fields.
		var fields = ['lcf_contactform_name', 'lcf_contactform_email', 'lcf_response', 'lcf_message'];
		var i, l = fields.length;
		var fieldname;
		for (i = 0; i < l; i++) {
			fieldname = fields[i];
			var el = document.forms['lcf-contactform'][fieldname];
			if ( el.value.trim() === '' ) {
				var errorLabel = document.createElement('label');
				errorLabel.setAttribute('for', fieldname); 
				errorLabel.className = 'error';
				errorLabel.innerText = "\u2191 <?php _e( 'This field is required.', 'lightweight-contact-form' ); ?>";
				el.parentNode.insertBefore(errorLabel, el.nextSibling);
				hasBlank = true;
				if ( 'lcf_contactform_email' == fieldname ) { emailBlank = true; }// is the Email field blank?
				if ( 'lcf_response' == fieldname ) { mathBlank = true; }// is the Math field blank?
			}
		}

	    if ( ! emailBlank ) { // if Email is entered, validate it
	    	var eNode = document.forms['lcf-contactform']['lcf_contactform_email'];
		    var filter = /^([\w-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.)|(([\w-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/;
		    if ( ! filter.test( eNode.value.trim() ) ) {
				var errorLabel = document.createElement('label');
				errorLabel.setAttribute('for', 'lcf_contactform_email'); 
				errorLabel.className = 'error';
				errorLabel.innerText = "<?php _e( 'Invalid email. Please enter a valid email.', 'lightweight-contact-form' ); ?>";
				eNode.parentNode.insertBefore(errorLabel, eNode.nextSibling);
				hasBlank = true;
		    }
		}

		if ( ! mathBlank ) { // if math response is entered, validate it
			var mathNode = document.forms['lcf-contactform']['lcf_response'];
			if ( isNaN( parseInt( mathNode.value ) ) ) { // Not numeric, so add an error
				var errorLabel = document.createElement('label');
				errorLabel.setAttribute('for', 'lcf_response'); 
				errorLabel.className = 'error';
				errorLabel.innerText = "<?php _e( 'Please solve the math problem. The answer must be a number.', 'lightweight-contact-form' ); ?>";
				mathNode.parentNode.insertBefore(errorLabel, mathNode.nextSibling);
				hasBlank = true;
			}
		}

	    if ( hasBlank ) { scroll(0,0);return false; }

	};</script>
<?php 
}

/**
 * Shortcode to display contact form
 */
function lcf_shortcode( $atts ) {
	$the_atts = shortcode_atts( array(
		'message_label'	=> __( 'Message', 'lightweight-contact-form' )
	), $atts, 'lcf_contact_form' );

	if (lcf_input_filter()) {
		return lcf_process_contact_form( $the_atts );
	} else {
		add_action( 'wp_footer', 'lcf_form_validation', 9999 );
		return lcf_display_contact_form( $the_atts );
	}
}
add_shortcode( 'lcf_contact_form', 'lcf_shortcode' );

/**
* Process contact form
*/
function lcf_process_contact_form( $atts ) {
	global $lcf_strings;
	$subject = sprintf( __( 'Contact form message from %s', 'lightweight-contact-form' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
	$name_label = __( 'Name:', 'lightweight-contact-form' );
	$email_label = __( 'Email:', 'lightweight-contact-form' );
	$name = isset( $lcf_strings['value_name'] ) ? esc_html( $lcf_strings['value_name'] ) : '';
	$email = isset( $lcf_strings['value_email'] ) ? sanitize_email( $lcf_strings['value_email'] ) : '';
	$form = esc_url( getenv("HTTP_REFERER") );
	$date = date_i18n( get_option( 'date_format' ) ) . ' @ ' . date_i18n( get_option( 'time_format' ) );
	$date = esc_html( $date );
	$filter = apply_filters( 'lcf_additional_field_values', false, $_POST );
	$message_label = esc_html( $atts['message_label'] );
	$message = isset( $lcf_strings['value_message'] ) ? sanitize_textarea_field( $lcf_strings['value_message'] ) : '';
	$intro = sprintf( __( 'You are being contacted via %s :', 'lightweight-contact-form' ), $form );
	$hello_i18n = __( 'Hello,', 'lightweight-contact-form' );

$fullmsg = ("$hello_i18n

$intro

$name_label      $name
$email_label     $email
$filter
${message_label}:

$message

-----------------------

");
	$fullmsg = stripslashes(strip_tags(trim($fullmsg)));
	
	wp_mail( get_bloginfo('admin_email'), $subject, $fullmsg, "Reply-To: $email\n" );

	$results = ( '<div id="lcf-success"><div class="lcf-success-msg">' . __( 'Your message has been sent.', 'lightweight-contact-form' ) . '</div>
<pre>' . $name_label . '    ' . $name    . '
' . $email_label . '   ' . $email   . '
' . __( 'Date:', 'lightweight-contact-form' ) . '    ' . $date . '
' . $filter . '
' . $message_label . ': ' . $message .'</pre><p class="lcf_reset">[ <a href="'. $form . '">' . __( 'Click here to reset form', 'lightweight-contact-form' ) . '</a> ]</p></div>' );
	echo $results;
}
/**
 * Display contact form
 */
function lcf_display_contact_form( $atts ) {
	global $lcf_strings;
	$lcf_form = '';
	$url = get_permalink();
	// entered values
	$name = isset( $lcf_strings['value_name'] ) ? sanitize_text_field( $lcf_strings['value_name'] ) : '';
	$email = isset( $lcf_strings['value_email'] ) ? sanitize_email( $lcf_strings['value_email'] ) : '';
	$message = isset( $lcf_strings['value_message'] ) ? sanitize_textarea_field( $lcf_strings['value_message'] ) : '';
	$response = isset( $lcf_strings['value_response'] ) ? sanitize_text_field( $lcf_strings['value_response'] ) : '';

	// set class attributes for fields based on whether there is an error
	$fields = array( 'name', 'email', 'response', 'message' );
	foreach ( $fields as $field ) {
		${"{$field}_class"} = empty( $lcf_strings["{$field}_error"] ) ? 'required' : 'lcf_contactform_error';
	}
	
	$captcha_box = '<label for="lcf_response"> ' . __( '1 + 1 =', 'lightweight-contact-form' )
				. '</label><input name="lcf_response" id="lcf_response" type="text" size="33" class="' . esc_attr( $response_class ) . '" maxlength="99" value="'. esc_attr( $response ) .'" required />';

	if ( isset( $lcf_strings['error'] ) ) {
		$lcf_form .= '<p class="lcf-error">' . esc_html( $lcf_strings['error'] ) . '</p>';

	}

	$lcf_form .= '<div id="lcf-contactform-wrap">
			<form action="'. esc_url( $url ) .'" method="post" name="lcf-contactform" id="lcf-contactform">
				<label for="lcf_contactform_name">' . __( 'Name', 'lightweight-contact-form' ) . '</label>
				<input name="lcf_contactform_name" id="lcf_contactform_name" type="text" size="33" class="' . esc_attr( $name_class ) . '" maxlength="99" value="'. $name .'" placeholder="' . __( 'Your name', 'lightweight-contact-form' ) . '" required />
				<label for="lcf_contactform_email">' . __( 'Email', 'lightweight-contact-form' ) .'</label>
				<input name="lcf_contactform_email" id="lcf_contactform_email" type="text" size="33" class="' . esc_attr( $email_class ) . '" value="'. $email .'" placeholder="' . __( 'Your email', 'lightweight-contact-form' ) . '" required />';
	// filter to allow insertion of more fields.
	$lcf_form .= apply_filters( 'lcf_form_fields', $captcha_box, $url );
	$lcf_form .= ( '<label for="lcf_message">' . esc_html( $atts['message_label'] ) . '</label>
				<textarea name="lcf_message" id="lcf_message" minlength="4" cols="33" rows="7" placeholder="' . __( 'Your message', 'lightweight-contact-form' ) . '" class="' . esc_attr( $message_class ) . '" required>'. esc_textarea( $message ) .'</textarea>
				<div class="lcf-submit">
					<input type="submit" name="Submit" id="lcf_contact" value="' . __( 'Send', 'lightweight-contact-form' ) . '">
					<input type="hidden" name="lcf_key" value="process_form">
				</div>
			</form>
		</div>' );
	return $lcf_form;
}
