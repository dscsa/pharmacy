jQuery(load)

function load() {
  
  jQuery('.erx').show()

  upgradeMedication()

  //Switch columns https://stackoverflow.com/questions/7742305/changing-the-order-of-elements
  jQuery('.col-1').prepend(jQuery('.woocommerce-additional-fields'))
  jQuery('.col-1').prepend(jQuery('#order_review_heading'))

  jQuery('form.checkout').show()
  jQuery('.woocommerce-MyAccount-content').hide()

  jQuery("#source_english")
  .change(function(){
    jQuery('#medication\\[\\]_field').toggle()
    jQuery('.erx, .pharmacy').toggle()
  })

  jQuery("#source_spanish").change(function(){
    jQuery('#medication\\[\\]_field').toggle()
    jQuery('.erx, .pharmacy').toggle()
  })

  //Trust commerce gateway is not smart enough to do MM/YYYY to MM/YY for us
  jQuery('input#trustcommerce-card-expiry').on('input', function() {
    var _this = jQuery(this)
    _this.val(_this.val().replace(/20(\d\d)/, '$1'))
  })

  jQuery("#billing_state").on('change', function($event){

    var shipAddress = jQuery("#ship-to-different-address-checkbox")

    if (this.value != 'GA')
      shipAddress.click().prop('disabled', true)
    else
      shipAddress.prop('disabled', false).click()
  })
}

function upgradeMedication(medications) {
  console.log('upgradeMedication')

  var select = jQuery('#medication\\[\\]')
  select.empty()

  var medicationGsheet = "https://spreadsheets.google.com/feeds/list/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/ovrg94l/public/values?alt=json"
  //ovrg94l is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/private/full

  jQuery.ajax({
    url:medicationGsheet,
    type: 'GET',
    cache:true,
    success:function($data) {
      console.log('medications gsheet', $data.feed.entry)
      select.select2({multiple:true,data:$data.feed.entry.map(medication2select)})
    }
  })
}

function upgradePharmacy(pharmacies) {
  console.log('upgradePharmacy')

  var select = jQuery('#backup_pharmacy')
  var pharmacyGsheet  = "https://spreadsheets.google.com/feeds/list/11Ew_naOBwFihUrkaQnqVTn_3rEx6eAwMvGzksVTv_10/1/public/values?alt=json"
  //ovrg94l is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/private/full

  jQuery.ajax({
    url:pharmacyGsheet,
    type: 'GET',
    cache:true,
    success:function($data) {
      var pharmacies = $data.feed.entry.map(pharmacy2select)
      select.select2({data:pharmacies, matcher:matcher, minimumInputLength:3})
    }
  })
}

function medication2select(entry, i) {
  var price     = entry.gsx$day_2.$t || entry.gsx$day.$t
  var message   = []

  if (entry.gsx$supplylevel.$t)
    message.push(entry.gsx$supplylevel.$t)

  message.push(entry.gsx$day.$t ? '30 day' : '90 day')

  var drug = ' '+entry.gsx$drugname.$t+', '+price+' ('+message.join(', ')+')'
  return {id:drug, text:drug, disabled:entry.gsx$supplylevel.$t == 'Out of Stock', price:price.replace('$', '')}
}

function pharmacy2select(entry, i) {
  var address  = entry.gsx$cleanaddress.$t.replace(/(\d{5})(\d{4})/, '$1')
  var pharmacy = entry.gsx$name.$t+', '+address+', Phone: '+entry.gsx$phone.$t
  return {id:pharmacy+', Fax:'+entry.gsx$fax.$t, text:pharmacy}
}

//http://stackoverflow.com/questions/36591473/how-to-use-matcher-in-select2-js-v-4-0-0
function matcher(param, data) {
   if ( ! param.term ||  ! data.text) return null
   var has = true
   var words = param.term.toUpperCase().split(" ")
   var text  = data.text.toUpperCase()
   for (var i =0; i < words.length; i++)
     if ( ! ~ text.indexOf(words[i])) return null

   return data
}
