#!/usr/bin/python

from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service

chrome_options = Options()
chrome_options.add_argument('--headless')
chrome_options.add_argument('--disable-gpu')
chrome_options.BinaryLocation = "/usr/bin/google-chrome"

RECIP='testuser@localhost.org'
IMAP_ID='0'
DRIVER_CMD =Service('/usr/bin/chromedriver')
SITE_URL = 'http://cypht-test.org'
USER = 'testuser'
PASS = 'testuser'
DESIRED_CAP = None

def get_driver(cap):
    return webdriver.Chrome(service=DRIVER_CMD, options=chrome_options)

def success(driver):
    pass
