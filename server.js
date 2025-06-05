const http = require('http');
const fs = require('fs');
const path = require('path');

const root = __dirname;
const logFile = path.join(root, 'statistical.log');

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
  if(req.method === 'POST' && req.url === '/log'){
    let body = '';
    req.on('data', chunk => body += chunk);
    req.on('end', () => {
      try{
        const data = JSON.parse(body);
        if(data.user && typeof data.score === 'number' && typeof data.total === 'number'){
          const line = `${data.user} ${data.score}/${data.total}\n`;
          fs.appendFile(logFile, line, err => {
            if(err) console.error('Failed to write log:', err);
          });
        }
      }catch(e){ /* ignore invalid json */ }
      res.statusCode = 204;
      res.end();
    });
    return;
  }
  if(req.method === 'GET' && req.url === '/log'){
    fs.readFile(logFile, 'utf8', (err, text) => {
      if(err){
        res.statusCode = 204;
        return res.end();
      }
      res.setHeader('Content-Type','text/plain');
      res.end(text);
    });
    return;
  }

  const pathname = req.url === '/' ? '/index.html' : req.url;
  const filePath = path.join(root, pathname);
  serveStatic(filePath, res);
});

const port = process.env.PORT || 3000;
server.listen(port, () => {
  console.log(`Server running on http://localhost:${port}`);
});
