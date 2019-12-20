<?php
/* Enter your custom functions here */

/// Add endpoint for a "Remove as Default Payment" button: https://www.sitepoint.com/creating-custom-endpoints-for-the-wordpress-rest-api/
add_action('rest_api_init', function () {
  register_rest_route( 'payment', 'remove-default/(?P<user_id>\d+)', [
    'methods'  => 'GET',
    'callback' => 'dscsa_remove_default_payment'
  ]);
});

function dscsa_remove_default_payment($params) {

    $user_id = $params['user_id']; //get_current_user_id() gives 0 so we must pass user_id as URL parameter

    $tokens  = WC_Data_Store::load( 'payment-token' );
		$token   = $tokens->get_users_default_token($user_id);
    $tokens->set_default_status($token->token_id, false);

    $patient_id = get_meta('guardian_id', $user_id);
    $coupon = get_meta('coupon', $user_id);

    update_payment_method($patient_id, is_pay_coupon($coupon) ? "PAY BY COUPON: $coupon" : "PAY BY MAIL");

    update_card_and_coupon($patient_id, null, $coupon);

    wp_redirect(home_url('/account/payment/'));

    exit;
}

add_filter('woocommerce_payment_methods_list_item', 'dscsa_payment_methods_list_item', 10, 2);
function dscsa_payment_methods_list_item($payment, $token) {
  if ($token->is_default() ) {
    $payment['actions']['default'] = array(
      'url'  => home_url('/wp-json/payment/remove-default/'.get_current_user_id()), //URL created in the rest_api_init hook
      'name' => esc_html__( 'Remove Autopay', 'woocommerce' )
    );
  }

  return $payment;
}

// Register custom style sheets and javascript.
add_action('admin_enqueue_scripts', 'dscsa_admin_scripts');
function dscsa_admin_scripts() {
  if ($_GET['post'] AND $_GET['action'] == 'edit') {
    wp_enqueue_script('dscsa-common', 'https://dscsa.github.io/webform/js/common.js');
    wp_enqueue_style('dscsa-select2', 'https://dscsa.github.io/webform/css/select2.css');
    wp_enqueue_style('dscsa-admin', 'https://dscsa.github.io/webform/css/admin.css');
    wp_enqueue_script('dscsa-admin', 'https://dscsa.github.io/webform/js/admin.js', ['jquery', 'dscsa-common']);
  }

  if ($_GET['post_type'] == 'ticket') {
    wp_enqueue_style('dscsa-support-css', 'https://dscsa.github.io/webform/css/support.css');
    wp_enqueue_script('dscsa-support-js', 'https://dscsa.github.io/webform/js/support.js', ['jquery']);
  }
}

add_action('send_headers', 'dscsa_salesforce_iframe');
function dscsa_salesforce_iframe() {
  header('Content-Security-Policy: frame-ancestors https://sirum.lightning.force.com/;');
}

