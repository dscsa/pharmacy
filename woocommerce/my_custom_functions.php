<?php
// Register custom style sheets and javascript.
add_action( 'wp_enqueue_scripts', 'register_custom_plugin_styles' );
function register_custom_plugin_styles() {
    wp_enqueue_style( 'account',  'https://dscsa.github.io/webform/woocommerce/account.css' );
    wp_enqueue_style( 'checkout',  'https://dscsa.github.io/webform/woocommerce/checkout.css' );
    wp_enqueue_style( 'storefront',  'https://dscsa.github.io/webform/woocommerce/storefront.css' );
    wp_enqueue_style( 'select',  'https://dscsa.github.io/webform/woocommerce/select2.css' );

    wp_enqueue_script( 'order',  'https://dscsa.github.io/webform/woocommerce/checkout.js',  array('jquery'));
}

function patient_fields() {
    //https://docs.woocommerce.com/wc-apidocs/source-function-woocommerce_form_field.html#1841-2061
    $user_id = get_current_user_id();

    return array(
    'account_language' => array(
        'type'   	  => 'radio',
        'label'     => __( 'Language', 'woocommerce' ),
        'label_class' => array('radio'),
        'required'  => true,
        'options'   => ['english' => 'English', 'spanish' => __('Spanish')],
        'default'   => get_user_meta($user_id, 'account_language', true) ?: 'english'
    ),
    'account_backup_pharmacy' => array(
        'type'   	  => 'select',
        'label'     => __('<span class="erx">Name and address of a backup pharmacy to fill your prescriptions if we are out-of-stock</span><span class="pharmacy">Name and address of pharmacy from which we should transfer your medication(s)</span>', 'woocommerce'),
        'required'  => true,
        'options'   => [''],
        'default'   => get_user_meta($user_id, 'account_backup_pharmacy', true)
    ),
    'account_medications_other' => array(
        'type'   	  => 'text',
        'label'     =>  __( 'List any other medication(s) or supplement(s) you are currently taking', 'woocommerce'),
        'default'   => get_user_meta($user_id, 'account_medications_other', true)
    ),
    'account_allergies' => array(
        'type'   	  => 'radio',
        'label'     => __( 'Allergies', 'woocommerce' ),
        'label_class' => array('radio'),
        'required'  => true,
        'default'   => 'Yes',
        'options'   => ['Yes' => __('Allergies Selected Below'), 'No' => __('No Medication Allergies')],
    	  'default'   => get_user_meta($user_id, 'account_allergies_english', true)
    ),
    'account_allergies_aspirin_salicylates' => array(
        'type'      => 'checkbox',
        'class'     => array('form-row-wide'),
        'label'     => __( 'Aspirin and salicylates', 'woocommerce' ),
        'default'   => get_user_meta($user_id, 'account_allergies_aspirin_salicylates', true)
    ),
    'account_allergies_erythromycin_biaxin_zithromax' => array(
        'type'      => 'checkbox',
        'class'     => array('form-row-wide'),
        'label'     => __( 'Erythromycin, Biaxin, Zithromax', 'woocommerce' ),
        'default'   => get_user_meta($user_id, 'account_allergies_erythromycin_biaxin_zithromax', true)
    ),
    'account_allergies_nsaids' => array(
        'type'      => 'checkbox',
        'class'     => array('form-row-wide'),
        'label'     => __( 'NSAIDS e.g., ibuprofen, Advil', 'woocommerce' ),
        'default'   => get_user_meta($user_id, 'account_allergies_nsaids', true)
    ),
    'account_allergies_penicillins_cephalosporins' => array(
        'type'      => 'checkbox',
        'class'     => array('form-row-wide'),
        'label'     => __( 'Penicillins/cephalosporins e.g., Amoxil, amoxicillin, ampicillin, Keflex, cephalexin', 'woocommerce' ),
        'default'   => get_user_meta($user_id, 'account_allergies_penicillins_cephalosporins', true)
    ),
    'account_allergies_sulfa' => array(
        'type'      => 'checkbox',
        'class'     => array('form-row-wide'),
        'label'     => __( 'Sulfa drugs e.g., Septra, Bactrim, TMP/SMX', 'woocommerce' ),
        'default'   => get_user_meta($user_id, 'account_allergies_sulfa', true)
    ),
    'account_allergies_tetracycline' => array(
        'type'      => 'checkbox',
        'class'     => array('form-row-wide'),
        'label'     => __( 'Tetracycline antibiotics', 'woocommerce' ),
        'default'   => get_user_meta($user_id, 'account_allergies_tetracycline', true)
    ),
    'account_allergies_other_checkbox' => array(
        'type'      => 'checkbox',
        'label'     =>__( 'Other Allergies', 'woocommerce' ).'<input class="input-text " name="account_allergies_other" id="account_allergies_other" value="'.get_user_meta($user_id, 'account_allergies_other', true).'">'
    ),
    'account_birth_date' => array(
        'label'     => __( 'Date of Birth', 'woocommerce' ),
        'required'  => true,
        'default'   => get_user_meta($user_id, 'account_birth_date', true)
    ),
    'account_phone' => array(
        'label'     => __( 'Phone', 'woocommerce' ),
       	'required'  => true,
        'type'      => 'tel',
        'validate'  => array ('phone'),
        'autocomplete' => 'tel',
        'default'   => get_user_meta($user_id, 'account_phone', true)
    )
  );
}

