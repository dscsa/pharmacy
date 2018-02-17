<?php
/* Enter your custom functions here */

// Register custom style sheets and javascript.
add_action('admin_enqueue_scripts', 'dscsa_admin_scripts');
function dscsa_admin_scripts() {
  if ($_GET['post'] AND $_GET['action'] == 'edit') {
    wp_enqueue_script('dscsa-common', 'https://dscsa.github.io/webform/woocommerce/common.js');
    wp_enqueue_style('dscsa-select2', 'https://dscsa.github.io/webform/woocommerce/select2.css');
    wp_enqueue_style('dscsa-admin', 'https://dscsa.github.io/webform/woocommerce/admin.css');
    wp_enqueue_script('dscsa-admin', 'https://dscsa.github.io/webform/woocommerce/admin.js', ['jquery', 'dscsa-common']);
  }
}

add_action('init', 'cache_login_registration');
function cache_login_registration() {
  if (strpos($_SERVER['REQUEST_URI'], 'gp-') === false || $_POST) return; //mimic cloud flare page rules
  remove_action('wp', ['WC_Cache_Helper', 'prevent_caching']);
}

add_action('wp_enqueue_scripts', 'dscsa_user_scripts');
function dscsa_user_scripts() {

  //is_wc_endpoint_url('orders') and is_wc_endpoint_url('account-details') seem to work
  wp_enqueue_script('ie9ajax', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-ajaxtransport-xdomainrequest/1.0.4/jquery.xdomainrequest.min.js', ['jquery']);
  wp_enqueue_script('jquery-ui', "/wp-admin/load-scripts.php?c=1&load%5B%5D=jquery-ui-core", ['jquery']);
  wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.min.css');
  wp_enqueue_script('datepicker', '/wp-includes/js/jquery/ui/datepicker.min.js', ['jquery-ui']);

  wp_enqueue_script('dscsa-common', 'https://dscsa.github.io/webform/woocommerce/common.js', ['datepicker', 'ie9ajax']);
  wp_enqueue_style('dscsa-common', 'https://dscsa.github.io/webform/woocommerce/common.css');

  if (substr($_SERVER['REQUEST_URI'], 0, 10) == '/gp-stock/') {
    wp_enqueue_script('select2', '/wp-content/plugins/woocommerce/assets/js/select2/select2.full.min.js'); //usually loaded by woocommerce but since this is independent page we need to load manually
	  wp_enqueue_style('select2', '/wp-content/plugins/woocommerce/assets/css/select2.css?ver=3.0.7'); //usually loaded by woocommerce but since this is independent page we need to load manually
    wp_enqueue_script('dscsa-inventory', 'https://dscsa.github.io/webform/woocommerce/inventory.js', ['jquery', 'ie9ajax']);
    wp_enqueue_style('dscsa-inventory', 'https://dscsa.github.io/webform/woocommerce/inventory.css');
  }

  if (is_user_logged_in()) {
    wp_enqueue_script('dscsa-account', 'https://dscsa.github.io/webform/woocommerce/account.js', ['jquery', 'dscsa-common']);
    wp_enqueue_style('dscsa-select2', 'https://dscsa.github.io/webform/woocommerce/select2.css');

    if (is_checkout() AND ! is_wc_endpoint_url()) {
      wp_enqueue_style('dscsa-checkout', 'https://dscsa.github.io/webform/woocommerce/checkout.css');
      wp_enqueue_script('dscsa-checkout', 'https://dscsa.github.io/webform/woocommerce/checkout.js', ['jquery', 'ie9ajax']);
    }
  } else if (substr($_SERVER['REQUEST_URI'], 0, 9) == '/account/') {
    wp_enqueue_style('dscsa-login', 'https://dscsa.github.io/webform/woocommerce/login.css');
  	wp_enqueue_script('dscsa-login', 'https://dscsa.github.io/webform/woocommerce/login.js', ['jquery', 'dscsa-common']);
  }
}

add_action( 'wp_print_scripts', 'DisableStrongPW', 100 );
function DisableStrongPW() {
    if ( wp_script_is( 'wc-password-strength-meter', 'enqueued' ) ) {
        wp_dequeue_script( 'wc-password-strength-meter' );
    }
}

add_action('wp_enqueue_scripts', 'remove_sticky_checkout', 99);
function remove_sticky_checkout() {
  wp_dequeue_script('storefront-sticky-payment');
}

function get_meta($field, $user_id = null) {
  return get_user_meta($user_id ?: get_current_user_id(), $field, true);
}

function get_default($field, $user_id = null) {
  return $_POST ? $_POST[$field] : get_meta($field, $user_id);
}

//do_action( 'woocommerce_stripe_add_card', $this->get_id(), $token, $response );
add_action('woocommerce_stripe_add_card', 'dscsa_stripe_add_card', 10, 3);
function dscsa_stripe_add_card($stripe_id, $card, $response) {

   $card = [
     'last4' => $card->get_last4(),
     'card' => $card->get_token(),
     'customer' => $stripe_id,
     'type' => $card->get_card_type(),
     'year' => $card->get_expiry_year(),
     'month' => $card->get_expiry_month()
   ];

   $user_id = get_current_user_id();
   $patient_id = get_meta('guardian_id', $user_id);

   update_user_meta($user_id, 'stripe', $card);
   //Meet guardian 50 character limit
   //Customer 18, Card 29, Delimiter 1 = 48
   update_stripe_tokens($patient_id, $card['customer'].','.$card['card'].',');

   $coupon = get_meta('coupon', $user_id);

   update_card_and_coupon($patient_id, $card, $coupon);
}

function order_fields($user_id = null, $medication = ['']) {


  $user_id = $user_id ?: get_current_user_id();

  $medication = [
    'type'   	  => 'select',
    'label'     => __('Search and select medications by generic name that you want to transfer to Good Pill'),
    'options'   => $medication
  ];

  return [
    'rx_source' => [
      'type'   	  => 'radio',
      'required'  => true,
      'default'   => get_default('rx_source', $user_id) ?: 'erx',
      'options'   => [
        'erx'     => __('Rx(s) were sent from my doctor'),
        'pharmacy' => __('Transfer Rx(s) with refills remaining from my pharmacy')

      ]
    ],
    'medication[]'  => $medication,
    'email' => [
      'label'     => __('Email'),
      'type'      => 'email',
      'validate'  => ['email'],
      'autocomplete' => 'email',
      'default'   => get_default('email', $user_id) ?: get_default('account_email', $user_id)
    ]
  ];

  //echo "email ".get_default('email', $user_id);
  //echo "<br>";
  //echo "account_email ".get_default('account_email', $user_id);

}

add_action('wp_footer','hidden_language_radio');
function hidden_language_radio(){
  $lang = get_meta('language');
  echo "<input type='radio' id='language_$lang' value='$lang' name='language' checked='checked' style='display:none'>";
}

function account_fields($user_id = null) {

  $user_id = $user_id ?: get_current_user_id();

  return [
    'language' => [
      'type'   	  => 'radio',
      'label'     => __('Language'),
      'required'  => true,
      'options'   => ['EN' => __('English'), 'ES' => __('Spanish')],
      'default'   => get_default('language', $user_id) ?: 'EN'
    ]
  ];
}

function admin_fields($user_id = null) {

  $user_id = $user_id ?: get_current_user_id();

  return [
    'guardian_id' => [
      'label'     =>  __('Guardian Patient ID'),
      'default'   => get_default('guardian_id', $user_id)
    ]
  ];
}

add_action('woocommerce_reset_password_notification', 'dscsa_reset_password_notification', 10, 2);
function dscsa_reset_password_notification($user_login, $reset_key){
  $link = add_query_arg( array( 'key' => $reset_key, 'login' => $user_login ), wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) ) );
  $link = "https://www.".str_replace(' ', '+', substr($link, 12));
  $user_id = get_user_by('login', $user_login)->ID;
  $phone = get_user_meta($user_id, 'phone', true) ?: get_user_meta($user_id, 'billing_phone', true);

  if( ! $phone)
    return wp_mail("adam.kircher@gmail.com", "Cannot sendSMS.  No Phone #", "Shipping phone: ".get_user_meta($user_id, 'shipping_phone', true).", billing phone: ".get_user_meta($user_id, 'billing_phone', true).", account phone:  ".get_user_meta($user_id, 'account_phone', true)." ".$link);

  sendSMS($phone, $link);
}

