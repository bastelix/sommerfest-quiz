const fs = require('fs');
const code = fs.readFileSync('public/js/sticker-editor.js', 'utf8');

// Ensure no debouncedSave in input listeners (check up to next 10 lines)
const lines = code.split('\n');
for (let i = 0; i < lines.length; i++) {
  if (lines[i].includes("addEventListener('input'")) {
    for (let j = i; j < lines.length; j++) {
      if (lines[j].includes('debouncedSave')) {
        throw new Error('debouncedSave should not be called in input listeners');
      }
      if (lines[j].includes(');')) {
        break;
      }
    }
  }
}

// Ensure qrSize uses change event for saving
if (!/qrSize\.addEventListener\('change',[\s\S]*debouncedSave\(\)/.test(code)) {
  throw new Error('qrSize should save on change events');
}

console.log('ok');