//Display custom fields on account/details
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

add_action( 'woocommerce_register_form_start', 'custom_login_form' );
function custom_login_form() {
  	$patient_fields = patient_fields();

	$first_name = array(
        'class' => array('form-row-first'),
        'label'  => __( 'First name', 'woocommerce' )
    );

    $last_name = array(
        'class' => array('form-row-last'),
        'label'  => __( 'Last name', 'woocommerce' )
    );

    echo woocommerce_form_field('account_language', $patient_fields['account_language']);
  	echo woocommerce_form_field('account_first_name', $first_name);
    echo woocommerce_form_field('account_last_name', $last_name);
  	echo woocommerce_form_field('account_birth_date', $patient_fields['account_birth_date']);
}

//After Registration, set default shipping/billing/account fields
//then save the user into GuardianRx
add_action('woocommerce_created_customer', 'customer_created');
function customer_created($user_id) {
    $first_name = sanitize_text_field($_POST['account_first_name']);
    $last_name = sanitize_text_field($_POST['account_last_name']);
    foreach(['', 'billing_', 'shipping_'] as $field) {
    	update_user_meta($user_id, $field.'first_name', $first_name);
        update_user_meta($user_id, $field.'last_name', $last_name);
    }
    update_user_meta($user_id, 'account_birth_date', $_POST['account_birth_date']);
    update_user_meta($user_id, 'account_language', $_POST['account_language']);

    //Run Guardian addEditPatient()
}

// Function to change email address
add_filter( 'wp_mail_from', 'email_address' );
function email_address() {
    return 'rx@goodpill.org';
}
add_filter( 'wp_mail_from_name', 'email_name' );
function email_name() {
	return 'Good Pill Pharmacy';
}

// After registration and login redirect user to account/orders.
// Clicking on Dashboard/New Order in Nave will add the actual product
add_action('woocommerce_registration_redirect', 'custom_redirect', 2);
add_action('woocommerce_login_redirect', 'custom_redirect', 2);
function custom_redirect() {
    return home_url('/account/orders');
}

add_filter ( 'woocommerce_account_menu_items', 'custom_my_account_menu' );
function custom_my_account_menu($nav) {

  //Clicking on Dashboard/New Order actually adds the product.
  //Hash is necessary to prevent the trailing slash to ignore query
  $new = array('?add-to-cart=30#' => __( 'New Order', 'woocommerce' ));

  //Preserve order otherwise new link is at the bottom of menu
  foreach ($nav as $key => $val) {
      if ($key != 'dashboard')
          $new[$key] = $val;
  }

  $new['edit-account'] = __( 'Account Details', 'woocommerce' );
  $new['edit-address'] = __( 'Address & Payment', 'woocommerce' );

  return $new;
}

//On new order and account/details save account fields back to user
//TODO should changing checkout fields overwrite account fields if they are set?
add_action('woocommerce_save_account_details', 'save_custom_fields_to_user' );
add_action('woocommerce_checkout_update_user_meta', 'save_custom_fields_to_user');
function save_custom_fields_to_user( $user_id) {

   wp_mail('adam.kircher@gmail.com', 'save_custom_fields_to_user', $user_id.' '.print_r($_POST, true));

   foreach (patient_fields() as $key => $field) {
    	update_user_meta( $user_id, $key, sanitize_text_field($_POST[$key]));
   }

   //Run Guardian update shipping address, allergies, etc
}

//Save Billing info to Guardian
add_action('updated_user_meta', 'custom_updated_user_meta', 10, 4);
function custom_updated_user_meta( $meta_id, $post_id, $meta_key, $meta_value )
{
   if ($meta_key != '_trustcommerce_customer_id') {
    return
   }

   wp_mail('adam.kircher@gmail.com', 'updated_post_meta', print_r([$meta_id, $post_id, $meta_key, $meta_value], true));
}

