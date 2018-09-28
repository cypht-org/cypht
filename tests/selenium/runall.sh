#!/bin/bash
rm -rf __pycache__/
PYTHON=`which python`
for suite in *
do
    pyc=`echo $suite | /bin/grep 'pyc$'`
    if [ -z "$pyc" ] && [ "$suite" != "runner.py" ] && [ "$suite" != "base.py" ] && [ "$suite" != "runall.sh" ]; then
        export TEST_SUITE="$suite"
        "$PYTHON" -u ./$suite
        if [ $? -ne 0 ]; then
            exit 1
        fi
    fi
done
