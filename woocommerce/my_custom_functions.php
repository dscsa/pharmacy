global $patient_fields;
$patient_fields = [
    'account_language' => array(
        'type'   	=> 'select',
        'account_required'  => true,
        'options'   => ['english' => 'English', 'spanish' => 'Espanol'],
    ),
    'billing_first_name' => $fields['billing']['billing_first_name'],
    'billing_last_name' => $fields['billing']['billing_last_name'],
    'source_english' => array(
        'type'   	=> 'select',
        'required'  => true,
        'class'     => array('english'),
        'options'   => [
            'erx' => 'Prescription(s) were sent to Good Pill from my doctor',
            'pharmacy' => 'Please transfer prescription(s) from my pharmacy'
        ]
    ),
    'source_spanish' => array(
        'type'   	=> 'select',
        'required'  => true,
        'class'     => array('spanish'),
        'options'   => [
            'erx' => 'Hola eRx',
            'pharmacy' => 'Adios Pharmacy'
        ]
    ),
    'medication' => array(
        'type'   	=> 'select',
        'label'     => '<span class="english">Search and select medications by generic name that you want to transfer to Good Pill</span><span class="spanish">Hola</span>',
        'options'   => [''],
    ),
    'account_backupPharmacy' => array(
        'type'   	=> 'select',
        'label'     => '<span class="english erx">Name and address of a backup pharmacy to fill your prescriptions if we are out-of-stock</span><span class="spanish erx">Hola Erx</span><span class="english pharmacy">Name and address of pharmacy from which we should transfer your medication(s)</span><span class="spanish pharmacy">Hola Pharmacy</span>',
        'required'  => true,
        'options'   => [''],
    ),
    'account_medicationsOther' => array(
        'type'   	=> 'text',
        'label'     => '<span class="english">List any other medication(s) or supplement(s) you are currently taking</span><span class="spanish">Hola</span>',
    ),
    'account_allergies_english' => array(
        'type'   	=> 'select',
        'label'     => '<span class="english">Allergies</span>',
        'class'     => array('english'),
        'required'  => true,
        'options'   => ['Yes' => 'Allergies Selected Below', 'No' => 'No Medication Allergies'],
    ),
    'account_allergies_spanish' => array(
        'type'   	=> 'select',
        'label'     => '<span class="spanish">Spanish Allergies</span>',
        'class'     => array('spanish'),
        'required' => true,
        'options'   => ['Yes' => 'Si', 'No' => 'No'],
    ),
    'account_allergies_aspirin_salicylates' => array(
        'type'   => 'checkbox',
        'class' => array('form-row-wide'),
        'label'  => '<span class="english">Aspirin and salicylates</span><span class="spanish">Hola</span>',
    ),
    'account_allergies_erythromycin_biaxin_zithromax' => array(
        'type'   => 'checkbox',
        'class' => array('form-row-wide'),
        'label'  => '<span class="english">Erythromycin, Biaxin, Zithromax</span><span class="spanish">Hola</span>',
    ),
    'account_allergies_nsaids' => array(
        'type'   => 'checkbox',
        'class' => array('form-row-wide'),
        'label'  => '<span class="english">NSAIDS e.g., ibuprofen, Advil</span><span class="spanish">Hola</span>',
    ),
    'account_allergies_penicillins_cephalosporins' => array(
        'type'   => 'checkbox',
        'class' => array('form-row-wide'),
        'label'  => '<span class="english">Penicillins/cephalosporins e.g., Amoxil, amoxicillin, ampicillin, Keflex, cephalexin</span><span class="spanish">Hola</span>',
    ),
    'account_allergies_sulfa' => array(
        'type'   => 'checkbox',
        'class' => array('form-row-wide'),
        'label'  => '<span class="english">Sulfa drugs e.g., Septra, Bactrim, TMP/SMX</span><span class="spanish">Hola</span>',
    ),
    'account_allergies_tetracycline' => array(
        'type'   => 'checkbox',
        'class' => array('form-row-wide'),
        'label'  => '<span class="english">Tetracycline antibiotics</span><span class="spanish">Hola</span>',
    ),
    'account_allergies_other_english' => array(
        'type' => 'text',
        'class' => array('english'),
        'placeholder'=> 'Other Allergies'
    ),
    'account_allergies_other_spanish' => array(
        'type' => 'text',
        'class' => array('spanish'),
        'placeholder'=> 'Otras Allergias'
    ),
    'account_birthdate' => array(
        'type' => 'text',
        'label'     => '<span class="english">Date of Birth</span><span class="spanish">Hola</span>',
        'required'  => true
    ),
    'account_phone' => $fields['billing']['billing_phone']
];

