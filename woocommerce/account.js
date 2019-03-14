jQuery(load)
function load() {

  signup2signout()
  translate()
  savePaymentCard()

  if (window.location.pathname == '/account/details/')
    return account_page()

  if (window.location.pathname == '/account/')
    return account_page()
}

function account_page() {
  console.log('account.js account page')
  upgradeAutofill()
  upgradePharmacy()
  upgradeAllergies()
  upgradeBirthdate()
  clearEmail()
  disableFixedFields()
  setSource()
}

function upgradeAutofill() {
  var rx_autofills = jQuery("input.rx_autofill")

  //When RX Autofill is unchecked, disable refill date input and have it display N/A
  //When RX Autofill is checked have it display placeholder of next-fill and allow user to select date
  rx_autofills.on('change', function(e, firstCall){
    var elem  = jQuery(this)
    var row   = elem.closest('tr.rx')
    var input = row.find('.next_fill')
    var nextFill = input.attr('next-fill')
    var disabled = row.hasClass('nextfill-disabled')
    var off = row.hasClass('autofill-off')
    var val = input.val()
    console.log('toggle rx autofill', this.checked, disabled, val, input.attr('next-fill'), input.attr('default'))
    if (off) {
      elem.prop('checked', false)
      elem.prop('disabled', true)
    }
    input.prop('disabled',  disabled)

    var twoDaysFromNow  = new Date()
    twoDaysFromNow.setDate(twoDaysFromNow.getDate() + 2)
    twoDaysFromNow = twoDaysFromNow.toJSON().slice(0, 10)

    var placeholder = 'Past Due'

    if (disabled)
      placeholder = 'N/A'
    else if ( ! this.checked)
      placeholder = 'On Request'
    else if (nextFill >= twoDaysFromNow)
      placeholder = nextFill

    if (placeholder)
      input.prop('placeholder', placeholder)

    if (placeholder == 'Past Due' && ! firstCall && ! val)
      input.val(twoDaysFromNow) //If changed to checked and nextFill is blank or past then set it for two day from now
  })

  //When Patient Autofill is unchecked, uncheck and disable all Rx Autofill checkboxes and uncheck the "New Rx" autofill
  //When Patient Autofill is checked, enable all Rx Autofills (but don't check them) and check the "New Rx" autofill
  jQuery(".pat_autofill input").on('change', function(e, firstCall){
    console.log('toggle patient autofill', this.checked)
    rx_autofills.prop('disabled', ! this.checked)
    if ( ! this.checked) rx_autofills.prop('checked', false)
    jQuery("input.new_rx_autofill").prop('checked', this.checked)
    rx_autofills.trigger('change', firstCall) //triggerHandler only works on first matched element
  })

  //Put date picker UI on any enabled next-fill input
  //Constraint only works for the UI calendar, it does not prevent writing in a date https://bugs.jqueryui.com/ticket/6917
  jQuery(".next_fill").each(function() {
    var elem = jQuery(this)
    elem.datepicker({changeMonth:true, changeYear:true, minDate:"+2d", maxDate:"+6m", dateFormat:"yy-mm-dd", constrainInput:true})
  })

  //Disable and set days based for new Rxs based on live inventory
  getInventory(function(inventory) {
    console.log('upgradeAutofill getInventory', 'inventory.length', inventory.length)

    jQuery("tr.rx").each(function(i, tableRow) {
      tableRow     = jQuery(tableRow)
      var gcn      = tableRow.attr('gcn')
      console.log('upgradeAutofill', i, gcn)

      var nextFill = tableRow.find(".next_fill")
      //We could do this in PHP but doing here because of the parrallel with refills-only, out-of-stock, and gcn-error

      if (nextFill.val() == 'No Refills') {
        tableRow.addClass('nextfill-disabled')
        console.log('upgradeAutofill "No Refills" is disabled', i, gcn, nextFill.val(), nextFill)
        return
      }

      if (nextFill.val() == 'Rx Expired') {
        tableRow.addClass('nextfill-disabled')
        console.log('upgradeAutofill "Rx Expired" is disabled', i, gcn, nextFill.val(), nextFill)
        return
      }

      if ( ~ nextFill.val().indexOf('Order')) {
        tableRow.addClass('nextfill-disabled')
        console.log('upgradeAutofill "Order" is disabled', i, gcn, nextFill.val(), nextFill)
        return
      }

      if (nextFill.val() == 'Transferred') {
        tableRow.addClass('nextfill-disabled autofill-off')
        console.log('upgradeAutofill "Transferred" is disabled', i, gcn, nextFill.val(), nextFill)
        return
      }

      for (var j in inventory) {

        var row = inventory[j]

        if ( ! ~ row.gcns.indexOf(gcn)) continue

        if (row.stock == 'Refills Only' && tableRow.hasClass('new')) {
          tableRow.addClass('nextfill-disabled autofill-off')
          nextFill.val('Refills Only')
          console.log('upgradeAutofill Refills Only', row.name, i, gcn, nextFill.val(), nextFill)
          jQuery("tr.rx td.day_qty").val(45)
          break
        }

        if (row.stock == 'Out of Stock' || row.stock == 'Not Offered') {
          console.log('upgradeAutofill Out of Stock || Not Offered', row.name, i, gcn, nextFill.val(), nextFill)
          tableRow.addClass('nextfill-disabled autofill-off')
          nextFill.val(row.stock)
          jQuery("tr.rx td.day_qty").val(45)
          break
        }
      }

      if (j == inventory.length) {
        console.log('upgradeAutofill Gcn Error', row.name, i, gcn, nextFill.val(), nextFill)
        tableRow.addClass('nextfill-disabled')
        nextFill.val('Gcn Error')
        jQuery("tr.rx td.day_qty").val('')
      }
    })

    jQuery(".pat_autofill input").triggerHandler('change', true)
  })
}
