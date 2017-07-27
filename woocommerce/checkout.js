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

  //Toggle medication select and backup pharmacy text based on whether
  //Rx is being sent from doctor or transferred from a pharmacy.
  jQuery("#rx_source_pharmacy, #rx_source_erx")
  .change(function(){
    console.log('rx source change', this.id)
    jQuery('#medication\\[\\]_field').toggle()
    jQuery('.erx, .pharmacy').toggle()
  })

  //Not used currently.  We don't need billing info because we don't check
  //it in stripe.  If we ever do then we will need something like this.
  // jQuery("#billing_state").on('change', function($event){
  //
  //   var shipAddress = jQuery("#ship-to-different-address-checkbox")
  //
  //   if (this.value != 'GA')
  //     shipAddress.click().prop('disabled', true)
  //   else
  //     shipAddress.prop('disabled', false).click()
  // })
}
