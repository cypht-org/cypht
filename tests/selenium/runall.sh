#!/bin/bash

for suite in *
do
    pyc=`echo $suite | /bin/grep 'pyc$'`
    if [ -z "$pyc" ] && [ "$suite" != "runner.py" ] && [ "$suite" != "base.py" ] && [ "$suite" != "runall.sh" ]; then
        /usr/bin/python ./$suite
        if [ $? -ne 0 ]; then
            exit 1
        fi
    fi
done