//https://20somethingfinance.com/how-to-send-text-messages-sms-via-email-for-free/
function sendSMS($phone, $text) {
  wp_mail("6507992817@txt.att.net", $phone, $text);
  wp_mail("$phone@txt.att.net", '', $text);
  wp_mail("$phone@tmomail.net", '', $text);
  wp_mail("$phone@vtext.com", '', $text);
  wp_mail("$phone@pm.sprint.com", '', $text);
  wp_mail("$phone@vmobl.com", '', $text);
  wp_mail("$phone@mmst5.tracfone.com", '', $text);
  wp_mail("$phone@mymetropcs.com", '', $text);
  wp_mail("$phone@myboostmobile.com", '', $text);
  wp_mail("$phone@mms.cricketwireless.net", '', $text);
  wp_mail("$phone@email.uscc.net", '', $text);
  wp_mail("$phone@cingularme.com", '', $text);
}

function shared_fields($user_id = null) {

    $user_id = $user_id ?: get_current_user_id();

    $pharmacy = [
      'type'  => 'select',
      'required' => true,
      'label' => __('<span class="erx">Name and address of a backup pharmacy to fill your prescriptions if we are out-of-stock</span><span class="pharmacy">Name and address of pharmacy from which we should transfer your medication(s)</span>'),
      'options' => ['' => __("Type to search. 'Walgreens Norcross' will show the one at '5296 Jimmy Carter Blvd, Norcross'")]
    ];
    //https://docs.woocommerce.com/wc-apidocs/source-function-woocommerce_form_field.html#1841-2061
    //Can't use get_default here because $POST check messes up the required property below.
    $pharmacy_meta = get_meta('backup_pharmacy', $user_id);

    if ($pharmacy_meta) {
      $store = json_decode($pharmacy_meta);
      $pharmacy['options'] = [$pharmacy_meta => $store->name.', '.$store->street.', '.$store->city.', GA '.$store->zip.' - Phone: '.$store->phone];
    }

    return [
    'backup_pharmacy' => $pharmacy,
    'medications_other' => [
        'label'     =>  __('List any other medication(s) or supplement(s) you are currently taking (We will not fill these but need to check for drug interactions)'),
        'default'   => get_default('medications_other', $user_id)
    ],
    'allergies_none' => [
        'type'   	  => 'radio',
        'label'     => __('Allergies'),
        'label_class' => ['radio'],
        'options'   => [99 => __('No Medication Allergies'), '' => __('Allergies Selected Below')],
    	  'default'   => get_default('allergies_none', $user_id)
    ],
    'allergies_aspirin' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Aspirin'),
        'default'   => get_default('allergies_aspirin', $user_id)
    ],
    'allergies_amoxicillin' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Amoxicillin'),
        'default'   => get_default('allergies_amoxicillin', $user_id)
    ],
    'allergies_ampicillin' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Ampicillin'),
        'default'   => get_default('allergies_ampicillin', $user_id)
    ],
    'allergies_azithromycin' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Azithromycin'),
        'default'   => get_default('allergies_azithromycin', $user_id)
    ],
    'allergies_cephalosporins' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Cephalosporins'),
        'default'   => get_default('allergies_cephalosporins', $user_id)
    ],
    'allergies_codeine' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Codeine'),
        'default'   => get_default('allergies_codeine', $user_id)
    ],
    'allergies_erythromycin' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Erythromycin'),
        'default'   => get_default('allergies_erythromycin', $user_id)
    ],
    'allergies_nsaids' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('NSAIDS e.g., ibuprofen, Advil'),
        'default'   => get_default('allergies_nsaids', $user_id)
    ],
    'allergies_penicillin' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Penicillin'),
        'default'   => get_default('allergies_penicillin', $user_id)
    ],
    'allergies_salicylates' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Salicylates'),
        'default'   => get_default('allergies_salicylates', $user_id)
    ],
    'allergies_sulfa' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Sulfa (Sulfonamide Antibiotics)'),
        'default'   => get_default('allergies_sulfa', $user_id)
    ],
    'allergies_tetracycline' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Tetracycline antibiotics'),
        'default'   => get_default('allergies_tetracycline', $user_id)
    ],
    'allergies_other' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     =>__('List Other Allergies Below').'<input class="input-text " name="allergies_other" id="allergies_other_input" value="'.get_default('allergies_other', $user_id).'">'
    ],
    'birth_date' => [
        'label'     => __('Date of Birth'),
        'required'  => true,
        'input_class' => ['date-picker'],
        'default'   => get_default('birth_date', $user_id)
    ],
    'phone' => [
      'label'     => __('Phone'),
      'required'  => true,
      'type'      => 'tel',
      'validate'  => ['phone'],
      'autocomplete' => 'tel',
      'default'   => get_default('phone', $user_id)
    ]
  ];
}

//Display custom fields on account/details
add_action('woocommerce_admin_order_data_after_order_details', 'dscsa_admin_edit_account');
function dscsa_admin_edit_account($order) {

  $medication = $order->get_meta('medication');

  $fields = order_fields($order->user_id, $medication)+shared_fields($order->user_id)+account_fields($order->user_id)+admin_fields($order->user_id);
  return dscsa_echo_form_fields($fields);
}

add_action('woocommerce_admin_order_data_after_order_details', 'dscsa_admin_invoice');
function dscsa_admin_invoice($order) {
  $invoice_doc_id  = $order->get_meta('invoice_doc_id', true);
  $tracking_number = $order->get_meta('tracking_number', true);
  $date_shipped = $order->get_meta('date_shipped', true);
  $address = $order->get_formatted_billing_address();

  if ($date_shipped AND $tracking_number) {
    echo "Your order was shipped on <mark class='order-date'>$date_shipped</mark> with tracking number <a target='_blank' href='https://tools.usps.com/go/TrackConfirmAction?tLabels=$tracking_number'>$tracking_number</a> to<br><br><address>$address</address>";
  } else {
    echo "Order will be shipped to<br><br><address>$address</address>";
  }

  if ($invoice_doc_id) {
    echo "<iframe src='https://docs.google.com/document/d/$invoice_doc_id/pub?embedded=true' style='border:none; padding:0px; overflow:hidden; width:100%; height:1800px;' scrolling='no'></iframe>";
  }
}

add_action( 'woocommerce_edit_account_form_start', 'dscsa_user_edit_account');
function dscsa_user_edit_account($user_id = null) {
  $fields = shared_fields($user_id)+account_fields($user_id);
  return dscsa_echo_form_fields($fields);
}

function dscsa_echo_form_fields($fields) {
  foreach ($fields as $key => $field) {
    echo woocommerce_form_field($key, $field);
  }
}

add_action('woocommerce_login_form_start', 'dscsa_login_form');
function dscsa_login_form() {
  login_form();
  $shared_fields = shared_fields();
  $shared_fields['birth_date']['id'] = 'birth_date_login';
  echo woocommerce_form_field('birth_date', $shared_fields['birth_date']);
}

add_action('woocommerce_register_form_start', 'dscsa_register_form');
function dscsa_register_form() {
  $account_fields = account_fields();
  $shared_fields = shared_fields();
  $shared_fields['birth_date']['id'] = 'birth_date_register';

  echo woocommerce_form_field('language', $account_fields['language']);
  login_form();
  echo woocommerce_form_field('birth_date', $shared_fields['birth_date']);
  echo woocommerce_form_field('phone', $shared_fields['phone']);
}

function login_form() {

  $first_name = [
    'type' => 'text',
    'class' => ['form-row-first'],
    'label'  => __('First name'),
    'required' => true,
    'default' => $_POST['first_name']
  ];

  $last_name = [
    'type' => 'text',
    'class' => ['form-row-last'],
    'label'  => __('Last name'),
    'required' => true,
    'default' => $_POST['last_name']
  ];

  echo woocommerce_form_field('first_name', $first_name);
  echo woocommerce_form_field('last_name', $last_name);
}

add_action('woocommerce_register_form', 'dscsa_register_form_acknowledgement');
function dscsa_register_form_acknowledgement() {
  echo '<div style="margin-bottom:8px">'.__('By clicking "Register" below, you agree to our <a href="/gp-terms">Terms of Use</a> and agree to receive and pay for your refills automatically unless you contact us to decline.').'</div>';
}

