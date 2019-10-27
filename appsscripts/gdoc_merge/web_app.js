function testPost() {
  var event = {parameter:{GD_KEY:GD_KEY}, content:{method:'findValue', file:'Order Summary #20660', needle:'(Fee:|Amount Due:)'}}
  debugEmail(event, doPost(event))
}

function doPost(e) {

  try{

    if (e.parameter.GD_KEY != GD_KEY)
      return debugEmail('web_app post wrong password', JSON.stringify(e))

    if ( ! e.postData || ! e.postData.contents)
      return debugEmail('web_app post not post data', JSON.stringify(e))

    var contents = JSON.parse(e.postData.contents)

    if (contents.method == 'mergeDoc')
      return mergeDoc(contents)

    if (contents.method == 'findValue')
      return findValue(contents)

    debugEmail('web_app post no matching method', JSON.stringify(e))

  } catch(err){
      debugEmail('web_app post error thrown', JSON.stringify([err, e]))
  }
}
