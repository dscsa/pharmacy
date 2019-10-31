function createCalendarEvent(event) {
  var eventStop = addHours(event.hours, event.start)
  CalendarApp.getCalendarById(event.cal_id).createEvent(event.title, event.start, event.stop, {description:event.description})
}

function removeCalendarEvents(opts) {

  var events = opts.events || CalendarApp.getCalendarById(opts.cal_id).getEventById(opts.ids[i])

  for (var i in events) {
    var title = event.getTitle()+' Deleted:'+new Date()

    event.setTitle(title)

    try { //We're sorry, a server error occurred. Please wait a bit and try again."
      event.deleteEvent()
    } catch (e) {
      Utilities.sleep(5000)
      event.deleteEvent()
    }
  }

  infoEmail('removeCalendarEvents', opts)
}

function searchCalendarEvents(opts) {

  var calendar = CalendarApp.getCalendarById(opts.cal_id)

  var start    = opts.start || new Date()
  var length   = addHours(opts.hours, start) //stop date seems to be required by Google.  Everything should happen within 90 days
  var config   = { search:opts.full_word_search }
  var events   = calendar.getEvents(start, stop, config) //Can't put in name because can't google cal doesn't seem to support a partial word search e.g, "greg" will not show results for gregory

  //TODO Remove if/when Calendar support partial word searches

  var matches = []
  var regex   = opts.regex_search && RegExp(opts.regex_search)
  for (var i in events) {
    var haystack = (event.getTitle()+' '+event.getDescription()).toLowerCase()
    if ( ! regex || haystack.match(regex))
      matches.push({id:event.getId(), event:event})
  }

  if (events.length)
    infoEmail('searchCalendarEvents', start, stop, matches ? matches.length : events.length, 'of '+events.length+' of the events below match the following:', opts)

  return matches
}

function modifyCalendarEvents(opts) {

  var response = {modified:[], unmodified:[]}
  var matches  = searchCalendarEvents(opts)
  var regex    = RegExp(opts.regex_replace[0], 'g')
  //TODO Remove if/when Calendar support partial word searches
  for (var i in matches) {
    var _id       = matches[i].id
    var title    = matches[i].event.getTitle()
    var old_desc = matches[i].event.getDescription()
    var new_desc = old_desc.replace(regex, opts.regex_replace[1])

    if (old_desc == new_desc) {
      response.unmodified.push({id:id, title:title, old_desc:old_desc, new_desc:new_desc})
    }
    else {
      response.modified.push({id:_id, title:title, old_desc:old_desc, new_desc:new_desc})
    }
  }

  if (events.length)
    infoEmail('modifyCalendarEvents', opts, response)

  return response
}

function addHours(hours, date) {
  var copy = date ? new Date(date.getTime ? date.getTime() : date) : new Date()
  copy.setTime(copy.getTime() + hours*60*60*1000)
  return copy
}
