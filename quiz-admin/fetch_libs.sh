#!/usr/bin/env bash
# Download JS/CSS dependencies for offline use for quiz-admin.
set -e
mkdir -p "$(dirname "$0")/libs"
cd "$(dirname "$0")/libs"

curl -L https://unpkg.com/vue@3/dist/vue.global.prod.js -o vue.global.prod.js
curl -L https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js -o Sortable.min.js
curl -L https://cdn.jsdelivr.net/npm/vuedraggable@4.1.0/dist/vuedraggable.umd.min.js -o vuedraggable.umd.min.js

cat > input.css <<'TAILWIND'
@tailwind base;
@tailwind components;
@tailwind utilities;
TAILWIND

npx tailwindcss@3.4.4 -i ./input.css -o tailwind.min.css --minify
rm input.css
