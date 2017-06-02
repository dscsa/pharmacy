const    fs = require('fs')
const    qs = require('querystring')
const   sql = require('mssql')
const   app = new (require('koa'))()
const  auth = require('../../auth.js')
const route = require('koa-route')
const https = require('https')
sql.connect({
  user:auth.username,
  password:auth.password,
  server: 'localhost',
  database: 'cph'
})

const select = async _ => {
  return await sql.query`select * from cppat`
}

const showPatients = async ctx => {

  const patient = await body(ctx.req)

  ctx.body = 'patient '+JSON.stringify(patient)

  try {
    const success = await select()
    ctx.body = JSON.stringify(success.recordset)
  } catch(err) {
    ctx.body = 'There was an error!!\n\n'+err.stack
  }
}

const findPatient = async ctx => {
  // const patient = await new sql.Request()
  //  .input('FName', sql.VarChar(50), 'cindy')
  //  .input('LName', sql.VarChar(50), 'Tompson')
  //  .input('DOB', sql.VarChar(50), '01-01-1980')
  //  .execute('SirumWeb_FindPatByNameandDOB')

  const patient = await sql.query
  `select
      p.pat_id
     ,p.fname
     ,mname
     ,lname
     ,birth_date = IsNull(convert(varchar(10), birth_date, 110), '')
     from
      cppat p (nolock)
    where
      p.lname = 'Tompson'
      and p.fname = 'cindy'
      and IsNULL(NULL, mname) = ''
      and IsNull(p.birth_date, '1980-01-01') = '1980-01-01'`

  console.dir(patient)
  ctx.body = patient
}

app.use(route.get('/patient', findPatient))
app.use(route.post('/patients', showPatients))
app.use(route.get('/patients', showPatients))

app.use(route.post('/billing', async ctx => {

  const billing = await body(ctx.req)

  console.log('billing', billing)
  ctx.body = 'billing '+JSON.stringify(billing)
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

https.createServer(opts, app.callback()).listen(443, _ => console.log('https server on port 443'))

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
        stream.body = qs.parse(stream.body)
        resolve(stream.body)
      } catch (err) {
        reject('Error: Invalid JSON '+stream.body)
      }
    })
  })
}