// After registration, logout the user and redirect to home page
add_action('woocommerce_registration_redirect', 'custom_redirect', 2);
add_action('woocommerce_login_redirect', 'custom_redirect', 2);
function custom_redirect() {
    return home_url('/account/orders');
}

// Function to change email address

add_filter( 'wp_mail_from', 'wpb_sender_email' );
function wpb_sender_email( $original_email_address ) {
    return 'rx@goodpill.org';
}

// Function to change sender name
add_filter( 'wp_mail_from_name', 'wpb_sender_name' );
function wpb_sender_name( $original_email_from ) {
	return 'Good Pill Pharmacy';
}

add_filter ( 'woocommerce_account_menu_items', 'custom_my_account_menu' );
function custom_my_account_menu($nav) {

  $new = array('?add-to-cart=30#' => __( 'New Order', 'woocommerce' ));

  //Preserve order otherwise new link is at the bottom of menu
  foreach ($nav as $key => $val) {
      if ($key != 'dashboard')
          $new[$key] = $val;
  }

  $new['address'] = __( 'Address & Payment', 'woocommerce' );

  return $new;
}

// Register style sheet.
add_action( 'wp_enqueue_scripts', 'register_custom_plugin_styles' );
function register_custom_plugin_styles() {
    wp_enqueue_style( 'account',  'https://dscsa.github.io/webform/woocommerce/account.css' );
    wp_enqueue_style( 'checkout',  'https://dscsa.github.io/webform/woocommerce/checkout.css' );
    wp_enqueue_style( 'storefront',  'https://dscsa.github.io/webform/woocommerce/storefront.css' );
    wp_enqueue_style( 'select',  'https://dscsa.github.io/webform/woocommerce/select2.css' );

    wp_enqueue_script( 'order',  'https://dscsa.github.io/webform/woocommerce/checkout.js',  array('jquery'));
}

/**
 * Update the order meta with field value
 */

add_action( 'woocommerce_save_account_details', 'save_custom_fields_to_user' );
add_action( 'woocommerce_checkout_update_user_meta', 'save_custom_fields_to_user');
function save_custom_fields_to_user( $user_id) {

   wp_mail('adam.kircher@gmail.com', 'save_custom_fields_to_user', print_r(func_get_args(), true));

    foreach ($_POST as $key => $val) {
        if (substr($key, 0, 8) === "account_") {
    		update_user_meta( $user_id, "_".$key, sanitize_text_field($val));
        }
    }
}

add_action( 'updated_user_meta', 'custom_updated_user_meta', 10, 4);
function custom_updated_user_meta( $meta_id, $post_id, $meta_key, $meta_value )
{
   //wp_remote_post('https://requestb.in/1cul1e71',  ['body' => 'added_post_meta '.print_r(func_get_args(), true)]);

   //wp_mail('adam.kircher@gmail.com', 'added_post_meta', print_r([$meta_id, $post_id, $meta_key, $meta_value], true));
   //$response = wp_remote_post( $url, );
//   if ($meta_key == '_trustcommerce_customer_id') {
    //$post = ['body' => ['trustcommerce_customer_id' => $meta_value, 'email' => wp_get_current_user()->data->user_email]];
    //$res  = wp_remote_post('https://webform.goodpill.org/billing', $post);
//   }

   //update_user_meta( $user_id, 'birthdate', htmlentities( $_POST[ 'birthdate' ] ) );
}