//Didn't work: https://stackoverflow.com/questions/38395784/woocommerce-overriding-billing-state-and-post-code-on-existing-checkout-fields
//Did work: https://stackoverflow.com/questions/36619793/cant-change-postcode-zip-field-label-in-woocommerce
add_filter('ngettext', 'custom_translate');
add_filter('gettext', 'custom_translate');
function custom_translate($term) {

     $toEnglish = array(
        'Spanish'  => 'Espanol',
        'Username or email address' => 'Email Address',
        'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">shipping and billing addresses</a> and <a href="%3$s">edit your password and account details</a>.' => '',
        'ZIP' => 'Zip code',
        'Your order' => '',
        '%s has been added to your cart.' => 'Fill out the form below to place a new order'
    );

    $toSpanish = array(
        'Language' => 'Lingua',
        'Use a new credit card' => 'Spanish Use a new credit card',
        'Place New Order' => 'Order Nueva',
        'Billing details' => 'La Cuenta Por Favor',
        'Ship to a different address?' => 'Spanish Ship to a different address?',
        'Search and select medications by generic name that you want to transfer to Good Pill' => 'Drogas de Transfer',
        '<span class="erx">Name and address of a backup pharmacy to fill your prescriptions if we are out-of-stock</span><span class="pharmacy">Name and address of pharmacy from which we should transfer your medication(s)</span>' => '<span class="erx">Pharmacia de out-of-stock</span><span class="pharmacy">Pharmacia de transfer</span>',
		    'Allergies' => 'Allergias',
        'Allergies Selected Below' => 'Si Allergias',
        'No Medication Allergies' => 'No Allergias',
		    'Aspirin and salicylates' => 'Drogas de Aspirin',
		    'Erythromycin, Biaxin, Zithromax' => 'Drogas de Erthromycin',
		    'NSAIDS e.g., ibuprofen, Advil' => 'Drogas de NSAIDS',
		    'Penicillins/cephalosporins e.g., Amoxil, amoxicillin, ampicillin, Keflex, cephalexin' => 'Drogas de Penicillin',
		    'Sulfa drugs e.g., Septra, Bactrim, TMP/SMX' => 'Drogas de Sulfa',
		    'Tetracycline antibiotics' => 'Antibiotics de Tetra',
        'Other Allergies' => 'Otras Allegerias',
		    'Phone' => 'Telefono',
        'List any other medication(s) or supplement(s) you are currently taking' => 'Otras Drogas',
        'Email address' => 'Spanish Email',
        'First name' => 'Nombre Uno',
        'Last name' => 'Nombre Dos',
        'Date of Birth' => 'Fetcha Cumpleanos',
        'Address' => 'Spanish Address',
        'State' => 'Spanish State',
        'Zip code' => 'Spanish Zip',
        'Town / City' => 'Ciudad',
        'Password change' => 'Spanish Password',
        'Current password (leave blank to leave unchanged)' => 'Spanish current password (leave blank to leave unchanged)',
        'New password (leave blank to leave unchanged)' => 'Spanish New password (leave blank to leave unchanged)',
        'Confirm new password' => 'Spanish Confirm new password',
        'Email Address' => 'Spanish Email Address'
    );

    $english = isset($toEnglish[$term]) ? $toEnglish[$term] : $term;

    $spanish = $toSpanish[$english];

    if ( ! isset($spanish)) return $english;

    //This allows client side translating based on jQuery listening to radio buttons
    return  "<span class='english'>$english</span><span class='spanish'>$spanish</span>";
}

// Hook in
add_filter( 'woocommerce_checkout_fields' , 'custom_checkout_fields' );
function custom_checkout_fields( $fields ) {

    $patient_fields = patient_fields();

    //Add some order fields that are not in patient profile
    $order_fields = array(
      'source_english' => array(
        'type'   	  => 'select',
        'required'  => true,
        'class'     => array('english'),
        'options'   => [
            'erx'   => 'Prescription(s) were sent to Good Pill from my doctor',
            'pharmacy' => 'Please transfer prescription(s) from my pharmacy'
        ]
      ),
      'source_spanish' => array(
        'type'   	  => 'select',
        'required'  => true,
        'class'     => array('spanish'),
        'options'   => [
            'erx'   => 'Spanish Source eRx',
            'pharmacy' => 'Spanish Source Pharmacy'
        ]
      ),
      'medication'  => array(
        'type'   	  => 'select',
        'label'     => __('Search and select medications by generic name that you want to transfer to Good Pill', 'woocommerce'),
        'options'   => ['']
      )
    );

    //Insert order fields at offset 2
    $offset = 1;
    $fields['order'] =
      array_slice($patient_fields, 0, $offset, true) +
      $order_fields +
      array_slice($patient_fields, $offset, NULL, true);

    //Allow billing out of state but don't allow shipping out of state
    $fields['shipping']['shipping_state']['type'] = 'select';
    $fields['shipping']['shipping_state']['options'] = ['GA' => 'Georgia'];

    //Remove Some Fields
    unset($fields['billing']['billing_first_name']['autofocus']);
    unset($fields['billing']['shipping_first_name']['autofocus']);
    unset($fields['billing']['billing_phone']);
    unset($fields['billing']['billing_email']);
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_country']);
    unset($fields['shipping']['shipping_country']);
    unset($fields['shipping']['shipping_company']);

    return $fields;
}

