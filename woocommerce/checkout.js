jQuery(load)

function load() {

  upgradeMedication()

  //Show our form fields at the beginning rather than the end
  jQuery('form.checkout .col-1').prepend(jQuery('.woocommerce-additional-fields'))

  //Hide default dashboard content and show our order form instead
  jQuery('form.checkout').show()
  jQuery('.woocommerce-MyAccount-content').hide()

  //Move coupon from top of page to be next to payment instead
  jQuery( "a.showcoupon" ).parent().appendTo(jQuery('#customer_details'))
  jQuery('form.checkout_coupon').appendTo(jQuery('#customer_details'))

  //Save card info to our account automatically
  jQuery('#wc-stripe-new-payment-method').prop('checked', true)

  //This class causes update_checkout_totals, which causes a spinner and delay that we don't need
  //https://github.com/woocommerce/woocommerce/blob/master/assets/js/frontend/checkout.js
  $(".address-field").removeClass("address-field")

  //Toggle medication select and backup pharmacy text based on whether
  //Rx is being sent from doctor or transferred from a pharmacy.
  setSource()
}