add_action('wp_enqueue_scripts', 'dscsa_user_scripts');
function dscsa_user_scripts() {

  wp_enqueue_script('google-analytics', 'https://www.googletagmanager.com/gtag/js?id=UA-102235287-1');
  wp_add_inline_script('google-analytics', 'window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag("js:", new Date());  gtag("config", "UA-102235287-1"); console.log("google analytics loaded");');

  //is_wc_endpoint_url('orders') and is_wc_endpoint_url('account-details') seem to work
  wp_enqueue_script('ie9ajax', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-ajaxtransport-xdomainrequest/1.0.4/jquery.xdomainrequest.min.js', ['jquery']);
  wp_enqueue_script('jquery-ui', "/wp-admin/load-scripts.php?c=1&load%5B%5D=jquery-ui-core", ['jquery']);
  wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.min.css');
  wp_enqueue_script('datepicker', '/wp-includes/js/jquery/ui/datepicker.min.js', ['jquery-ui']);

  wp_enqueue_script('dscsa-common', 'https://dscsa.github.io/webform/js/common.js', ['datepicker', 'ie9ajax', 'select2']);
  wp_enqueue_style('dscsa-common', 'https://dscsa.github.io/webform/css/common.css');

  if (substr($_SERVER['REQUEST_URI'], 0, 10) == '/gp-stock/') {
    wp_enqueue_script('select2', '/wp-content/plugins/woocommerce/assets/js/select2/select2.full.min.js'); //usually loaded by woocommerce but since this is independent page we need to load manually
	  wp_enqueue_style('select2', '/wp-content/plugins/woocommerce/assets/css/select2.css?ver=3.0.7'); //usually loaded by woocommerce but since this is independent page we need to load manually
    wp_enqueue_script('dscsa-inventory', 'https://dscsa.github.io/webform/js/inventory.js', ['jquery', 'ie9ajax']);
    wp_enqueue_style('dscsa-inventory', 'https://dscsa.github.io/webform/css/inventory.css');
  }

  if (substr($_SERVER['REQUEST_URI'], 0, 11) == '/gp-prices/') {
    wp_enqueue_script('select2', '/wp-content/plugins/woocommerce/assets/js/select2/select2.full.min.js'); //usually loaded by woocommerce but since this is independent page we need to load manually
	  wp_enqueue_style('select2', '/wp-content/plugins/woocommerce/assets/css/select2.css?ver=3.0.7'); //usually loaded by woocommerce but since this is independent page we need to load manually
    wp_enqueue_script('dscsa-prices', 'https://dscsa.github.io/webform/js/prices.js', ['jquery', 'ie9ajax']);
    wp_enqueue_style('dscsa-prices', 'https://dscsa.github.io/webform/css/prices.css');
  }

  if (is_user_logged_in()) {
    wp_enqueue_script('dscsa-account', 'https://dscsa.github.io/webform/js/account.js', ['jquery', 'dscsa-common']);
    wp_enqueue_style('dscsa-select2', 'https://dscsa.github.io/webform/css/select2.css');

    if (is_checkout() AND ! is_wc_endpoint_url()) { //hack to get wp_add_inline_style() to work. https://www.cssigniter.com/late-enqueue-inline-css-wordpress/
      if ( ! is_registered()) {
        wp_register_style( 'hide-nav-for-new-users', false );
      	wp_enqueue_style( 'hide-nav-for-new-users' );
      	wp_add_inline_style( 'hide-nav-for-new-users', '.woocommerce-MyAccount-navigation { display:none }');
      }
      wp_enqueue_style('dscsa-checkout', 'https://dscsa.github.io/webform/css/checkout.css');
      wp_enqueue_script('dscsa-checkout', 'https://dscsa.github.io/webform/js/checkout.js', ['jquery', 'ie9ajax']);
    }
  } else if (substr($_SERVER['REQUEST_URI'], 0, 9) == '/account/') {
    wp_enqueue_style('dscsa-login', 'https://dscsa.github.io/webform/css/login.css');
  	wp_enqueue_script('dscsa-login', 'https://dscsa.github.io/webform/js/login.js', ['jquery', 'dscsa-common']);
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

add_action('wc_stripe_delete_source', 'dscsa_stripe_delete_source', 10, 2);
function dscsa_stripe_delete_source($stripe_id, $customer) {
  $user_id = get_current_user_id();
  $patient_id = get_meta('guardian_id', $user_id);

  $tokens = WC_Payment_Tokens::get_customer_default_token($user_id); //Unset Guardian only if we deleted the default token

  wp_mail("adam.kircher@gmail.com", "dscsa_stripe_delete_source autopay off", "WP: $user_id | Stripe: $stripe_id | Guardian: $patient_id ".count($tokens)." ".print_r($tokens, true)." ".print_r($customer, true));

  if (count($tokens)) return;

  $coupon = get_meta('coupon', $user_id);

  update_payment_method($patient_id, is_pay_coupon($coupon) ? "PAY BY COUPON: $coupon" : "PAY BY MAIL");

  update_card_and_coupon($patient_id, null, $coupon);
}

function is_pay_coupon($coupon) {
  $coupon = new WC_Coupon($coupon);
  return $coupon->get_free_shipping();
}

add_action('wc_stripe_set_default_source', 'dscsa_stripe_set_default_source', 10, 2);
function dscsa_stripe_set_default_source($stripe_id, $customer) {

  $card = [
    'last4' => $customer->sources->data[0]->card->last4,
    'card' => $customer->default_source,
    'customer' => $stripe_id,
    'type' => $customer->sources->data[0]->card->brand,
    'year' => $customer->sources->data[0]->card->exp_year,
    'month' => $customer->sources->data[0]->card->exp_month
  ];

  $user_id = get_current_user_id();
  $patient_id = get_meta('guardian_id', $user_id);

  wp_mail("adam.kircher@gmail.com", "dscsa_stripe_set_default_source", "WP: $user_id | Stripe: $stripe_id | Guardian: $patient_id | REQUEST_URI: ".$_SERVER['REQUEST_URI']." | wc-ajax: ".$_GET['wc-ajax']." | HTTP_REFERER: ".$_SERVER['HTTP_REFERER']." ".print_r($card, true)." ".print_r($customer, true));

  if ( ! is_add_payment_page() && ! $_POST['rx_source']) { //This means on Orders->Pay page but not AutoPay or Registration/New Orders pages
    //Undo this card being set as default
    //https://github.com/woocommerce/woocommerce/blob/7f12c4e4364105be0c4fb94c4c3381619b0e7214/includes/class-wc-payment-tokens.php
    //https://github.com/woocommerce/woocommerce/search?q=set_users_default&unscoped_q=set_users_default
    //https://github.com/woocommerce/woocommerce-gateway-stripe/blob/453f739d2df7316f1a60aeb37e8036a337de903a/includes/class-wc-stripe-customer.php
    $tokens = WC_Data_Store::load( 'payment-token' );
		$token  = $tokens->get_users_default_token($user_id);
    $tokens->set_default_status($token->token_id, false);
    return;
  }

  update_user_meta($user_id, 'stripe', $card);

  if ( ! $patient_id || $_POST['rx_source']) return; //in case they fill this out before saving account details or a new order. Check rx_source so we don't duplicate calls

  //Meet guardian 50 character limit
  //Customer 18, Card 29, Delimiter 1 = 48

  $coupon = get_meta('coupon', $user_id);

  update_payment_method($patient_id, is_pay_coupon($coupon) ? "PAY BY COUPON: $coupon" : "PAY BY CARD: $card[type] $card[last4]");

  update_card_and_coupon($patient_id, $card, $coupon);
}

//Set first payment method in guardian even if it is not a "default"
add_action('woocommerce_stripe_add_source', 'dscsa_stripe_add_source', 10, 4);
function dscsa_stripe_add_source($stripe_id, $wc_token, $customer, $source_id) {// Called after creating/attaching a source to a customer.

  $user_id = get_current_user_id();
  wp_mail("adam.kircher@gmail.com", "woocommerce_stripe_add_source", "Token: $wc_token WP: $user_id | Stripe: $stripe_id | Card $source_id | REQUEST_URI: ".$_SERVER['REQUEST_URI']." | wc-ajax: ".$_GET['wc-ajax']." | HTTP_REFERER: ".$_SERVER['HTTP_REFERER']." ".print_r($customer, true));

  if ($wc_token->is_default() OR ! is_add_payment_page()) return;

  WC_Payment_Tokens::set_users_default($user_id, $wc_token->get_id());
}

//is_add_payment_page ("Autopay" page OR "Checkout" page) vs just paying for an order on "Orders" page
function is_add_payment_page() {
  //Outer parenthesis are important as assignment comes before OR in precedence http://php.net/manual/en/language.operators.logical.php
  return (strpos($_SERVER['REQUEST_URI'], '/add-payment/') OR strpos($_SERVER['REQUEST_URI'], '/default-payment/') OR ($_GET['wc-ajax'] == 'update_order_review' AND strpos($_SERVER['HTTP_REFERER'], '/add-payment/')));
}

function order_fields($user_id = null, $ordered = null, $rxs = []) {

  $user_id = $user_id ?: get_current_user_id();

  $fields = [
    'rx_source' => [
      'priority'  => 1,
      'type'   	  => 'radio',
      'required'  => true,
      'default'   => get_default('rx_source', $user_id) ?: 'erx',
      'options'   => [
        'erx' => __(is_registered() ? 'Refill the Rx(s) selected below' : 'Rx(s) were sent from my doctor'),
        'pharmacy' => __('Transfer Rx(s) with refills remaining from my pharmacy')

      ]
    ],
    'email' => [
      'priority'  => 21,
      'label'     => __('Email'),
      'type'      => 'email',
      'validate'  => ['email'],
      'autocomplete' => 'email',
      'default'   => get_default('email', $user_id) ?: get_default('account_email', $user_id)
    ]
  ];

  if ($ordered) { //Admin and Order Confirmation Pages
    $fields['ordered[]']  = [
      'type'   	  => 'select',
      'label'     => __('Here are the Rx(s) in your order.  Call us to make a change'),
      'options'   => [''],
      'custom_attributes' => ['data-rxs' => json_encode($ordered)]
    ];

  } else { //Checkout Page

    $fields['transfer[]']  = [
      'priority'  => 3,
      'type'   	  => 'select',
      'class'     => ['pharmacy'],
      'label'     => __('Search and select medications by generic name that you want to transfer to Good Pill'),
      'options'   => ['' => 'Select RX(s)']
    ];

    $fields['rxs[]'] = [
      'priority'  => 3,
      'type'   	  => 'select',
      'class'     => ['erx'],
      'label'     => __('Below are the Rx(s) that we have gotten from your doctor and are able to fill'),
      'options'   => ['' => __("We haven't gotten any Rx(s) that we can fill from your doctor yet")],
      'custom_attributes' => ['data-rxs' => json_encode($rxs)]
    ];

  }

  return $fields;

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

function search($arr, $gcn) {
  foreach($arr as $i => $row) {
    //print_r([$gcn, $row['gsx$_cpzh4']]);
    if(strpos($row['gsx$gcns']['$t'], $gcn) !== false) return $row;
  }
  return FALSE;
}

function admin_fields($user_id = null) {

  $user_id = $user_id ?: get_current_user_id();

  return [
    'guardian_id' => [
      'type'   	  => 'select',
      'label'     =>  __('Guardian Patient ID'),
      'options'   => [get_default('guardian_id', $user_id)]
      //'default'   => get_default('guardian_id', $user_id) //TODO if multiple matches we should default to current id but allow others to be choosen
    ]
  ];
}

add_action('retrieve_password_key', 'dscsa_retrieve_password_key', 10, 2);
function dscsa_retrieve_password_key($user_login, $reset_key) {

  $user_id = get_user_by('login', $user_login)->ID;

  $link = add_query_arg( array( 'key' => $reset_key, 'id' => $user_id ), wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) ) );
  $link = "https://www.".str_replace(' ', '+', substr($link, 12));

  debug_email("Password Reset", "$user_login, $reset_key Shipping phone: ".get_user_meta($user_id, 'shipping_phone', true).", billing phone: ".get_user_meta($user_id, 'billing_phone', true).", account phone:  ".get_user_meta($user_id, 'account_phone', true)." ".$link);

  passwordResetNotice($user_id, $link);
}

//https://20somethingfinance.com/how-to-send-text-messages-sms-via-email-for-free/
function passwordResetNotice($user_id, $link) {

  $phone = get_user_meta($user_id, 'phone', true) ?: get_user_meta($user_id, 'billing_phone', true);
  $email = get_user_meta($user_id, 'email', true) ?: get_user_meta($user_id, 'billing_email', true);
  $msg   = "The following link will enable you to reset your password.  If clicking it doesn't work, try copying & pasting it into a browser instead. $link";

  commCalendar([
    "password" => COMM_CALENDAR_KEY,
    "title" => "Password Reset. Created:".date_create()->format('Y-m-d H:i:s'),
    "body" => [
      [
        "sms" => $phone, //COMM_CALENDAR_DEBUG,
        "workHours" => false,
        "message" => $msg,
        "fallbacks" => [
           [
            "call" => $phone, //COMM_CALENDAR_DEBUG,
            "workHours" => false,
            "message" => 'Hi, this is Good Pill Pharmacy <Pause /> We had trouble emailing and texting you with a link to reset your password.  You will need to give us a call at 8,,,,8,,,,8 <Pause />9,,,,8,,,,7 <Pause />5,,,,1,,,,8,,,,7. <Pause length="2" /> Again please call us to reset your password at 8,,,,8,,,,8 <Pause />9,,,,8,,,,7 <Pause />5,,,,1,,,,8,,,,7. <Pause />'
           ]
        ]
      ],
      [
        "email" => $email,
        "workHours" => false,
        "subject" => "Good Pill Password Reset",
        "message" => "Hello,<br><br>$msg<br><br>Thanks!<br>Good Pill Pharmacy",
      ]
    ]
  ]);
}

function commCalendar($comm_array) {

  $json = json_encode($comm_array, JSON_PRETTY_PRINT);

  wp_mail("adam.kircher@gmail.com", 'COMM-CALENDAR START', "COMM-CALENDAR START ".$json);

  $ch = curl_init();

  curl_setopt($ch, CURLOPT_URL, COMM_CALENDAR_URL);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Receive server response ...
  curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST"); //https://evertpot.com/curl-redirect-requestbody/
  curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
  //curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); //Google App Script always redirects so we need to follow

  $res = curl_exec($ch);

  curl_close ($ch);

  // Further processing ...
  if ($res['success']) {
    wp_mail("adam.kircher@gmail.com", 'COMM-CALENDAR SUCCCESS', "COMM-CALENDAR SUCCCESS $json".print_r($res, true));
  } else {
    wp_mail("adam.kircher@gmail.com", 'COMM-CALENDAR ERROR', "COMM-CALENDAR ERROR $json".print_r($res, true));
  }
}


function shared_fields($user_id = null) {


    $user = wp_get_current_user();

    $user_id = $user_id ?: $user->ID;

    $pharmacy = [
      'priority'  => 2,
      'type'  => 'select',
      'required' => true,
      'label' => __('Backup pharmacy that we can transfer your prescription(s) to and from'),
      'options' => ['' => __("Type to search. 'Walgreens Norcross' will show the one at '5296 Jimmy Carter Blvd, Norcross'")]
    ];
    //https://docs.woocommerce.com/wc-apidocs/source-function-woocommerce_form_field.html#2064-2279
    //Can't use get_default here because $POST check messes up the required property below.
    $pharmacy_meta = get_meta('backup_pharmacy', $user_id);

    if ($pharmacy_meta) {
      $store = json_decode($pharmacy_meta);
      $pharmacy['options'] = [$pharmacy_meta => $store->name.', '.$store->street.', '.$store->city.', GA '.$store->zip.' - Phone: '.$store->phone];
    }

    //THis is necessary because http://woocommerce.wp-a2z.org/oik_api/wc_checkoutget_value/ overwrites an DOB in username with old DOB
    $birth_date = substr($user->user_login, -10) ?: $_POST['birth_date'];
    if ($user_id AND $birth_date != get_user_meta($user_id, 'birth_date', true)) {
      update_user_meta($user_id, 'birth_date', $birth_date);
    }

    return [
    'backup_pharmacy' => $pharmacy,
    'medications_other' => [
        'priority'  => 4,
        'label'     =>  __('List any other medication(s) or supplement(s) you are currently taking<i style="font-size:14px; display:block; margin-bottom:-20px">We will not fill these but need to check for drug interactions</i>'),
        'default'   => get_default('medications_other', $user_id)
    ],
    'allergies_none' => [
        'priority'  => 5,
        'type'   	  => 'radio',
        'label'     => __('Allergies'),
        'label_class' => ['radio'],
        'options'   => [99 => __('No Medication Allergies'), '' => __('Allergies Selected Below')],
    	  'default'   => get_default('allergies_none', $user_id)
    ],
    'allergies_aspirin' => [
        'priority'  => 6,
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Aspirin'),
        'default'   => get_default('allergies_aspirin', $user_id)
    ],
    'allergies_amoxicillin' => [
        'priority'  => 7,
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Amoxicillin'),
        'default'   => get_default('allergies_amoxicillin', $user_id)
    ],
    'allergies_ampicillin' => [
        'priority'  => 8,
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Ampicillin'),
        'default'   => get_default('allergies_ampicillin', $user_id)
    ],
    'allergies_azithromycin' => [
        'priority'  => 9,
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Azithromycin'),
        'default'   => get_default('allergies_azithromycin', $user_id)
    ],
    'allergies_cephalosporins' => [
        'priority'  => 10,
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Cephalosporins'),
        'default'   => get_default('allergies_cephalosporins', $user_id)
    ],
    'allergies_codeine' => [
        'priority'  => 11,
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Codeine'),
        'default'   => get_default('allergies_codeine', $user_id)
    ],
    'allergies_erythromycin' => [
        'priority'  => 12,
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Erythromycin'),
        'default'   => get_default('allergies_erythromycin', $user_id)
    ],
    'allergies_nsaids' => [
        'priority'  => 13,
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('NSAIDS e.g., ibuprofen, Advil'),
        'default'   => get_default('allergies_nsaids', $user_id)
    ],
    'allergies_penicillin' => [
        'priority'  => 14,
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Penicillin'),
        'default'   => get_default('allergies_penicillin', $user_id)
    ],
    'allergies_salicylates' => [
        'priority'  => 15,
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Salicylates'),
        'default'   => get_default('allergies_salicylates', $user_id)
    ],
    'allergies_sulfa' => [
        'priority'  => 16,
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Sulfa (Sulfonamide Antibiotics)'),
        'default'   => get_default('allergies_sulfa', $user_id)
    ],
    'allergies_tetracycline' => [
        'priority'  => 17,
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Tetracycline antibiotics'),
        'default'   => get_default('allergies_tetracycline', $user_id)
    ],
    'allergies_other' => [
        'priority'  => 18,
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     =>__('List Other Allergies Below').'<input class="input-text " name="allergies_other" id="allergies_other_input" value="'.get_default('allergies_other', $user_id).'">'
    ],
    'birth_date' => [
        'priority'  => 19,
        'label'     => __('Date of Birth'),
        'required'  => true,
        'input_class' => ['date-picker'],
        'autocomplete' => 'off',
        'custom_attributes' => ['readonly' => true],
        'default'   => $birth_date
    ],
    'phone' => [
      'priority'  => 20,
      'label'     => __('Phone'),
      'required'  => true,
      'type'      => 'tel',
      'validate'  => ['phone'],
      'autocomplete' => 'user-phone', //https://www.20spokes.com/blog/what-to-do-when-chrome-ignores-autocomplete-off-on-your-form
      'default'   => get_default('phone', $user_id)
    ]
  ];
}

//From: https://stackoverflow.com/questions/45516819/add-a-custom-action-button-in-woocommerce-admin-order-list
// Add your custom order status action button (for orders with "processing" status)
//101 Priority so it executes after duplicate-order plugin which uses 100 priority
add_filter( 'woocommerce_admin_order_actions', 'dscsa_admin_order_actions', 101, 2 );
function dscsa_admin_order_actions( $actions, $order ) {
    // Display the button for all orders that have a 'processing' status
    if ( $order->has_status( array( 'shipped-unpaid' ) ) ) {

        // Set the action button
        $actions['shipped-paid-mail'] = array(
            'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=shipped-paid-mail&order_id=' .  $order->get_id() ), 'woocommerce-mark-order-status' ),
            'name'      => __('Marked Paid By Mail', 'woocommerce' ),
            'action'    => "shipped-paid-mail", // keep "view" class for a clean button CSS
        );

        unset($actions['duplicate']); //just so pharm techs don't accidentally click this instead
    }
    return $actions;
}

// Set Here the WooCommerce icon for your action button
//List of icons https://rawgit.com/woothemes/woocommerce-icons/master/demo.html
add_action( 'admin_head', 'dscsa_add_custom_order_status_actions_button_css' );
function dscsa_add_custom_order_status_actions_button_css() {
    echo '<style>.wc-action-button-shipped-paid-mail::after { font-family: woocommerce !important; content: "\e02d" !important; }</style>';
}

