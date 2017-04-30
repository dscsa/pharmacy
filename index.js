var gsheet = "https://spreadsheets.google.com/feeds/list/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/ovrg94l/public/values?alt=json"
//ovrg94l is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/private/full
var medications

Cognito.load("forms", { id: "17" }, {success:load})

jQuery.ajax({
   url:gsheet,
   type: 'GET',
   cache:true,
   success:function($data) {
     console.log('$data.feed.entry', $data.feed.entry)
     medications = $data.feed.entry.map(gsheet2select)
     load()
   }
})

load.count = 0
function load() {
  if(load.count++ < 2) return
  showAcceptTerms()
  upgradeMedication()
}

function upgradeMedication() {
  var medicationSelect = jQuery('[data-field="SearchAndSelectMedicationsByGenericName"] select')
  var medicationPrice  = jQuery('[data-field="MedicationPrice"] select')
  var medicationList   = jQuery('[data-field="MedicationList"] input')

  medicationSelect.children().remove()
  medicationSelect.select2({multiple:true,data:medications})
  .on("select2:open", toggleHeader)
  .on("select2:close", toggleHeader)
  .on("change", updatePrice)

  function updatePrice(e) {
    var price = medicationSelect.select2('data').reduce(sum, 0)

    //We have to update a text box because cognito won't save values from a multi-select form
    medicationPrice.val(Math.min(100, price)).click().change()
    medicationList.val(medicationSelect.val()).click().change()
  }
}

function gsheet2select(entry, i) {
  if ( ! entry.gsx$stocklevel || ! entry.gsx$genericdrugname || ! entry.gsx$strength || ! entry.gsx$onli30)
    console.log(entry, i)
  var disabled = entry.gsx$stocklevel.$t == 'Out of Stock' ? ' (Out of Stock)' : ''
  var drug = ' '+entry.gsx$genericdrugname.$t+' '+entry.gsx$strength.$t+', $'+entry.gsx$onli30.$t+'.00'+disabled
  var result = {id:drug, text:drug, disabled:!!disabled, price:entry.gsx$onli30.$t}
  return result
}

function toggleHeader() {
  jQuery('header').toggle()
}

function sum(a, b) {
  return +b.price+a
}

function showAcceptTerms() {
  jQuery('#loading').hide()
  jQuery('.c-button-section').prepend('<div style="font-size:12px; max-width:785px; margin-left:10px; margin-bottom:10px">By clicking Accept & Submit, I attest to the statements below and understand that the medication(s) that I am receiving from SIRUM now & in the future may have been donated, previously dispensed, and potentially stored in an uncontrolled environment.</div>')
}