//Customer created hook called to late in order to create username
//    https://github.com/woocommerce/woocommerce/blob/e24ca9d3bce1f9e923fcd00e492208511cdea727/includes/class-wc-form-handler.php#L1002
add_action('wp_loaded', 'dscsa_default_post_value');
function dscsa_default_post_value() {

  if ($_POST['birth_date']) {
    $birth_date = date_create($_POST['birth_date']);

    if ($birth_date) {
      $birth_date = date_format($birth_date, 'Y-m-d'); //in case html type=date does not work (e.g. IE)

      $array = explode('-',$birth_date);

      if ($array[0] > date('Y'))
        $array[0] -= 100;

      if (checkdate($array[1],$array[2],$array[0])) {
        $_POST['birth_date'] = implode('-', $array);
        if ($_POST['first_name'] AND $_POST['last_name']) {    //Set user name for both login and registration
           //single quotes / apostrophes were being escaped with backslash on error
           $_POST['first_name'] = stripslashes($_POST['first_name']);
           $_POST['last_name'] = stripslashes($_POST['last_name']);
           $_POST['username'] = str_replace("'", "", "$_POST[first_name] $_POST[last_name] $_POST[birth_date]");
        }
      }
    }
  }

  //For resetting password
  $phone = $_POST['phone'] ?: $_POST['user_login'];

  if ($phone) {

     $phone = cleanPhone($phone);

     if ( ! $phone) return;

     $_POST['phone'] = $phone;

     if ($_POST['register'] AND ! $_POST['email'])
        $_POST['email'] = "$phone@goodpill.org";

     if ($_POST['save_account_details'] AND ! $_POST['account_email'])
       $_POST['account_email'] = "$phone@goodpill.org";

     if ($_POST['user_login']) //reset password if phone rather than email is supplied
        $_POST['user_login'] = "$phone@goodpill.org";
  }
}

function cleanPhone($phone) { //get rid of all delimiters and a leading 1 if it exists
  $phone = preg_replace('/\D+/', '', $phone);
  if (strlen($phone) == 11 AND substr($phone, 0, 1) == 1)
    return substr($phone, 1, 10);

  return strlen($phone) == 10 ? $phone : NULL;
}

add_filter('random_password', 'dscsa_random_password');
function dscsa_random_password() {
  return $_POST['phone'];
}

//After Registration, set default shipping/billing/account fields
//then save the user into GuardianRx
add_action('woocommerce_created_customer', 'customer_created');
function customer_created($user_id) {
  wp_mail('hello@goodpill.org', 'New Webform Patient', 'New Registration. Page 1 of 2');
  wp_mail('adam.kircher@gmail.com', 'New Webform Patient', print_r($_POST, true));

  $first_name = sanitize_text_field($_POST['first_name']);
  $last_name = sanitize_text_field($_POST['last_name']);
  $birth_date = sanitize_text_field($_POST['birth_date']);
  $language = sanitize_text_field($_POST['language']);
  $email = sanitize_text_field($_POST['email']);

  foreach(['', 'billing_', 'shipping_'] as $field) {
    update_user_meta($user_id, $field.'first_name', $first_name);
    update_user_meta($user_id, $field.'last_name', $last_name);
  }
  update_user_meta($user_id, 'birth_date', $birth_date);
  update_user_meta($user_id, 'language', $language);
  update_user_meta($user_id, 'email', $email);

  if ($_POST['phone']) {
    update_user_meta($user_id, 'phone', $_POST['phone']);
    update_user_meta($user_id, 'billing_phone', $_POST['phone']);
  }
}

// Function to change email address
add_filter('wp_mail_from', 'email_address');
function email_address() {
  return 'support@goodpill.org';
}
add_filter('wp_mail_from_name', 'email_name');
function email_name() {
  return 'Good Pill Pharmacy';
}

// After registration and login redirect user to account/orders.
// Clicking on Dashboard/New Order in Nave will add the actual product
add_action('woocommerce_registration_redirect', 'dscsa_registration_redirect', 2);
function dscsa_registration_redirect() {
  return home_url('/account/?add-to-cart=8#/');
}

add_action('woocommerce_login_redirect', 'dscsa_login_redirect', 2);
function dscsa_login_redirect() {
  return home_url('/account/orders/?add-to-cart=8#/');
}

add_filter ('wp_redirect', 'dscsa_wp_redirect');
function dscsa_wp_redirect($location) {

  //After successful order, add another item back into cart.
  //Add to card won't work unless we replace query params e.g., key=wc_order_594de1d38152e
  if (substr($_GET['key'], 0, 9) == 'wc_order_')
   return substr($location, 0, -26).'add-to-cart=8';

  //Hacky, but only way I could get add-to-cart not to be called twice in a row.
  if (substr($location, -14) == '?add-to-cart=8')
   return substr($location, 0, -14);

  return $location;
}

add_action( 'template_redirect', 'wc_bypass_logout_confirmation' );
function wc_bypass_logout_confirmation() {
    global $wp;

    if ( isset( $wp->query_vars['customer-logout'] ) ) {
        wp_redirect( str_replace( '&amp;', '&', wp_logout_url( wc_get_page_permalink( 'myaccount' ).'?gp-login' ) ) );
        exit;
    }
}

add_filter ('woocommerce_account_menu_items', 'dscsa_my_account_menu');
function dscsa_my_account_menu($nav) {
  $nav['dashboard'] = __(substr($_SERVER['REQUEST_URI'], -23) == '/account/?add-to-cart=8' ? 'Registration' : 'New Order');
  return $nav;
}

add_action('woocommerce_save_account_details_errors', 'dscsa_account_validation');
function dscsa_account_validation() {
   dscsa_validation(shared_fields()+account_fields());
}
add_action('woocommerce_checkout_process', 'dscsa_order_validation');
function dscsa_order_validation() {
   dscsa_validation(order_fields()+shared_fields());
}

function dscsa_validation($fields) {
  $allergy_missing = true;
  foreach ($fields as $key => $field) {
    //if ($field['required'] AND ! $_POST[$key]) {
    //   wc_add_notice('<strong>'.__($field['label']).'</strong> '.__('is a required field'), 'error');
    //}

    if (substr($key, 0, 10) == 'allergies_' AND $_POST[$key])
 	  $allergy_missing = false;
  }

  if ($allergy_missing) {
    wc_add_notice('<strong>'.__('Allergies').'</strong> '.__('is a required field'), 'error');
  }
}

// replace woocommerce id with guardian one
add_filter( 'woocommerce_order_number', 'dscsa_invoice_number', 10 , 2);
function dscsa_invoice_number($order_id, $order) {
  return get_post_meta($order_id, 'invoice_number', true) ?: 'Pending-'.$order_id;
}

add_filter( 'woocommerce_shop_order_search_fields', 'dscsa_order_search_fields');
function dscsa_order_search_fields( $search_fields ) {
	array_push( $search_fields, 'invoice_number' );
    //array_push( $search_fields, 'guardian_id' ); // Doesn't seem to work
	return $search_fields;
}

//On new order and account/details save account fields back to user
//TODO should changing checkout fields overwrite account fields if they are set?
add_action('woocommerce_save_account_details', 'dscsa_save_account');
function dscsa_save_account($user_id) {
  $sanitized = $_POST;
  unset($sanitized['password_current'], $sanitized['password_1'], $sanitized['password_2']);
  wp_mail('adam.kircher@gmail.com', "dscsa_save_account_details", print_r($sanitized, true));
  $patient_id = dscsa_save_patient($user_id, shared_fields($user_id) + account_fields($user_id));
  update_email($patient_id, sanitize_text_field($_POST['account_email']));
}

//Update an order with trackin g number and date_shipped
global $already_run;
add_filter('rest_request_parameter_order', 'dscsa_rest_update_order', 10, 2);
function dscsa_rest_update_order($order, $request) {

  global $already_run;

  if ( ! $already_run AND $request->get_method() == 'PUT' AND substr($request->get_route(), 0, 14) == '/wc/v2/orders/') {
      $already_run = true;

      //wp_mail('adam.kircher@gmail.com', 'dscsa_rest_update_order', print_r($order, true));
    try {
      //TODO incorrectly assuming that invoice number is always 1st (only) value in metadata
      $orders = get_orders_by_invoice_number($request['id']);

      //Sometimes Guardian order id changes so "get_orders_by_invoice_number" won't work
  	  if (count($orders) < 1) {
        $meta_data = $request->get_json_params()['meta_data'];
        foreach ($meta_data as $val) {
          if ($val['key'] == 'guardian_id') {
            //wp_mail('adam.kircher@gmail.com', "guardian_id", $val['value']);
            $users = get_users_by_guardian_id($val['value']);
            //wp_mail('adam.kircher@gmail.com', "users", print_r($users, true));
            $orders = get_pending_orders_by_user_id($users[0]->user_id);
            //wp_mail('adam.kircher@gmail.com', "users", print_r($orders, true));
          }
        }
      }

      $count = count($orders);

      if ($count < 1) {
        wp_mail('adam.kircher@gmail.com', "dscsa_rest_update_order: no orders", $request['id'].' | /wc/v2/orders/'+$orders[0]->post_id.' '.print_r($orders, true).' '.print_r($request['body'], true).' '.print_r($request, true));
    	  return new WP_Error('could not locate order by invoice number', __( "Order #$invoice_number has $count matches", 'woocommerce' ), 200);
      }

      if ($count > 1) {
        wp_mail('adam.kircher@gmail.com', "dscsa_rest_update_order: multiple orders", $request['id'].' | /wc/v2/orders/'+$orders[0]->post_id.' '.print_r($orders, true).' '.print_r($request, true));
      }

      $request['id'] = $orders[0]->post_id;

    } catch (Exception $e) {
      wp_mail('adam.kircher@gmail.com', "dscsa_rest_update_order: error", print_r($e, true).' | '.$request['id'].' | /wc/v2/orders/'+$orders[0]->post_id.' '.print_r($orders, true).' '.print_r($request, true));
    }
    //wp_mail('adam.kircher@gmail.com', "dscsa_rest_update_order", $request['id'].' | /wc/v2/orders/'+$orders[0]->post_id.' '.print_r($orders, true).' '.print_r($request, true));
  }

  return $order;
}

