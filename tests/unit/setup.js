// jsdom sets location to 'about:blank' by default.
// Establish a stable origin so tests that read window.location.origin/hostname
// get a predictable value without needing to stub it in every test file.
Object.defineProperty(window, 'location', {
  writable: true,
  value: new URL('https://example.com'),
});
