var medicationGsheet = "https://spreadsheets.google.com/feeds/list/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/ovrg94l/public/values?alt=json"
var pharmacyGsheet  = "https://spreadsheets.google.com/feeds/list/11Ew_naOBwFihUrkaQnqVTn_3rEx6eAwMvGzksVTv_10/1/public/values?alt=json"
//ovrg94l is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/private/full
var medications
var pharmacies

Cognito.load("forms", { id: "17" }, {success:load})


function load() {
  ExoJQuery(function() {
    showAcceptTerms()

    ExoJQuery(document).on('afterNavigate.cognito', navigate)

    ExoJQuery.ajax({
      url:medicationGsheet,
      type: 'GET',
      cache:true,
      success:function($data) {
        console.log('medications gsheet', $data.feed.entry)
        medications = $data.feed.entry.map(medication2select)
        console.log('medications cleaned', medications)
      }
    })

    ExoJQuery.ajax({
      url:pharmacyGsheet,
      type: 'GET',
      cache:true,
      success:function($data) {
        console.log('medications gsheet', $data.feed.entry)
        pharmacies = $data.feed.entry.map(pharmacy2select)
        console.log('medications cleaned', pharmacies)
      }
    })
  })
}

function navigate(e, data) {
  upgradeMedication()
}

function upgradeMedication() {
  console.log('upgradeMedication')
  var medicationSelect = jQuery('[data-field="SearchAndSelectMedicationsByGenericName"] select')
  var medicationPrice  = jQuery('[data-field="MedicationPrice"] select')
  var medicationList   = jQuery('[data-field="MedicationList"] input')
  medicationSelect.children().remove()
  medicationSelect.select2({multiple:true,data:medications}).on("change", updatePrice)

  var medicationInput = jQuery('.select2-search__field')       //this doesn't exist until select2 is run
  medicationInput.removeAttr("type")                           //Removes conflict between enfold and select2

  function updatePrice(e) {

    var price = medicationSelect.select2('data').reduce(sum, 0)

    console.log('updatePrice', price)
    //We have to update a text box because cognito won't save values from a multi-select form
    //We could just upgrade a text box (rather than select) but that would require full select2 not lite
    medicationPrice.val(Math.min(100, price)).click().change()
    medicationList.val(medicationSelect.val()).click().change()
  }
}

function sum(a, b) {
  console.log('a', a, 'b', b)
  return +b.price+a
}

function showAcceptTerms() {
  ExoJQuery('.loader').hide()
  ExoJQuery('#c-submit-button').parent().parent().prepend('<div style="font-size:12px; max-width:785px; margin-left:10px; margin-bottom:10px; padding-top:10px">By clicking Accept & Submit, I attest to the statements below and understand that the medication(s) that I am receiving from SIRUM now & in the future may have been donated, previously dispensed, and potentially stored in an uncontrolled environment.</div>')
}


function medication2select(entry, i) {
  var price     = entry.gsx$day_2.$t || entry.gsx$day.$t
  var message   = []

  if (entry.gsx$supplylevel.$t)
    message.push(entry.gsx$supplylevel.$t)

  if (entry.gsx$day.$t)
    message.push('30 day')

  message = message.length ? ' ('+message.join(', ')+')' : ''

  var drug = ' '+entry.gsx$drugname.$t+', '+price+message
  return {id:drug, text:drug, disabled:entry.gsx$supplylevel.$t == 'Out of Stock', price:price.replace('$', '')}
}

function pharmacy2select(entry, i) {
  console.log('pharmacy entry')
  return {id:drug, text:entry.gsx$name.$t+', '+entry.gsx$cleanaddress.$t+'(phone:'+entry.gsx$phone.$t+', fax:'+entry.gsx$fax.$t+')'}
}
