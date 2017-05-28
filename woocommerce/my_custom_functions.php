add_action( 'added_post_meta', 'custom_added_post_meta', 10, 4);
add_action( 'updated_post_meta', 'custom_added_post_meta', 10, 4);
function custom_added_post_meta( $meta_id, $post_id, $meta_key, $meta_value )
{
   //$response = wp_remote_post( $url, );
   if ($meta_key == '_trustcommerce_customer_id') {
    $post = ['body' => ['trustcommerce_customer_id' => $meta_value, 'email' => wp_get_current_user()->data->user_email]];
    $res  = wp_remote_post('https://webform.goodpill.org/billing', $post);
   	wp_mail('adam@sirum.org', 'added_post_meta', print_r($res, true));
   }
}

add_action( 'deleted_post_meta', 'custom_deleted_post_meta', 10, 4);
function custom_deleted_post_meta( $deleted_meta_ids, $post_id, $meta_key, $only_delete_these_meta_values )
{
   wp_mail('adam@sirum.org', 'deleted_post_meta', print_r(func_get_args(), true));
}

// Hook in
add_filter( 'woocommerce_checkout_fields' , 'custom_checkout_fields' );

// Our hooked in function - $fields is passed via the filter!
function custom_checkout_fields( $fields ) {

    //Hide some fields
    $fields['billing']['billing_state']['type'] = 'hidden';
    $fields['billing']['billing_email']['type'] = 'hidden';
    $fields['billing']['billing_first_name']['type'] = 'hidden';
    $fields['billing']['billing_last_name']['type'] = 'hidden';

    //Make Phone Field Wide
    $fields['billing']['billing_phone']['class'] = array('form-row-wide');

    //Translate Some Labels
    //$fields['billing']['billing_address_1']['label'] = '<span class="english">Address</span><span class="spanish">Hola</span>';
    //$fields['billing']['billing_city']['label'] = '<span class="english">Town / City</span><span class="spanish">Hola</span>';
    //$fields['billing']['billing_postcode']['label'] = '<span class="english">Zip Code</span><span class="spanish">Hola</span>';
    //$fields['billing']['billing_phone']['label'] = '<span class="english">Phone</span><span class="spanish">Hola</span>';

    $custom = [
        'language' => array(
        'type'   	=> 'select',
        'required'  => true,
        'options'   => ['english' => 'English', 'spanish' => 'Espanol'],
        ),
        'source_english' => array(
        'type'   	=> 'select',
        'required'  => true,
        'class'     => array('english'),
        'options'   => [
            'eRx' => 'Prescription(s) were sent to Good Pill from my doctor',
			'pharmacy' => 'Please transfer prescription(s) from my pharmacy'
        ]
        ),
        'source_spanish' => array(
        'type'   	=> 'select',
        'required'  => true,
        'class'     => array('spanish'),
        'options'   => [
            'eRx' => 'Hola',
			'pharmacy' => 'Adios'
        ]
        ),
        'medication' => array(
        'type'   	=> 'select',
        'label'     => '<span class="english">Search and select medications by generic name that you want to transfer to Good Pill</span><span class="spanish">Hola</span>',
        'required'  => true,
        'options'   => [''],
        ),
        'backupPharmacy' => array(
        'type'   	=> 'select',
        'label'     => '<span class="english">Name and address of pharmacy from which we should transfer your medication(s)</span><span class="spanish">Hola</span>',
        'required'  => true,
        'options'   => [''],
        ),
        'medicationsOther' => array(
        'type'   	=> 'text',
        'label'     => '<span class="english">List any other medication(s) or supplement(s) you are currently taking</span><span class="spanish">Hola</span>',
        ),
        'allergies_english' => array(
        'type'   	=> 'select',
        'label'     => 'Allergies',
        'class'     => array('english'),
        'required'  => true,
        'options'   => ['Yes' => 'Allergies Selected Below', 'No' => 'No Medication Allergies'],
        ),
        'allergies_spanish' => array(
        'type'   	=> 'select',
        'label'     => 'Spanish Allergies',
        'class'     => array('spanish'),
        'required' => true,
        'options'   => ['Yes' => 'Si', 'No' => 'No'],
        ),
        'allergies[aspirin-salicylates]' => array(
        'type'   	=> 'checkbox',
        'label'     => '<span class="english">Aspirin and salicylates</span><span class="spanish">Hola</span>',
        ),
        'allergies[erythromycin-biaxin-zithromax]' => array(
        'type'   => 'checkbox',
        'label'  => '<span class="english">Erythromycin, Biaxin, Zithromax</span><span class="spanish">Hola</span>',
        ),
        'allergies[nsaids]' => array(
        'type'   => 'checkbox',
        'label'  => '<span class="english">NSAIDS e.g., ibuprofen, Advil</span><span class="spanish">Hola</span>',
    	),
        'allergies[penicillins-cephalosporins]' => array(
        'type'   => 'checkbox',
        'label'  => '<span class="english">Penicillins/cephalosporins e.g., Amoxil, amoxicillin, ampicillin, Keflex, cephalexin</span><span class="spanish">Hola</span>',
    	),
        'allergies[sulfa]' => array(
        'type'   => 'checkbox',
        'label'  => '<span class="english">Sulfa drugs e.g., Septra, Bactrim, TMP/SMX</span><span class="spanish">Hola</span>',
    	),
        'allergies[tetracycline]' => array(
        'type'   => 'checkbox',
        'label'  => '<span class="english">Tetracycline antibiotics</span><span class="spanish">Hola</span>',
    	),
        'allergies[other_english]' => array(
        'type' => 'text',
        'class' => array('english'),
        'placeholder'=> 'Other Allergies'
        ),
        'allergies[other_spanish]' => array(
        'type' => 'text',
        'class' => array('spanish'),
        'placeholder'=> 'Otras Allergias'
        ),
        'date_of_birth' => array(
        'type' => 'text',
        'label'     => '<span class="english">Date of Birth</span><span class="spanish">Hola</span>',
        'required'  => true
        ),
    ];

    foreach ($fields['billing'] as $key => $val) {
        $custom[$key] = $val;
    }

    $fields['billing'] = $custom;

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
add_action( 'woocommerce_created_customer', 'bbloomer_save_name_fields' );
function bbloomer_save_name_fields( $customer_id ) {
    if ( isset( $_POST['billing_first_name'] ) ) {
        update_user_meta( $customer_id, 'billing_first_name', sanitize_text_field( $_POST['billing_first_name'] ) );
    }
    if ( isset( $_POST['billing_last_name'] ) ) {
        update_user_meta( $customer_id, 'billing_last_name', sanitize_text_field( $_POST['billing_last_name'] ) );
    }
}
