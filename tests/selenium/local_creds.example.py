#!/usr/bin/python

# This is an example config file to run the Selenium tests locally

# recipient E-mail for the send test
RECIP='testuser@localhost.localdomain'

# IMAP id (used to select the correct server in the servers test)
IMAP_ID='2'

# Get webdrivers
from selenium import webdriver

# Define the webdriver to use
DRIVER = webdriver.Chrome

# Define the location of the webdriver. This is the default on Debian sid
DRIVER_CMD='/usr/lib/chromium/chromedriver'

# The base URL to run the tests against
SITE_URL = 'http://localhost/cypht/'

# A valid username to login with
USER = 'testuser'

# A valid password for the username
PASS = 'testpass'

# Unused for local testing
DESIRED_CAP = None

# A function that returns a webdriver object.
def get_driver(cap):
    return DRIVER(DRIVER_CMD)

# A function called when all tests from one set
# complete
def success(driver):
    pass
