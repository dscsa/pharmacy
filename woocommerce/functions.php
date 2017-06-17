<?php //For IDE styling only

//TODO
//RUN REVISED CHRIS SCRIPTS
//CALL DB FUNCTIONS AT RIGHT PLACES
//MAKE ALLERGY LIST MATCH GUARDIAN
//FIX EDIT ADDRESS FIELD LABELS
//FIX BLANK BUTTON ON SAVE CARD

// Register custom style sheets and javascript.
add_action( 'wp_enqueue_scripts', 'register_custom_plugin_styles' );
function register_custom_plugin_styles() {
  wp_enqueue_style('account', 'https://dscsa.github.io/webform/woocommerce/account.css');
  wp_enqueue_style('checkout', 'https://dscsa.github.io/webform/woocommerce/checkout.css');
  wp_enqueue_style('storefront', 'https://dscsa.github.io/webform/woocommerce/storefront.css');
  wp_enqueue_style('select', 'https://dscsa.github.io/webform/woocommerce/select2.css');

  wp_enqueue_script('order', 'https://dscsa.github.io/webform/woocommerce/checkout.js', ['jquery']);
}

function order_fields() {
  return [
    'source_english' => [
      'type'   	  => 'select',
      'required'  => true,
      'class'     => ['english'],
      'options'   => [
        'erx'     => 'Prescription(s) were sent to Good Pill from my doctor',
        'pharmacy' => 'Please transfer prescription(s) from my pharmacy'
      ]
    ],
    'source_spanish' => [
      'type'   	  => 'select',
      'required'  => true,
      'class'     => ['spanish'],
      'options'   => [
        'erx'     => 'Spanish Source eRx',
        'pharmacy' => 'Spanish Source Pharmacy'
      ]
    ],
    'medication[]'  => [
      'type'   	  => 'select',
      'label'     => __('Search and select medications by generic name that you want to transfer to Good Pill'),
      'options'   => ['']
    ]
  ];
}

function patient_fields($user_id) {
    //https://docs.woocommerce.com/wc-apidocs/source-function-woocommerce_form_field.html#1841-2061
    $user_id = $user_id ?: get_current_user_id();
    $backup_pharmacy = get_user_meta($user_id, 'backup_pharmacy', true);

    return [
    'language' => [
        'type'   	  => 'radio',
        'label'     => __('Language'),
        'label_class' => ['radio'],
        'required'  => true,
        'options'   => ['english' => 'English', 'spanish' => __('Spanish')],
        'default'   => get_user_meta($user_id, 'language', true) ?: 'english'
    ],
    'backup_pharmacy' => [
        'type'   	  => 'select',
        'label'     => __('<span class="erx">Name and address of a backup pharmacy to fill your prescriptions if we are out-of-stock</span><span class="pharmacy">Name and address of pharmacy from which we should transfer your medication(s)</span>'),
        'required'  => true,
        'options'   => [$backup_pharmacy => $backup_pharmacy],
        'default'   => $backup_pharmacy
    ],
    'medications_other' => [
        'label'     =>  __('List any other medication(s) or supplement(s) you are currently taking'),
        'default'   => get_user_meta($user_id, 'medications_other', true)
    ],
    'allergies_none' => [
        'type'   	  => 'radio',
        'label'     => __('Allergies'),
        'label_class' => ['radio'],
        'options'   => ['' => __('Allergies Selected Below'), 99 => __('No Medication Allergies')],
    	  'default'   => get_user_meta($user_id, 'allergies_none', true) ?: ''
    ],
    'allergies_aspirin' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Aspirin'),
        'default'   => get_user_meta($user_id, 'allergies_aspirin', true)
    ],
    'allergies_penicillin' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Penicillin'),
        'default'   => get_user_meta($user_id, 'allergies_penicillin', true)
    ],
    'allergies_ampicillin' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Ampicillin'),
        'default'   => get_user_meta($user_id, 'allergies_ampicillin', true)
    ],
    'allergies_erythromycin' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Erythromycin'),
        'default'   => get_user_meta($user_id, 'allergies_erythromycin', true)
    ],
    'allergies_nsaids' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('NSAIDS e.g., ibuprofen, Advil'),
        'default'   => get_user_meta($user_id, 'allergies_nsaids', true)
    ],
    'allergies_sulfa' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Sulfa (Sulfonamide Antibiotics)'),
        'default'   => get_user_meta($user_id, 'allergies_sulfa', true)
    ],
    'allergies_tetracycline' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Tetracycline antibiotics'),
        'default'   => get_user_meta($user_id, 'allergies_tetracycline', true)
    ],
    'allergies_other' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     =>__( 'List Other Allergies Below').'<input class="input-text " name="allergies_other" id="allergies_other_input" value="'.get_user_meta($user_id, 'allergies_other', true).'">'
    ],
    'birth_date' => [
        'label'     => __('Date of Birth'),
        'required'  => true,
        'default'   => get_user_meta($user_id, 'birth_date', true) ?: $_POST['birth_date']
    ],
    'phone' => [
        'label'     => __('Phone'),
       	'required'  => true,
        'type'      => 'tel',
        'validate'  => ['phone'],
        'autocomplete' => 'tel',
        'default'   => get_user_meta($user_id, 'phone', true)
    ]
  ];
}

