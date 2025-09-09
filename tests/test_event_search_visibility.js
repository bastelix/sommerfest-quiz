const fs = require('fs');
const code = fs.readFileSync('public/js/admin.js', 'utf8');

if (!/eventSearchInput\.hidden = !(Array\.isArray\(list\) && list\.length >= 10)/.test(code)) {
  throw new Error('event search visibility condition missing');
}

console.log('ok');
