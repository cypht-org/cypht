#!/bin/bash

CYPHT_DIR="/home/jason/cypht"

cd "$CYPHT_DIR"
git log --pretty=format:'%h%  - %s [%aD]' \
    --abbrev-commit \
    --since 2017-05-11 \
    --graph release-1.1.0 \
    | sed -r "s/[|\*\/\\]//g" \
    | tr -s ' ' \
    | grep -v '^ $' \
    | sed -r "s/^ //" > CHANGES
