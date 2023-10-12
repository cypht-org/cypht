#!/bin/bash

VERSION=$(
	dpkg -s google-chrome-stable | grep Version | awk '{print $2}' | sed 's/-.*//'
)

wget -O /tmp/chromedriver-linux64.zip https://edgedl.me.gvt1.com/edgedl/chrome/chrome-for-testing/"${VERSION}"/linux64/chromedriver-linux64.zip

unzip /tmp/chromedriver-linux64.zip -d /tmp

mv /tmp/chromedriver-linux64/chromedriver /usr/bin/chromedriver
