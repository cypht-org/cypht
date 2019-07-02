#!/bin/bash

CYPHT_DIR="/home/jason/cypht"
VERSION="v1.1.0"
SINCE="2018-11-11"

cd "$CYPHT_DIR"
git log --pretty=format:'%h%  - %s [%aD]' \
    --abbrev-commit \
    --since "$SINCE" \
    "$VERSION"..HEAD \
    | sed -r "s/[|\*\/\\]//g" \
    | tr -s ' ' \
    | grep -v '^ $' \
    | sed -r "s/^ //"
