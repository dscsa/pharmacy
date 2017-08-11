<?php //For IDE styling only

//TODO
//RUN REVISED CHRIS SCRIPTS
//CALL DB FUNCTIONS AT RIGHT PLACES
//MAKE ALLERGY LIST MATCH GUARDIAN
//FIX EDIT ADDRESS FIELD LABELS
//FIX BLANK BUTTON ON SAVE CARD

// Register custom style sheets and javascript.
add_action('wp_enqueue_scripts', 'dscsa_scripts');
function dscsa_scripts() {
  //is_wc_endpoint_url('orders') and is_wc_endpoint_url('account-details') seem to work
  wp_enqueue_script('ie9ajax', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-ajaxtransport-xdomainrequest/1.0.4/jquery.xdomainrequest.min.js', ['jquery']);
  wp_enqueue_script('jquery-ui', "https://goodpill.org/wp-admin/load-scripts.php?c=1&load%5B%5D=jquery-ui-core", ['jquery']);
  wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.min.css');
  wp_enqueue_script('datepicker', 'https://goodpill.org/wp-includes/js/jquery/ui/datepicker.min.js', ['jquery-ui']);

  wp_enqueue_script('dscsa-common', 'https://dscsa.github.io/webform/woocommerce/common.js', ['datepicker', 'ie9ajax']);
  wp_enqueue_style('dscsa-common', 'https://dscsa.github.io/webform/woocommerce/common.css');

  if (substr($_SERVER['REQUEST_URI'], 0, 11) == '/inventory/') {
    wp_enqueue_script('select2', 'https://goodpill.org/wp-content/plugins/woocommerce/assets/js/select2/select2.full.min.js'); //usually loaded by woocommerce but since this is independent page we need to load manually
	wp_enqueue_style('select2', 'https://goodpill.org/wp-content/plugins/woocommerce/assets/css/select2.css?ver=3.0.7'); //usually loaded by woocommerce but since this is independent page we need to load manually
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

function get_meta($field, $user_id) {
  return get_user_meta($user_id ?: get_current_user_id(), $field, true);
}

function get_default($field, $user_id) {
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

   update_user_meta($user_id, 'stripe', $card);
   //Meet guardian 50 character limit
   //Customer 18, Card 29, Delimiter 1 = 48
   update_stripe_tokens($card['customer'].','.$card['card'].',');

   $coupon = get_meta('coupon');

   update_card_and_coupon($card, $coupon);
}

function order_fields() {

  return [
    'rx_source' => [
      'type'   	  => 'radio',
      'required'  => true,
      'default'  => 'pharmacy',
      'options'   => [
        'erx'     => __('Prescription(s) were sent from my doctor'),
        'pharmacy' => __('Transfer prescription(s) from my pharmacy')

      ]
    ],
    'medication[]'  => [
      'type'   	  => 'select',
      'label'     => __('Search and select medications by generic name that you want to transfer to Good Pill'),
      'options'   => ['']
    ],
    'email' => [
      'label'     => __('Email'),
      'type'      => 'email',
      'validate'  => ['email'],
      'autocomplete' => 'email',
      'default'   => get_default('email', $user_id)
    ]
  ];
}

function account_fields($user_id) {

  return [
    'language' => [
      'type'   	  => 'radio',
      'label'     => __('Language'),
      'label_class' => ['radio'],
      'required'  => true,
      'options'   => ['EN' => __('English'), 'ES' => __('Spanish')],
      'default'   => get_default('language', $user_id) ?: 'EN'
    ]
  ];
}

function shared_fields($user_id) {

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
        'label'     =>  __('List any other medication(s) or supplement(s) you are currently taking'),
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
  echo '<br><br>'.get_meta('rx_source', $order->user_id);
  echo '<br><br>'.get_meta('medication[]', $order->user_id);
  echo '<br><br>'.get_meta('medication[]', $order->user_id);
  return dscsa_edit_account_form($order->user_id);

}
add_action( 'woocommerce_edit_account_form_start', 'dscsa_user_edit_account');
function dscsa_user_edit_account() {
  return dscsa_edit_account_form();
}

function dscsa_edit_account_form($user_id) {

  $fields = shared_fields($user_id)+account_fields($user_id);

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

function login_form($language) {

  $first_name = [
    'class' => ['form-row-first'],
    'label'  => __('First name'),
    'default' => $_POST['first_name']
  ];

  $last_name = [
    'class' => ['form-row-last'],
    'label'  => __('Last name'),
    'default' => $_POST['last_name']
  ];

  echo woocommerce_form_field('first_name', $first_name);
  echo woocommerce_form_field('last_name', $last_name);
}

add_action('woocommerce_register_form', 'dscsa_register_form_acknowledgement');
function dscsa_register_form_acknowledgement() {
  echo __('<div style="margin-bottom:8px">By clicking "Register" below, you agree to our <a href="/terms">Terms of Use</a> and agree to receive and pay for your refills automatically unless you contact us to decline.</div>');
}

//Customer created hook called to late in order to create username
//    https://github.com/woocommerce/woocommerce/blob/e24ca9d3bce1f9e923fcd00e492208511cdea727/includes/class-wc-form-handler.php#L1002
add_action('wp_loaded', 'dscsa_set_username');
function dscsa_set_username() {

  if ($_POST['birth_date'] AND $_POST['first_name'] AND $_POST['last_name']) {
     $_POST['birth_date'] = date_format(date_create($_POST['birth_date']), 'Y-m-d'); //in case html type=date does not work (e.g. IE)
     //Set user name for both login and registration
     $_POST['username'] = $_POST['first_name'].' '.$_POST['last_name'].' '.$_POST['birth_date'];
  }

  $phone = $_POST['phone'] ?: $_POST['user_login'];

  if ($phone) {

     $phone = cleanPhone($phone);

      if ( ! $phone) return;

      $_POST['password'] = $_POST['phone'] = $phone;

     if (empty($_POST['email']))
        $_POST['user_login'] = $_POST['email'] = $_POST['phone'].'@goodpill.org';

     if (empty($_POST['account_email']))
        $_POST['account_email'] = $_POST['phone'].'@goodpill.org';
  }
}

function cleanPhone($phone) { //get rid of all delimiters and a leading 1 if it exists
  $phone = preg_replace('/\D+/', '', $phone);
  if (strlen($phone) == 11 AND substr($phone, 0, 1) == 1)
    return substr($phone, 1, 10);

  return strlen($phone) == 10 ? $phone : NULL;
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

  foreach(['', 'billing_', 'shipping_'] as $field) {
    update_user_meta($user_id, $field.'first_name', $first_name);
    update_user_meta($user_id, $field.'last_name', $last_name);
  }
  update_user_meta($user_id, 'birth_date', $birth_date);
  update_user_meta($user_id, 'language', $language);

  if ($_POST['phone'])
    update_user_meta($user_id, 'phone', $_POST['phone']);
}

// Function to change email address
add_filter('wp_mail_from', 'email_address');
function email_address() {
  return 'rx@goodpill.org';
}
add_filter('wp_mail_from_name', 'email_name');
function email_name() {
  return 'Good Pill Pharmacy';
}

// After registration and login redirect user to account/orders.
// Clicking on Dashboard/New Order in Nave will add the actual product
add_action('woocommerce_registration_redirect', 'dscsa_redirect', 2);
function dscsa_redirect() {
  return home_url('/account/?add-to-cart=30#/');
}

add_filter ('wp_redirect', 'dscsa_wp_redirect');
function dscsa_wp_redirect($location) {

  //This goes back to account/details rather than /account after saving account details
  if (substr($location, -9) == '/account/') {
    return $location.'details/';
  }

  //After successful order, add another item back into cart.
  //Add to card won't work unless we replace query params e.g., key=wc_order_594de1d38152e
  if (substr($_GET['key'], 0, 9) == 'wc_order_')
   return substr($location, 0, -26).'add-to-cart=30';

  //Hacky, but only way I could get add-to-cart not to be called twice in a row.
  if (substr($location, -15) == '?add-to-cart=30')
   return substr($location, 0, -15);

  return $location;
}

add_filter ('woocommerce_account_menu_items', 'dscsa_my_account_menu');
function dscsa_my_account_menu($nav) {
  $nav['dashboard'] = __('New Order');
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

//On new order and account/details save account fields back to user
//TODO should changing checkout fields overwrite account fields if they are set?
add_action('woocommerce_save_account_details', 'dscsa_save_account');
function dscsa_save_account($user_id) {
  //TODO should save if they don't exist, but what if they do, should we be overriding?
  update_email(sanitize_text_field($_POST['account_email']));

  dscsa_save_patient($user_id, shared_fields($user_id) + account_fields($user_id));
}

add_action('woocommerce_checkout_update_order_meta', 'dscsa_save_order');
function dscsa_save_order($order_id) {
  $order = wc_get_order( $order_id );
  $user_id = $order->user_id;

  wp_mail('hello@goodpill.org', 'New Webform Order', 'New Registration. Page 2 of 2. Source: '.print_r($_POST['rx_source'], true));
  wp_mail('adam.kircher@gmail.com', 'New Webform Order', print_r([get_current_user_id(), $order->user_id, $order->customer_id, $order->get_used_coupons()[0], $order->get_used_coupons()[0]->code, $order->get_used_coupons()], true));

  //THIS MUST BE CALLED FIRST IN ORDER TO CREATE GUARDIAN ID
  //TODO should save if they don't exist, but what if they do, should we be overriding?
  dscsa_save_patient($user_id, shared_fields($user_id) + order_fields($user_id));

  $coupon = $order->get_used_coupons()[0] ?: get_meta('coupon');
  $card = get_meta('stripe');

  update_user_meta($user_id, 'coupon', $coupon);
  update_card_and_coupon($card, $coupon);

  $prefix = $_POST['ship_to_different_address'] ? 'shipping_' : 'billing_';

  //TODO this should be called on the edit address page as well
  $address = update_shipping_address(
    sanitize_text_field($_POST[$prefix.'address_1']),
    sanitize_text_field($_POST[$prefix.'address_2']),
    sanitize_text_field($_POST[$prefix.'city']),
    sanitize_text_field($_POST[$prefix.'postcode'])
  );

  if ($_POST['medication']) {
    foreach ($_POST['medication'] as $drug_name) {
      add_preorder($drug_name, $_POST['backup_pharmacy']);
    }
  }
}

function dscsa_save_patient($user_id, $fields) {

  $patient_id = add_patient(
    $_POST['billing_first_name'],
    $_POST['billing_last_name'],
    $_POST['birth_date'],
    $_POST['email'],
    get_meta('language', $user_id)
  );

  update_user_meta($user_id, 'guardian_id', $patient_id);

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

  foreach ($fields as $key => $field) {

    //In case of backup pharmacy json, sanitize gets rid of it
    $val = sanitize_text_field($_POST[$key]);

    if ($key == 'backup_pharmacy')
      update_pharmacy($val);

    if ($key == 'medications_other')
      append_comment($val);

    if ($key == 'phone')
      update_phone($val);

    update_user_meta($user_id, $key, $val);

    if ($allergy_codes[$key]) {
      //Since all checkboxes submitted even with none selected.  If none
      //is selected manually set value to false for all except none
      $val = ($_POST['allergies_none'] AND $key != 'allergies_none') ? NULL : $val;
      //wp_mail('adam.kircher@gmail.com', 'save allergies', "$key $allergy_codes[$key] $val");
      add_remove_allergy($allergy_codes[$key], $val);
    }
  }
}

//Didn't work: https://stackoverflow.com/questions/38395784/woocommerce-overriding-billing-state-and-post-code-on-existing-checkout-fields
//Did work: https://stackoverflow.com/questions/36619793/cant-change-postcode-zip-field-label-in-woocommerce
global $lang;
global $phone;
add_filter('ngettext', 'dscsa_translate', 10, 3);
add_filter('gettext', 'dscsa_translate', 10, 3);
function dscsa_translate($term, $raw, $domain) {

  global $phone;
  if ( ! $phone AND substr($_SERVER['REQUEST_URI'], 0, 21) == '/account/?add-to-cart')
     $phone = get_meta('phone');

  if (strpos($term, 'Shipping') !== false) {
     //echo htmlentities($term).'<br>';
    //wp_mail('adam.kircher@gmail.com', 'been added to your cart', $term);
  }
  $toEnglish = [
    'Spanish'  => 'Espanol', //Registering
    'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">shipping and billing addresses</a> and <a href="%3$s">edit your password and account details</a>.' => '',
    'ZIP' => 'Zip code', //Checkout
    'Your order' => '', //Checkout
    'No saved methods found.' => 'No credit or debit cards are saved to your account',
    '%s has been added to your cart.' => $phone
      ? 'Step 2 of 2: You are almost done! Please complete this page so we can fill your prescription(s).  If you need to login again, your temporary password is '.$phone.'.  You can change your password on the "Account Details" page'
      : 'Thank you for your order! Your prescription(s) should arrive within 3-5 days.',
    'Username or email' => 'Phone number', //For resetting passwords
    'Additional information' => '',  //Checkout
    'Billing address' => 'Shipping address', //Order confirmation
	'Billing &amp; Shipping' => 'Shipping Address', //Checkout
    'Lost your password? Please enter your username or email address. You will receive a link to create a new password via email.' => 'Lost your password? Call us for assistance or enter the phone number you used to register.', //Logging in
    'Please provide a valid email address.' => 'Please provide a valid 10-digit phone number.',
    'Please enter a valid account username.' => 'Please enter your name and date of birth.',
    'Please enter an account password.' => 'Please provide a valid 10-digit phone number.',
    'Username is required.' => 'Name and date of birth are required.',
    'Invalid username or email.' => '<strong>Error</strong>: We cannot find an account with that phone number.',
    '<strong>ERROR</strong>: Invalid username.' => '<strong>Error</strong>: We cannot find an account with that name and date of birth.',
    'An account is already registered with your email address. Please login.' => 'An account is already registered with that phone number. Please login.'
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
    'List any other medication(s) or supplement(s) you are currently taking' => 'Indique cualquier otro medicamento o suplemento que usted toma actualmente',
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
    'Free shipping coupon' => 'Cupón para envíos gratuitos',
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
    //Can't include below since its uses the same message as the "Thank You for your order"
    'Phone number' => 'Teléfono',
    'Email:' => 'Email:',
    'Prescription(s) were sent from my doctor' => 'Prescription(s) were sent from my doctor',
    'Transfer prescription(s) from my pharmacy' => 'Transfer prescription(s) from my pharmacy',
    'Street address' => 'Street address',
    'Apartment, suite, unit etc. (optional)' => 'Apartment, suite, unit etc. (optional)',
    'Payment methods' => 'Payment methods',
    'Account details' => 'Account details',
    'Logout' => 'Logout',
    'No order has been made yet.' => 'No order has been made yet.',
    'The following addresses will be used on the checkout page by default.' => 'The following addresses will be used on the checkout page by default.',
    'Billing address' => 'Billing address',
    'Shipping address' => 'Shipping address',
    'Save address' => 'Save address',
    'No credit or debit cards are saved to your account' => 'No credit or debit cards are saved to your account',
    'Add payment method' => 'Add payment method',
    'Save changes' => 'Save changes',
    'is a required field' => 'is a required field',
    'Order #%1$s was placed on %2$s and is currently %3$s.' => 'Order #%1$s was placed on %2$s and is currently %3$s.',
    'Payment method:' => 'Payment method:',
    'Order details' => 'Order details',
    'Customer details' => 'Customer details',
    'Amoxicillin' => 'Amoxicillin',
    'Azithromycin' => 'Azithromycin',
    'Cephalosporins' => 'Cephalosporins',
    'Codeine' => 'Codeine',
    'Salicylates' => 'Salicylates',
    'Thank you for your order!  Your prescription(s) should arrive within 3-5 days.' => 'Thank you for your order!  Your prescription(s) should arrive within 3-5 days.',
    'Please choose a pharmacy' => 'Please choose a pharmacy',
    '<div style="margin-bottom:8px">By clicking "Register" below, you agree to our <a href="/terms">Terms of Use</a></div>' => '<div style="margin-bottom:8px">By clicking "Register" below, you agree to our <a href="/terms">Terms of Use</a></div>',
  ];

  $english = isset($toEnglish[$term]) ? $toEnglish[$term] : $term;
  $spanish = $toSpanish[$english];

  if (is_admin() OR ! isset($spanish))
    return $english;

  global $lang;
  $lang = $lang ?: get_meta('language');

  if ($lang == 'EN')
    return $english;

  if ($lang == 'ES')
    return $spanish;

  //This allows client side translating based on jQuery listening to radio buttons
  if (isset($_GET['register']))
    return  "<span class='english'>$english</span><span class='spanish'>$spanish</span>";

  return $english;
}

// Hook in
add_filter( 'woocommerce_checkout_fields' , 'dscsa_checkout_fields' );
function dscsa_checkout_fields( $fields ) {

  $shared_fields = shared_fields();

  //Add some order fields that are not in patient profile
  $order_fields = order_fields();

  $fields['order'] = $order_fields + $shared_fields;

  //Allow billing out of state but don't allow shipping out of state
  $fields['shipping']['shipping_state']['type'] = 'select';
  $fields['shipping']['shipping_state']['options'] = ['GA' => 'Georgia'];
  unset($fields['shipping']['shipping_country']);
  unset($fields['shipping']['shipping_company']);

  // We are using our billing address as the shipping address for now.
  $fields['billing']['billing_state']['type'] = 'select';
  $fields['billing']['billing_state']['options'] = ['GA' => 'Georgia'];

  //Remove Some Fields
  unset($fields['billing']['billing_first_name']['autofocus']);
  unset($fields['billing']['shipping_first_name']['autofocus']);
  unset($fields['billing']['billing_phone']);
  unset($fields['billing']['billing_email']);
  unset($fields['billing']['billing_company']);
  unset($fields['billing']['billing_country']);

  return $fields;
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
function add_remove_allergy($allergy_id, $value) {
  return db_run("SirumWeb_AddRemove_Allergy(?, ?, ?, ?)", [
    get_meta('guardian_id'), $value ? 1 : 0, $allergy_id, $value
  ]);
}

// SirumWeb_AddUpdateHomePhone(
//   @PatID int,  -- ID of Patient
//   @PatCellPhone VARCHAR(20)
// }
function update_phone($cell_phone) {
  return db_run("SirumWeb_AddUpdatePatHomePhone(?, ?)", [
    get_meta('guardian_id'), $cell_phone
  ]);
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
function update_shipping_address($address_1, $address_2, $city, $zip) {
  $params = [
    get_meta('guardian_id'), $address_1, $address_2, $city, substr($zip, 0, 5)
  ];

  //wp_mail('adam.kircher@gmail.com', "update_shipping_address", print_r($params, true));
  return db_run("SirumWeb_AddUpdatePatHomeAddr(?, ?, ?, NULL, ?, 'GA', ?, 'US')", $params);
}

//$query = sqlsrv_query( $db, "select * from cppat where cppat.pat_id=1003";);
// SirumWeb_FindPatByNameandDOB(
//   @LName varchar(30),           -- LAST NAME
//   @FName varchar(20),           -- FIRST NAME
//   @MName varchar(20)=NULL,     -- Middle Name (optional)
//   @DOB DateTime                -- Birth Date
// )
function find_patient($first_name, $last_name, $birth_date) {
  return db_run("SirumWeb_FindPatByNameandDOB(?, ?, ?)", [$first_name, $last_name, $birth_date]);
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
function add_patient($first_name, $last_name, $birth_date, $email, $language) {
  //wp_mail('adam.kircher@gmail.com', "db add patient", print_r(func_get_args(), true));
  return db_run("SirumWeb_AddEditPatient(?, ?, ?, ?, ?)", [$first_name, $last_name, $birth_date, $email, $language])['PatID'];
}

// Procedure dbo.SirumWeb_AddToPatientComment (@PatID int, @CmtToAdd VARCHAR(4096)
// The comment will be appended to the existing comment if it is not already in the comment field.
function append_comment($comment) {
  return db_run("SirumWeb_AddToPatientComment(?, ?)", [get_meta('guardian_id'), $comment]);
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
function add_preorder($drug_name, $pharmacy) {

   $store = json_decode(stripslashes($pharmacy));

   return db_run("SirumWeb_AddToPreorder(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
       get_meta('guardian_id'),
       explode(",", $drug_name)[0],
       $store->npi,
       $store->name,
       $store->street,
       $store->city,
       $store->state,
       $store->zip,
       cleanPhone($store->phone),
       cleanPhone($store->fax)
   ]);
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
function update_pharmacy($pharmacy) {

  $store = json_decode(stripslashes($pharmacy));

  $args = [
    $store->npi,
    $store->name,
    $store->street,
    $store->city,
    $store->state,
    $store->zip,
    cleanPhone($store->phone),
    cleanPhone($store->fax)
  ];

  wp_mail('adam.kircher@gmail.com', "update_pharmacy 1", print_r($args, true));

  db_run("SirumWeb_AddExternalPharmacy(?, ?, ?, ?, ?, ?, ?, ?)", $args);

  wp_mail('adam.kircher@gmail.com', "update_pharmacy 2", print_r(sqlsrv_errors(), true));

  db_run("SirumWeb_AddUpdatePatientUD(?, 1, ?)", [get_meta('guardian_id'), $store->name]);
  //Because of 50 character limit, the street will likely be cut off.
  return db_run("SirumWeb_AddUpdatePatientUD(?, 2, ?)", [get_meta('guardian_id'), $store->npi.','.$store->fax.','.$store->phone.','.$store->street]);
}

function update_stripe_tokens($value) {
  return db_run("SirumWeb_AddUpdatePatientUD(?, 3, ?)", [get_meta('guardian_id'), $value]);
}

function update_card_and_coupon($card, $coupon) {
  //Meet guardian 50 character limit
  //Last4 4, Month 2, Year 2, Type (Mastercard = 10), Delimiter 4, So coupon will be truncated if over 28 characters
  $value = $card['last4'].','.$card['month'].'/'.substr($card['year'] ?: '', 2).','.$card['type'].','.$coupon;

    wp_mail('adam.kircher@gmail.com', "update_card_and_coupon", print_r([get_meta('guardian_id'), $value], true));

  return db_run("SirumWeb_AddUpdatePatientUD(?, 4, ?)", [get_meta('guardian_id'), $value]);
}

//Procedure dbo.SirumWeb_AddUpdatePatEmail (@PatID int, @EMailAddress VARCHAR(255)
//Set the patID and the new email address
function update_email($email) {
  return db_run("SirumWeb_AddUpdatePatEmail(?, ?)", [get_meta('guardian_id'), $email]);
}

global $conn;
function db_run($sql, $params, $noresults) {
  global $conn;
  $conn = $conn ?: db_connect();
  $stmt = db_query($conn, "{call $sql}", $params);

  if ( ! sqlsrv_has_rows($stmt)) {
    email_error("no rows for result of $sql");
    return [];
  }

  $data = db_fetch($stmt) ?: email_error("fetching $sql");

  sqlsrv_free_stmt($stmt);

  //wp_mail('adam.kircher@gmail.com', "db query: $sql", print_r($params, true).print_r($data, true));
  //wp_mail('adam.kircher@gmail.com', "db testing", print_r(sqlsrv_errors(), true));

  return $data;
}

function db_fetch($stmt) {
 return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function db_connect() {
  sqlsrv_configure("WarningsReturnAsErrors", 0);
  return sqlsrv_connect("GOODPILL-SERVER", ["Database"=>"cph"]) ?: email_error('Error Connection');
}

function db_query($conn, $sql, $params) {
  return sqlsrv_query($conn, $sql, $params) ?: email_error("Query $sql");
}

function email_error($heading) {
   wp_mail('adam.kircher@gmail.com', "db error: $heading", print_r(sqlsrv_errors(), true));
}