//Create an order for a guardian refill
add_filter('woocommerce_rest_pre_insert_shop_order_object', 'dscsa_rest_create_order', 10, 3);
function dscsa_rest_create_order($order, $request, $creating) {

  if ( ! $creating) return $order;

  //wp_mail('adam.kircher@gmail.com', 'dscsa_rest_create_order', print_r($order, true));
  //wp_mail('adam.kircher@gmail.com', "dscsa_rest_create_order", print_r($creating, true));

  $invoice_number = $order->get_meta('invoice_number', true);
  $guardian_id = $order->get_meta('guardian_id', true);

  $orders = get_orders_by_invoice_number($invoice_number);

  if (count($orders))
    return new WP_Error('refill_order_already_exists', __( "Refill Order #$invoice_number already exists", 'woocommerce' ), 200);

  $users = get_users_by_guardian_id($guardian_id);

  if ( ! count($users))
    return new WP_Error( 'could_not_find_user_by_guardian_id', __( "Could not find the user for Guardian Patient Id #$guardian_id", 'woocommerce' ), 400);

  $order->set_customer_id($users[0]->user_id);

  return $order;
}

function get_users_by_guardian_id($guardian_id) {
  global $wpdb;
  return $wpdb->get_results("SELECT user_id FROM wp_usermeta WHERE (meta_key='guardian_id') AND meta_value = '$guardian_id'");
}

function get_orders_by_invoice_number($invoice_number) {
  global $wpdb;
  return $wpdb->get_results("SELECT post_id FROM wp_postmeta WHERE (meta_key='invoice_number') AND meta_value = '$invoice_number' ORDER BY post_id DESC");
}

//Sometimes Guardian order id changes so "get_orders_by_invoice_number" won't work
function get_pending_orders_by_user_id($user_id) {
  global $wpdb;
  return $wpdb->get_results("SELECT post_id FROM wp_postmeta JOIN wp_posts ON wp_posts.id = post_id WHERE (meta_key='_customer_user') AND meta_value = '$user_id' AND (post_status = 'wc-pending' OR post_status = 'wc-on-hold') ORDER BY post_id DESC");
}

add_action('woocommerce_order_details_after_order_table', 'dscsa_show_order_invoice');
function dscsa_show_order_invoice($order) {
    $invoice_doc_id  = $order->get_meta('invoice_doc_id', true);
    $tracking_number = $order->get_meta('tracking_number', true);
    $date_shipped = $order->get_meta('date_shipped', true);
    $address = $order->get_formatted_billing_address();

    echo '<style>.woocommerce-customer-details, .woocommerce-order-details__title, .woocommerce-table--order-details { display:none }</style>';

    if ($date_shipped AND $tracking_number) {
       echo "Your order was shipped on <mark class='order-date'>$date_shipped</mark> with tracking number <a target='_blank' href='https://tools.usps.com/go/TrackConfirmAction?tLabels=$tracking_number'>$tracking_number</a> to<br><br><address>$address</address>";
    } else {
       echo "Order will be shipped to<br><br><address>$address</address>";
    }

    if ($invoice_doc_id) {
      $url  = "https://docs.google.com/document/d/$invoice_doc_id/pub?embedded=true";
      $top  = '-65px';
      $left = '-60px';
    } else {
      $url = "https://www.goodpill.org/order-confirmation";
      $top = '0px';
      $left = '-40px';
    }

    echo "<iframe src='$url' style='border:none; padding:0px; overflow:hidden; width:100%; height:1800px; position:relative; z-index:-1; left:$left; top:$top' scrolling='no'></iframe>";
}

//woocommerce_checkout_update_order_meta
global $alreadySaved;
add_action('woocommerce_before_order_object_save', 'dscsa_before_order_object_save', 10, 2);
function dscsa_before_order_object_save($order, $data) {

  try {
    global $alreadySaved;

    if ($alreadySaved OR ! $_POST) return; //$_POST is not set on duplicate order

    $alreadySaved = true;

    //$order = wc_get_order( $order_id );
    $user_id = $order->get_user_id();

    //THIS MUST BE CALLED FIRST IN ORDER TO CREATE GUARDIAN ID
    //TODO should save if they don't exist, but what if they do, should we be overriding?
    $patient_id = dscsa_save_patient($user_id, shared_fields($user_id) + order_fields($user_id) + ['order_comments' => true]);

    //$invoice_number = $order->get_meta('invoice_number', true) ?: get_invoice_number($patient_id);

    $invoice_number = $order->get_meta('invoice_number', true);

    if ( ! $invoice_number) {
      $guardian_order = get_guardian_order($patient_id);
      $invoice_number = $guardian_order['invoice_nbr'];

      //HACK for now is to save to POST.  Every status change in this method seemed to get overriden
      //Tried $order->status = $status, $data->status = $status, $order->set_status($status), $order->update_status($status)
      $_POST['order_rxs'] = $guardian_order['rxs'];
    }


    if ( ! $invoice_number)
      wp_mail('adam.kircher@gmail.com', "NO INVOICE #", "Patient ID: $patient_id\r\n\r\nInvoice #:$invoice_number \r\n\r\nMSSQL:".print_r(mssql_get_last_message(), true)."\r\n\r\nOrder Meta Invoice #:".$order->get_meta('invoice_number', true)."\r\n\r\nPOST:".print_r($_POST, true));

    if ( ! is_admin()) {
      wp_mail('hello@goodpill.org', 'New Webform Order', "New Order #$invoice_number Webform Complete. Source: ".print_r($_POST['rx_source'], true)."\r\n\r\n".print_r($_POST['medication'], true));
      wp_mail('adam.kircher@gmail.com', "New Webform Order", "New Order #$invoice_number.  Patient #$patient_id\r\n\r\n".print_r($_POST, true));
    }

    $order->update_meta_data('invoice_number', $invoice_number);

    update_email($patient_id, sanitize_text_field($_POST['email']));

    $coupon = $order->get_used_coupons()[0];

    if (  ! $coupon) {
      $stored_coupon = get_meta('coupon', $user_id);
      if ($stored_coupon != 'ckim') $coupon = $stored_coupon; //persist all coupons except ckim
    }

    $card = get_meta('stripe', $user_id);

    update_user_meta($user_id, 'coupon', $coupon);
    update_card_and_coupon($patient_id, $card, $coupon);

    //$prefix = $_POST['ship_to_different_address'] ? 'shipping_' : 'billing_';

    //TODO this should be called on the edit address page as well
    //Underscore is for saving on the admin page, no underscore is for the customer checkout
    $address = update_shipping_address(
      $patient_id,
      $_POST['_billing_address_1'] ?: $_POST['billing_address_1'],
      $_POST['_billing_address_2'] ?: $_POST['billing_address_2'],
      $_POST['_billing_city']      ?: $_POST['billing_city'],
      $_POST['_billing_postcode']  ?: $_POST['billing_postcode']
    );

    //wp_mail('adam.kircher@gmail.com', "saved order 1", "$patient_id | $invoice_number ".print_r($_POST, true).print_r(mssql_get_last_message(), true));

    if ($_POST['medication']) {
      foreach ($_POST['medication'] as $drug_name) {
        if ($drug_name)
          add_preorder($patient_id, $drug_name, $_POST['backup_pharmacy']);
      }
      $order->update_meta_data('medication', $_POST['medication']);
    }
  } catch (Exception $e) {
    wp_mail('adam.kircher@gmail.com', "woocommerce_before_order_object_save", "$patient_id | $invoice_number ".$e->getMessage()." ".print_r($_POST, true).print_r(mssql_get_last_message(), true));
  }
}

add_action('woocommerce_customer_save_address', 'dscsa_customer_save_address', 10, 2);
function dscsa_customer_save_address($user_id, $load_address) {
  update_shipping_address(
    get_meta('guardian_id', $user_id),
    $_POST['billing_address_1'],
    $_POST['billing_address_2'],
    $_POST['billing_city'],
    $_POST['billing_postcode']
  );
}

