function removeFiles(opts) {
  var folder   = DriveApp.getFoldersByName(opts.folder).next()
  var iterator = folder.searchFiles('title contains "'+opts.file+'"')
  var res      = []

  while (iterator.hasNext()) {
    var file = iterator.next()
    try {
      file.setTrashed(true) //Prevent printing an old list that Cindy pended and shipped on her own
      res.push(['success', file.getUrl(), file.getName(), file.getOwner()])
    } catch (e) {
      res.push(['error', file.getUrl(), file.getName(), file.getOwner()]) //e.g., Error: "Access denied: DriveApp."
    }
  }

  //infoEmail('removeFiles', opts, res)
  return ['removeFiles', opts, res]
}

function testWatch() {
  var folder = DriveApp.getFoldersByName('Published').next()
  var query  = 'modifiedDate > "2019-11-19T16:07:49.089Z"'
  var iterator = folder.searchFiles(query)

  Logger.log(['testWatch', query, iterator.hasNext() ? iterator.next().getUrl() : 'No Files Modified'])
}

function watchFiles(opts) {

  var today     = new Date();
  var minutes   = opts.minutes || 10
  var startTime = new Date(today.getTime() - minutes * 60 * 1000);
  var tooRecent = new Date(today.getTime() - 1 * 60 * 1000); //Don't call if we are still making edits

  var parentFolder  = DriveApp.getFoldersByName(opts.folder).next()
  var printedFolder = parentFolder.getFoldersByName('Printed')
  var faxedFolder   = parentFolder.getFoldersByName("Faxed")

  var query    = 'modifiedDate > "' + startTime.toJSON() + '" AND modifiedDate < "' + tooRecent.toJSON() + '"'
  var iterator = parentFolder.searchFiles(query)

  var parent = []
  while (iterator.hasNext()) {
    var file = isModified(iterator.next(), opts)
    if (file) parent.push(file)
  }

  var printed = []
  if (printedFolder.hasNext()) {
    var iterator = printedFolder.next().searchFiles(query)
    while (iterator.hasNext()) {
      var file = isModified(iterator.next(), opts)
      if (file) printed.push(file)
    }
  }

  var faxed = []
  if (faxedFolder.hasNext()) {
    var iterator = faxedFolder.next().searchFiles(query)
    while (iterator.hasNext()) {
      var file = isModified(iterator.next(), opts)
      if (file) faxed.push(file)
    }
  }

  //if (parent.length || printed.length || faxed.length)
  //  infoEmail('watchFiles', parent, printed, faxed)

  return {'parent':parent, 'printed':printed, 'faxed':faxed}
}

function isModified(next, opts) {

  var file = {
    name:next.getName(),
    id:next.getId(),
    url:next.getUrl(),
    date_modified:next.getLastUpdated(),
    date_created:next.getDateCreated()
  }

  //If don't want watch to keep returning the same file with the same change multiple times
  var lastEdit = file.name.split(' Modified:')

  file.lastEdit = lastEdit[1]
  file.newFile  = (file.date_modified - file.date_created) < 10 * 60 * 1000 //1 minute
  file.newEdit  = file.newFile ? false : ( ! file.lastEdit || file.date_modified.toJSON().slice(0, 16) > file.lastEdit)

  file.skip  = ! file.newEdit && ! (file.newFile && opts.includeNew)

  Logger.log(JSON.stringify(['file', file], null, ' '))

  if (file.skip) return

  //This makes last_watched logic work
  next.setName(lastEdit[0]+' Modified:'+new Date().toJSON()) //This changes the modified date

  if (next.getMimeType() != MimeType.GOOGLE_DOCS) return

  //getBody does not have headers or footers
  try {
    var doc = DocumentApp.openById(next.getId())
  } catch (e) {
    //In Trash or Permission Issue
    debugEmail('watchFiles PERMISSION ERROR', next.getId())
    return
  }

  var documentElement = doc.getBody().getParent()
  var numChildren = documentElement.getNumChildren()

  for (var i = 0; i<numChildren; i++) {
    var child = documentElement.getChild(i)
    file['part'+i] = child.getText()
    //file['table'+i] = child.getTables() http://ramblings.mcpher.com/Home/excelquirks/goinggas/arrayifytable
  }

  return file
}

//Drive (not DriveApp) must be turned on under Resources -> Advanced Google Services -> Drive
//https://stackoverflow.com/questions/40476324/how-to-publish-to-the-web-a-spreadsheet-using-drive-api-and-gas
function publishFile(opts){

  var folder = DriveApp.getFoldersByName(opts.folder).next()
  var file   = folder.searchFiles('title contains "'+opts.file+'"')
  
  if ( ! file.hasNext()) {
    return debugEmail('publishFile NO SUCH FILE', 'File', opts)
  }
  
  file = file.next()
  var fileId = file.getId()
  
  console.log('publishFile '+file.getName())
  
  //Side effect of this is that this account can no longer delete/trash/remove this file since must be done by owner
  
  if (file.getOwner().getEmail() != 'webform@goodpill.org') {
    debugEmail('publishFile WRONG OWNER', 'File', file.getName(), 'Active User', Session.getActiveUser().getEmail(),'Effective User', Session.getEffectiveUser().getEmail(), 'File Owner', file.getOwner().getEmail())    
    file.setOwner('webform@sirum.org') //support@goodpill.org can only publish files that require sirum sign in
  }
 
  var revisions = Drive.Revisions.list(fileId);
  var items = revisions.items;
  var revisionId = items[items.length-1].id;
  var resource = Drive.Revisions.get(fileId, revisionId);

  resource.published = true;
  resource.publishAuto = true;
  resource.publishedOutsideDomain = true;
  resource = Drive.Revisions.update(resource, fileId, revisionId);

  return resource
}

function moveFile(opts, retry) {
  var fromFolder = DriveApp.getFoldersByName(opts.fromFolder).next()
  var file = fromFolder.searchFiles('title contains "'+opts.file+'"')
  
  console.log('moveFile '+opts.file+' '+file.hasNext())

  if (file.hasNext()) {
    file = file.next()
    //debugEmail('gdoc_helpers moveFile CALLED', file.getName(), opts)
    return moveToFolder(file, opts.toFolder)
  }

  if ( ! retry) {
    Utilities.sleep(30000)
    moveFile(opts, true)
  }

  debugEmail('gdoc_helpers moveFile NO FILE #'+(retry ? 2 : 1)+' of 2', opts)
}

function newSpreadsheet(opts) {

  var ss   = SpreadsheetApp.create(opts.file)
  var file = DriveApp.getFileById(ss.getId())

  if (opts.vals) {
    ss.getActiveSheet()
      .getRange(1, 1, opts.vals.length, opts.vals[0].length)
      .setValues(opts.vals)
      .setHorizontalAlignment('left')
      .setFontFamily('Roboto Mono')
  }

  var widths = opts.widths || {}
  for (var col in widths) {
    ss.setColumnWidth(col, widths[col]); //show the full id when it print
  }

  moveToFolder(file, opts.folder)
}

function moveToFolder(file, folder) {
  if ( ! folder ) return

  console.log('moveToFolder '+folder+'/'+file.getName())
  file.moveTo(folderByName(folder))
  //parentByFile(file).removeFile(file)
  //folderByName(folder).addFile(file)

  return file
}

function folderByName(name) {
  return DriveApp.getFoldersByName(name).next()
}

function parentByFile(file) {

  try {
    return file.getParents().next()
  } catch(e) {
    return DriveApp.getRootFolder()
  }
}
