import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'jsdom',
    globals: true,
    include: ['tests/unit/**/*.test.js'],
    setupFiles: ['tests/unit/setup.js'],
    coverage: {
      provider: 'v8',
      include: ['public/js/admin-*.js'],
      reporter: ['text', 'lcov'],
    },
  },
});
