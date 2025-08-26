#!/bin/sh
set -e
docker build -t sommerfest-quiz:latest "$(dirname "$0")/.."
printf '{"status":"built"}\n'
