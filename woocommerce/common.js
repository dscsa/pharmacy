jQuery(load)

function load() {

  if (window.location.search == '?register')
    return register_page()

  if (window.location.pathname == '/account/details/')
    return account_page()

  if (window.location.pathname == '/account/')
    return account_page()
}

function register_page() {
  translate()
  createUsername()
  upgradeBirthdate()
}

function account_page() {
  upgradePharmacy()
  upgradeAllergies()
  disableFixedFields()
}

function translate() {
  jQuery("#language_EN").change(hideSpanish)
  jQuery("#language_ES").change(hideEnglish)
  jQuery("<style id='language' type='text/css'></style>").appendTo('head')
  jQuery("input[name=language]:checked").triggerHandler('change')
}

function hideEnglish() {
  jQuery('#language').html(".english{display:none}")
}

function hideSpanish() {
  jQuery('#language').html(".spanish{display:none}")
}

function upgradeAllergies() {
  jQuery("input[name=allergies_none]").on('change', function(){
    var children = jQuery(".allergies")
    this.value ? children.hide() : children.show()
  })
  jQuery("input[name=allergies_none]:checked").triggerHandler('change')

  var allergies_other = jQuery('#allergies_other').prop('disabled', true)
  jQuery('#allergies_other_input').on('input', function() {
    allergies_other.prop('checked', this.value)
  })
}

function upgradePharmacy(pharmacies) {
  console.log('upgradePharmacy')

  var select = jQuery('#backup_pharmacy')
  var pharmacyGsheet = "https://spreadsheets.google.com/feeds/list/1ivCEaGhSix2K2DvgWQGvd9D7HmHEKA3VkQISbhQpK8g/1/public/values?alt=json"
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

function createUsername() {
  jQuery('#customer_login > div').toggle()
}

function upgradeBirthdate() {
  jQuery('#birth_date').prop('type', 'date') //can't easily set date type in woocommerce
}

//Disabled fields not submitted causing validation error.
function disableFixedFields() {
  jQuery('#birth_date').prop('readonly', true)
  jQuery('#account_first_name').prop('readonly', true)
  jQuery('#account_last_name').prop('readonly', true)
  //Readonly doesn't work for radios https://stackoverflow.com/questions/1953017/why-cant-radio-buttons-be-readonly
  jQuery('input[name=language]:not(:checked)').attr('disabled', true)

  var other_allergy = jQuery('#allergies_other_input')
  if (other_allergy.val()) //we cannot properly edit in guardian right now
    other_allergy.prop('disabled', true)
}

function pharmacy2select(entry, i) {

  var store = {
    fax:entry.gsx$fax.$t,
    phone:entry.gsx$phone.$t,
    npi:entry.gsx$npi.$t,
    street:entry.gsx$street.$t,
    city:entry.gsx$city.$t,
    state:'GA',
    zip:entry.gsx$zip.$t,
    name:entry.gsx$name.$t
  }
  var text = store.name+', '+store.street+', '+store.city+', GA '+store.zip+' - Phone: '+store.phone
  return {id:JSON.stringify(store), text:text}
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
