/*
 * Install "My Custom Functions" plugin and insert this code into Wordpress > Appearance > Custom Functions
 */

add_action( 'added_post_meta', 'custom_added_post_meta', 10, 4);
add_action( 'updated_post_meta', 'custom_added_post_meta', 10, 4);
function custom_added_post_meta( $meta_id, $post_id, $meta_key, $meta_value )
{
   //$response = wp_remote_post( $url, );
   if ($meta_key == '_trustcommerce_customer_id') {
    $post = ['body' => ['trustcommerce_customer_id' => $meta_value, 'email' => wp_get_current_user()->data->user_email]];
    $res  = wp_remote_post('https://webform.goodpill.org/billing', $data);
   	wp_mail('adam@sirum.org', 'added_post_meta', print_r($res, true));
   }
}

add_action( 'deleted_post_meta', 'custom_deleted_post_meta', 10, 4);
function custom_deleted_post_meta( $deleted_meta_ids, $post_id, $meta_key, $only_delete_these_meta_values )
{
   wp_mail('adam@sirum.org', 'deleted_post_meta', print_r(func_get_args(), true));
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
// 1. ADD FIELDS
add_action( 'woocommerce_register_form_start', 'bbloomer_add_name_woo_account_registration' );
function bbloomer_add_name_woo_account_registration() {
    ?>
    <p class="form-row form-row-first">
    <label for="reg_billing_first_name"><?php _e( 'First name', 'woocommerce' ); ?> <span class="required">*</span></label>
    <input type="text" class="input-text" name="billing_first_name" id="reg_billing_first_name" value="<?php if ( ! empty( $_POST['billing_first_name'] ) ) esc_attr_e( $_POST['billing_first_name'] ); ?>" />
    </p>
    <p class="form-row form-row-last">
    <label for="reg_billing_last_name"><?php _e( 'Last name', 'woocommerce' ); ?> <span class="required">*</span></label>
    <input type="text" class="input-text" name="billing_last_name" id="reg_billing_last_name" value="<?php if ( ! empty( $_POST['billing_last_name'] ) ) esc_attr_e( $_POST['billing_last_name'] ); ?>" />
    </p>
    <div class="clear"></div>
    <?php
}
///////////////////////////////
// 2. VALIDATE FIELDS
add_filter( 'woocommerce_registration_errors', 'bbloomer_validate_name_fields', 10, 3 );
function bbloomer_validate_name_fields( $errors, $username, $email ) {
    if ( isset( $_POST['billing_first_name'] ) && empty( $_POST['billing_first_name'] ) ) {
        $errors->add( 'billing_first_name_error', __( '<strong>Error</strong>: First name is required!', 'woocommerce' ) );
    }
    if ( isset( $_POST['billing_last_name'] ) && empty( $_POST['billing_last_name'] ) ) {
        $errors->add( 'billing_last_name_error', __( '<strong>Error</strong>: Last name is required!.', 'woocommerce' ) );
    }
    return $errors;
}
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
