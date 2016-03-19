#!/bin/bash
# - put this code in ~/bin/php_test.sh
# - make it executable: chmod +x ~/bni/php_test.sh
# - add to your vimrc: map <F9> :! ~/bin/php_test.sh %<CR>
# - Use F9 from command mode to run a check on the currently open file

RED="\e[1;31m"
GREEN="\e[1;32m"
NONE="\e[1;0m"
FILE=$1

# make sure we have a readable file
function test_arg() {
        if [ ! -e $FILE ]; then
                echo
                echo -e "${RED}Invalid file argument $NONE"
                exit 0
        fi
}

# make sure the file is PHP
function file_test() {
        FILETEST=`echo $FILE | egrep '\.php$' | wc -l`
        if [ $FILETEST -lt 1 ]; then
                echo
                echo -e "${RED}Not a PHP file $NONE"
                exit 0
        fi
}

# make sure the file is in GIT
function git_test() {
        GITTEST=`git status $FILE 2>&1 | grep 'not a working copy' | wc -l`
        if [ $GITTEST -gt 0 ]; then
                echo
                echo -e "${RED}Not a working copy $NONE"
                exit 0
        fi
}

# get the size of the current diff
function diff_size() {
        DIFF_SIZE=`git diff $FILE | wc -l`
        DIFF_ADDS=`git diff $FILE | grep '^+' | wc -l`
        DIFF_DELS=`git diff $FILE | grep '^-' | wc -l`
        echo
        echo -e "Diff size: $DIFF_SIZE (${GREEN}+${DIFF_ADDS}${NONE}/${RED}-${DIFF_DELS}${NONE})"
}

# check for syntax errors
function php_errors {
        PHP_VAL=`php -l $FILE | cut -f 1 -d ' '`
        if [ $PHP_VAL = 'No' ]; then
                echo -e "${GREEN}No errors found $NONE"
        else
                echo -e "${RED}Errors found! $NONE"
        fi
}

# look for left over debugs
function debug_check() {
        DEBUG_VAL=`git diff $FILE | egrep '(print_r|error_log|elog)'`
        if [[ -z $DEBUG_VAL ]]; then
                echo -e "${GREEN}No debug found $NONE"
        else
                echo -e "${RED}Debug found! $NONE"
                git diff $FILE | egrep '(print_r|error_log|elog)'
        fi
}

# check for todo lines
function todo_check() {
        TODO=`git diff $FILE | egrep '\<(TODO|todo)\>'`
        if [[ -z $TODO ]]; then
                echo -e "${GREEN}No todo found $NONE"
        else
                echo -e "${RED}Todo found! $NONE"
                git diff $FILE | egrep '\<(TODO|todo)\>'
        fi

}

# check input and bail if it fails
function check_input() {
        test_arg
        git_test
        file_test
}

# run file checks
function process_diff() {
        echo
        echo "-------------------------------------------------------------";
        echo "Checking $FILE"
        echo
        php_errors
        debug_check
        todo_check
        #update_check
        echo "-------------------------------------------------------------";
        diff_size
        echo
}

# check input
check_input

# do stuff
process_diff

# adios
exit 0

