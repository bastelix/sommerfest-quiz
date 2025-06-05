#!/usr/bin/env bash
# Create a ZIP package of the quiz app with all dependencies for offline use.
set -e
cd "$(dirname "$0")"

if [ ! -f libs/vue.global.prod.js ]; then
  echo "Dependencies missing. Fetching..." >&2
  bash fetch_libs.sh
fi

zipfile="quiz-app-offline.zip"
rm -f "$zipfile"
zip -r "$zipfile" . > /dev/null

printf "Created %s\n" "$zipfile"

