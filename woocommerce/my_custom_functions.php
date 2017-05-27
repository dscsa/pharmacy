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
     $res  = wp_remote_post('https://webform.goodpill.org/billing', $post);
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
