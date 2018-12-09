jQuery(load)

//<select id="medication[]" data-placeholder="Search available medications" multiple></select>
function load() {
  upgradeStock()
}

function upgradeStock() {

  var select = jQuery('#stock\\[\\]')

  getInventory(function(data) {
    console.log('upgradeStock data', data.length, data)

    //Remove low stock (disabled) items
    data = disableInventory(data, {}).filter(function(drug) { return ! drug.disabled })

    select.select2({closeOnSelect:false, data:data})

    open()
    //<IE9 subsitute for 100vh
    //Only way I could get results to be scrollable and logo off the page
    jQuery('.select2-results__options').unbind('mousewheel').css('max-height', 'none')

    select //keep it open always and don't allow selection
    .on("select2:closing", preventDefault)
    .on("select2:selecting", preventDefault)
    .on("select2:closed", open)
  })

  function open() {
    console.log('select2 open')
    select.select2("open")
    jQuery(':focus').blur()
  }
}
