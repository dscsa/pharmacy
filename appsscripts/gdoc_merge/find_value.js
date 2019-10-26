
function findValue(content) {



  //Return the first doc, assuming this is always the most recent?
  var docs = filesByName(content.file)

  //We should be able to do replaceText on invoice but we use differing headers footers for the first page
  //which don't get picked up so we need to make our replacements on every https://issuetracker.google.com/issues/36763014
  var documentElement = docs[0].getBody().getParent()
  var numChildren = documentElement.getNumChildren()

  var res = {doc_id:docs[0].getId(), doc_name:docs[0].getName(), values:[]}

  for (var i = 0; i<numChildren; i++) {
    var child = documentElement.getChild(i)
    var match = child.findText('('+content.needle+') *[$\w]+')

    if ( ! match) continue

    var text   = match.getElement().getText()
    var value  = text.replace(RegExp('('+content.needle+') *'), '')
    var digits = value.replace(/\D/g, '')
    var match  = text.replace(RegExp(' *'+value.replace('$', '\\$')), '')

    res.values.push({text:text, match:match, value:value, digits:digits})
  }

  return res
}
