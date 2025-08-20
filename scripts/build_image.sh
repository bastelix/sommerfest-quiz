#!/bin/sh
set -e
docker build -t sommerfest:latest "$(dirname "$0")/.."
printf '{"status":"built"}\n'
