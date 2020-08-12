jQuery(load)

//<select id="medication[]" data-placeholder="Search available medications" multiple></select>
function load() {
  upgradeStock();
  stickySidebar();

  if (window.sessionStorage)
    upgradePharmacy() //not needed on this page but fetch && cache the results for a quicker checkout page
}

function stickySidebar(){
  let $sidebar = $('.medication-list__info'),
      offset = $sidebar.offset(),
      $parent = $sidebar.parent(),
      tmp = $sidebar.find('nav').clone().attr('class', 'tmp').css('visibility', 'hidden');

  window.addEventListener('scroll', function() {
    if (window.pageYOffset > offset.top) {
      $parent.append(tmp);
      $sidebar.css({'position': 'fixed', 'top': 0});
    } else {
      $parent.find('.tmp').remove();
      $sidebar.css({'position': 'absolute', 'top': 660});
    }
  });
}

function upgradeStock() {

  var select = jQuery('#stock\\[\\]')

  getInventory(function(data) {
    console.log('upgradeStock data', data.length, data)

    //Remove low stock (disabled) items
    data = disableInventory(data, {}).filter(function(drug) { return ! drug.disabled })

    select.select2({closeOnSelect:false, data:data, dropdownParent:$('.medication-list__content')});

    $('.medication-list').addClass('medication-list--loaded');

    open();
    //<IE9 subsitute for 100vh
    //Only way I could get results to be scrollable and logo off the page
    jQuery('.select2-results__options').unbind('mousewheel').css('max-height', 'none');

    select //keep it open always and don't allow selection
    .on("select2:closing", preventDefault)
    .on("select2:selecting", preventDefault)
    .on("select2:closed", open)
  });

  function open() {
    console.log('select2 open')
    select.select2("open")
    jQuery(':focus').blur()
  }
}
