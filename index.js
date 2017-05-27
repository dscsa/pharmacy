const    fs = require('fs')
const  path = require('path')
const   sql = require('mssql')
const  auth = require('../../auth.js')
const route = require('koa-route')
const   app = new (require('koa'))()
const  pool = sql.connect({
  user:auth.username,
  password:auth.password,
  server: 'localhost',
  database: 'cph'
})

app.use(route.post('/patient', async ctx => {

  const patient = await body(ctx.req)
  console.log('patient', patient)
  ctx.body = 'patient '+patient
  // try {
  //   const success = await select()
  //   res.end(JSON.stringify(success.recordset))
  // } catch(err) {
  //   res.end('There was an error!!\n\n'+err.stack)
  // }
}))

app.use(route.get('(.*)', async ctx => {

  console.log('get', __dirname+'/..'+ctx.url)
  ctx.body = __dirname+'/..'+ctx.url
  // try {
  //   const success = await select()
  //   res.end(JSON.stringify(success.recordset))
  // } catch(err) {
  //   res.end('There was an error!!\n\n'+err.stack)
  // }
}))

var opts = {
    key: fs.readFileSync('C:/live/webform.goodpill.org/privkey.pem', 'utf8'),
   cert: fs.readFileSync('C:/live/webform.goodpill.org/cert.pem', 'utf8'),
     ca: fs.readFileSync('C:/live/webform.goodpill.org/chain.pem', 'utf8'),
}

https.createServer(opts, app).listen(443, _ => console.log('https server on port 443'))

const select = async _ => {
  return await sql.query`select * from cppat`
}

function body(stream) {

  if (stream.body) //maybe this stream has already been collected.
    return Promise.resolve(stream.body)

  if (typeof stream.on != 'function') //ducktyping http://stackoverflow.com/questions/23885095/nodejs-check-if-variable-is-readable-stream
    throw 'http.json was not given a stream'

  if ( ! stream.readable)
    throw 'http.json stream is already closed'

  stream.body = ''
  return new Promise((resolve, reject) => {
    stream.on('error', err => reject(err))
    stream.on('data', data => stream.body += data)
    stream.on('end', _ => {
      try {
         //default to {} this is what other body parsers do in strict mode.  Not sure what we want to do here.
        stream.body = JSON.parse(stream.body || '{}')
        resolve(stream.body)
      } catch (err) {
        reject('Error: Invalid JSON '+stream.body)
      }
    })
  })
}