/**
 * To display additional field at My Account page
 * Once member login: edit account
 */
add_action( 'woocommerce_edit_account_form', 'my_woocommerce_edit_account_form' );
function my_woocommerce_edit_account_form() {
    global $patient_fields;

    $user_id = get_current_user_id();

    foreach ($patient_fields as $key => $field) {
        if (substr($key, 0, 8) === "account_") {
        	$val = get_user_meta( $user_id, "_".$key, true);
        	echo woocommerce_form_field($key, $field, $val);
        }
    }

} // end func

//Didn't work: https://stackoverflow.com/questions/38395784/woocommerce-overriding-billing-state-and-post-code-on-existing-checkout-fields
//Did work: https://stackoverflow.com/questions/36619793/cant-change-postcode-zip-field-label-in-woocommerce
add_filter( 'gettext', 'zip_and_town_labels');
function zip_and_town_labels( $translated_text) {
    if ('ZIP' == $translated_text )
        return '<span class="english">Zip Code</span><span class="spanish">Hola</span>';

    if ('Town / City' == $translated_text )
        return '<span class="english">Town / City</span><span class="spanish">Hola</span>';

    return $translated_text;
}

// Hook in
add_filter( 'woocommerce_checkout_fields' , 'custom_checkout_fields' );
function custom_checkout_fields( $fields ) {

    global $patient_fields;

   //Also accepts a 'priority' property and a 'default' property
     $fields['order'] = $patient_fields;

    //Translate Some Labels
    $fields['order']['billing_first_name']['label'] ='<span class="english">First Name</span><span class="spanish">Hola</span>';
    $fields['order']['billing_last_name']['label'] = '<span class="english">Last Name</span><span class="spanish">Hola</span>';
    $fields['order']['account_phone']['label'] = '<span class="english">Phone</span><span class="spanish">Hola</span>';
    $fields['order']['account_phone']['class'] = array('form-row-wide');
    $fields['billing']['billing_address_1']['label'] = '<span class="english">Address</span><span class="spanish">Hola</span>';
    $fields['shipping']['shipping_address_1']['label'] = '<span class="english">Georgia Address</span><span class="spanish">Hola</span>';

    unset($fields['billing']['billing_last_name']);
    unset($fields['billing']['billing_first_name']);
    unset($fields['billing']['billing_phone']);
    unset($fields['billing']['billing_company']);
    unset($fields['shipping']['shipping_company']);

    return $fields;
}

/**
 * @snippet       Add First & Last Name to My Account Register Form - WooCommerce
 * @how-to        Watch tutorial @ https://businessbloomer.com/?p=19055
 * @sourcecode    https://businessbloomer.com/?p=21974
 * @author        Rodolfo Melogli
 * @credits       Claudio SM Web
 * @compatible    WC 2.6.14, WP 4.7.2, PHP 5.5.9
 */

///////////////////////////////
// 3. SAVE FIELDS
add_action( 'woocommerce_created_customer', 'save_name_fields_on_registration' );
function save_name_fields_on_registration( $customer_id ) {
    if ( isset( $_POST['sr_firstname'] ) ) {
        update_user_meta( $customer_id, 'shipping_first_name', sanitize_text_field( $_POST['sr_firstname'] ) );
        update_user_meta( $customer_id, 'billing_first_name', sanitize_text_field( $_POST['sr_firstname'] ) );
    }
    if ( isset( $_POST['sr_lastname'] ) ) {
        update_user_meta( $customer_id, 'shipping_last_name', sanitize_text_field( $_POST['sr_firstname'] ) );
        update_user_meta( $customer_id, 'billing_last_name', sanitize_text_field( $_POST['sr_lastname'] ) );
    }
}
