jQuery(load)

//<select id="medication[]" data-placeholder="Search available medications" multiple></select>
function load() {

  upgradeMedication(function(medication) {
    open()

    medication //keep it open always and don't allow selection
    .on("select2:closing, select2:selecting", preventDefault)
    .on("select2:closed", open)

    function open() {
      medication.select2("open")
    }

    function preventDefault(e) {
      e.preventDefault()
    }
  })
}
