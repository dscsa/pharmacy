jQuery(load)

//<select id="medication[]" data-placeholder="Search available medications" multiple></select>
function load() {

  upgradeStock(function(select, stock) {
    console.log(typeof select, typeof stock, arguments)
    open()
    //<IE9 subsitute for 100vh
    //Only way I could get results to be scrollable and logo off the page
    jQuery('.select2-results__options').unbind('mousewheel').css('max-height', 'none')

    function custom() {
      console.log('upgradeStock', 'stock.length', stock.length, stock)
      preventDefault()
    }

    select //keep it open always and don't allow selection
    .on("select2:closing", custom)
    .on("select2:selecting", custom)
    .on("select2:closed", open)

    function open() {
      console.log('select2 open')
      select.select2("open")
      jQuery(':focus').blur()
    }
  })
}
