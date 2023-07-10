#!/bin/bash

CYPHT_DIR="/home/vicy/work/cypht"
RED="\e[00;31m"
GREEN="\e[00;32m"
YELLOW="\e[00;33m"
END="\e[00m"
STARTTIME=`date +%s`
SUITE=''

# exit on error
err_condition() {
    if [[ $? != 0 ]];
    then
        echo; echo -e "$RED FAILED $END"; echo;
        exit 1;
    fi;
}

# check for super global usage by modules
global_check() {
    GLOBALS=$(find modules/ -name "*.php" | xargs grep -Er '(_ENV|_FILES|_POST|_GET|GLOABALS|_COOKIE|_SERVER)')
    if [ -n "$GLOBALS" ]; then
        echo 'SUPER GLOBAL FOUND';
        echo "$GLOBALS"
        echo; echo  -e "$RED FAILED $END"; echo
        exit 1
    fi

}

# run config
config_check() {
    echo; echo -e "$YELLOW CONFIG CHECK $END"; echo
    php ./scripts/config_gen.php
    err_condition
}

# syntax check on all php files
php_check() {
    echo; echo -e "$YELLOW PHP CHECK $END"; echo
    find . -name "*.php" -print \
        | grep -v .autoload-legacy.php \
        | grep -v PhpCsFixer.php \
        | xargs -L 1  php -l
    err_condition
}

# syntax check on all javascript files
js_check() {
    echo; echo -e "$YELLOW JS CHECK $END"; echo
    find . -name "*.js" \
        | while read fname;
        do echo $fname;
            acorn --ecma2020 --silent "$fname"; if [[ $? != 0 ]];
        then exit 1; fi;
        done
    err_condition
}

# syntax check on all css files
css_check() {
    echo; echo -e "$YELLOW CSS CHECK $END"; echo
    find . -name "*.css" | grep -v ./vendor \
        | while read fname;
        do CHECK=`csslint --errors=errors "$fname"`
            if [[ $? != 0 ]]; then echo "$CHECK"; exit 1; fi;
            echo "$CHECK" | grep -v '^$';
        done
    err_condition
}

# run unit tests
unit_test_check() {
    echo; echo -e "$YELLOW UNIT TEST CHECK $END"; echo
    if [ -z "$SUITE" ]; then
        cd tests/phpunit && \
            ../../vendor/bin/phpunit --debug -v --stop-on-error --stop-on-failure 2>/dev/null && \
            cd "$CYPHT_DIR"
    else
        cd tests/phpunit && \
            ../../vendor/bin/phpunit --debug -v --stop-on-error --stop-on-failure --testsuite "$SUITE" 2>/dev/null && \
            cd "$CYPHT_DIR"
    fi
    err_condition
}

# run ui tests
ui_test_check() {
    echo; echo -e "$YELLOW UI TEST CHECK $END"; echo
    cd tests/selenium && \
        sh ./runall.sh && \
        cd "$CYPHT_DIR"
    #err_condition
}

# check for debug
debug_check() {
    echo; echo -e "$YELLOW DEBUG CHECK $END"; echo
    DEBUG=`grep -r elog lib/* modules/* \
        | grep -v 'function elog' \
        | grep -v 'var elog = function' \
        | grep -v Binary`

    if [ -z "$DEBUG" ]; then
        echo 'No debugs';
    else
        echo 'DEBUG FOUND';
        echo "$DEBUG"
        echo; echo  -e "$RED FAILED $END"; echo
        exit 1
    fi
}

# update git version
version_update() {
    echo; echo -e "$YELLOW VERSION UPDATE $END"; echo
    COUNT=`git rev-list --all --count`
    sed -i "s/GIT VERSION: [[:digit:]]\+/GIT VERSION: $COUNT/" index.php
}

# git status
git_stat() {
    echo; echo -e "$YELLOW GIT STATUS $END"; echo
    git status --short --branch
}

# output success message
success() {
    ENDTIME=`date +%s`
    RUNTIME=$((ENDTIME-STARTTIME))
    echo; echo -e "$GREEN SUCCESS ($RUNTIME seconds) $END"; echo
}

# run all checks
run_all() {
    config_check
    debug_check
    global_check
    php_check
    js_check
    css_check
    unit_test_check
    ui_test_check
    git_stat
    success
}

cd "$CYPHT_DIR"
if [ $# -eq 0 ]
  then
      run_all
  else
    case $1 in
        config)
            config_check
            success
        ;;
        debug)
            debug_check
            success
        ;;
        globals)
            global_check
        ;;
        php)
            php_check
            success
        ;;
        js)
            js_check
            success
        ;;
        css)
            css_check
            success
        ;;
        unit_test)
            SUITE="$2"
            unit_test_check
            success
        ;;
        ui_test)
            ui_test_check
            success
        ;;
        git_stat)
            git_stat
            success
        ;;
        all)
            run_all
        ;;
        *)
            echo "command not found"
        ;;
    esac
fi
