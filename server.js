// Einfacher statischer HTTP-Server zum lokalen Testen des Quizzes
const http = require('http');
const fs = require('fs');
const path = require('path');

// Einfache Zuordnung der Content-Types
const mimeTypes = {
  '.html': 'text/html',
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.svg': 'image/svg+xml'
};

// Basisverzeichnis, in dem sich alle Dateien des Projekts befinden
const root = __dirname;

// Liest eine Datei ein und liefert sie an den Client aus
function serveStatic(filePath, res){
  fs.readFile(filePath, (err, data) => {
    if(err){
      // Datei wurde nicht gefunden
      res.statusCode = 404;
      res.end('Not found');
    }else{
      // Content-Type anhand der Dateiendung setzen
      const ext = path.extname(filePath);
      const type = mimeTypes[ext] || 'application/octet-stream';
      res.setHeader('Content-Type', type);
      // Erfolgreich gelesene Datei ausgeben
      res.end(data);
    }
  });
}

// HTTP-Server, der eingehende Requests beantwortet
const server = http.createServer((req, res) => {
  if (req.method !== 'GET') {
    res.statusCode = 405;
    res.end('Method Not Allowed');
    return;
  }
  // Startseite liefern, wenn nur '/' angefordert wird
  const reqPath = req.url.split('?')[0];
  const pathname = reqPath === '/' ? '/index.html' : decodeURIComponent(reqPath);
  const filePath = path.normalize(path.join(root, pathname));
  // Pfad darf nicht aus dem Stammverzeichnis herausführen
  if (!filePath.startsWith(root)) {
    res.statusCode = 400;
    res.end('Bad Request');
    return;
  }
  serveStatic(filePath, res);
});

// Standardport 3000, kann über die Umgebungsvariable PORT überschrieben werden
const port = process.env.PORT || 3000;
server.listen(port, () => {
  console.log(`Server running on http://localhost:${port}`);
});
