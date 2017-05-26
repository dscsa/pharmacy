const http = require('http')
const  sql = require('mssql')
const auth = require('../../auth.js')
const pool = sql.connect({
    user:auth.username,
    password:auth.password,
    server: 'localhost',
    database: 'cph'
})

const test = async _ => {
    return await sql.query`select * from cppat`
}

const server = http.createServer(async (req, res) => {
    try {
        const success = await test()
	res.end(JSON.stringify(success.recordset))
    } catch(err) {
        res.end('There was an error '+err.stack)
    }
})

server.listen(9000)
console.log('webform server listening on port 9000')
