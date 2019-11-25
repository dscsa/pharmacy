function testCalendarEvent() {
  createCalendarEvent({
    cal_id:'sirum.org_k8j983q66k1t6gvgg59t0amq0k@group.calendar.google.com',
    start: '2019-11-27T13:00:00',
    hours: 0.5,
    title: '22740 Order Failed: Patient.  Created:2019:11:20 17:58:24',
    description:'Hello Test'
  })
}

function createCalendarEvent(event) {
  event.stopDate  = addHours(event.hours, event.start)
  event.startDate = new Date(event.start)
  infoEmail('createCalendarEvent', event)
  return CalendarApp.getCalendarById(event.cal_id).createEvent(event.title, event.startDate, event.stopDate, {description:event.description})
}

function removeCalendarEvents(opts) {

  if ( ! opts.events) {
    var cal = CalendarApp.getCalendarById(opts.cal_id)
    opts.events = opts.ids.map(function(id) {
      return cal.getEventById(id)
    })
  }

  for (var i in opts.events) {
    var title = opts.events[i].getTitle()+' Deleted:'+new Date()

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

function modifyCalendarEvents(opts) {

  var cal = CalendarApp.getCalendarById(opts.cal_id)

  for (var i in opts.events) {

    if ( ! opts.events[i].setTitle || ! opts.events[i].setDescription)
      opts.events[i] = cal.getEventById(opts.events[i].id)

    opts.events[i].setTitle(opts.events[i].title)
    opts.events[i].setDescription(opts.events[i].description)
  }

  infoEmail('modifyCalendarEvents', opts)
}

function searchCalendarEvents(opts) {

  var calendar = CalendarApp.getCalendarById(opts.cal_id)

  if ( ! calendar)
    debugEmail('ERROR searchCalendarEvents: cal_id or permission error', opts)

  var start    = opts.start || new Date()
  var stop     = addHours(opts.hours, start) //stop date seems to be required by Google.  Everything should happen within 90 days
  var config   = { search:opts.word_search }
  var events   = calendar.getEvents(start, stop, config) //Can't put in name because can't google cal doesn't seem to support a partial word search e.g, "greg" will not show results for gregory
  //TODO Remove if/when Calendar support partial word searches

  var matches = []
  var regex   = opts.regex_search && RegExp(opts.regex_search)
  for (var i in events) {

    var event = {
      id:events[i].getId(),
      title:events[i].getTitle(),
      description:events[i].getDescription(),
      start:events[i].getStartTime(),
      end:events[i].getStartTime()
    }

    if ( ! opts.past && (~ event.title.indexOf('CALLED') ||  ~ event.title.indexOf('EMAILED') ||  ~ event.title.indexOf('TEXTED'))) continue;

    var haystack = (event.title+' '+event.description).toLowerCase()

    if ( ! regex || haystack.match(regex))
      matches.push(event)
  }

  if (events.length)
    infoEmail('searchCalendarEvents', start, stop, matches ? matches.length : events.length, 'of '+events.length+' of the events below match the following:', opts)

  return matches
}

function addHours(hours, date) {
  var copy = date ? new Date(date.getTime ? date.getTime() : date) : new Date()
  copy.setTime(copy.getTime() + hours*60*60*1000)
  return copy
}