//TODO implement this funciton
function get_field($key) {
   $val = $order->get_meta($key, true);
   if ( ! $val) {
     $val = get_from_guardian($key);
     $order->update_meta_data($key, $val);
   }
   return $val;
}

//TODO implement this funciton
function set_field($key, $newVal) {
   $oldVal = $order->get_meta($key, true);
   if ($newValue != $oldVal) {
     save_to_guardian($key, $newVal);
   }
   return $newVal;
}

function dscsa_save_patient($user_id, $fields) {

  if ($_POST['guardian_id']) {
    $patient_id = sanitize_text_field($_POST['guardian_id']);
    update_user_meta($user_id, 'guardian_id', $patient_id);
  } else if ( ! is_admin()) { //allow admin to delete guardian id (if incorrect) and have it reload
    $patient_id = get_meta('guardian_id', $user_id);
  }

  if ( ! $patient_id) {
    $patient_id = add_patient(
      //checkout, account details, admin page
      $_POST['billing_first_name'] ?: $_POST['account_first_name'] ?: $_POST['_billing_first_name'],
      $_POST['billing_last_name'] ?: $_POST['account_last_name'] ?: $_POST['_billing_last_name'],
      $_POST['birth_date'],
      $_POST['phone'],
      get_meta('language', $user_id)
    );

    update_user_meta($user_id, 'guardian_id', $patient_id);

    //wp_mail('adam.kircher@gmail.com', "new patient", $patient_id.' '.print_r($_POST, true).print_r(mssql_get_last_message(), true));
  } else {
    update_phone($patient_id, $_POST['phone']);
  }

  $allergy_codes = [
    'allergies_tetracycline' => 1,
    'allergies_cephalosporins' => 2,
    'allergies_sulfa' => 3,
    'allergies_aspirin' => 4,
    'allergies_penicillin' => 5,
    'allergies_ampicillin' => 6,
    'allergies_erythromycin' => 7,
    'allergies_codeine' => 8,
    'allergies_nsaids' => 9,
    'allergies_salicylates' => 10,
    'allergies_azithromycin' => 11,
    'allergies_amoxicillin' => 12,
    'allergies_none' => 99,
    'allergies_other' => 100
  ];

  //TODO should save if they don't exist, but what if they do, should we be overriding?
  foreach ($fields as $key => $field) {

    //In case of backup pharmacy json, sanitize gets rid of it
    $val = sanitize_text_field($_POST[$key]);

    if ($key == 'backup_pharmacy') {
      //wp_mail('adam.kircher@gmail.com', "backup_pharmacy",
      update_pharmacy($patient_id, $val);
    }

    if ($key == 'medications_other') {
      //wp_mail('adam.kircher@gmail.com', "medications_other",
      append_comment($patient_id, $val);
    }

    if ($key == 'order_comments') {
      //wp_mail('adam.kircher@gmail.com', "order_comments",
      append_comment($patient_id, " $val");
    }

    if ($key == 'phone') {
      //wp_mail('adam.kircher@gmail.com', "phone",
      update_user_meta($user_id, 'billing_phone', $val); //this saves it on the user page as well
    }

    update_user_meta($user_id, $key, $val);

    if ($allergy_codes[$key]) {
      //Since all checkboxes submitted even with none selected.  If none
      //is selected manually set value to false for all except none
      $val = ($_POST['allergies_none'] AND $key != 'allergies_none') ? NULL : str_replace("'", "''", $val);
      //wp_mail('adam.kircher@gmail.com', 'save allergies', "$key $allergy_codes[$key] $val");
      add_remove_allergy($patient_id, $allergy_codes[$key], $val);
      //wp_mail('adam.kircher@gmail.com', "patient saved",
    }
  }

  //wp_mail('adam.kircher@gmail.com', "patient saved", $patient_id.' '.print_r($_POST, true));

  return $patient_id;
}

add_filter( 'woocommerce_email_headers', 'dscsa_email_headers', 10, 2);
function dscsa_email_headers( $headers, $template) {
		return array($headers, "Bcc:hello@goodpill.org\r\n");
}

//Tried woocommerce_status_changed, woocommerce_status_on-hold, woocommerce_thankyou and setting it before_order_object_save and nothing else worked
add_filter('wp_insert_post_data', 'dscsa_update_order_status');
function dscsa_update_order_status( $data) {

    //wp_mail('adam.kircher@gmail.com', "dscsa_update_order_status", is_admin()." | ".strlen($_POST['order_rxs'])." | ".(!!$_POST['order_rxs'])." | ".var_export($_POST['order_rxs'], true)." | ".print_r($_POST, true)." | ".print_r($data, true));

    if (is_admin() OR $data['post_type'] != 'shop_order') return $data;

    if ($_POST['order_rxs']) { //Skip on-hold and go straight to processing if set
      $data['post_status'] = 'wc-processing';
    } else if($_POST['rx_source']) { //checking for rx_source ensures that API calls to update status still work.  Even though we are not "capturing charge" setting "needs payment" seems to make the status goto processing
      $data['post_status'] = $_POST['medication'] ? 'wc-awaiting-transfer' : 'wc-awaiting-rx';
    }

    return $data;
}

//On hold emails only triggered in certain circumstances, so we need to trigger them manually
//https://github.com/woocommerce/woocommerce/blob/f8552ebbad227293c7b819bc4b06cbb6deb2c725/includes/emails/class-wc-email-customer-on-hold-order.php#L39
add_action('woocommerce_new_order', 'dscsa_new_order');
function dscsa_new_order($order_id) {

  if ( ! $_POST['rx_source']) return //not triggered by API calls, only form submissions

  $order  = wc_get_order($order_id);
  $status = $order->get_status();
  $type   = $_POST['order_rxs'] ? 'Processing_Order' : 'On_Hold_Order';

  // Select the email we want & trigger it to send
  WC()->mailer()->get_emails()["WC_Email_Customer_$type"]->trigger($order_id, $order);
}

add_filter( 'wc_order_statuses', 'dscsa_renaming_order_status' );
function dscsa_renaming_order_status( $order_statuses ) {
    $order_statuses['wc-processing'] = _x('Received Rx. Preparing to fill', 'Order status', 'woocommerce');
    return $order_statuses;
}

add_filter( 'wc_order_is_editable', 'dscsa_order_is_editable', 10, 2);
function dscsa_order_is_editable($editable, $order) {

  if ($editable) return true;

  return in_array($order->get_status(), array('processing', 'awaiting-rx', 'awaiting-transfer', 'shipped-unpaid', 'shipped-autopay', 'shipped-coupon'), true);
}

add_filter('woocommerce_order_is_paid_statuses', 'dscsa_order_is_paid_statuses');
function dscsa_order_is_paid_statuses($paid_statuses) {
  return array('completed', 'shipped-paid');
}

add_filter( 'woocommerce_order_button_text', 'dscsa_order_button_text');
function dscsa_order_button_text() {
    return substr($_SERVER['HTTP_REFERER'], -14) == '?add-to-cart=8' ? 'Complete Registration' : 'Place order';
}

