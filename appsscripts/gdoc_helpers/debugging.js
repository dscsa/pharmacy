var scriptStart = new Date() //A unique id per script run

function Log() {
  Logger.log(argArray(arguments, ['Log', getCaller()]).join(' '))
}

function argArray(args, prepend) {
  prepend = prepend || []
  for (var i in args) {

    if (args[i] instanceof Error)
      args[i] = '\nError: "'+args[i].message+'"'+(args[i].stack ? ' '+args[i].stack.trim().split('\n') : '')+'\n\n' //only stack if Error is thrown

    if (args[i] && typeof args[i] == 'object') {
      args[i] = '<pre>'+JSON.stringify(args[i], null, ' ')+'</pre>'
    }

    prepend.push(args[i])
  }
  return prepend
}

function getCaller() {
  try { //weirdly new Error().stack is null, must throw
    throw new Error()
  } catch (err) {
    return err.stack.split('\n')[2].trim()
  }
}
