#!/bin/bash

if [ "$DB" = "postgresql" ]; then
    cd tests/phpunit/usr/local/bin/phpunit && cd ../selenium && sh ./runall.sh && cd ../../
else
    cd tests/phpunit && /usr/local/bin/phpunit && cd ../../
fi
