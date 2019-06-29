jQuery(load)
function load() {

  var map = {
   'General Info':'info',
   'Rx Info':'rx-info',
   'Inventory': 'inventory',
   'Registration':'registration',
   'Delivery Issue':'delivery-issue',
   'Cancel/Delay Order':'cancel-order',
   'Refill Request': 'refill-request',
   'Transfer Request':'transfer-request',
   'Payment': 'payment'
  }


  jQuery('#wpas_call-type').on("select2:selecting", function(e) {
     jQuery('#wpas_'+ map[e.params.args.data.text]).show()
  });

  jQuery('#wpas_call-type').on("select2:unselecting", function(e) {
     jQuery('#wpas_'+ map[e.params.args.data.text]).hide()
  });
}
