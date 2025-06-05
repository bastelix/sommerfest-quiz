const http = require('http');
const fs = require('fs');
const path = require('path');

const root = __dirname;

function serveStatic(filePath, res){
  fs.readFile(filePath, (err, data) => {
    if(err){
      res.statusCode = 404;
      res.end('Not found');
    }else{
      res.end(data);
    }
  });
}

const server = http.createServer((req, res) => {
  // no logging endpoints

  const pathname = req.url === '/' ? '/index.html' : req.url;
  const filePath = path.join(root, pathname);
  serveStatic(filePath, res);
});

const port = process.env.PORT || 3000;
server.listen(port, () => {
  console.log(`Server running on http://localhost:${port}`);
});
