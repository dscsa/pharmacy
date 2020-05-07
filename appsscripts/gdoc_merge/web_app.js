function testMergeInvoice() {

  var contents = JSON.stringify({
    method:'mergeDoc',
    template:'Invoice Template v1',
    file:'TEST Invoice #'+test_order[0]['invoice_number'],
    folder: 'Pending',
    order:test_order
  })

  Logger.log(contents)

  var event = {parameter:{GD_KEY:GD_KEY}, postData:{contents:contents}}

  debugEmail('testMergeInvoice', event, doPost(event))
}

function testMergeInvoice2() {

  var contents = JSON.stringify({
    method:'mergeDoc',
    template:'Invoice Template v1',
    file:'TEST Invoice #'+test_order3[0]['invoice_number'],
    folder: 'Pending',
    order:test_order3
  })

  Logger.log(contents)

  var event = {parameter:{GD_KEY:GD_KEY}, postData:{contents:contents}}

  debugEmail('testMergeInvoice2', event, doPost(event))
}


function testMergeFax() {

   var contents = JSON.stringify({
    method:'mergeDoc',
    template:'Transfer Out Fax v1',
    file:'TEST Transfer #'+test_order[0]['pharmacy_phone'],
    folder: 'Test Transfers',
    order:test_order
  })

  Logger.log(contents)

  var event = {parameter:{GD_KEY:GD_KEY}, postData:{contents:contents}}

  debugEmail(event, doPost(event))
}


function doPost(e) {

  try{

    if (e.parameter.GD_KEY != GD_KEY)
      return debugEmail('gdoc_merge post wrong password', e)

    if ( ! e.postData || ! e.postData.contents)
      return debugEmail('gdoc_merge post not post data', e)
      
    //debugEmail('gdoc_merge called and will be run', e.postData.contents.length)

    var response
    var contents = JSON.parse(e.postData.contents)

    if (contents.method == 'mergeDoc')
      response = mergeDoc(contents)
      
    else
      debugEmail('gdoc_merge post no matching method', e)
      
    if ( ! response)
      debugEmail('gdoc_merge no response given', contents)
    //else 
    //  debugEmail('gdoc_merge response was', response)

    return ContentService
      .createTextOutput(JSON.stringify(response))
      .setMimeType(ContentService.MimeType.JSON)

  } catch(err){
      debugEmail('gdoc_merge post error thrown', err, err.errorMessage, err.scriptStackTraceElements, e)
  }
}
