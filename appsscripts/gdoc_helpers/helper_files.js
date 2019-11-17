function removeFiles(opts) {
  var folder = DriveApp.getFolderById(opts.folderId)
  var iterator = folder.searchFiles('title contains "'+opts.title+'"')

  while (iterator.hasNext()) {
    var file = iterator.next().setTrashed(true) //Prevent printing an old list that Cindy pended and shipped on her own
    infoEmail('removeFiles', file.getUrl(), file.getName(), opts)
  }
}

function watchFiles(opts) {

  var today     = new Date();
  var minutes   = opts.minutes || 5
  var oneDayAgo = new Date(today.getTime() - minutes * 60 * 60 * 1000);
  var startTime = oneDayAgo.toISOString();

  var files    = []
  var folder   = DriveApp.getFoldersByName(opts.folder).next()
  var iterator = folder.searchFiles('modifiedDate > "' + startTime + '")')

  while (iterator.hasNext()) {

    var next = iterator.next()
    var file = {
      id:next.getId(),
      name:next.getName(),
    }

    //getBody does not have headers or footers
    var documentElement = next.getBody().getParent()
    var numChildren = documentElement.getNumChildren()

    for (var i = 0; i<numChildren; i++) {
      var child = documentElement.getChild(i)
      file['part'+i] = child.getText()
    }

    files[] = file
  }

  infoEmail('watchFiles', folderId, files)
  return files
}
