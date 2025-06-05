// Einfacher statischer HTTP-Server zum lokalen Testen des Quizzes
const http = require('http');
const fs = require('fs');
const path = require('path');

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
      // Erfolgreich gelesene Datei ausgeben
      res.end(data);
    }
  });
}

// HTTP-Server, der eingehende Requests beantwortet
const server = http.createServer((req, res) => {
  // Startseite liefern, wenn nur '/' angefordert wird
  const pathname = req.url === '/' ? '/index.html' : req.url;
  const filePath = path.join(root, pathname);
  serveStatic(filePath, res);
});

// Standardport 3000, kann über die Umgebungsvariable PORT überschrieben werden
const port = process.env.PORT || 3000;
server.listen(port, () => {
  console.log(`Server running on http://localhost:${port}`);
});
