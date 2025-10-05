const fs = require('fs');

const code = fs.readFileSync('public/js/admin.js', 'utf8');

const rebuildMatch = code.match(/rebuildButton\.addEventListener\('click', \(\) => {([\s\S]*?)\n\s*}\);\n\s*}\n\n\s*if \(downloadButton\)/);
if (!rebuildMatch) {
  throw new Error('domain chat rebuild block not found');
}

const block = rebuildMatch[1];

if (!/const success = data && data\.success === true;/.test(block)) {
  throw new Error('success guard for rebuild response missing');
}

if (!/if \(!res\.ok \|\| !success\) {/.test(block)) {
  throw new Error('rebuild handler must treat non-success as failure');
}

if (!/showStatus\(message, 'danger', details\);/.test(block)) {
  throw new Error('rebuild failure should update status area with details');
}

if (!/notify\(message, 'danger'\);/.test(block)) {
  throw new Error('rebuild failure should notify the user');
}

console.log('ok');
