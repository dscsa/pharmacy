jQuery(load)

function load() {

  //hide saved cards on everything but the account details page which has a password field
  //for some reason there is a space in the id so need the \\20
  if (jQuery('#password_current').length)
    jQuery('#tc-saved-cards\\20').show().next().show()

  if ( ! ~ ['/account/edit/', '/account/'].indexOf(window.location.pathname))
    return

  if (window.location.pathname != '/account/')
    return

  //Switch columns https://stackoverflow.com/questions/7742305/changing-the-order-of-elements
  jQuery('.col-1').prepend(jQuery('.woocommerce-additional-fields'))
  jQuery('.col-1').prepend(jQuery('#order_review_heading'))

  jQuery('#order_review_heading').html('<span class="english">New Order</span><span class="spanish">Order Nuevo</span>')
  jQuery('form.checkout').show()
  jQuery('.woocommerce-MyAccount-content').hide()
  jQuery('#account_date_of_birth').prop('type', 'date') //can't easily set date type in woocommerce
  var medicationGsheet = "https://spreadsheets.google.com/feeds/list/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/ovrg94l/public/values?alt=json"
  var pharmacyGsheet  = "https://spreadsheets.google.com/feeds/list/11Ew_naOBwFihUrkaQnqVTn_3rEx6eAwMvGzksVTv_10/1/public/values?alt=json"
  //ovrg94l is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/private/full

  jQuery.ajax({
    url:medicationGsheet,
    type: 'GET',
    cache:true,
    success:function($data) {
      console.log('medications gsheet', $data.feed.entry)
      var medications = $data.feed.entry.map(medication2select)
      upgradeMedication(medications)
    }
  })

  jQuery.ajax({
    url:pharmacyGsheet,
    type: 'GET',
    cache:true,
    success:function($data) {
      var pharmacies = $data.feed.entry.map(pharmacy2select)
      upgradePharmacy(pharmacies)
    }
  })

  jQuery("#source_english").change(function(){
    jQuery('#medication_field').toggle()
    togglePharmacyLabel('english', this.value)
  })

  jQuery("#source_spanish").change(function(){
    jQuery('#medication_field').toggle()
    togglePharmacyLabel('spanish', this.value)
  })

  jQuery("#account_language").change(function(){
    jQuery('.spanish, .english').toggle()
    togglePharmacyLabel(this.value, jQuery("#source_"+this.value).val())
  })

  function togglePharmacyLabel($lang, $src) {
    jQuery('.erx, .pharmacy').hide()
    jQuery('.'+$lang+'.'+$src).show()
  }

  //Trust commerce gateway is not smart enough to do MM/YYYY to MM/YY for us
  jQuery('input#trustcommerce-card-expiry').on('input', function() {
    var _this = jQuery(this)
    _this.val(_this.val().replace(/20(\d\d)/, '$1'))
  })

  jQuery("#account_allergies_english").on('change', function($event){
    jQuery(".checkbox, #account_allergies\\[other_english\\]_field").toggle()
  })

  jQuery("#account_allergies_spanish").on('change', function($event){
    jQuery(".checkbox, #account_allergies\\[other_spanish\\]_field").toggle()
  })

  jQuery("#billing_state").on('change', function($event){
    if (this.value == 'GA')
      jQuery("#ship-to-different-address-checkbox").prop('checked', true).prop('disabled', true)
    else
      jQuery("#ship-to-different-address-checkbox").prop('disabled', false)
  })
}

function upgradeMedication(medications) {
  console.log('upgradeMedication')
  var select = jQuery('#medication')
  select.empty()
  select.select2({multiple:true,data:medications})
}

function upgradePharmacy(pharmacies) {
  console.log('upgradePharmacy')
  var select = jQuery('#account_backupPharmacy')
  select.select2({data:pharmacies, matcher:matcher, minimumInputLength:3})
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
