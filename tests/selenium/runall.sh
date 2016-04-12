#!/bin/bash
for suite in *
do
    pyc=`echo $suite | grep 'pyc$'`
    if [ -z "$pyc" ] && [ "$suite" != "runner.py" ] && [ "$suite" != "base.py" ] && [ "$suite" != "runall.sh" ]; then
        python ./$suite
    fi
done
