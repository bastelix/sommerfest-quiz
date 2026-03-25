#!/bin/sh
set -e
docker build -t edocs-cloud:latest "$(dirname "$0")/.."
printf '{"status":"built"}\n'
