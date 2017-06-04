function patient_fields() {
    // 'autofocus' => true, default => 'value'
    $user_id = get_current_user_id();

    return array(
    'account_language' => array(
        'type'   	=> 'select',
        'account_required'  => true,
        'options'   => ['english' => 'English', 'spanish' => 'Espanol'],
        'default'   => get_user_meta($user_id, 'account_language', true)
    ),
    'account_backup_pharmacy' => array(
        'type'   	=> 'select',
        'label'     => __('<span class="erx">Name and address of a backup pharmacy to fill your prescriptions if we are out-of-stock</span><span class="pharmacy">Name and address of pharmacy from which we should transfer your medication(s)</span>', 'woocommerce'),
        'required'  => true,
        'options'   => [''],
        'default'   => get_user_meta($user_id, 'account_backup_pharmacy', true)
    ),
    'account_medications_other' => array(
        'type'   	=> 'text',
        'label'     =>  __( 'List any other medication(s) or supplement(s) you are currently taking', 'woocommerce'),
        'default'   => get_user_meta($user_id, 'account_medications_other', true)
    ),
    'account_allergies_english' => array(
        'type'   	=> 'select',
        'label'     => __( 'Allergies', 'woocommerce' ),
        'class'     => array('english'),
        'required'  => true,
        'options'   => ['Yes' => 'Allergies Selected Below', 'No' => 'No Medication Allergies'],
    	'default'   => get_user_meta($user_id, 'account_allergies_english', true)
    ),
    'account_allergies_spanish' => array(
        'type'   	=> 'select',
        'label'     => __( 'Allergies', 'woocommerce' ),
        'class'     => array('spanish'),
        'required' => true,
        'options'   => ['Yes' => 'Si', 'No' => 'No'],
        'default'   => get_user_meta($user_id, 'account_allergies_spanish', true)
	),
    'account_allergies_aspirin_salicylates' => array(
        'type'   => 'checkbox',
        'class' => array('form-row-wide'),
        'label'  => __( 'Aspirin and salicylates', 'woocommerce' ),
        'default'   => get_user_meta($user_id, 'account_allergies_aspirin_salicylates', true)
    ),
    'account_allergies_erythromycin_biaxin_zithromax' => array(
        'type'   => 'checkbox',
        'class' => array('form-row-wide'),
        'label'  => __( 'Erythromycin, Biaxin, Zithromax', 'woocommerce' ),
        'default'   => get_user_meta($user_id, 'account_allergies_erythromycin_biaxin_zithromax', true)
    ),
    'account_allergies_nsaids' => array(
        'type'   => 'checkbox',
        'class' => array('form-row-wide'),
        'label'  => __( 'NSAIDS e.g., ibuprofen, Advil', 'woocommerce' ),
        'default'   => get_user_meta($user_id, 'account_allergies_nsaids', true)
    ),
    'account_allergies_penicillins_cephalosporins' => array(
        'type'   => 'checkbox',
        'class' => array('form-row-wide'),
        'label'  => __( 'Penicillins/cephalosporins e.g., Amoxil, amoxicillin, ampicillin, Keflex, cephalexin', 'woocommerce' ),
        'default'   => get_user_meta($user_id, 'account_allergies_penicillins_cephalosporins', true)
    ),
    'account_allergies_sulfa' => array(
        'type'   => 'checkbox',
        'class' => array('form-row-wide'),
        'label'  => __( 'Sulfa drugs e.g., Septra, Bactrim, TMP/SMX', 'woocommerce' ),
        'default'   => get_user_meta($user_id, 'account_allergies_sulfa', true)
    ),
    'account_allergies_tetracycline' => array(
        'type'   => 'checkbox',
        'class' => array('form-row-wide'),
        'label'  => __( 'Tetracycline antibiotics', 'woocommerce' ),
        'default'   => get_user_meta($user_id, 'account_allergies_tetracycline', true)
    ),
    'account_allergies_other_english' => array(
        'class' => array('english'),
        'placeholder'=> 'Other Allergies',
        'default'   => get_user_meta($user_id, 'account_allergies_other_english', true)
    ),
    'account_allergies_other_spanish' => array(
        'class' => array('spanish'),
        'placeholder'=> 'Otras Allergias',
        'default'   => get_user_meta($user_id, 'account_allergies_other_spanish', true)
    ),
    'account_birth_date' => array(
        'label'     => __( 'Date of Birth', 'woocommerce' ),
        'required'  => true,
        'default'   => get_user_meta($user_id, 'account_birth_date', true)
    ),
    'account_phone' => array(
        'label' => __( 'Phone', 'woocommerce' ),
       	'required' => true,
        'type' => 'tel',
        'validate' => array ('phone'),
        'autocomplete' => 'tel',
        'default'   => get_user_meta($user_id, 'account_phone', true)
    )
  );
}

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

  $new['edit-address'] = __( 'Address & Payment', 'woocommerce' );

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

   global $patient_fields;
   foreach ($patient_fields as $key => $field) {
    	update_user_meta( $user_id, $key, sanitize_text_field($_POST[$key]));
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
add_action( 'woocommerce_edit_account_form_start', 'custom_edit_account_form' );
function custom_edit_account_form() {

  foreach (patient_fields() as $key => $field) {
    //echo $key.'<br>'.$val.'<br>';
    if ($key === "account_backup_pharmacy") {
      $field['options'] = array($field['default'] => $field['default']);
    }

    echo woocommerce_form_field($key, $field);
  }
}

//Didn't work: https://stackoverflow.com/questions/38395784/woocommerce-overriding-billing-state-and-post-code-on-existing-checkout-fields
//Did work: https://stackoverflow.com/questions/36619793/cant-change-postcode-zip-field-label-in-woocommerce
add_filter( 'gettext', 'zip_and_town_labels');
function zip_and_town_labels($term) {

    $toSpanish = array(
        'Use a new credit card' => 'Spanish Use a new credit card',
        'Your order' => 'Order Nueva',
        'Billing details' => 'La Cuenta Por Favor',
        'Ship to a different address?' => 'Spanish Ship to a different address?',
        'Search and select medications by generic name that you want to transfer to Good Pill' => 'Drogas de Transfer',
        '<span class="erx">Name and address of a backup pharmacy to fill your prescriptions if we are out-of-stock</span><span class="pharmacy">Name and address of pharmacy from which we should transfer your medication(s)</span>' => '<span class="erx">Pharmacia de out-of-stock</span><span class="pharmacy">Pharmacia de transfer</span>',
		'Allergies' => 'Allergias',
		'Aspirin and salicylates' => 'Drogas de Aspirin',
		'Erythromycin, Biaxin, Zithromax' => 'Drogas de Erthromycin',
		'NSAIDS e.g., ibuprofen, Advil' => 'Drogas de NSAIDS',
		'Penicillins/cephalosporins e.g., Amoxil, amoxicillin, ampicillin, Keflex, cephalexin' => 'Drogas de Penicillin',
		'Sulfa drugs e.g., Septra, Bactrim, TMP/SMX' => 'Drogas de Sulfa',
		'Tetracycline antibiotics' => 'Antibiotics de Tetra',
		'Phone' => 'Telefono',
        'List any other medication(s) or supplement(s) you are currently taking' => 'Otras Drogas',
        'First name' => 'Nombre Uno',
        'Last name' => 'Nombre Dos',
        'Date of Birth' => 'Fetcha Cumpleanos',
        'Address' => 'Spanish Address',
        'Shipping address' => 'Spanish Georgia Shipping Address',
        'State' => 'Spanish State',
        'ZIP' => 'Spanish Zip',
        'Town / City' => 'Ciudad',
        'Password change' => 'Spanish Password',
        'Current password (leave blank to leave unchanged)' => 'Spanish current password (leave blank to leave unchanged)',
        'New password (leave blank to leave unchanged)' => 'Spanish New password (leave blank to leave unchanged)',
        'Confirm new password' => 'Spanish Confirm new password'
    );

    $toEnglish = array(
        'Shipping address' => 'Georgia Shipping Address',
        'ZIP' => 'Zip code',
        'Your order' => 'New Order'
    );

    $spanish = $toSpanish[$term];

    if ( ! $spanish) return $term;

    $english = $toEnglish[$term] ?: $term;

    return  "<span class='english'>$english</span><span class='spanish'>$spanish</span>";
}

// Hook in
add_filter( 'woocommerce_checkout_fields' , 'custom_checkout_fields' );
function custom_checkout_fields( $fields ) {

     $patient_fields = patient_fields();

    //Also accepts a 'autofocus', 'autocomplete', 'validate', 'priority', 'default' properties

    //Add some order fields that are not in patient profile
    $order_fields = array(
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
            'erx' => 'Spanish Source eRx',
            'pharmacy' => 'Spanish Source Pharmacy'
        ]
      ),
      'medication' => array(
        'type'   	=> 'select',
        'label'     => __('Search and select medications by generic name that you want to transfer to Good Pill', 'woocommerce'),
        'options'   => ['']
      )
    );

     # Insert order fields at offset 2
    $offset = 1;
    $fields['order'] = array_slice($patient_fields, 0, $offset, true) +
    $order_fields +
    array_slice($patient_fields, $offset, NULL, true);

    //Translate Some Labels
    $fields['shipping']['shipping_address_1']['label'] = __('Shipping address', 'woocommerce');

    //Remove Some Fields
    unset($fields['billing']['billing_phone']);
    unset($fields['billing']['billing_company']);
    unset($fields['shipping']['shipping_company']);

    return $fields;
}

///////////////////////////////
// 3. SAVE FIELDS
/*
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
*/
