#!/bin/bash

# This will no longer be used. Anyone interested can simply ready the Git commit log.

CYPHT_DIR="/home/vicy/work/cypht"
VERSION="v1.3.0"
SINCE="2021-07-07"

cd "$CYPHT_DIR"
git log --pretty=format:'%h%  - %s [%aD]' \
    --abbrev-commit \
    --since "$SINCE" \
    "$VERSION"..HEAD \
    | sed -r "s/[|\*\/\\]//g" \
    | tr -s ' ' \
    | grep -v '^ $' \
    | sed -r "s/^ //"
