jQuery(load)

//<select id="medication[]" data-placeholder="Search available medications" multiple></select>
function load() {

  upgradeMedication(true, function(medication) {
    open()

    //<IE9 subsitute for 100vh
    //Only way I could get results to be scrollable and logo off the page
    var height = jQuery(window).height() - 200 //200 accounts for the nav and searchbox
    jQuery('.select2-results__options').unbind('mousewheel').css('max-height', 'none')

    medication //keep it open always and don't allow selection
    .on("select2:closing", preventDefault)
    .on("select2:selecting", preventDefault)
    .on("select2:closed", open)

    function open() {
      console.log('select2 open')
      medication.select2("open")
    }

    function preventDefault(e) {
      console.log('select2 preventDefault')
      e.preventDefault()
    }
  })
}
