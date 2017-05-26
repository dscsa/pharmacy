const  sql = require('mssql')
const http = require('http')
const auth = require('../../auth.js')
const pool = sql.connect({
  user:auth.username,
  password:auth.password,
  server: 'localhost',
  database: 'cph'
})

const select = async _ => {
  return await sql.query`select * from cppat`
}

http.createServer(async (req, res) => {
  try {
    const success = await select()
    res.end(JSON.stringify(success.recordset))
  } catch(err) {
    res.end('There was an error!!\n\n'+err.stack)
  }
}).listen(9000)

console.log('webform server listening on port 9000')
