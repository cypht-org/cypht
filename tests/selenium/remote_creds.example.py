#!/usr/bin/python

# This is an example config file to run the Selenium tests remotely,
# specifically with BrowserStack (https://www.browserstack.com)

# recipient E-mail for the send test
RECIP='testuser@localhost.localdomain'

# IMAP id (used to select the correct server in the servers test)
IMAP_ID='2'

# Get webdrivers
from selenium import webdriver

# Define the webdriver to use
DRIVER = webdriver.Remote

# Define the remote command. This format is specific to browserstack.com
DRIVER_CMD='http://<yourcreds>@hub.browserstack.com:80/wd/hub'

# Set the browser and OS attributes. If this is a list of attribute
# dictionaries, the test suites will be run across each set
DESIRED_CAP = {'os': 'Windows', 'os_version': '7', 'browser': 'IE', 'browser_version': '11', 'resolution': '1920x1080' }

# The base URL to run the tests against
SITE_URL = 'https://some-public-site-running-cypht.com'

# A valid username to login with
USER = 'testuser'

# A valid password for the username
PASS = 'testpass'

# A function that returns a webdriver object.
def get_driver(cap):
    if not cap:
        cap = DESIRED_CAP
    return DRIVER(command_executor=DRIVER_CMD, desired_capabilities=cap)

# A function called when all tests from one set
# complete
def success(driver):
    pass