//Didn't work: https://stackoverflow.com/questions/38395784/woocommerce-overriding-billing-state-and-post-code-on-existing-checkout-fields
//Did work: https://stackoverflow.com/questions/36619793/cant-change-postcode-zip-field-label-in-woocommerce
global $lang;
global $phone;
add_filter('ngettext', 'dscsa_translate', 10, 3);
add_filter('gettext', 'dscsa_translate', 10, 3);
function dscsa_translate($term, $raw, $domain) {

  global $phone;

  $phone = $phone ?: get_default('phone');

  $toEnglish = [
    "An account is already registered with that username. Please choose another." => 'Looks like you have already registered. Goto the <a href="/account/?gp-login">Login page</a> and use your 10 digit phone number as your default password e.g. the phone number (123) 456-7890 would have a default password of 1234567890.',
    "<span class='english'>Pay by Credit or Debit Card</span><span class='spanish'>Pago con tarjeta de crédito o débito</span>" => "Pay by Credit or Debit Card",
    'Spanish'  => 'Espanol', //Registering
    'Email:' => 'Email', //order details
    'Email address' => 'Email', //accounts/details
    'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">shipping and billing addresses</a> and <a href="%3$s">edit your password and account details</a>.' => '',
    'ZIP' => 'Zip code', //Checkout
    'Your order' => '', //Checkout
    'Shipping:' => 'New Patient Fee:',
    'Free shipping coupon' => 'Paid with Coupon',
    'Free shipping' => 'Paid with Coupon', //not working (order details page)
    'No saved methods found.' => 'No credit or debit cards are saved to your account',
    '%s has been added to your cart.' => strtok($_SERVER["REQUEST_URI"],'?') == '/account/'
      ? 'Step 2 of 2: You are almost done! Please complete this "Registration" page so we can fill your prescription(s).  If you need to login again, your temporary password is '.$phone.'.  You can change your password on the "Account Details" page'
      : 'Thank you for your order!',
    'Username or email' => '<strong>Email or phone number</strong>', //For resetting passwords
    'Password reset email has been sent.' => "Before you reset your password by following the instructions below, first try logging in with your 10 digit phone number as your default password",
    'A password reset email has been sent to the email address on file for your account, but may take several minutes to show up in your inbox. Please wait at least 10 minutes before attempting another reset.' => 'If you provided an email address or mobile phone number during registration, then an email and/or text message with instructions on how to reset your password was sent to you.  If you do not get an email or text message from us within 5mins, please call us at <span style="white-space:nowrap">(888) 987-5187</span> for assistance',
    'Additional information' => '',  //Checkout
    'Billing address' => 'Shipping address', //Order confirmation
	  'Billing &amp; Shipping' => 'Shipping Address', //Checkout
    'Lost your password? Please enter your username or email address. You will receive a link to create a new password via email.' => '<h2>Lost your password?</h2>Before you reset your password, first try logging in with your 10 digit phone number without any extra characters as your password.  For example, use the password 1234567890 for the phone number <span style="white-space:nowrap">(123) 456-7890</span>.<br><br>If using your phone number as your password did not work, enter your email or phone number below to receive an email with instructions on how to reset your password. Please note that this option will only work if we have your email on file.<br><br>If you did not provide an email when registering for your account, please call us at <span style="white-space:nowrap">(888) 987-5187</span> for assistance.', //Logging in
    'Please provide a valid email address.' => 'Please provide a valid 10-digit phone number.',
    'Please enter a valid account username.' => 'Please enter your name and date of birth in mm/dd/yyyy format.',
    'Please enter an account password.' => 'Please provide a valid 10-digit phone number.',
    'Username is required.' => 'Name and date of birth in mm/dd/yyyy format are required.',
    'Invalid username or email.' => '<strong>Error</strong>: We cannot find an account with that phone number.',
    '<strong>ERROR</strong>: Invalid username.' => '<strong>Error</strong>: We cannot find an account with that name and date of birth.',
    'An account is already registered with your email address. Please log in.' => 'An account is already registered with your phone number. Please log in.',
    'Your order is on-hold until we confirm payment has been received. Your order details are shown below for your reference:' => $_POST['medication'] ? 'We are currently requesting a transfer of your Rx(s) from your pharmacy' : 'We are currently waiting on Rx(s) to be sent from your doctor',
    'Your order has been received and is now being processed. Your order details are shown below for your reference:' => 'We got your prescription(s) and will start working on them right away',
    'Your password has been automatically generated:' => 'Your temporary password is your phone number:'
  ];

  $toSpanish = [
    'Language' => 'Idioma',
    'Use a new credit card' => 'Use una tarjeta de crédito nueva',
    'Place New Order' => 'Haga un pedido nuevo',
    'Place order' => 'Haga un pedido',
    'Billing details' => 'Detalles de facturas',
    'Ship to a different address?' => '¿Desea envíos a una dirección diferente?',
    'Search and select medications by generic name that you want to transfer to Good Pill' => 'Busque y seleccione los medicamentos por nombre genérico que usted desea transferir a Good Pill',
    '<span class="erx">Name and address of a backup pharmacy to fill your prescriptions if we are out-of-stock</span><span class="pharmacy">Name and address of pharmacy from which we should transfer your medication(s)</span>' => '<span class="erx">Nombre y dirección de una farmacia de respaldo para surtir sus recetas si no tenemos los medicamentos en existencia</span><span class="pharmacy">Nombre & dirección de la farmacia de la que debemos transferir sus medicamentos.</span>',
    'Allergies' => 'Alergias',
    'Allergies Selected Below' => 'Alergias seleccionadas abajo',
    'No Medication Allergies' => 'No hay alergias a medicamentos',
    'Aspirin' => 'Aspirina',
    'Erythromycin' => 'Eritromicina',
    'NSAIDS e.g., ibuprofen, Advil' => 'NSAIDS; por ejemplo, ibuprofeno, Advil',
    'Penicillin' => 'Penicilina',
    'Ampicillin' => 'Ampicilina',
    'Sulfa (Sulfonamide Antibiotics)' => 'Sulfamida (antibióticos de sulfonamidas)',
    'Tetracycline antibiotics' => 'Antibióticos de tetraciclina',
    'List Other Allergies Below' => 'Indique otras alergias abajo',
    'Phone' => 'Teléfono',
    'List any other medication(s) or supplement(s) you are currently taking (We will not fill these but need to check for drug interactions)' => 'Indique cualquier otro medicamento o suplemento que usted toma actualmente',
    'First name' => 'Nombre',
    'Last name' => 'Apellido',
    'Date of Birth' => 'Fecha de nacimiento',
    'Address' => 'Dirección',
    'Addresses' => 'Direcciónes',
    'State' => 'Estado',
    'Zip code' => 'Código postal',
    'Town / City' => 'Poblado / Ciudad',
    'Password change' => 'Cambio de contraseña',
    'Current password (leave blank to leave unchanged)' => 'Contraseña actual (deje en blanco si no hay cambios)',
    'New password (leave blank to leave unchanged)' => 'Contraseña nueva (deje en blanco si no hay cambios)',
    'Confirm new password' => 'Confirmar contraseña nueva',
    'Have a coupon?' => '¿Tiene un cupón?',
    'Click here to enter your code' => 'Haga clic aquí para ingresar su código',
    'Coupon code' => 'Cupón',
    'Apply Coupon' => 'Haga un Cupón',
    '[Remove]' => '[Remover]',
    'Card number' => 'Número de tarjeta',
    'Expiry (MM/YY)' => 'Fecha de expiración (MM/AA)',
    'Card code' => 'Código de tarjeta',
    'New Order' => 'Pedido Nuevo',
    'Orders' => 'Pedidos',
    'Shipping Address' => 'Dirección de Envíos',

    //Need to be translated
    // Can't translate on login page because we don't know user's language (though we could make dynamic like registration page)
    //<div class="english">Register (Step 1 of 2)</div><div class="spanish">Registro (Uno de Dos)</div>

    'Phone number' => 'Teléfono',
    'Email' => 'Correo electrónico',
    'Rx(s) were sent from my doctor' => 'La/s receta/s fueron enviadas de parte de mi médico',
    'Transfer Rx(s) with refills remaining from my pharmacy' => 'Transferir la/s receta/s desde mi farmacia',
    'House number and street name' => 'Dirección de envío',
    'Apartment, suite, unit etc. (optional)' => 'Apartamento, suite, unidad, etc. (opcional)',
    'Payment methods' => 'Métodos de pago',
    'Account details' => 'Detalles de la cuenta',
    'Logout' => 'Cierre de sesión',
    'No order has been made yet.' => 'No se ha hecho aún ningún pedido',
    'The following addresses will be used on the checkout page by default.' => 'Se utilizarán de forma estándar las siguientes direcciones en la página de pago.',
    'Billing address' => 'Dirección de facturación',
    'Shipping address' => 'Dirección de envío',
    'Save address' => 'Guardar la dirección',
    'No credit or debit cards are saved to your account' => 'Las tarjetas de crédito o débito no se guardan en su cuenta',
    'Add payment method' => 'Agregar método de pago',
    'Save changes' => 'Guardar los cambios',
    'is a required field' => 'es una información requerida',
    'Order #%1$s was placed on %2$s and is currently %3$s.' => 'La orden %1$s se ordenó en %2$s y actualmente está %3$s.',
    'Payment method:' => 'Método de pago:',
    'Order details' => 'Detalles de la orden',
    'Customer details' => 'Detalles del cliente',
    'Amoxicillin' => 'Amoxicilina',
    'Azithromycin' => 'Azitromicina',
    'Cephalosporins' => 'Cefalosporinas',
    'Codeine' => 'Codeína',
    'Salicylates' => 'Salicilatos',
    'Thank you for your order! Your prescription(s) should arrive within 3-5 days.' => '¡Gracias por su orden! Sus medicamentos llegarán dentro de 3-5 días.',
    'Please choose a pharmacy' => 'Por favor, elija una farmacia',
    'By clicking "Register" below, you agree to our <a href="/gp-terms">Terms of Use</a> and agree to receive and pay for your refills automatically unless you contact us to decline.' => 'Al hacer clic en "Register" a continuación, usted acepta los <a href="/gp-terms">Términos de Uso</a> y acepta recibir y pagar por sus rellenos automáticamente, a menos que usted se ponga en contacto con nosotros para descontinuarlo.',

    'Coupon' => 'Cupón', //not working (checkout applied coupon)
    'Edit' => 'Cambio',
    'Apply coupon' => 'Agregar cupón',
    'Step 2 of 2: You are almost done! Please complete this page so we can fill your prescription(s).  If you need to login again, your temporary password is '.$phone.'.  You can change your password on the "Account Details" page' => 'Paso 2 de 2: ¡Casi has terminado! Por favor complete esta página para poder llenar su (s) receta (s). Si necesita volver a iniciar sesión, su contraseña temporal es '.$phone.'. Puede cambiar su contraseña en la página "Detalles de la cuenta"',
    'Pay by Credit or Debit Card' => 'Pago con tarjeta de crédito o débito',
    'New Patient Fee:' => 'Cuota de persona nueva:',
    'Paid with Coupon' => 'Pagada con cupón',
  ];

  $english = isset($toEnglish[$term]) ? $toEnglish[$term] : $term;
  $spanish = $toSpanish[$english];

  if (is_admin() OR ! isset($spanish))
    return $english;

  global $lang;

  $lang = $lang ?: (get_meta('language') ?: '<language>');

  if ($lang == 'EN')
    return $english;

  if ($lang == 'ES')
    return $spanish;

  //This allows client side translating based on jQuery listening to radio buttons
  if (isset($_GET['register']))
    return  "<span class='english'>$english</span><span class='spanish'>$spanish</span>";

  return $english;
}