//Display custom fields on account/details
add_action('woocommerce_admin_order_data_after_order_details', 'custom_admin_edit_account');
function custom_admin_edit_account($order) {
  return custom_edit_account_form($order->user_id);
}
add_action( 'woocommerce_edit_account_form_start', 'custom_user_edit_account');
function custom_user_edit_account() {
  return custom_edit_account_form();
}

function custom_edit_account_form($user_id) {
  foreach (patient_fields($user_id) as $key => $field) {
    if ($key === "backup_pharmacy") {
      $field['options'] = [$field['default'] => $field['default']];
    }

    echo woocommerce_form_field($key, $field);
  }
}

add_action('woocommerce_register_form_start', 'custom_register_form');
function custom_register_form() {
  $patient_fields = patient_fields();

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

  echo woocommerce_form_field('language', $patient_fields['language']);
  echo woocommerce_form_field('first_name', $first_name);
  echo woocommerce_form_field('last_name', $last_name);
  echo woocommerce_form_field('birth_date', $patient_fields['birth_date']);
}

//After Registration, set default shipping/billing/account fields
//then save the user into GuardianRx
add_action('woocommerce_created_customer', 'customer_created');
function customer_created($user_id) {
  $first_name = sanitize_text_field($_POST['first_name']);
  $last_name = sanitize_text_field($_POST['last_name']);
  $birth_date = sanitize_text_field($_POST['birth_date']);
  foreach(['', 'billing_', 'shipping_'] as $field) {
    update_user_meta($user_id, $field.'first_name', $first_name);
    update_user_meta($user_id, $field.'last_name', $last_name);
  }
  update_user_meta($user_id, 'birth_date', $birth_date);
  update_user_meta($user_id, 'language', $_POST['language']);

  $patient_id = add_patient($first_name, $last_name, $birth_date);

  update_user_meta($user_id, 'guardian_id', $patient_id);
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
add_action('woocommerce_registration_redirect', 'custom_redirect', 2);
add_action('woocommerce_login_redirect', 'custom_redirect', 2);
function custom_redirect() {
  return home_url('/account/orders');
}

add_filter ('wp_redirect', 'custom_wp_redirect');
function custom_wp_redirect($location) {
  if (substr($location, -9) == '/account/')
    return $location.'details/';

  return $location;
}


add_filter ('woocommerce_account_menu_items', 'custom_my_account_menu');
function custom_my_account_menu($nav) {

  //Clicking on Dashboard/New Order actually adds the product.
  //Hash is necessary to prevent the trailing slash to ignore query
  $new = ['?add-to-cart=30#' => __('New Order')];

  //Preserve order otherwise new link is at the bottom of menu
  foreach ($nav as $key => $val) {
    if ($key != 'dashboard')
      $new[$key] = $val;
  }

  $new['edit-account'] = __('Account Details');
  $new['edit-address'] = __('Address & Payment');

  return $new;
}

//On new order and account/details save account fields back to user
//TODO should changing checkout fields overwrite account fields if they are set?
add_action('woocommerce_save_account_details', 'custom_save_account_details');
function custom_save_account_details($user_id) {

  $allergy_codes = [
    'allergies_none' => 99,
    'allergies_aspirin' => 4,
    'allergies_penicillin' => 5,
    'allergies_ampicillin' => 6,
    'allergies_erythromycin' => 7,
    'allergies_nsaids' => 9,
    'allergies_sulfa' => 3,
    'allergies_tetracycline' => 1,
    'allergies_other' => 100
  ];

  foreach (patient_fields() as $key => $field) {

    $val = $_POST[$key];

    update_user_meta($user_id, $key, $val);

    if ($allergy_codes[$key]) {
      //Since all checkboxes submitted even with none selected.  If none
      //is selected manually set value to false for all except none
      $val = ($_POST['allergies_none'] AND $key != 'allergies_none') ? NULL : $val;
      add_remove_allergy($allergy_codes[$key], $val);
    }
  }
  wp_mail('adam.kircher@gmail.com', 'save account data', print_r($_POST, true));

  append_comment(sanitize_text_field($_POST['medications_other']));
  update_email(sanitize_text_field($_POST['account_email']));
  update_phone(sanitize_text_field($_POST['phone']));
}

add_action('woocommerce_checkout_update_user_meta', 'custom_save_checkout_details');
function custom_save_checkout_details($user_id) {
  //TODO should save if they don't exist, but what if they do, should we be overriding?
  custom_save_account_details($user_id);

  $address = update_shipping_address(
    sanitize_text_field($_POST['shipping_address_1']),
    sanitize_text_field($_POST['shipping_address_2']),
    sanitize_text_field($_POST['shipping_city']),
    sanitize_text_field($_POST['shipping_postcode'])
  );
  wp_mail('adam.kircher@gmail.com', 'after update shipping', print_r($address, true));
}

//Save Billing info to Guardian
add_action('updated_user_meta', 'custom_updated_user_meta', 10, 4);
function custom_updated_user_meta($meta_id, $user_id, $meta_key, $meta_val)
{
  if ($meta_key != '_trustcommerce_saved_profiles') return;

  // $meta_value = [
  //   [customer_id] => Q1USQ7
  //   [last4] => 1111
  //   [exp_year] => 19
  //   [exp_month] => 01
  // ]
 update_billing_token($meta_value['customer_id'], $meta_value['last4'], $meta_value['exp_month'], $meta_value['exp_year']);
}

//Didn't work: https://stackoverflow.com/questions/38395784/woocommerce-overriding-billing-state-and-post-code-on-existing-checkout-fields
//Did work: https://stackoverflow.com/questions/36619793/cant-change-postcode-zip-field-label-in-woocommerce
add_filter('ngettext', 'custom_translate');
add_filter('gettext', 'custom_translate');
function custom_translate($term) {

  $toEnglish = [
    'Spanish'  => 'Espanol',
    'Username or email address' => 'Email Address',
    'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">shipping and billing addresses</a> and <a href="%3$s">edit your password and account details</a>.' => '',
    'ZIP' => 'Zip code',
    'Your order' => '',
    '%s has been added to your cart.' => 'Fill out the form below to place a new order'
  ];

  $toSpanish = [
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
    'Aspirin' => 'Drogas de Aspirin',
    'Erythromycin' => 'Drogas de Erthromycin',
    'NSAIDS e.g., ibuprofen, Advil' => 'Drogas de NSAIDS',
    'Penicillin' => 'Drogas de Penicillin',
    'Ampicillin' => 'Drogas de Ampicillin',
    'Sulfa (Sulfonamide Antibiotics)' => 'Drogas de Sulfa',
    'Tetracycline antibiotics' => 'Antibiotics de Tetra',
    'List Other Allergies Below' => 'Otras Allegerias',
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
    'Email Address' => 'Spanish Email Address',
    'Card Number' => 'Spanish Card Number',
    'Expiry (MM/YY)' => 'Spanish Exp',
    'Card Code' => 'Spanish Card Code'
  ];

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
  $order_fields = order_fields();

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

add_action('wp_enqueue_scripts', 'remove_sticky_checkout', 99);
function remove_sticky_checkout() {
  wp_dequeue_script('storefront-sticky-payment' );
}

function update_billing_token($trustcommerce_id, $last4, $exp_month, $exp_year) {
  return db_run("SirumWeb_AddRemove_Billing(?, ?, ?, ?)", [
    guardian_id(), $trustcommerce_id
  ]);
}

// SirumWeb_AddRemove_Allergy(
//   @PatID int,     --Carepoint Patient ID number
//   @AddRem int = 1,-- 1=Add 0= Remove
//   @AlrNumber int,  -- From list
//   @OtherDescr varchar(80) = '' -- Description for "Other"
// )
// if      @AlrNumber = 1  -- TETRACYCLINE 250 MG CAPSULE
// else if @AlrNumber = 3  -- Sulfa (Sulfonamide Antibiotics)
// else if @AlrNumber = 4  -- Aspirin
// else if @AlrNumber = 5  -- Penicillins
// else if @AlrNumber = 6  -- Ampicillin
// else if @AlrNumber = 7  -- Erythromycin Base
// else if @AlrNumber = 8  -- Codeine
// else if @AlrNumber = 9  -- NSAIDS e.g., ibuprofen, Advil
// else if @AlrNumber = 99  -- none
// else if @AlrNumber = 100 -- other
function add_remove_allergy($allergy_id, $value) {
  return db_run("SirumWeb_AddRemove_Allergy(?, ?, ?, ?)", [
    guardian_id(), $value ? 1 : 0, $allergy_id, $value
  ]);
}

// SirumWeb_AddUpdateCellPhone(
//   @PatID int,  -- ID of Patient
//   @PatCellPhone VARCHAR(20)
// }
function update_phone($cell_phone) {
  return db_run("SirumWeb_AddUpdatePatCellPhone(?, ?)", [
    guardian_id(), $cell_phone
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
  return db_run("SirumWeb_AddUpdatePatShipAddr(?, ?, ?, NULL, ?, 'GA', ?, 'US')", [
    guardian_id(), $address_1, $address_2, $city, $zip
  ]);
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
function add_patient($first_name, $last_name, $birth_date) {
  return db_run("SirumWeb_AddEditPatient(?, ?, ?)", [$first_name, $last_name, $birth_date])['PatID'];
}

// Procedure dbo.SirumWeb_AddToPatientComment (@PatID int, @CmtToAdd VARCHAR(4096)
// The comment will be appended to the existing comment if it is not already in the comment field.
function append_comment($comment) {
  return db_run("SirumWeb_AddToPatientComment(?, ?)", [guardian_id(), $comment]);
}

// Create Procedure dbo.SirumWeb_AddToPreorder(
//    @PatID int
//   ,@NDC varchar(11) ='' -- NDC to add
//   ,@DrugName varchar(60) ='' -- Drug Name to look up NDC
//   ,@PharmacyOrgID int -- Org_id from Pharmacy List
//   ,@PharmacyName varchar(80)
//   ,@PharmacyAddr1 varchar(50)    -- Address Line 1
//   ,@PharmacyAddr2 varchar(50)    -- Address Line 2
//   ,@PharmacyAddr3 varchar(50)    -- Address Line 3
//   ,@PharmacyCity varchar(20)     -- City Name
//   ,@PharmacyState varchar(2)     -- State Name
//   ,@PharmacyZip varchar(10)      -- Zip Code
//   ,@PharmacyPhone varchar(20)   -- Phone Number
//   ,@PharmacyFaxNo varchar(20)   -- Phone Fax Number
// If you send the NDC, it will use it.  If you do not send and NCD it will attempt to look up the drug by the name.  I am not sure that this will work correctly, the name you pass in would most likely have to be an exact match, even though I am using  like logic  (ie “%Aspirin 325mg% “) to search.  We may have to work on this a bit more
function add_preorder($drug_name, $pharmacy) {
   wp_mail('adam.kircher@gmail.com', 'add preorder', print_r($pharmacy, true));
  //return db_run("SirumWeb_AddToPreorder(?, ?)", [guardian_id(), $drug_name]);
}

// Procedure dbo.SirumWeb_AddUpdatePatientUD (@PatID int, @UDNumber int, @UDValue varchar(50) )
// Set the @UD number for the3 field that you want to update, and set the text value.
function update_ud($pharmacy) {
    return db_run("SirumWeb_AddUpdatePatientUD(?, ?)", [guardian_id(), $drug_name]);
}

//Procedure dbo.SirumWeb_AddUpdatePatEmail (@PatID int, @EMailAddress VARCHAR(255)
//Set the patID and the new email address
function update_email($email) {
    return db_run("SirumWeb_AddUpdatePatEmail(?, ?)", [guardian_id(), $email]);
}

// SirumWeb_AddToPreorder(
//  @PatID int
// ,@NDC varchar(11)  -- NDC to add
// ,@PharmacyOrgID int -- Org_id from Pharmacy List
// ,@PharmacyName varchar(80)
// ,@PharmacyAddr1 varchar(50)    -- Address Line 1
// ,@PharmacyAddr2 varchar(50)    -- Address Line 2
// ,@PharmacyAddr3 varchar(50)    -- Address Line 3
// ,@PharmacyCity varchar(20)     -- City Name
// ,@PharmacyState varchar(2)     -- State Name
// ,@PharmacyZip varchar(10)      -- Zip Code
// ,@PharmacyPhone varchar(20)   -- Phone Number
// ,@PharmacyFaxNo varchar(20)   -- Phone Fax Number
// )
// function preorder($first_name, $last_name, $birth_date) {
//   return run("SirumWeb_AddToPreorder()", [
//     [$first_name, SQLSRV_PARAM_IN],
//     [$last_name, SQLSRV_PARAM_IN],
//     [$birth_date, SQLSRV_PARAM_IN]
//   ]);
// }
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

  wp_mail('adam.kircher@gmail.com', "db query: $sql", print_r($params, true).print_r($data, true));
  wp_mail('adam.kircher@gmail.com', "db testing", print_r(sqlsrv_errors(), true));

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

function guardian_id() {
   return get_user_meta(get_current_user_id(), 'guardian_id', true);
}
