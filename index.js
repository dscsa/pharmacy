const http = require('http')
const  sql = require('mssql')


const config = {
    user: 'sirum',
    password: '...',
    server: 'localhost',
    database: 'cph'
}

const pool = sql.connect(config)

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