add_filter( 'esc_html', 'dscsa_esc_html', 10, 2);
function dscsa_esc_html($safe_text, $text) {

    $english = "/&lt;span class=.*?english.*?&gt;(.*?)&lt;\/?span&gt;/";
    $spanish = "/&lt;span class=.*?spanish.*?&gt;(.*?)&lt;\/?span&gt;/";

    return preg_replace([$english, $spanish], ['$1', ''], $safe_text);
}

add_filter('woocommerce_email_order_items_table', 'dscsa_email_items_table');
function dscsa_email_items_table($items_table) {
  return '';
}

add_action('woocommerce_email_header', 'dscsa_add_css_to_email');
function dscsa_add_css_to_email() {
  echo '<style type="text/css">thead, tbody, tfoot { display:none }</style>';
}

add_filter('woocommerce_cart_needs_payment', 'dscsa_show_payment_options');
function dscsa_show_payment_options($show_payment_options) {
  return empty(WC()->cart->applied_coupons);
}

// Hook in
add_filter( 'woocommerce_checkout_fields' , 'dscsa_checkout_fields' );
function dscsa_checkout_fields( $fields ) {

  $shared_fields = shared_fields();

  //Add some order fields that are not in patient profile
  $order_fields = order_fields();

  //wp_mail('adam.kircher@gmail.com', "db error: $heading", print_r($fields['order']['order_comments'], true).' '.print_r($fields['order'], true));
  $fields['order'] = $order_fields + $shared_fields + ['order_comments' => $fields['order']['order_comments']];

  //Allow billing out of state but don't allow shipping out of state
  $fields['shipping']['shipping_state']['type'] = 'select';
  $fields['shipping']['shipping_state']['options'] = ['GA' => 'Georgia'];
  unset($fields['shipping']['shipping_country']);
  unset($fields['shipping']['shipping_company']);

  // We are using our billing address as the shipping address for now.
  $fields['billing']['billing_state']['type'] = 'select';
  $fields['billing']['billing_state']['options'] = ['GA' => 'Georgia'];
  $fields['billing']['billing_first_name']['label'] = 'Patient First Name';
  $fields['billing']['billing_last_name']['label'] = 'Patient Last Name';

  //Remove Some Fields
  unset($fields['billing']['billing_first_name']['autofocus']);
  unset($fields['billing']['shipping_first_name']['autofocus']);
  unset($fields['billing']['billing_phone']);
  unset($fields['billing']['billing_email']);
  unset($fields['billing']['billing_company']);
  unset($fields['billing']['billing_country']);

  return $fields;
}

//This is for the address details page
add_filter( 'woocommerce_billing_fields', 'dscsa_billing_fields');
function dscsa_billing_fields( $fields ) {
  unset($fields['billing_company']);
  unset($fields['billing_country']);
  return $fields;
}

function get_invoice_number($guardian_id) {
  $result = db_run("SirumWeb_AddFindInvoiceNbrByPatID '$guardian_id'");
  wp_mail('adam.kircher@gmail.com', "get_invoice_number", $guardian_id.print_r($result, true));
  return $result['invoice_nbr'];
}

function get_guardian_order($guardian_id) {
  $result = db_run("SirumWeb_AddFindOrder '$guardian_id'");
  wp_mail('adam.kircher@gmail.com', "get_guardian_order", $guardian_id.print_r($result, true));
  return $result;
}


// SirumWeb_AddRemove_Allergy(
//   @PatID int,     --Carepoint Patient ID number
//   @AddRem int = 1,-- 1=Add 0= Remove
//   @AlrNumber int,  -- From list
//   @OtherDescr varchar(80) = '' -- Description for "Other"
// )
/*
    Allergies supported
  if      @AlrNumber = 1  -- TETRACYCLINE
  else if @AlrNumber = 2  -- Cephalosporins
  else if @AlrNumber = 3  -- Sulfa (Sulfonamide Antibiotics)
  else if @AlrNumber = 4  -- Aspirin
  else if @AlrNumber = 5  -- Penicillins
  else if @AlrNumber = 6  -- Ampicillin
  else if @AlrNumber = 7  -- Erythromycin Base
  else if @AlrNumber = 8  -- Codeine
  else if @AlrNumber = 9  -- NSAIDS e.g., ibuprofen, Advil
  else if @AlrNumber = 10  -- Salicylates
  else if @AlrNumber = 11  -- azithromycin,
  else if @AlrNumber = 12  -- amoxicillin,
  else if @AlrNumber = 99  -- none
  else if @AlrNumber = 100 -- other
*/
function add_remove_allergy($guardian_id, $allergy_id, $value) {
  $isValue = $value ? 1 : 0;
  return db_run("SirumWeb_AddRemove_Allergy '$guardian_id', '$isValue', '$allergy_id', '$value'");
}

// SirumWeb_AddUpdateHomePhone(
//   @PatID int,  -- ID of Patient
//   @PatCellPhone VARCHAR(20)
function update_phone($guardian_id, $cell_phone) {
  return db_run("SirumWeb_AddUpdatePatHomePhone '$guardian_id', '$cell_phone'");
}

// dbo.SirumWeb_AddUpdatePatShipAddr(
//  @PatID int
// ,@Addr1 varchar(50)    -- Address Line 1
// ,@Addr2 varchar(50)    -- Address Line 2
// ,@Addr3 varchar(50)    -- Address Line 3
// ,@City varchar(20)     -- City Name
// ,@State varchar(2)     -- State Name
// ,@Zip varchar(10)      -- Zip Code
// ,@Country varchar(3)   -- Country Code
function update_shipping_address($guardian_id, $address_1, $address_2, $city, $zip) {
  //wp_mail('adam.kircher@gmail.com', "update_shipping_address", print_r($params, true));
  $zip = substr($zip, 0, 5);
  $city = mb_convert_case($city, MB_CASE_TITLE, "UTF-8" );
  $address_1 = mb_convert_case(str_replace("'", "''", $address_1), MB_CASE_TITLE, "UTF-8" );
  $address_2 = $address_2 ? "'".mb_convert_case(str_replace("'", "''", $address_2), MB_CASE_TITLE, "UTF-8" )."'" : "NULL";
  $query = "SirumWeb_AddUpdatePatHomeAddr '$guardian_id', '$address_1', $address_2, NULL, '$city', 'GA', '$zip', 'US'";
  //wp_mail('adam.kircher@gmail.com', "update_shipping_address", $query);
  return db_run($query);
}

//$query = sqlsrv_query( $db, "select * from cppat where cppat.pat_id=1003";);
// SirumWeb_FindPatByNameandDOB(
//   @LName varchar(30),           -- LAST NAME
//   @FName varchar(20),           -- FIRST NAME
//   @MName varchar(20)=NULL,     -- Middle Name (optional)
//   @DOB DateTime                -- Birth Date
// )
function find_patient($first_name, $last_name, $birth_date, $phone) {
  return db_run("SirumWeb_FindPat '$first_name', '$last_name', '$birth_date', '$phone'");
}

