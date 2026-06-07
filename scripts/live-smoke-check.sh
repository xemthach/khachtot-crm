#!/usr/bin/env bash
set -e

BASE="${1:-https://khachtot.com}"

echo "Smoke checking $BASE"

curl -Ik "$BASE/" | head -n 1
curl -Ik "$BASE/signup" | head -n 1
curl -Ik "$BASE/pricing" | head -n 1
curl -Ik "$BASE/admin" | head -n 1

echo "Done"
