jQuery(load)

function load() {

  jQuery("form.register").submit(function($event){
    $event.preventDefault()
    this.username.value = this.account_first_name.value+' '+this.account_last_name.value+' '+this.account_birth_date.value
  })

  //hide saved cards on everything but the account details page which has a password field
  //for some reason there is a space in the id so need the \\20
  if (window.location.pathname == '/account/address/')
    jQuery('#tc-saved-cards\\20').next().show()

  if ( ! ~ ['/account/details/', '/account/'].indexOf(window.location.pathname))
    return

  upgradePharmacy()

  var lang = jQuery("input[name=account_language]:checked").val()

  showLang()
  setTimeout(showLang, 3000) //Both languages hide by default.  Need delay because ZIP, City/Town, Credit Card are delayed
  function showLang() { jQuery('.'+lang).show() }

  jQuery("input[name=account_language]").change(function(){
    jQuery('.spanish, .english').toggle()
  })

  jQuery("input[name=account_allergies]").on('change', function(){
    var children = jQuery(".checkbox, #account_allergies_other")
    console.log('children', children, this.value)
    this.value == 'No' ? children.hide() : children.show()
  }).triggerHandler('change')

  var other_allergy_checkbox = jQuery('#account_allergies_other_checkbox').prop('disabled', true)
  jQuery('#account_allergies_other').on('input', function() {
    other_allergy_checkbox.prop('checked', this.value)
  })

  if (window.location.pathname != '/account/')
    return jQuery('.pharmacy').show()//Both pharmacy labels hidden by default.  Show the one with value

  jQuery('.erx').show()

  upgradeMedication()

  //Switch columns https://stackoverflow.com/questions/7742305/changing-the-order-of-elements
  jQuery('.col-1').prepend(jQuery('.woocommerce-additional-fields'))
  jQuery('.col-1').prepend(jQuery('#order_review_heading'))

  jQuery('form.checkout').show()
  jQuery('.woocommerce-MyAccount-content').hide()
  jQuery('#account_birth_date').prop('type', 'date') //can't easily set date type in woocommerce

  jQuery("#source_english")
  .change(function(){
    jQuery('#medication_field').toggle()
    jQuery('.erx, .pharmacy').toggle()
  })

  jQuery("#source_spanish").change(function(){
    jQuery('#medication_field').toggle()
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

  var select = jQuery('#medication')
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

  var select = jQuery('#account_backup_pharmacy')
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