// SirumWeb_AddEditPatient(
//    @FirstName varchar(20)
//   ,@MiddleName varchar(20)= NULL -- Optional
//   ,@LastName varchar(30)
//   ,@BirthDate datetime
//   ,@ShipAddr1 varchar(50)    -- Address Line 1
//   ,@ShipAddr2 varchar(50)    -- Address Line 2
//   ,@ShipAddr3 varchar(50)    -- Address Line 3
//   ,@ShipCity varchar(20)     -- City Name
//   ,@ShipState varchar(2)     -- State Name
//   ,@ShipZip varchar(10)      -- Zip Code
//   ,@ShipCountry varchar(3)   -- Country Code
//   ,@CellPhone varchar(20)    -- Cell Phone
// )
function add_patient($first_name, $last_name, $birth_date, $phone, $language) {
  
  $first_name = mb_convert_case(str_replace("'", "''", $first_name), MB_CASE_TITLE, "UTF-8");
  $last_name = strtoupper(str_replace("'", "''", $last_name));


  $result = db_run("SirumWeb_AddUpdatePatient '$first_name', '$last_name', '$birth_date', '$phone', '$language'");

  wp_mail('adam.kircher@gmail.com', "add_patient", "$first_name $last_name ".print_r(func_get_args(), true).print_r($result, true));

  return $result['PatID'];
}

// Procedure dbo.SirumWeb_AddToPatientComment (@PatID int, @CmtToAdd VARCHAR(4096)
// The comment will be appended to the existing comment if it is not already in the comment field.
function append_comment($guardian_id, $comment) {
  $comment = str_replace("'", "''", $comment); //We need to escape single quotes in case comment has one
  return db_run("SirumWeb_AddToPatientComment '$guardian_id', '$comment'");
}

// Create Procedure dbo.SirumWeb_AddToPreorder(
//    @PatID int
//   ,@DrugName varchar(60) ='' -- Drug Name to look up NDC
//   ,@PharmacyOrgID int
//   ,@PharmacyName varchar(80)
//   ,@PharmacyAddr1 varchar(50)    -- Address Line 1
//   ,@PharmacyCity varchar(20)     -- City Name
//   ,@PharmacyState varchar(2)     -- State Name
//   ,@PharmacyZip varchar(10)      -- Zip Code
//   ,@PharmacyPhone varchar(20)   -- Phone Number
//   ,@PharmacyFaxNo varchar(20)   -- Phone Fax Number
// If you send the NDC, it will use it.  If you do not send and NCD it will attempt to look up the drug by the name.  I am not sure that this will work correctly, the name you pass in would most likely have to be an exact match, even though I am using  like logic  (ie “%Aspirin 325mg% “) to search.  We may have to work on this a bit more
function add_preorder($guardian_id, $drug_name, $pharmacy) {
   $store = json_decode(stripslashes($pharmacy));
   $drug_name = explode(',', $drug_name)[0];
   $phone = cleanPhone($store->phone) ?: '0000000000';
   $fax = cleanPhone($store->fax) ?: '0000000000';
   $store_name = str_replace("'", "''", $store->name); //We need to escape single quotes in case comment has one

   $query = "SirumWeb_AddToPreorder '$guardian_id', '$drug_name', '$store->npi', '$store_name P:$phone F:$fax', '$store->street', '$store->city', '$store->state', '$store->zip', '$phone', '$fax'";

   $result = db_run($query);

   if ( ! $store->phone OR ! $store->fax)
     wp_mail('adam.kircher@gmail.com', "add_preorder", "$query ".print_r(func_get_args(), true).print_r($result, true));

   return $result;
}

// Procedure dbo.SirumWeb_AddUpdatePatientUD (@PatID int, @UDNumber int, @UDValue varchar(50) )
// Set the @UD number can be 1-4 for the field that you want to update, and set the text value.
// 1 is backup pharmacy, 2 is stripe billing token.
// Create Procedure dbo.SirumWeb_AddToPreorder(
//    @PatID int
//   ,@DrugName varchar(60) ='' -- Drug Name to look up NDC
//   ,@PharmacyOrgID int
//   ,@PharmacyName varchar(80)
//   ,@PharmacyAddr1 varchar(50)    -- Address Line 1
//   ,@PharmacyCity varchar(20)     -- City Name
//   ,@PharmacyState varchar(2)     -- State Name
//   ,@PharmacyZip varchar(10)      -- Zip Code
//   ,@PharmacyPhone varchar(20)   -- Phone Number
//   ,@PharmacyFaxNo varchar(20)   -- Phone Fax Number
// If you send the NDC, it will use it.  If you do not send and NCD it will attempt to look up the drug by the name.  I am not sure that this will work correctly, the name you pass in would most likely have to be an exact match, even though I am using  like logic  (ie “%Aspirin 325mg% “) to search.  We may have to work on this a bit more
function update_pharmacy($guardian_id, $pharmacy) {

  $store = json_decode(stripslashes($pharmacy));

  $phone = cleanPhone($store->phone);
  $fax = cleanPhone($store->fax);

  $store_name = str_replace("'", "''", $store->name); //We need to escape single quotes in case pharmacy name has a ' for example Lamar's Pharmacy
  $store_street = str_replace("'", "''", $store->street);

  db_run("SirumWeb_AddExternalPharmacy '$store->npi', '$store_name, $store->phone, $store_street', '$store_street', '$store->city', '$store->state', '$store->zip', '$phone', '$fax'");

  db_run("SirumWeb_AddUpdatePatientUD '$guardian_id', '1', '$store_name'");

  //Because of 50 character limit, the street will likely be cut off.
  $user_def_2 = $store->npi.','.$store->fax.','.$store->phone.','.$store_street;
  return db_run("SirumWeb_AddUpdatePatientUD '$guardian_id', '2', '$user_def_2'");
}

function update_stripe_tokens($guardian_id, $value) {
  return db_run("SirumWeb_AddUpdatePatientUD '$guardian_id', '3', '$value'");
}

function update_card_and_coupon($guardian_id, $card = [], $coupon) {
  //Meet guardian 50 character limit
  //Last4 4, Month 2, Year 2, Type (Mastercard = 10), Delimiter 4, So coupon will be truncated if over 28 characters
  $value = $card['last4'].','.$card['month'].'/'.substr($card['year'] ?: '', 2).','.$card['type'].','.$coupon;

  return db_run("SirumWeb_AddUpdatePatientUD '$guardian_id', '4', '$value'");
}

//Procedure dbo.SirumWeb_AddUpdatePatEmail (@PatID int, @EMailAddress VARCHAR(255)
//Set the patID and the new email address
function update_email($guardian_id, $email) {
  return db_run("SirumWeb_AddUpdatePatEmail '$guardian_id', '$email'");
}

global $conn;
function db_run($sql, $resultIndex = 0) {
  global $conn;
  $conn = $conn ?: db_connect();
  $stmt = db_query($conn, $sql);

  if ( ! is_resource($stmt)) {
    if ($stmt !== true)  //mssql_query($sql, $conn) returns true on no result, false on error, or recource with rows
      wp_mail('adam.kircher@gmail.com', "No Resource", print_r($sql, true).' '.print_r($stmt, true).' '.print_r(mssql_get_last_message(), true));

    return;
  }

  for ($i = 0; $i < $resultIndex; $i++) {
    mssql_next_result($stmt);
  }

  if ( ! is_resource($stmt) OR ! mssql_num_rows($stmt)) {
    wp_mail('adam.kircher@gmail.com', "No Resource or Rows", print_r($sql, true).' '.print_r($stmt, true).' '.print_r(mssql_get_last_message(), true));

    //email_error("no rows for result of $sql");
    return [];
  }

  $data = db_fetch($stmt) ?: email_error("fetching $sql");

  //wp_mail('adam.kircher@gmail.com', "db query: $sql", print_r($params, true).print_r($data, true));
  //wp_mail('adam.kircher@gmail.com', "db testing", print_r(sqlsrv_errors(), true));

  return $data;
}

function db_fetch($stmt) {
 return mssql_fetch_array($stmt);
}

function db_connect() {
  //sqlsrv_configure("WarningsReturnAsErrors", 0);
  $conn = mssql_connect(GUARDIAN_IP, GUARDIAN_ID, GUARDIAN_PW) ?: email_error('Error Connection');
  mssql_select_db('cph', $conn) ?: email_error('Could not select database cph');
  return $conn;
}

function db_query($conn, $sql) {
  return mssql_query($sql, $conn) ?: email_error("Query $sql");
}

function email_error($heading) {
   $errors = mssql_get_last_message();
   if ($errors)
     wp_mail('adam.kircher@gmail.com', "db error: $heading", print_r($errors, true));
}