//Display custom fields on account/details
add_action('woocommerce_admin_order_data_after_order_details', 'dscsa_admin_edit_account');
function dscsa_admin_edit_account($order) {

  $fields =
    order_fields($order->user_id, ordered_rxs($order))+
    shared_fields($order->user_id)+
    account_fields($order->user_id)+
    admin_fields($order->user_id);

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

add_filter('woocommerce_save_account_details_required_fields', 'dscsa_save_account_details_required_fields' );
function dscsa_save_account_details_required_fields( $required_fields ){
    unset( $required_fields['account_display_name'] );
    return $required_fields;
}

add_action( 'woocommerce_edit_account_form_start', 'dscsa_user_edit_account');
function dscsa_user_edit_account($user_id = null) {

  $fields = shared_fields($user_id)+account_fields($user_id);

  //debug_email( "woocommerce_edit_account_form_start $user_id", get_meta('billing_first_name').' | '.get_meta('billing_last_name').' | '.print_r($fields, true));

  //DISPLAY AUTOFILL DRUG TABLE.  BECAUSE OF COMPLEXITY DECIDED NOT TO PUT THIS IN ACCOUNT_FIELDS()

  //if (get_current_user_id() == 1559 || get_current_user_id() == 645) {

  //IF AVAILABLE, PREPOPULATE RX ADDRESS AND RXS INTO REGISTRATION
  //This hook seems to be called again once the checkout is being saved.
  //Also don't want run on subsequent orders - rx_source works well because
  //it is currently saved to user_meta (not sure why) and cannot be entered anywhere except the order page
  $patient_profile = patient_profile(
    get_meta('billing_first_name'), //use billing because get_user_meta() and get_meta() of account_first_name are empty
    get_meta('billing_last_name'),  //use billing because get_user_meta() and get_meta() of account_first_name are empty
    $fields['birth_date']['default'],
    $fields['phone']['default']
  );

  echo make_rx_table($patient_profile);

  return dscsa_echo_form_fields($fields);
}

function make_rx_table($patient_profile, $email = false) {

  $patient_profile = $patient_profile ?: []; //default argument was causing issues

  // New Prescriptions Sent to good pill, , , , Disabled Checkbox
  // Medicine Name, Next Refill Date, Days (QTY), Refills, Last Refill Input, Autofill Checkbox
  $fields['birth_date']['default'] = date_format(date_create($patient_profile[0]['birth_date']), 'Y-m-d'); //just in case user entered DOB incorrectly we can fix it in guardian

  $pat_autofill = $email
  ? 'Autofill'
  : woocommerce_form_field("pat_autofill", [
    'type' => 'checkbox',
    'label' => 'Autofill',
    'default' => $patient_profile[0]['pat_autofill'],
    'label_class' => ['pat_autofill'],
    'return' => true
  ]);

  $padding = $email ? "padding:16px 8px 16px 0px" : "padding:16px 8px";

  $table = "<table class='autofill_table' style='text-align:left'><tr><th style='width:400px; $padding'>Medication</th><th style='$padding'>Last&nbsp;Refill</th><th style='$padding'>Days&nbsp;(Qty)</th><th style='$padding'>Refills</th><th style='width:120px; padding:16px 4px'>Next&nbsp;Refill</th><th style='width:110px; font-weight:bold; $padding'>$pat_autofill</th></tr>";

  foreach ($patient_profile as $i => $rx) {

    $drug_name = substr($rx['drug_name'], 1, -1);

    if ( ! $drug_name OR $rx['script_status'] == 'Inactive') continue; //Empty orders will have one row with a blank drug name

    $refills_total = $rx['refills_total'];
    $is_refill     = $rx['is_refill'];
    $refill_date   = $rx['refill_date'];
    $autofill_date = $rx['autofill_date'];
    $gcn           = $rx['gcn_seqno'];
    $rx_id         = $rx['rx_id'];
    $in_order      = $rx['in_order'];
    $qty           = (int) $rx['dispense_qty'];


    //From Shopping-SHEET
    $dispenseDate    = $is_refill ? $rx['last_dispense_date'] : $rx['orig_disp_date'];
    $daysSinceRefill = floor((strtotime($rx['order_added']) - strtotime($dispenseDate))/60/60/24) || '';
    $isDispensed     = $rx['dispense_date'] ? !!$in_order : ($in_order && $daysSinceRefill && $daysSinceRefill < 4); //&& daysToRefill >= 15 removed because of Order 17109
    $inOrder         = $isDispensed || ($in_order && $refills_total);   //Even if its "in the order" it could be a pending or denied surescript refill request (order 7236) so need to make sure refills are available

    if ($inOrder)
      $autofill_date = 'Order '.explode('-', $in_order)[0];
    else if ($refills_total)
      $autofill_date = $autofill_date ? date_format(date_create($autofill_date), 'Y-m-d') : '';
    else if ($rx['script_status'] == 'Transferred Out')
      $autofill_date = 'Transferred';
    else if (strtotime($patient_profile[$i]['expire_date']) < time())
      $autofill_date = 'Rx Expired';
    else
      $autofill_date = 'No Refills';

    if ($rx['last_dispense_date']) { //New Rx that is just dispensed should show that date
      $tr_class    = "rx gcn$gcn";
      $last_refill = date_format(date_create($rx['last_dispense_date']), 'm/d');
      $next_refill = date_format(date_create($refill_date), 'Y-m-d');
      $day_qty = $rx['days_supply']." (".$qty.")";
    } else if ($autofill_date == 'Transferred') { //Never Filled Transferred Out
      $tr_class    = "transferred rx gcn$gcn";
      $last_refill = 'Never&nbsp;Filled';
      $next_refill = ''; //ideally we could do date('Y-m-d', strtotime('+2 days')) but sometimes its not included in the order
      $day_qty     = '';
    } else { //New Rx, Never Filled but not transferred out (yet?)
      $tr_class    = "new rx gcn$gcn";
      $last_refill = 'New Rx';
      $next_refill = ''; //ideally we could do date('Y-m-d', strtotime('+2 days')) but sometimes its not included in the order
      $day_qty     = '90';
    }

    $autofill_resume = $email
    ? ($autofill_date ?: '&nbsp;') //Maintain height of row
    : woocommerce_form_field("autofill_resume[$gcn]", [
      'type' => 'text',
      'default' => $autofill_date,
      'input_class' => ['next_fill'],
      'custom_attributes' => [
        'default' => $autofill_date,
        'next-fill' => $refills_total ? $next_refill : 'No Refills'
      ],
      'return' => true
    ]);

    $rx_autofill = $email
    ? ($rx['rx_autofill'] ? 'On' : 'Off')
    : woocommerce_form_field("rx_autofill[$gcn]", [
      'type' => 'checkbox',
      'default' => $rx['rx_autofill'],
      'input_class' => ['rx_autofill'],
      'return' => true
    ]);

    $table .= "<tr class='$tr_class' gcn='$gcn' style='font-size:14px'>".
      "<td class='drug_name'>".$drug_name.
      "</td><td class='last_refill'>".$last_refill.
      "</td><td class='day_qty'>".$day_qty.
      "</td><td class='refills_total'>".$refills_total.
      "</td><td style='padding:8px'>".$autofill_resume.
      "</td><td style='font-size:16px'>".$rx_autofill.
      "</td></tr>";

  }
  $new_rxs = $email
    ? ($patient_profile[0]['pat_autofill'] ? 'On' : 'Off')
    : '<input type="checkbox" class="input-checkbox new_rx_autofill" name="new_rx_autofill" value="1" disabled="true">';
  $table  .= "<tr style='font-size:14px'><td>NEW PRESCRIPTION(S) SENT TO GOOD PILL</td><td></td><td></td><td></td><td style='padding:8px'>&nbsp;</td><td style='font-size:16px'>$new_rxs</td></tr></table>";
  return $table;
}

function dscsa_echo_form_fields($fields) {
  foreach ($fields as $key => $field) {
    echo woocommerce_form_field($key, $field);
  }
}

add_action('woocommerce_lostpassword_form', 'dscsa_lostpassword_form');
function dscsa_lostpassword_form() {
  login_form('lostpassword');
  $shared_fields = shared_fields();
  $shared_fields['birth_date']['id'] = 'birth_date_lostpassword';
  $shared_fields['birth_date']['custom_attributes']['readonly'] = false;
  echo woocommerce_form_field('birth_date', $shared_fields['birth_date']);
}

add_action('woocommerce_login_form_start', 'dscsa_login_form');
function dscsa_login_form() {
  login_form('login');
  $shared_fields = shared_fields();
  $shared_fields['birth_date']['id'] = 'birth_date_login';
  $shared_fields['birth_date']['custom_attributes']['readonly'] = false;
  echo woocommerce_form_field('birth_date', $shared_fields['birth_date']);
}

add_action('woocommerce_register_form_start', 'dscsa_register_form');
function dscsa_register_form() {
  $account_fields = account_fields();
  $shared_fields = shared_fields();
  $shared_fields['birth_date']['id'] = 'birth_date_register';
  $shared_fields['birth_date']['custom_attributes']['readonly'] = false;
  $shared_fields['phone']['custom_attributes']['readonly'] = false;
  $shared_fields['phone']['autocomplete'] = 'tel'; //allow autocomplete on first page but not second

  echo woocommerce_form_field('language', $account_fields['language']);
  login_form('register');
  echo woocommerce_form_field('birth_date', $shared_fields['birth_date']);
  echo woocommerce_form_field('phone', $shared_fields['phone']);
}

function verify_username($id) {
  echo "<div id='verify_username_$id'>Legal Name: <span id='verify_first_name_$id'></span> <span id='verify_last_name_$id'></span> <span id='verify_birth_date_$id'></span></div>";
}

function login_form($id) {

  $first_name = [
    'type' => 'text',
    'class' => ['form-row-first'],
    'label'  => __('First name'),
    'required' => true,
    'id' => "first_name_$id",
    'default' => $_POST['first_name']
  ];

  $last_name = [
    'type' => 'text',
    'class' => ['form-row-last'],
    'label'  => __('Last name'),
    'required' => true,
    'id' => "last_name_$id",
    'default' => $_POST['last_name']
  ];

  echo woocommerce_form_field('first_name', $first_name);
  echo woocommerce_form_field('last_name', $last_name);
}

add_action('woocommerce_login_form', 'dscsa_login_form_acknowledgement');
function dscsa_login_form_acknowledgement() {
  verify_username('login');
}

add_action('woocommerce_register_form', 'dscsa_register_form_acknowledgement');
function dscsa_register_form_acknowledgement() {
  verify_username('register');
  echo woocommerce_form_field('certify',[
    'type'   	  => 'checkbox',
    'label'     => __("I certify that<br>(1) I understand this program provides medications to those who cannot afford them.<br>(2) I am eligible because my co-pays and/or deductibles are too high to afford or I don't have health insurance.<br>(3) I agree to Good Pill's <a href='/gp-terms'>Terms of Use</a> including receiving and paying for my refills automatically"),
    'required'  => true
  ]);
  //echo '<div style="margin-bottom:8px">'.__('By clicking "Register" below,').'</div>';
}


add_action('woocommerce_register_post', 'dscsa_register_post', 10, 3);
function dscsa_register_post($username, $email, $validation_errors) {

    //These are handled by the username check
    // if ( ! $_POST['first_name']) {
    //     $validation_errors->add('first_name_error', __('<strong>Error</strong>: First name is required!', 'text_domain'));
    // }
    //
    // if ( ! $_POST['last_name']) {
    //     $validation_errors->add('last_name_error', __('<strong>Error</strong>: Last name is required!', 'text_domain'));
    // }
    //
    // if ( ! $_POST['birth_date']) {
    //     $validation_errors->add('birth_date_error', __('<strong>Error</strong>: Birth date is required!', 'text_domain'));
    // }

    $birth_date = cleanBirthDate($_POST['birth_date']);

    if ( ! $birth_date) {
        $validation_errors->add('birth_date_error', __('Please make sure your date of birth is accurate!', 'text_domain'));
    }

    $phone = cleanPhone($_POST['phone']);

    if ( ! $phone) {
        $validation_errors->add('phone_error', __('A valid 10-digit phone number is required!', 'text_domain'));
    }

    if ( ! $_POST['certify']) {
        $validation_errors->add('certify_error', __('Certification checkbox is required!', 'text_domain'));
    }

    return $validation_errors;
}

function cleanBirthDate($birth_date) {
    if ( ! $birth_date) return false;

    $nondelimited = preg_match('/^\d{6}$|^\d{8}$/', $birth_date);

    if ($nondelimited) //Assume its MMDDYY or MMDDYYYY and add delimiters otherwise will fail date_create
      $birth_date = substr($birth_date, 0, 2).'/'.substr($birth_date, 2, 2).'/'.substr($birth_date, 4);

    $birth_date = date_create($birth_date);

    if ( ! $birth_date) return false;

    $birth_date = date_format($birth_date, 'Y-m-d'); //in case html type=date does not work (e.g. IE)

    $birth_array = explode('-', $birth_date);

    if ($birth_array[0] > date('Y'))
      $birth_array[0] -= 100;


    //debug_email('Clean Birth Date', print_r($birth_array, true)." |||| birth_array[0] > 1900 ".($birth_array[0] > 1900)." |||| birth_array[0] > '1900' ".($birth_array[0] > '1900'));


    return (checkdate($birth_array[1],$birth_array[2],$birth_array[0]) AND $birth_array[0] > 1900 AND $birth_array[0] < 2100) ? implode('-', $birth_array) : false;
}

function clean_field($field) {
  return sanitize_text_field(stripslashes(trim($field)));
}

add_action('plugins_loaded', 'dscsa_plugins_loaded');
function dscsa_plugins_loaded() {
  //Enable Fast User Switching to work with Usernames.  This needs to load BEFORE the "init" hook that the plugin uses
  if ($_GET['impersonate'] AND ! is_numeric($_GET['impersonate'])) {
    $login = preg_replace('/[^\w\d\s\-]/', '', $_GET['impersonate']);
    $user  = get_user_by('login', $login);
    debug_email('impersonate_by_name', 'Current User Id: '.get_current_user_id().'LOGIN: '.$login.' USER: '.print_r($user, true).' Is Admin: '.is_admin().' GET:'.print_r($_GET, true).' SERVER:'.print_r($_SERVER, true));

    if($user)
    {
       $_GET['impersonate'] = $user->ID;
    }
  }
}

//Customer created hook called to late in order to create username
//    https://github.com/woocommerce/woocommerce/blob/e24ca9d3bce1f9e923fcd00e492208511cdea727/includes/class-wc-form-handler.php#L1002
add_action('wp_loaded', 'dscsa_default_post_value');
function dscsa_default_post_value() {

  if ( ! $_POST) return;

  //Registration/Checkout/Account Details ?: (Admin?)
  $birth_date = $_POST['birth_date'] ?: get_meta('birth_date', $user_id);
  if ($birth_date) $_POST['birth_date'] =  cleanBirthDate(clean_field($birth_date));

  //Registration ?: Account Details ?: Checkout ?: admin page when changing user ?: admin page
  $first_name = $_POST['first_name'] ?: $_POST['account_first_name'] ?: $_POST['billing_first_name'] ?: $_POST['_billing_first_name'] ?: get_meta('first_name', $user_id);
  if ($first_name) $_POST['first_name'] = mb_convert_case(clean_field($first_name), MB_CASE_TITLE, "UTF-8");

  //Registration ?: Account Details ?: Checkout ?: admin page when changing user ?: admin page
  $last_name = $_POST['last_name'] ?: $_POST['account_last_name'] ?: $_POST['billing_last_name'] ?: $_POST['_billing_last_name'] ?: get_meta('last_name', $user_id);
  if ($last_name) $_POST['last_name'] = strtoupper(clean_field($last_name));

  if ($birth_date AND $first_name AND $last_name) {    //Set username for login, registration & user_login for lost password
     $_POST['username'] = str_replace("'", "", "$_POST[first_name] $_POST[last_name] $_POST[birth_date]");
     $_POST['user_login'] = $_POST['username'];

     if ( ! validate_username( $_POST['user_login'] )) {
       debug_email("invalid username", $_POST['user_login'].print_r($_POST, true));
     }
  }

  $email = $_POST['email'] ?: $_POST['account_email'] ?: $_POST['_billing_email'];
  if ($email) $_POST['email'] = clean_field($email);

  if ($_POST['username']) { //Satisfy required fields on different pages

    $defaultEmail = str_replace(" ", "_", clean_field($_POST['username']))."@goodpill.org";

    if ($_POST['register'] AND ! $_POST['email'])
      $_POST['email'] = $defaultEmail;

    if ($_POST['rx_source'] AND ! $_POST['email'])
      $_POST['email'] = $defaultEmail;

    if ($_POST['save_addess'] AND ! $_POST['billing_email'])
      $_POST['billing_email'] = $defaultEmail;

    if ($_POST['save_account_details'] AND ! $_POST['account_email'])
      $_POST['account_email'] = $defaultEmail;
  }

  //For resetting password
  $phone = $_POST['phone'] ?: $_POST['billing_phone'] ?: $_POST['user_login'];

  if ($phone) {

     $_POST['raw_phone'] = $phone;

     $phone = cleanPhone(clean_field($phone));

     if ( ! $phone) return;

     $_POST['phone'] = $phone;
  }
}

function cleanPhone($phone) { //get rid of all delimiters and a leading 1 if it exists
  $phone = preg_replace('/\D+/', '', $phone);
  if (strlen($phone) == 11 AND substr($phone, 0, 1) == 1)
    return substr($phone, 1, 10);

  return strlen($phone) == 10 ? $phone : NULL;
}

add_filter('random_password', 'dscsa_random_password');
function dscsa_random_password($password) {
  return $_POST['phone'] ?: $password;
}

//After Registration, set default shipping/billing/account fields
//then save the user into GuardianRx
add_action('woocommerce_created_customer', 'customer_created');
function customer_created($user_id) {
  debug_email('New Webform Patient', print_r(sanitize($_POST), true));

  foreach(['', 'billing_', 'shipping_'] as $field) {
    update_user_meta($user_id, $field.'first_name', $_POST['first_name']);
    update_user_meta($user_id, $field.'last_name', $_POST['last_name']);
  }
  update_user_meta($user_id, 'birth_date', $_POST['birth_date']);
  update_user_meta($user_id, 'language', $_POST['language']);
  update_user_meta($user_id, 'email', $_POST['email']);

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

add_filter('site_url', 'dscsa_site_url', 10, 4);
function dscsa_site_url($site_url) {
  //debug_email('dscsa_wp_redirect dscsa_site_url', print_r(func_get_args(), true));
  return $site_url;
}

add_filter('admin_url', 'dscsa_admin_url', 10, 4);
function dscsa_admin_url($admin_url) {
  //debug_email('dscsa_wp_redirect dscsa_admin_url', print_r(func_get_args(), true));
  return $admin_url;
}

add_filter('home_url', 'dscsa_home_url', 10, 4);
function dscsa_home_url($home_url) {
  //debug_email('dscsa_wp_redirect dscsa_home_url', print_r(func_get_args(), true));
  return $home_url;
}


add_filter ('wp_redirect', 'dscsa_wp_redirect');
function dscsa_wp_redirect($location) {

  debug_email('dscsa_wp_redirect', 'Current User Id: '.get_current_user_id().' REQUEST_URI: '.home_url($_SERVER['REQUEST_URI']).' Is Admin: '.is_admin().' Location: '.$location.' GET:'.print_r($_GET, true).' SERVER:'.print_r($_SERVER, true));


  //Not sure how or why domain changes to salesforce but need to revert it back
  if (substr($location, 0, 33) == 'https://sirum.lightning.force.com') {
    debug_email('dscsa_wp_redirect changing base url', $location, substr($location, 33), home_url(substr($location, 33)));
    $location = 'https://www.goodpill.org/wp-admin/'.substr($location, 33);
  }

  //After successful order, add another item back into cart, so that the "Request Refills" page continues to have a CheckOut which requires >=1 item in the "Cart"
  //https://www.goodpill.org/order-confirmation/
  if (substr($location, -20) == '/order-confirmation/')
    return $location.'?add-to-cart=8';

  //If someone Saves Acount Details, bring them back to that page not the "Request Refills Page"
  if ($_POST['save_account_details'])
    return home_url('/account/details/');


  //If Admin is impersonating user and tries to impersonate a different user before logging out of old user, they will be redirected to old users page
  //So we logout of that user first then redirect to that page again
  if ( ! $_GET['imp'] AND is_impersonating() AND $_SERVER['SCRIPT_NAME'] == '/wp-admin/index.php' AND substr($location, -9) == '/account/') {
    debug_email('dscsa_wp_redirect end impersonating', 'Current User Id: '.get_current_user_id().' REQUEST_URI: '.home_url($_SERVER['REQUEST_URI']).' Is Admin: '.is_admin().' Location: '.$location.' GET:'.print_r($_GET, true).' SERVER:'.print_r($_SERVER, true));
    $_SERVER['HTTP_REFERER'] = home_url($_SERVER['REQUEST_URI']);
    //wp_destroy_current_session();
    wp_logout(); //Fast User Switching Hooks into this and redirect to $_SERVER['HTTP_REFERER'])
    exit;
  }

  //If Admin is impersonating user with "Fast-User-Switching" Plugin, take them to the account details page
  //If they are already impersonating (logged in as a differing user) log them out of that user first
  //If they are not registered take them to the default New Order page which is the 2nd half of registration
  if ($_GET['imp'] AND is_impersonating() AND is_registered()) {
    //debug_email('dscsa_wp_redirect start impersonating', 'Current User Id: '.get_current_user_id().' REQUEST_URI: '.home_url($_SERVER['REQUEST_URI']).' Is Admin: '.is_admin().' Location: '.$location.' GET:'.print_r($_GET, true).' SERVER:'.print_r($_SERVER, true));
    return home_url('/account/details/'); //If user is registered already, switch to account/details rather than new order.  Otherwise goto new order page so we complete the registration.
  }

  //When logging out of an impersonated user, bring admin back to that person's order page
  if ($_GET['action'] == 'logout' AND is_impersonating()) {
    WC()->cart->remove_coupons(); //applied coupons seem to follow the admin user otherwise
    return home_url('/wp-admin/edit.php?s&post_status=all&post_type=shop_order&_customer_user='.get_current_user_id()); //return home_url('/wp-admin/edit.php?post_type=ticket&author='.get_current_user_id()); //Switch to user's tickets rather than ??
  }

  return $location;
}

add_action('admin_page_access_denied', 'dscsa_admin_page_access_denied');
function dscsa_admin_page_access_denied($var) {

  debug_email('dscsa_wp_redirect dscsa_admin_page_access_denied', 'Current User Id: '.get_current_user_id().' REQUEST_URI: '.home_url($_SERVER['REQUEST_URI']).' Is Admin: '.is_admin().' Location: '.$var.' GET:'.print_r($_GET, true).' SERVER:'.print_r($_SERVER, true));

  if (is_impersonating()) {
    $_SERVER['HTTP_REFERER'] = home_url($_SERVER['REQUEST_URI']);
    //wp_destroy_current_session();
    wp_logout(); //Fast User Switching Hooks into this and redirect to $_SERVER['HTTP_REFERER'])
    exit;
  }

}

function is_impersonating() {
  return strpos($_SERVER['HTTP_COOKIE'], 'impersonated_by') !== false;
}

function is_registered() {
   return get_user_meta(get_current_user_id(), 'rx_source', true);
}

add_filter ('woocommerce_account_menu_items', 'dscsa_my_account_menu');
function dscsa_my_account_menu($nav) {
  $nav['dashboard'] = __('Request Refills');
  $nav['payment-methods'] = __('Autopay');
  return $nav;
}

add_action('woocommerce_save_account_details_errors', 'dscsa_account_validation');
function dscsa_account_validation() {
   dscsa_validation(shared_fields()+account_fields(), true);
}
add_action('woocommerce_checkout_process', 'dscsa_order_validation');
function dscsa_order_validation() {
   dscsa_validation(order_fields()+shared_fields(), false);

   if ($_POST['rx_source'] == 'pharmacy' AND ! $_POST['transfer'])
     wc_add_notice('<strong>'.__('Medications Required').'</strong> '.__('Please select the medications you want us to transfer.  If they do not appear on the list, then we do not have them in-stock'), 'error');
}

function dscsa_validation($fields, $required) {
  $allergy_missing = true;
  foreach ($fields as $key => $field) {
    if ($required AND $field['required'] AND ! $_POST[$key]) {
      wc_add_notice('<strong>'.__($field['label']).'</strong> '.__('is a required field'), 'error');
    }

    if (substr($key, 0, 10) == 'allergies_' AND $_POST[$key])
 	  $allergy_missing = false;
  }

  if ($allergy_missing) {
    wc_add_notice('<strong>'.__('Allergies').'</strong> '.__('is a required field'), 'error');
  }
}

function sanitize($data) {
  $sanitized = $data;
  unset($sanitized['password_current'], $sanitized['password_1'], $sanitized['password_2']);
  return $sanitized;
}

// replace woocommerce id with guardian one
add_filter( 'woocommerce_order_number', 'dscsa_invoice_number', 10 , 2);
function dscsa_invoice_number($order_id, $order) {
  return get_post_meta($order_id, 'invoice_number', true) ?: 'Pending-'.$order_id;
}

add_filter( 'woocommerce_shop_order_search_fields', 'dscsa_order_search_fields');
function dscsa_order_search_fields( $search_fields ) {
	//array_push( $search_fields, 'invoice_number' );
  //return $search_fields;
	return []; //$this forces search_orders to skip slow query so we can do our own fast query in woocommerce_shop_order_search_results
}

//Add Phone Number Search to Fast User Switching Plugin on Top Menu
//Plugin as no hooks but uses WP_User_Quey which does have Hooks
//https://github.com/WordPress/WordPress/blob/master/wp-includes/class-wp-user-query.php

add_action('pre_get_users', 'dscsa_pre_get_users');
function dscsa_pre_get_users($class) {

  if ( ! $class->query_vars['search']) return;

  $phone = cleanPhone($class->query_vars['search']);

  if ($phone) {
    $class->query_vars['meta_key']   = 'phone';
    $class->query_vars['meta_value'] = $phone;
    unset($class->query_vars['search']);
  }

  //debug_email("dscsa_pre_get_users", print_r($class->query_vars, true));
}

add_filter('woocommerce_shop_order_search_results',  'dscsa_shop_order_search_results', 10, 3);
function dscsa_shop_order_search_results($order_ids, $term, $search_fields) {

  /*
  global $wpdb;
  $new_order_ids = array_unique(
		array_merge(
			$order_ids,
			faster_wc_search_orders($term),
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items as order_items WHERE order_item_name LIKE %s",
					'%' . $wpdb->esc_like( wc_clean( $term ) ) . '%'
				)
			)
		)
	);*/

  return faster_wc_search_orders($term); //$new_order_ids; //$order_ids;
}


//On new order and account/details save account fields back to user
//TODO should changing checkout fields overwrite account fields if they are set?
add_action('woocommerce_save_account_details', 'dscsa_save_account');
function dscsa_save_account($user_id) {

  $patient_id = dscsa_save_patient($user_id, shared_fields($user_id) + account_fields($user_id));
  $profile = update_autofill($patient_id, $_POST['pat_autofill'], $_POST['rx_autofill'], $_POST['autofill_resume']);

  debug_email("dscsa_save_account_details", print_r($_POST, true).print_r($profile, true));

  if (count($profile) AND $_POST['email'])
    wp_mail($_POST['email'], "Summary of your Good Pill Rx(s)", "<html><body>We saved your account.  Below is a summary of your current Rx(s) and any upcoming Order(s). Please let us know if you have any questions.<br><br>Thanks,<br>The Good Pill Team<br><br>".make_rx_table($profile, true)."</body></html>", ['Content-Type: text/html; charset=UTF-8']);

  update_email($patient_id, $_POST['email']);
}

//Update an order with trackin g number and date_shipped
global $already_run;
add_filter('rest_request_parameter_order', 'dscsa_rest_update_order', 10, 2);
function dscsa_rest_update_order($order, $request) {

  global $already_run;

  if ( ! $already_run AND $request->get_method() == 'PUT' AND substr($request->get_route(), 0, 14) == '/wc/v2/orders/') {
      $already_run = true;

      //debug_email('dscsa_rest_update_order', print_r($order, true));
    try {

      $invoice_number = $request['id'];
      $json_params    = $request->get_json_params();
      $meta_data      = $json_params['meta_data'];
      $no_shipping    = empty($json_params['shipping_lines']) || empty($json_params['shipping_lines'][0]['total']);

      //debug_email("no guardian id was provided in this REST request",

      foreach ($meta_data as $val) {
        if ($val['key'] == 'guardian_id') {
          $guardian_id = $val['value'];
        }
      }

      if ( ! $guardian_id) {
        debug_email("no guardian id was provided in this REST request", $invoice_number.print_r($meta_data, true).print_r($request, true));
      }

      $orders = get_woocommere_orders($guardian_id, $invoice_number);

      //Sometimes Guardian order id changes so "get_orders_by_invoice_number" won't work
      if (count($orders) < 1) {
        debug_email("Exact invoice number could not be found, using guardian_id instead", $invoice_number.print_r($meta_data, true).print_r($request, true));
        $orders = get_pending_orders_by_guardian_id($guardian_id);
      }

      $count = count($orders);

      if ($count > 1) {
        debug_email("dscsa_rest_update_order: multiple orders", $invoice_number.' | using first one /wc/v2/orders/'.$orders[0]->post_id.' '.print_r($orders, true).' '.print_r($request, true));
      }

      //debug_email("dscsa_rest_update_order: debug", $invoice_number.' | using first one /wc/v2/orders/'.$orders[0]->post_id.' '.print_r($orders, true).' '.print_r($request, true));

      if ($count > 0) {

        //This fixes shipping_lines being cumulative by deleting all shipping lines before we add the new one
        if ( ! $no_shipping) {
          $order = new WC_Order($orders[0]->post_id);
          $order->remove_order_items('shipping');
        }

        $request['id'] = $orders[0]->post_id;
      }

    } catch (Exception $e) {
      debug_email("dscsa_rest_update_order: error", print_r($e, true).' | '.$request['id'].' | /wc/v2/orders/'.$orders[0]->post_id.' '.print_r($orders, true).' '.print_r($request, true));
    }

    //Move this outside of try/catch block since this error should go back to the client
    if ($count == 0) {
      debug_email("dscsa_rest_update_order: no orders", $invoice_number.' | '.print_r($orders, true).' '.print_r($request['body'], true).' '.print_r($request, true));
  	  return new WP_Error('no_matching_invoice', __( "Order #$invoice_number has $count matches", 'woocommerce' ), print_r($request['body'], true));
    }
  }

  return $order;
}

//Create an order for a guardian refill
add_filter('woocommerce_rest_pre_insert_shop_order_object', 'dscsa_rest_create_order', 10, 3);
function dscsa_rest_create_order($order, $request, $creating) {

  if ( ! $creating) return $order;

  //debug_email('dscsa_rest_create_order', print_r($order, true));
  //debug_email("dscsa_rest_create_order", print_r($creating, true));

  $invoice_number = $order->get_meta('invoice_number', true);
  $guardian_id = $order->get_meta('guardian_id', true);

  $orders = get_woocommere_orders($guardian_id, $invoice_number);

  if (count($orders))
    return new WP_Error('refill_order_already_exists', __( "Refill Order #$invoice_number already exists", 'woocommerce' ), 200);

  $users = get_users_by_guardian_id($guardian_id);

  if ( ! count($users))
    return new WP_Error( 'could_not_find_user_by_guardian_id', __( "Could not find the user for Guardian Patient Id #$guardian_id", 'woocommerce' ), 400);

  $order->set_customer_id($users[0]->user_id);

  return $order;
}

function faster_wc_search_orders($invoice_number) {
  global $wpdb;
  return $wpdb->get_col("SELECT post_id FROM wp_postmeta WHERE meta_key='invoice_number' AND meta_value = '$invoice_number'");
}

function get_users_by_guardian_id($guardian_id) {
  global $wpdb;
  return $wpdb->get_results("SELECT user_id FROM wp_usermeta WHERE meta_key='guardian_id' AND meta_value = '$guardian_id'");
}

function get_woocommere_orders($guardian_id, $invoice_number) {
  global $wpdb;
  return $wpdb->get_results("SELECT meta1.post_id FROM wp_posts JOIN wp_postmeta meta1 ON wp_posts.id = meta1.post_id JOIN wp_postmeta meta2 ON wp_posts.id = meta2.post_id WHERE meta1.meta_key='guardian_id' AND meta1.meta_value = '$guardian_id' AND meta2.meta_key='invoice_number' AND meta2.meta_value = '$invoice_number' ORDER BY wp_posts.id DESC");
}

//Sometimes Guardian order id changes so "get_orders()" won't work
function get_pending_orders_by_guardian_id($guardian_id) {
  global $wpdb;
  return $wpdb->get_results("SELECT post_id FROM wp_postmeta JOIN wp_posts ON wp_posts.id = post_id WHERE meta_key='guardian_id' AND meta_value = '$guardian_id' AND (post_status = 'wc-pending' OR post_status = 'wc-on-hold') ORDER BY post_id DESC");
}

/*
function get_orders_by_invoice_number($invoice_number) {
  global $wpdb;
  return $wpdb->get_results("SELECT post_id FROM wp_postmeta WHERE (meta_key='invoice_number') AND meta_value = '$invoice_number' ORDER BY post_id DESC");
}
*/

function ordered_rxs($order) {
  return $order->get_meta('transfer') ?: ($order->get_meta('rxs') ?: ["Awaiting RX(s) from your doctor"]);
}

add_action('woocommerce_order_details_after_order_table', 'dscsa_show_order_invoice');
function dscsa_show_order_invoice($order) {
    $invoice_doc_id  = $order->get_meta('invoice_doc_id', true);
    $tracking_number = $order->get_meta('tracking_number', true);
    $date_paid    = $order->get_date_paid();
    $date_shipped = $order->get_meta('date_shipped', true);
    $address = $order->get_formatted_billing_address();

    echo '<button style="float:right; margin-top:-40px" onclick="window.print()">Print</button>';

    //TODO REFACTOR THIS WHOLE PAGE TO BE LESS HACKY
    echo '<style>.woocommerce-customer-details, .woocommerce-order-details__title, .woocommerce-table--order-details { display:none }</style>';

    if ($date_shipped OR $tracking_number) {

       echo "<h4>Your order was";

       if ($date_paid)
         echo " paid on ".substr($date_paid, 0, 10)." and ";

       echo "shipped";

       if ($date_shipped)
         echo "on <mark class='order-date'>$date_shipped</mark>";

       if ($tracking_number)
         echo " with tracking number <a target='_blank' href='https://tools.usps.com/go/TrackConfirmAction?tLabels=$tracking_number'>$tracking_number</a>";

       echo $address ? " to</h4><address>$address</address>" : "</h4>";


    } else {
      echo "<script>jQuery(function() { upgradeOrdered(function(select) { var rxs = select.data('rxs'); select.val(rxs).change(); select.on('select2:unselecting', preventDefault);}) })</script>";

      echo woocommerce_form_field('ordered[]', [
        'type'   	  => 'select',
        'label'     => __('Here are the Rx(s) in your order.  Call us to make a change'),
        'options'   => [''],
        'custom_attributes' => ['data-rxs' => json_encode(ordered_rxs($order))]
      ]);

      echo "<h4>Order will be shipped to</h4><address>$address</address>";
    }

    if ($invoice_doc_id) {
      $url  = "https://docs.google.com/document/d/$invoice_doc_id/pub?embedded=true";
      $top  = '-65px';
      $left = '-60px';
    } else {
      $url = "https://www.goodpill.org/order-confirmation";
      $top = '0px';
      $left = '-7%';
    }

    echo "<iframe src='$url' style='border:none; padding:0px; overflow:hidden; width:100%; height:1800px; position:relative; z-index:-1; left:$left; top:$top' scrolling='no'></iframe>";
}

//woocommerce_checkout_update_order_meta
global $alreadySaved;
add_action('woocommerce_before_order_object_save', 'dscsa_save_order', 10, 2);
function dscsa_save_order($order, $data) {

  try {
    global $alreadySaved;

    if ($alreadySaved OR ! $_POST['rx_source']) return; //$_POST is not set on duplicate order, and only payment method fields are set in order-pay

    $alreadySaved = true;

    $user_id = $order->get_user_id();
    $is_registered = is_registered(); //dscsa_save_patient will overwrite and set as true so save current value here

    //THIS MUST BE CALLED FIRST IN ORDER TO CREATE GUARDIAN ID
    //TODO should save if they don't exist, but what if they do, should we be overriding?
    $patient_id = dscsa_save_patient($user_id, shared_fields($user_id) + order_fields($user_id) + ['order_comments' => true]);

    $invoice_number = $order->get_meta('invoice_number', true);

    if ($invoice_number && ! is_admin())
      debug_email("INVOICE# IS ALREDY SAVED.  NOT CREATING ORDER", "Patient ID: $patient_id\r\n\r\nInvoice #:$invoice_number \r\n\r\nMSSQL:".print_r(mssql_get_last_message(), true)."\r\n\r\nOrder Meta Invoice #:".$order->get_meta('invoice_number', true)."\r\n\r\nPOST:".print_r(sanitize($_POST), true).print_r($order, true));

    if ( ! $invoice_number) {
      $guardian_order = get_guardian_order($patient_id, $_POST['rx_source'], $_POST['order_comments'], $is_registered);
      $invoice_number = $guardian_order['invoice_nbr'];
    }

    if ( ! $invoice_number)
      debug_email("NO INVOICE #", "Patient ID: $patient_id\r\n\r\nInvoice #:$invoice_number \r\n\r\nMSSQL:".print_r(mssql_get_last_message(), true)."\r\n\r\nOrder Meta Invoice #:".$order->get_meta('invoice_number', true)."\r\n\r\nPOST:".print_r(sanitize($_POST), true));

    if ( ! is_admin()) {
      wp_mail('hello@goodpill.org', 'New Webform Order', "New Order #$invoice_number Webform Complete. Source: ".print_r($_POST['rx_source'], true)."\r\n\r\n".print_r($_POST['rxs'], true)."\r\n\r\n".print_r($_POST['transfer'], true));
      debug_email("New Webform Order", "New Order #$invoice_number.  Patient #$patient_id\r\n\r\n".print_r($guardian_order, true).print_r(sanitize($_POST), true));
    }

    $order->update_meta_data('invoice_number', $invoice_number);

    update_email($patient_id, $_POST['email']);

    $coupon = $order->get_used_coupons();
    debug_email("order->get_used_coupons", print_r($coupon, true));
    $coupon = end($coupon);
    if ($coupon == 'onetimecoupon' || $coupon == 'removecoupon') //don't persist these cookies
      $coupon = null;

    $card = get_meta('stripe', $user_id);

    update_user_meta($user_id, 'coupon', $coupon);

    if (is_pay_coupon($coupon))
      update_payment_method($patient_id, "PAY BY COUPON: $coupon");
    else if ($card)
      update_payment_method($patient_id, "PAY BY CARD: $card[type] $card[last4]");
    else
      update_payment_method($patient_id, "PAY BY MAIL");

    update_card_and_coupon($patient_id, $card, $coupon);

    //Underscore is for saving on the admin page, no underscore is for the customer checkout
    $address_1   = $_POST['_billing_address_1'] ?: $_POST['billing_address_1'];
    $address_2   = $_POST['_billing_address_2'] ?: $_POST['billing_address_2'];
    $city        = $_POST['_billing_city']      ?: $_POST['billing_city'];
    $postcode    = $_POST['_billing_postcode']  ?: $_POST['billing_postcode'];

    $address = update_shipping_address($patient_id, $address_1, $address_2, $city, $postcode);

    if ($_POST['rx_source'] == 'pharmacy') {
      $texts = array_map(function($rx) { return json_decode(stripslashes($rx))->text; }, $_POST['transfer']);
      add_preorder($patient_id, $invoice_number, $texts, $_POST['backup_pharmacy']);
      $order->update_meta_data('transfer', $texts);
    } else if ($_POST['rx_source'] == 'erx') {
      $script_nos = array_map(function($rx) { return json_decode(stripslashes($rx))->script_no; }, $_POST['rxs']);
      $texts = array_map(function($rx) { return json_decode(stripslashes($rx))->text; }, $_POST['rxs']);
      add_rxs_to_order($invoice_number, $script_nos);
      $order->update_meta_data('rxs', $texts);
    } else {
      debug_email("order saved without rx_source", "$patient_id | $invoice_number ".print_r($guardian_order, true).print_r(sanitize($_POST), true).print_r(mssql_get_last_message(), true));
    }

    debug_email("saved order 1", "$patient_id | $invoice_number ".print_r($guardian_order, true).print_r(sanitize($_POST), true).print_r(mssql_get_last_message(), true));

  } catch (Exception $e) {
    debug_email("woocommerce_before_order_object_save", "$patient_id | $invoice_number ".$e->getMessage()." ".print_r(sanitize($_POST), true).print_r(mssql_get_last_message(), true));
  }
}

add_action('woocommerce_customer_save_address', 'dscsa_customer_save_address', 10, 2);
function dscsa_customer_save_address($user_id, $load_address) {
  debug_email('woocommerce_customer_save_address', get_meta('billing_address_1')."\r\n\r\n".print_r(sanitize($_POST), true)."\r\n\r\n".print_r($load_address, true));

  $patient_id = get_meta('guardian_id', $user_id);
  if ($patient_id) {//in case they fill this out before saving account details or a new order
    update_shipping_address(
      $patient_id,
      $_POST['billing_address_1'],
      $_POST['billing_address_2'],
      $_POST['billing_city'],
      $_POST['billing_postcode']
    );
  }
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

  //checkout, account details, admin page with correct user, admin page when changing user
  debug_email('dscsa_save_patient_start', is_registered()."|||".print_r(get_user_meta($user_id), true)."|||".print_r($_POST, true));

  //Detect Identity Changes and Email Us a Warning
  if ( ! is_admin()) {

    global $woocommerce;

    $old_name   = [
     'birth_date' => substr($woocommerce->customer->username, -10),
     'first_name' => $woocommerce->customer->first_name,
     'last_name'  => $woocommerce->customer->last_name,
     'email'  => $woocommerce->customer->email
    ];

    $firstname_changed = strtolower($_POST['first_name']) != strtolower($old_name['first_name']);
    $lastname_changed  = strtolower($_POST['last_name']) != strtolower($old_name['last_name']);
    $birthdate_changed = $_POST['birth_date'] != $old_name['birth_date'];
    $email_changed = ((strtolower($_POST['email'] ?: $_POST['account_email']) != strtolower($old_name['email'])) AND (strpos($old_name['email'], '@goodpill.org') === false) AND (strlen($old_name['email']) > 0));

    if ($firstname_changed OR $lastname_changed OR $birthdate_changed OR $email_changed) {
      //wp_mail('hello@goodpill.org', 'Patient Name Change', print_r(sanitize($_POST), true)."\r\n\r\n".print_r($order, true));
      debug_email('Warning Patient Identity Changed!', "firstname_changed $firstname_changed | lastname_changed $lastname_changed | birthdate_changed $birthdate_changed | email_changed $email_changed.\r\n\r\nNew Info: $_POST[first_name] $_POST[last_name] $_POST[birth_date] $_POST[email]\r\n\r\nstrpos($old_name[email], '@goodpill.org'): ".strpos($old_name['email'], '@goodpill.org')."\r\n\r\nOld Info:".print_r($old_name, true)."\r\n\r\nPOST:".print_r(sanitize($_POST), true));
    }
  }

  if ( ! $_POST['first_name'] OR ! $_POST['last_name'] OR ! $_POST['birth_date']) {
    debug_email('DEBUG dscsa_save_patient', print_r([$_POST['first_name'], $_POST['last_name'], $_POST['birth_date']], true)."|||".print_r(get_user_meta($user_id), true)."|||".print_r($_POST, true));
    return;
  }

  //debug_email('dscsa_save_patient_1', is_registered()."|||".print_r(get_user_meta($user_id), true)."|||".print_r($_POST, true));

  //TODO Enable Admin to Pick a different Patient ID if there are multiple matches
  $patient_id = add_patient(
    $_POST['first_name'],
    $_POST['last_name'],
    $_POST['birth_date'],
    $_POST['phone'],
    get_meta('language', $user_id)
  );

  //debug_email('dscsa_save_patient_2', is_registered()."|||".print_r(get_user_meta($user_id), true)."|||".print_r($_POST, true));

  update_user_meta($user_id, 'guardian_id', $patient_id);

  $allergies = [];

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
    $val = clean_field($_POST[$key]);

    if ($key == 'backup_pharmacy') {
      update_pharmacy($patient_id, $val);
    }

    if ($key == 'medications_other') {
      append_comment($patient_id, $val);
    }

    if ($key == 'phone') {
      update_phone($patient_id, $_POST['phone']);
      update_user_meta($user_id, 'billing_phone', $_POST['phone']); //this saves it on the user page as well
    }

    update_user_meta($user_id, $key, $val);

    if (substr($key, 0, 10) == 'allergies_') {
      $allergies[$key] = str_replace("'", "''", $val);
    }
  }

  add_remove_allergies($patient_id, $allergies);

  debug_email("patient saved", $patient_id.' '.print_r(sanitize($_POST), true).' '.print_r($fields, true).print_r(get_user_meta($user_id), true));

  return $patient_id;
}

add_filter( 'woocommerce_email_headers', 'dscsa_email_headers', 10, 2);
function dscsa_email_headers( $headers, $template) {
		return array($headers, "Bcc:hello@goodpill.org\r\n");
}

//Tried woocommerce_status_changed, woocommerce_status_on-hold, woocommerce_thankyou and setting it before_order_object_save and nothing else worked
add_filter('wp_insert_post_data', 'dscsa_update_order_status');
function dscsa_update_order_status( $data) {

    //debug_email("dscsa_update_order_status", is_admin()." | ".strlen($_POST['rxs'])." | ".(!!$_POST['rxs'])." | ".var_export($_POST['rxs'], true)." | ".print_r(sanitize($_POST), true)." | ".print_r($data, true));
    if (is_admin() OR $data['post_type'] != 'shop_order') return $data;

    if ($_POST['rx_source'] != 'pharmacy' && $_POST['rxs'] && $_POST['rxs'][0]) { //Skip on-hold and go straight to processing if set.  In some case rather than being [] (falsey) rxs was [0 => ''] (truthy), so checking first element too
      debug_email("New Order - Rx Received (dscsa_update_order_status)", print_r($data, true).print_r(sanitize($_POST), true).print_r(mssql_get_last_message(), true).print_r($_SERVER, true).print_r($_SESSION, true).print_r($_COOKIE, true));

      $data['post_status'] = 'wc-processing';
    } else if($_POST['rx_source']) { //checking for rx_source ensures that API calls to update status still work.  Even though we are not "capturing charge" setting "needs payment" seems to make the status goto processing

      if ($_POST['rx_source'] == 'pharmacy') {
        $subject = "Awaiting Transfer";
        $data['post_status'] = 'wc-awaiting-transfer';
      }
      else if ($_POST['rx_source'] == 'erx') {
        $subject = "Awaiting Doctor Rxs";
        $data['post_status'] = 'wc-awaiting-rx';
      }
      else {
        $subject = "This shouldn't have happend ".$_POST['rx_source'];
        $data['post_status'] = 'wc-processing';
      }

      debug_email("New Order - $subject (dscsa_update_order_status)", print_r($data, true).print_r(sanitize($_POST), true).print_r(mssql_get_last_message(), true).print_r($_SERVER, true).print_r($_SESSION, true).print_r($_COOKIE, true));

    } else if($_POST['payment_method'] == 'stripe') { //order-pay page
      debug_email("Order Paid Manually (dscsa_update_order_status)", print_r($data, true).print_r(sanitize($_POST), true).print_r(mssql_get_last_message(), true).print_r($_SERVER, true).print_r($_SESSION, true).print_r($_COOKIE, true));

      $data['post_status'] = $data['post_status'] == 'wc-failed' ? 'wc-shipped-payfail' : 'wc-shipped-paid-card';
    }

    //debug_email("dscsa_update_order_status 2", print_r($data, true));
    return $data;
}

//On hold emails only triggered in certain circumstances, so we need to trigger them manually
//https://github.com/woocommerce/woocommerce/blob/f8552ebbad227293c7b819bc4b06cbb6deb2c725/includes/emails/class-wc-email-customer-on-hold-order.php#L39
//woocommerce_new_order hook was causing wc_get_order() to sometimes fail from being called to early (order might not actually be created yet)
add_action('woocommerce_thankyou', 'dscsa_new_order');
function dscsa_new_order($order_id) {
  try { // Select the email we want & trigger it to send
    debug_email("dscsa_new_order", print_r($order_id, true).print_r(sanitize($_POST), true).print_r(mssql_get_last_message(), true));
  } catch (Exception $e) {
    debug_email("dscsa_new_order FAILED", print_r($e, true).$e->getMessage());
  }
}

/**
 * Unhook and remove WooCommerce default emails.
 */
add_action( 'woocommerce_email', 'unhook_those_pesky_emails' );

function unhook_those_pesky_emails( $email_class ) {

		/**
		 * Hooks for sending emails during store events
		 **/
		//remove_action( 'woocommerce_low_stock_notification', array( $email_class, 'low_stock' ) );
		//remove_action( 'woocommerce_no_stock_notification', array( $email_class, 'no_stock' ) );
		//remove_action( 'woocommerce_product_on_backorder_notification', array( $email_class, 'backorder' ) );

		// New order emails
		remove_action( 'woocommerce_order_status_pending_to_processing_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
		//remove_action( 'woocommerce_order_status_pending_to_completed_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
		remove_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
		remove_action( 'woocommerce_order_status_failed_to_processing_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
		//remove_action( 'woocommerce_order_status_failed_to_completed_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
		remove_action( 'woocommerce_order_status_failed_to_on-hold_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );

    remove_action( 'woocommerce_order_status_on-hold_to_processing_notification', array( $email_class->emails['WC_Email_Customer_Processing_Order'], 'trigger' ) );

		// Processing order emails
		remove_action( 'woocommerce_order_status_pending_to_processing_notification', array( $email_class->emails['WC_Email_Customer_Processing_Order'], 'trigger' ) );
		remove_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $email_class->emails['WC_Email_Customer_Processing_Order'], 'trigger' ) );

		// Completed order emails
		//remove_action( 'woocommerce_order_status_completed_notification', array( $email_class->emails['WC_Email_Customer_Completed_Order'], 'trigger' ) );

		// Note emails
		//remove_action( 'woocommerce_new_customer_note_notification', array( $email_class->emails['WC_Email_Customer_Note'], 'trigger' ) );
}

add_filter( 'wc_order_statuses', 'dscsa_renaming_order_status' );
function dscsa_renaming_order_status( $order_statuses ) {
    $order_statuses['wc-processing'] = _x('Received Rx', 'Order status', 'woocommerce');
    return $order_statuses;
}

add_filter( 'wc_order_is_editable', 'dscsa_order_is_editable', 10, 2);
function dscsa_order_is_editable($editable, $order) {

  if ($editable) return true;

  return in_array($order->get_status(), array('processing', 'awaiting-rx', 'awaiting-transfer', 'shipped-unpaid', 'shipped-overdue', 'shipped-autopay', 'shipped-payfail', 'shipped-coupon'), true);
}

add_filter('woocommerce_order_is_paid_statuses', 'dscsa_order_is_paid_statuses');
function dscsa_order_is_paid_statuses($paid_statuses) {
  return array('completed', 'shipped-paid');
}

add_filter( 'woocommerce_order_button_text', 'dscsa_order_button_text');
function dscsa_order_button_text() {
    return is_registered() ? 'Place order' : 'Complete Registration';
}

add_filter('auth_cookie_expiration', 'dscsa_auth_cookie_exp', 99, 3);
function dscsa_auth_cookie_exp($seconds, $user_id, $remember) {

    if (is_admin()) {
      $seconds = 60*60*24*365; //ADMIN EXPIRES AFTER ONE YEAR
    } else if ( ! $remember) {
      $seconds = 20*60; //20 minutes;
    } else {
      $seconds = 60*60*24*14;
    }

    //http://en.wikipedia.org/wiki/Year_2038_problem
    if ( PHP_INT_MAX - time() < $seconds ) {
        //Fix to a little bit earlier!
        $seconds =  PHP_INT_MAX - time() - 5;
    }

    return $seconds; //Keep the WP default of 2 weeks for "remember me";
}

//Didn't work: https://stackoverflow.com/questions/38395784/woocommerce-overriding-billing-state-and-post-code-on-existing-checkout-fields
//Did work: https://stackoverflow.com/questions/36619793/cant-change-postcode-zip-field-label-in-woocommerce
global $lang;
global $phone;
global $toEnglish;
global $toSpanish;

add_filter('ngettext', 'dscsa_translate', 10, 3);
add_filter('gettext', 'dscsa_translate', 10, 3);
function dscsa_translate($term, $raw, $domain) {


  global $lang;
  global $toEnglish;
  global $toSpanish;

  if ( ! $lang) {
    $lang = is_admin() ? 'EN' : get_meta('language');
  }

  $phone = $phone ?: get_default('phone');

  if ($term == '%s has been added to your cart.') {
    debug_email("DEBUG Phone", "phone: $phone deafult: ".get_default('phone')." ".print_r(sanitize($_POST), true));
  }

  if ( ! $toEnglish ) {

    $toEnglish = [
      'Hello %1$s (not %1$s? <a href="%2$s">Log out</a>)' => '',
      'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">shipping and billing addresses</a>, and <a href="%3$s">edit your password and account details</a>.' => '',
      "An account is already registered with that username. Please choose another." => 'Looks like you have already registered. Goto the <a href="/account/?gp-login">Login page</a> and use your 10 digit phone number as your default password e.g. the phone number '.$_POST['raw_phone'].' would have a default password of '.$_POST['phone'].'.',
      "<span class='english'>Pay by Credit or Debit Card</span><span class='spanish'>Pago con tarjeta de crédito o débito</span>" => "Pay by Credit or Debit Card",
      'Spanish'  => 'Espanol', //Registering
      'First name' => 'Legal First Name',
      'Last name' => 'Legal Last Name',
      'Email:' => 'Email', //order details
      'Email address' => 'Email', //accounts/details
      'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">shipping and billing addresses</a> and <a href="%3$s">edit your password and account details</a>.' => '',
      'ZIP' => 'Zip code', //Checkout
      'Your order' => '', //Checkout
      'Shipping:' => 'New Patient Fee:',
      'Free shipping coupon' => 'Paid with Coupon',
      'Free shipping' => 'Paid with Coupon', //not working (order details page)
      'No saved methods found.' => 'Add a credit/debit card below to activate autopay.  Or pay manually on the "Orders" page',
      '%s has been added to your cart.' => strtok($_SERVER["REQUEST_URI"],'?') == '/account/'
        ? 'Step 2 of 2: You are almost done! Please complete this "Registration" page so we can fill your prescription(s).  If you need to login again, your temporary password is '.$phone.'.  After completing your registration, you can change your password on the "Account Details" page'
        : 'Thank you for your order!',
      'Username or email' => '<strong>Email (or cell phone number if no email provided)</strong>', //For resetting passwords
      'Password reset email has been sent.' => "A link to reset your password has been sent by text message and/or email",
      'A password reset email has been sent to the email address on file for your account, but may take several minutes to show up in your inbox. Please wait at least 10 minutes before attempting another reset.' => 'If you provided an email address or mobile phone number during registration, then an text message and/or email with instructions on how to reset your password was sent to you.  If you do not get an email or text message from us within 5mins, please call us at <span style="white-space:nowrap">(888) 987-5187</span> for assistance',
      'Additional information' => '',  //Checkout
      'Make default' => 'Set for Autopay',
      'This payment method was successfully set as your default.' => 'This credit/debit card will be used for automatic payments on the first week of the month after you receive your medications.',
      'Payment method successfully added.' => 'This credit/debit card will be used for automatic payments on the first week of the month after you receive your medications.',
      'Billing address' => 'Shipping address', //Order confirmation
  	  'Billing &amp; Shipping' => 'Shipping Address', //Checkout
      //Logging in
      'Lost your password? Please enter your username or email address. You will receive a link to create a new password via email.' => 'Lost your password? Before reseting, please note that new accounts use your phone number - e.g., 4701234567 - as a temporary password. To reset, you will receive a link to create a new password via text message and/or email. If you have trouble, call us at (888) 987-5187 for assistance.',
      'Please enter a valid account username.' => 'Please enter your name and date of birth in mm/dd/yyyy format.',
      'Username is required.' => 'Name and date of birth in mm/dd/yyyy format are required.',
      'Invalid username or email.' => '<strong>Error</strong>: We cannot find an account with name and date of birth.',
      '<strong>ERROR</strong>: Invalid username.' => '<strong>Error</strong>: We cannot find an account with that name and date of birth.',
      'An account is already registered with your email address. Please log in.' => substr($_POST['email'], -13) == '@goodpill.org' ? 'An account is already registered with that name and date of birth. Please login or use a different name and date of birth.' : 'Another account is already using that email address.  Please login, use another email, or leave this field blank',
      'Your order is on-hold until we confirm payment has been received. Your order details are shown below for your reference:' => $_POST['rx_source'] == 'pharmacy' ? 'We are currently requesting a transfer of your Rx(s) from your pharmacy' : 'We are currently waiting on Rx(s) to be sent from your doctor',
      'Your order has been received and is now being processed. Your order details are shown below for your reference:' => 'We got your prescription(s) and will start working on them right away',
      'Thanks for creating an account on %1$s. Your username is %2$s' => 'Thanks for completing Registration Step 1 of 2 on %1$s. Your username is %2$s',
      'Your password has been automatically generated: %s' => 'Your temporary password is your phone number: %s',
      'Add payment method' => strtok($_SERVER["REQUEST_URI"],'?') == '/account/add-payment/' ? 'Save autopay card' : 'Add a new debit/credit card',
      'If you have a coupon code, please apply it below.' => 'By entering a coupon below, I authorize Good Pill to release my Personal Health Information (https://www.goodpill.org/gp-npp/) to members of the organization sponsoring this coupon.'
    ];
  }

  $english = isset($toEnglish[$term]) ? $toEnglish[$term] : $term;

  if ($lang == 'EN') return $english;

  if ( ! $toSpanish ) {

    $phone = $phone ?: get_default('phone');

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
      'List any other medication(s) or supplement(s) you are currently taking<i style="font-size:14px; display:block; margin-bottom:-20px">We will not fill these but need to check for drug interactions</i>' => 'Indique cualquier otro medicamento o suplemento que usted toma actualmente',
      'Legal First Name' => 'Nombre',
      'Legal Last Name' => 'Apellido',
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
      'Step 2 of 2: You are almost done! Please complete this page so we can fill your prescription(s).  If you need to login again, your temporary password is '.$phone.'.  Afterwards you can change your password on the "Account Details" page' => 'Paso 2 de 2: ¡Casi has terminado! Por favor complete esta página para poder llenar su (s) receta (s). Si necesita volver a iniciar sesión, su contraseña temporal es '.$phone.'. Puede cambiar su contraseña en la página "Detalles de la cuenta"',
      'Pay by Credit or Debit Card' => 'Pago con tarjeta de crédito o débito',
      'New Patient Fee:' => 'Cuota de persona nueva:',
      'Paid with Coupon' => 'Pagada con cupón',
      'Refill the Rx(s) selected below' => 'Refill the Rx(s) selected below'
    ];
  }

  @$spanish = $toSpanish[$english];

  if (isset($spanish) && $lang == 'ES')
    return $spanish;

  //This allows client side translating based on jQuery listening to radio buttons
  if (isset($spanish) && isset($_GET['gp-register']))
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

add_action('woocommerce_applied_coupon', 'dscsa_applied_coupon');
function dscsa_applied_coupon($coupon) {
  if ($coupon == 'removecoupon') update_user_meta(get_current_user_id(), 'coupon', null);
}

add_filter('woocommerce_cart_needs_payment', 'dscsa_show_payment_options', 10, 2);
function dscsa_show_payment_options($show_payment_options, $cart) {

  if ( ! is_checkout()) return; //this gets called on every account page otherwise

  $coupon = end($cart->applied_coupons);

  if ($coupon == 'removecoupon') return true;

  //debug_email("woocommerce_cart_needs_payment", print_r($chosen_shipping_methods, true)."cart->get_shipping_total() ".$cart->get_shipping_total()." |||| get_totals ".print_r($cart->get_totals, true)." |||| coupon_discount_totals ".print_r($cart->coupon_discount_totals, true)." |||| coupon_discount_totals ".print_r($cart->coupon_discount_totals, true)." |||| coupon_discount_tax_totals ".print_r($cart->coupon_discount_tax_totals, true)." |||| applied_coupons ".print_r($cart->applied_coupons, true)." |||| ".print_r($show_payment_options, true)." |||| cart->get_cart() ".print_r($cart->get_cart(), true));

  return ! is_pay_coupon($coupon);
}

add_filter( 'wc_stripe_generate_payment_request', 'dscsa_stripe_generate_payment_request', 10, 3);
function dscsa_stripe_generate_payment_request($post_data, $order, $prepared_source ) {
  debug_email("dscsa_stripe_generate_payment_request before", $order->get_status()." ".print_r($post_data, true));
  $post_data['capture'] = 'true';
  return $post_data;
}

add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', 'dscsa_valid_order_statuses_for_payment');
add_filter( 'woocommerce_valid_order_statuses_for_payment', 'dscsa_valid_order_statuses_for_payment' );
function dscsa_valid_order_statuses_for_payment($statuses) {
  $statuses[] = 'shipped-unpaid';
  $statuses[] = 'shipped-overdue';
  $statuses[] = 'shipped-autopay';
  $statuses[] = 'shipped-payfail';
  return $statuses;
}

// Hook in
add_filter( 'woocommerce_checkout_fields' , 'dscsa_checkout_fields', 9999);
function dscsa_checkout_fields( $fields ) {

  if ( ! is_checkout() OR is_wc_endpoint_url()) return [];  //this gets called on every account page otherwise

  $user_id = get_current_user_id();
  $coupon  = get_meta('coupon', $user_id);
  $cart    = WC()->cart;

  if ($coupon && ! $cart->has_discount($coupon)) $cart->add_discount($coupon);

  $shared_fields = shared_fields($user_id);

  //IF AVAILABLE, PREPOPULATE RX ADDRESS AND RXS INTO REGISTRATION
  //This hook seems to be called again once the checkout is being saved.
  //Also don't want run on subsequent orders - rx_source works well because
  //it is currently saved to user_meta (not sure why) and cannot be entered anywhere except the order page
  $patient_profile = patient_profile(
    get_meta('billing_first_name'), //$field['billing']['billing_first_name']['default'] and/or ['value'] is not set yet
    get_meta('billing_last_name'),  //$field['billing']['billing_last_name']['default'] and/or ['value'] is not set yet
    $shared_fields['birth_date']['default'],
    $shared_fields['phone']['default']
  );

  if (count($patient_profile)) {
    $shared_fields['birth_date']['default'] = date_format(date_create($patient_profile[0]['birth_date']), 'Y-m-d'); //just in case user entered DOB incorrectly we can fix it in guardian
    $fields['billing']['billing_address_1']['default'] = substr($patient_profile[0]['address_1'], 1, -1);
    $fields['billing']['billing_address_2']['default'] = substr($patient_profile[0]['address_2'], 1, -1);
    $fields['billing']['billing_city']['default']      = $patient_profile[0]['city'];
    $fields['billing']['billing_postcode']['default']  = $patient_profile[0]['zip'];
  }

  //Add some order fields that are not in patient profile
  $order_fields  = order_fields($user_id, null, $patient_profile);

  $fields['order']['order_comments']['priority'] = 22;

  //debug_email("db error: $heading", print_r($fields['order']['order_comments'], true).' '.print_r($fields['order'], true));
  $fields['order'] = $order_fields + $shared_fields + ['order_comments' => $fields['order']['order_comments']];

  //These seem to be required fields.  I think its because we don't check the "make shipping address the same as billing address"
  //For this reason the shipping and billing addresses need to have these fields match, otherwise we get a "please enter an address to continue" error
  $fields['shipping']['shipping_state']['type'] = 'select';
  $fields['shipping']['shipping_state']['options'] = ['GA' => 'Georgia'];
  unset($fields['shipping']['shipping_country']);
  unset($fields['shipping']['shipping_company']);

  // We are using our billing address as the shipping address for now.
  $fields['billing']['billing_first_name']['priority'] = 10;
  $fields['billing']['billing_last_name']['priority'] = 20;
  $fields['billing']['billing_first_name']['label'] = 'Patient First Name';
  $fields['billing']['billing_last_name']['label'] = 'Patient Last Name';
  $fields['billing']['billing_first_name']['autocomplete'] = 'user-first-name';
  $fields['billing']['billing_last_name']['autocomplete'] = 'user-last-name';
  $fields['billing']['billing_first_name']['custom_attributes'] = ['readonly' => true];
  $fields['billing']['billing_last_name']['custom_attributes'] = ['readonly' => true];

  //Remove Some Fields
  unset($fields['billing']['billing_first_name']['autofocus']);
  unset($fields['billing']['shipping_first_name']['autofocus']);
  unset($fields['billing']['billing_phone']);
  unset($fields['billing']['billing_email']);

  debug_email("woocommerce_checkout_fields", print_r($fields, true).print_r($order_fields, true).print_r($shared_fields, true));

  return $fields;
}

//This is for the address details page
add_filter( 'woocommerce_billing_fields', 'dscsa_billing_fields');
function dscsa_billing_fields( $fields ) {
  unset($fields['billing_company']);

  $fields['billing_state']['type'] = 'select';
  $fields['billing_state']['options'] = ['GA' => 'Georgia'];

  if( ! is_account_page() ) return $fields;

  unset($fields['billing_first_name']);
  unset($fields['billing_last_name']);
  unset($fields['billing_email']);
  unset($fields['billing_phone']);

  return $fields;
}

function get_invoice_number($guardian_id) {
  if ( ! $guardian_id) return;
  $result = db_run("SirumWeb_AddFindInvoiceNbrByPatID '$guardian_id'");
  debug_email("get_invoice_number", $guardian_id.print_r($result, true));
  return $result['invoice_nbr'];
}

function get_guardian_order($guardian_id, $source, $comment, $is_registered) {
  if ( ! $guardian_id) return;

  $comment = str_replace("'", "''", $comment ?: '');
  // Categories can be found or added select * From csct_code where ct_id=5007, UPDATE csct_code SET code_num=2, code=2, user_owned_yn = 1 WHERE code_id = 100824
  // 0 Unspecified, 1 Webform Complete, 2 Webform eRx, 3 Webform Transfer, 6 Webform Refill, 7 Webform eRX with Note, 8 Webform Transfer with Note,

  if ($source == 'pharmacy')
    $category = $comment ? 8 : 3;
  else if ($source == 'erx' AND $is_registered)
    $category = $comment ? 9 : 6;
  else if ($source == 'erx'AND ! $is_registered)
    $category = $comment ? 7 : 2;
  else
    $category = 0;

  $result = db_run("SirumWeb_AddFindOrder '$guardian_id', '$category', '$comment'");
  //debug_email("get_guardian_order *$source*", "SirumWeb_AddFindOrder '$guardian_id', '$category', '$comment'".print_r($result, true));
  return $result;
}

function add_rxs_to_order($invoice_number, $script_nos) {
  if ( ! $script_nos) return;
  $script_nos = json_encode($script_nos);
  $result = db_run("SirumWeb_AddScriptNosToOrder '$invoice_number', '$script_nos'", 2, true);
  debug_email("add_rxs_to_order Order #$invoice_number", print_r($script_nos, true).print_r(func_get_args(), true).print_r($_POST, true).print_r($result, true));
  return $result;
}

function remove_rxs_from_order($invoice_number, $script_nos) {
  if ( ! $script_nos) return;
  $script_nos = json_encode($script_nos);
  $result = db_run("SirumWeb_RemoveScriptNosFromOrder '$invoice_number', '$script_nos'", 2, true);
  debug_email("remove_rxs_from_order Order #$invoice_number", print_r($script_nos, true).print_r(func_get_args(), true).print_r($_POST, true).print_r($result, true));
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

function add_remove_allergies($guardian_id, $post) {

  if ( ! $guardian_id) return;

  $post  = json_encode($post);
  $query = "SirumWeb_AddRemove_Allergies '$guardian_id', '$post'";

  $result = db_run($query, 15, true, true);

  debug_email("add_remove_allergies", $query." | ".print_r($result, true).print_r(mssql_get_last_message(), true).print_r(func_get_args(), true).print_r($_POST, true));

  return $result;
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
  //debug_email("update_shipping_address", print_r($params, true));
  if ( ! $guardian_id) return;

  $zip = substr($zip, 0, 5);
  $city = mb_convert_case($city, MB_CASE_TITLE, "UTF-8" );
  $address_1 = mb_convert_case(str_replace("'", "''", $address_1), MB_CASE_TITLE, "UTF-8" );
  $address_2 = $address_2 ? "'".mb_convert_case(str_replace("'", "''", $address_2), MB_CASE_TITLE, "UTF-8" )."'" : "NULL";
  $query = "SirumWeb_AddUpdatePatHomeAddr '$guardian_id', '$address_1', $address_2, NULL, '$city', 'GA', '$zip', 'US'";
  debug_email("update_shipping_address", $query);
  return db_run($query);
}

function patient_profile($first_name, $last_name, $birth_date, $phone) {

  //debug_email("patient_profile start", "$first_name $last_name $birth_date, $phone".print_r(func_get_args(), true).print_r(sanitize($_POST), true));

  if ( ! $first_name OR ! $last_name OR ! $birth_date) {
    //debug_email("patient_profile_error!", "is_admin ".is_admin()." ".print_r(func_get_args(), true).print_r($_POST, true));
    return;
  }

  $first_name = str_replace("'", "''", $first_name);
  $last_name = str_replace("'", "''", $last_name);

  $result = db_run("SirumWeb_PatProfile '$first_name', '$last_name', '$birth_date', '$phone'", 0, true);

  //debug_email("patient_profile end", "$first_name $last_name ".print_r(func_get_args(), true).print_r(sanitize($_POST), true).print_r($result, true));

  return $result;
}

function order_defaults($first_name, $last_name, $birth_date, $phone) {

  $first_name = str_replace("'", "''", $first_name);
  $last_name = str_replace("'", "''", $last_name);

  //debug_email("order_defaults", "$first_name $last_name ".print_r(func_get_args(), true).print_r(sanitize($_POST), true));

  $result = db_run("SirumWeb_OrderDefaults '$first_name', '$last_name', '$birth_date', '$phone'");

  debug_email("order_defaults", "$first_name $last_name ".print_r(func_get_args(), true).print_r(sanitize($_POST), true).print_r($result, true));

  return $result;
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

  $first_name = str_replace("'", "''", $first_name);
  $last_name  = str_replace("'", "''", $last_name);
  $autofill   = is_registered() ? "NULL" : "'1'"; //Turn on autofill when a patient first registers, otherwise keep it the same

  //debug_email("add_patient", "$first_name $last_name ".print_r(func_get_args(), true).print_r(sanitize($_POST), true));
  $sql = "SirumWeb_AddUpdatePatient '$first_name', '$last_name', '$birth_date', '$phone', '$language', $autofill"; //IMPORTANT NO QUOTES AROUND AUTOFILL
  $result = db_run($sql);

  debug_email("add_patient", "$first_name $last_name $sql is_registered:".is_registered()." ".print_r(func_get_args(), true).print_r(sanitize($_POST), true).print_r($result, true));

  return $result['PatID'];
}

// Procedure dbo.SirumWeb_AddToPatientComment (@PatID int, @CmtToAdd VARCHAR(4096)
// The comment will be appended to the existing comment if it is not already in the comment field.
function append_comment($guardian_id, $comment) {
  if ( ! $guardian_id) return;
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
function add_preorder($guardian_id, $invoice_number, $drug_names, $pharmacy) {
   if ( ! $guardian_id) return;
   $store = json_decode(stripslashes($pharmacy));
   $phone = cleanPhone($store->phone) ?: '0000000000';
   $fax = cleanPhone($store->fax) ?: '0000000000';
   $store_name = str_replace("'", "''", $store->name); //We need to escape single quotes in case comment has one
   debug_email("select2 add_preorder", print_r(func_get_args(), true).print_r(sanitize($_POST), true));

   foreach ($drug_names as $drug_name) {
     if ($drug_name) {
       $drug_name = preg_replace('/,[^,]*$/', '', $drug_name); //remove pricing data after last comma (don't use explode because of combo drugs)
       $drug_name = str_replace("'", "''", $drug_name); //We need to escape single quotes e.g., the ' of don't in Fluoxetine 40mg (Prozac, please don't specify tablet vs capsule)
       $query = "SirumWeb_AddToPreorder '$guardian_id', '$invoice_number', '$drug_name', '$store->npi', '$store_name P:$phone F:$fax', '$store->street', '$store->city', '$store->state', '$store->zip', '$phone', '$fax'";
       $res = db_run($query);

       if (trim($res['message']))
        debug_email("add_preorder for Order #$invoice_number drug has error message $drug_name", json_encode($res['message'])."|".strlen($res['message'])."|".var_export($res['message'], true)."|".var_export($res[1], true)."|$query|".print_r($res, true).print_r(func_get_args(), true).print_r(sanitize($_POST), true));
     }
   }

   if ( ! $store->phone OR ! $store->fax)
     debug_email("add_preorder for Order #$invoice_number", "$query ".print_r(func_get_args(), true).print_r(sanitize($_POST), true));
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

  if ( ! $guardian_id) return;

  $store = json_decode(stripslashes($pharmacy));

  $store_name = str_replace("'", "''", $store->name); //We need to escape single quotes in case pharmacy name has a ' for example Lamar's Pharmacy
  $store_street = str_replace("'", "''", $store->street);

  db_run("SirumWeb_AddExternalPharmacy '$store->npi', '$store_name, $store->phone, $store_street', '$store_street', '$store->city', '$store->state', '$store->zip', '$phone', '$fax'");

  db_run("SirumWeb_AddUpdatePatientUD '$guardian_id', '1', '$store_name'");

  //Because of Guardian's 50 character limit for UD fields and 3x 10 character fields with 3 delimiters, we need to cutoff street
  $user_def_2 = $store->npi.','.cleanPhone($store->fax).','.cleanPhone($store->phone).','.substr($store_street, 0, 50-10-10-10-3);
  return db_run("SirumWeb_AddUpdatePatientUD '$guardian_id', '2', '$user_def_2'");
}

function update_payment_method($guardian_id, $value) {
  if ( ! $guardian_id) return;
  return db_run("SirumWeb_AddUpdatePatientUD '$guardian_id', '3', '$value'");
}

function update_card_and_coupon($guardian_id, $card = [], $coupon = "") {
  if ( ! $guardian_id) return;
  //Meet guardian 50 character limit
  //Last4 4, Month 2, Year 2, Type (Mastercard = 10), Delimiter 4, So coupon will be truncated if over 28 characters
  $value = $card['last4'].','.$card['month'].'/'.substr($card['year'] ?: '', 2).','.$card['type'].','.$coupon;

  return db_run("SirumWeb_AddUpdatePatientUD '$guardian_id', '4', '$value'");
}

//Procedure dbo.SirumWeb_AddUpdatePatEmail (@PatID int, @EMailAddress VARCHAR(255)
//Set the patID and the new email address
function update_email($guardian_id, $email) {
  if ( ! $guardian_id) return;
  return db_run("SirumWeb_AddUpdatePatEmail '$guardian_id', '$email'");
}

//Procedure dbo.SirumWeb_ToggleAutofill (@PatID int, @json {rx_ids:autofill_resume_dates})
//Set the patID and the new email address
//If user has no explicit dates then PHP will set $_POST[autofill_resume] AND $_POST[$rx_autofill] to null rathern than array of empty keys.  So we have to use the rx_autofill_array instead.
function update_autofill($guardian_id, $pat_autofill, $rx_autofill, $autofill_resume) {

  if ( ! $guardian_id) return;

  $rx_autofill = $pat_autofill ? json_encode($rx_autofill ?: []) : '';
  $autofill_resume = json_encode($autofill_resume ?: []);

  //If javascript doesn't disable input in time, we may get values like "No Refill" "Transferred" in here rather than blanks and dates that we neeed to remove
  $autofill_resume = preg_replace('/Out of Stock|Transferred|No Refills|Rx Expired|Order \d+/', '', $autofill_resume);

  $sql = "SirumWeb_ToggleAutofill '$guardian_id', '$rx_autofill', '$autofill_resume'";
  $res = db_run($sql, 0, true); //Get all rows of first table (one row per drug)
  debug_email("update_autofill", $sql." ".print_r(sanitize($_POST), true)." ".print_r($res, true));
  return $res;
}

global $conn;
function db_run($sql, $resultIndex = 0, $all_rows = false, $debug = false) {
  global $conn;
  $conn = $conn ?: db_connect();
  $stmt = db_query($conn, $sql);

  if ( ! is_resource($stmt)) {

    $last_message = mssql_get_last_message();

    if ($stmt !== true)  //mssql_query($sql, $conn) returns true on no result, false on error, or recource with rows
      debug_email("No Resource", print_r($sql, true).' '.print_r($stmt, true).' '.print_r($last_message, true));

    //Transaction (Process ID 67) was deadlocked on lock resources with another process and has been chosen as the deadlock victim. Rerun the transaction.
    if (strpos($last_message, 'Rerun the transaction') !== false)
      db_run($sql, $resultIndex, $all_rows);

    return;
  }

  $results = [];
  do {

    $results[] = db_fetch($stmt, $sql);

  } while (mssql_next_result($stmt));


  if ($debug) {
    debug_email("Debug MSSQL", print_r($sql, true).' '.print_r(mssql_get_last_message(), true).' '.print_r($results, true));
  }

  return $all_rows ? $results[$resultIndex] : $results[$resultIndex][0];
}

function db_fetch($stmt, $sql) {

  if ( ! mssql_num_rows($stmt)) {
    debug_email("No Rows", print_r($sql, true).' '.print_r($stmt, true).' '.print_r(mssql_get_last_message(), true));
    return [];
  }

  $data = [];
  while ($row = mssql_fetch_array($stmt)) {

      if (trim($row['Message'])) {
        debug_email("db query: $sql", print_r($row, true).print_r($data, true).print_r(sanitize($_POST), true));
        if (is_admin()) echo '<div class="notice notice-success is-dismissible"><p><strong>Error Saving To Guardian:</strong> "'.$row['Message'].'"</p></div>';
      }

      $data[] = $row;
  }

  return $data;
}

function db_connect() {
  //sqlsrv_configure("WarningsReturnAsErrors", 0);
  $conn = mssql_connect(GUARDIAN_IP, GUARDIAN_ID, GUARDIAN_PW);

  if ( ! is_resource($conn)) {
    email_error('Error Connection 1');

    $conn = mssql_connect(GUARDIAN_IP, GUARDIAN_ID, GUARDIAN_PW);

    if ( ! is_resource($conn)) {
      email_error('Error Connection 2');
      echo 'mssql_get_last_message(): '.mssql_get_last_message();
      return false;
    }
  }

  mssql_select_db('cph', $conn) ?: email_error('Could not select database cph');
  return $conn;
}

function db_query($conn, $sql) {
  return mssql_query($sql, $conn) ?: email_error("Query $sql");
}

function email_error($heading) {
   $errors = mssql_get_last_message();
   if ($errors)
     debug_email("db error: $heading", "Errors: ".print_r($errors, true)."POST: ".print_r(sanitize($_POST), true));
}

function debug_email($subject, $body) {
   $type = (is_admin() OR strpos($_SERVER['HTTP_COOKIE'], 'impersonated_by') !== false) ? "ADMIN" : "USER";
   wp_mail('adam.kircher@gmail.com', "WP_MAIL $type: $subject", $body);
}

add_filter( 'wp_mail_failed', 'dscsa_mail_failed');
function dscsa_mail_failed($error) {
  error_log("dscsa_mail_failed ".print_r($error, true));
  mail('adam.kircher@gmail.com', "dscsa_mail_failed", print_r($error, true));
}
