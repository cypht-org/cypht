#!/bin/bash

CYPHT_DIR="/home/jason/cypht"
VERSION="v1.1.0-rc4"
SINCE="2017-05-11"

cd "$CYPHT_DIR"
git log --pretty=format:'%h%  - %s [%aD]' \
    --abbrev-commit \
    --since "$SINCE" \
    "$VERSION"..HEAD \
    | sed -r "s/[|\*\/\\]//g" \
    | tr -s ' ' \
    | grep -v '^ $' \
    | sed -r "s/^ //"
