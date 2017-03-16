#!/bin/bash
FILE=`realpath $0`
PATH=`dirname $FILE`
cd $PATH

for suite in *
do
    pyc=`echo $suite | /bin/grep 'pyc$'`
    if [ -z "$pyc" ] && [ "$suite" != "runner.py" ] && [ "$suite" != "base.py" ] && [ "$suite" != "runall.sh" ]; then
        /usr/bin/python ./$suite
    fi
done
