#!/bin/bash

PYTHON=$(command -v python3)
rm -rf __pycache__/

for suite in login.py folder_list.py pages.py profiles.py settings.py servers.py send.py search.py inline_msg.py keyboard_shortcuts.py
do
    export TEST_SUITE="$suite"
    "$PYTHON" -u ./$suite
    if [ $? -ne 0 ]; then
        exit 1
    fi
done