function guardian() {
    global $db;
    $db = sqlsrv_connect('GOODPILL-SERVER', ['Database' => 'cph']);

    if($db === false) {
       echo "sqlsrv_connect error<br>";
       print_r(sqlsrv_errors());
    }

	echo "start2<br>";
    $fname = 'cindy';
    $lname = 'Tompson';
	$DOB   = '1980-01-01';
	$ShipZip = '30093';
	$PatID   = '';
    $params = array(
        array($lname, SQLSRV_PARAM_IN),
        array($fname, SQLSRV_PARAM_IN),
        array($DOB, SQLSRV_PARAM_IN),
        array(&$PatID, SQLSRV_PARAM_OUT),
        //array($ShipZip, SQLSRV_PARAM_IN),
    );
	$SP = "{call SirumWeb_FindPatByNameandDOB(?, ?, NULL, ?)}";
	//$SP = "{call SirumWeb_AddEditPatient('Cindy', NULL, 'Thompson', '01/01/1980', NULL, NULL, NULL, NULL, NULL, '30093', NULL, NULL)}";
	echo "start3<br>";
 /* Execute the query. */
    $stmt1 = sqlsrv_query($db, $SP, $params);
    if( $stmt1 === false )
    {
        echo "Error in executing statement 3.<br>";
        print_r(sqlsrv_errors());
    }
	echo "start4<br>";
    /* Display the value of the output parameter $vacationHrs. */
     while( $row = sqlsrv_fetch_array( $stmt1, SQLSRV_FETCH_ASSOC )) {
        print_r($row);
    }
    echo "Patient ID is $PatID<br>";
    print_r($row);

    echo 'end<br>';

    /* Free the statement and connection resources. */
    sqlsrv_free_stmt( $stmt1 );
    echo 'SirumWeb_AddEditPatient<br>';
    $params = array(
     	array($fname, SQLSRV_PARAM_IN),
        array($lname, SQLSRV_PARAM_IN),
        array($DOB, SQLSRV_PARAM_IN),
        //array(&$PatID, SQLSRV_PARAM_OUT),
        //array($ShipZip, SQLSRV_PARAM_IN),
    );
	//$SP = "{call SirumWeb_FindPatByNameandDOB(?, ?, NULL, ?)}";
	$SP = "{call SirumWeb_AddEditPatient('Kiah', 'JaSong', 'Williams', '07/10/1985', '2245 Latham', 'Apt', '#25', 'Mountain View', 'CA', '94040', 'USA', '6504887414')}";
	echo "start3<br>";
 /* Execute the query. */
    $stmt2 = sqlsrv_query($db, $SP);
    if( $stmt2 === false )
    {
        echo "Error in executing statement 3.<br>";
        print_r(sqlsrv_errors());
    }
	echo "start4<br>";
    /* Display the value of the output parameter $vacationHrs. */
     while( $row = sqlsrv_fetch_array( $stmt2, SQLSRV_FETCH_ASSOC )) {
        print_r($row);
    }
    echo "Patient ID is $PatID<br>";
    print_r($row);

    echo 'end<br>';

    /* Free the statement and connection resources. */
    sqlsrv_free_stmt( $stmt2 );

	echo 'select & from cppat<br>';
	$sql = "select * from cppat where cppat.pat_id=1003";

    $query = sqlsrv_query( $db, $sql);
    if( $query === false ) {
        print_r(sqlsrv_errors());
    }

    print_r($query);

    while( $row = sqlsrv_fetch_array( $query, SQLSRV_FETCH_ASSOC )) {
        print_r($row);
    }

	sqlsrv_free_stmt( $query );
    sqlsrv_close( $db );
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
 ?>
