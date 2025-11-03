#!/usr/bin/python

from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service

chrome_options = Options()
chrome_options.add_argument('--disable-gpu')
chrome_options.BinaryLocation = "/usr/bin/google-chrome"

chrome_options.add_argument("--headless=new")  # or "--headless" depending on Chrome version
chrome_options.add_argument("--window-size=1920,1080")
chrome_options.add_argument("--force-device-scale-factor=1")
chrome_options.add_argument("--no-sandbox")
chrome_options.add_argument("--disable-dev-shm-usage")
chrome_options.add_experimental_option("excludeSwitches", ["enable-automation", "enable-logging"])
chrome_options.add_experimental_option('useAutomationExtension', False)
chrome_options.add_argument('--disable-blink-features=AutomationControlled')

# Enable browser console logs
chrome_options.set_capability("goog:loggingPrefs", {"browser": "ALL"})

RECIP='testuser@localhost.org'
IMAP_ID='0'
DRIVER_CMD =Service('/usr/bin/chromedriver')
SITE_URL = 'http://cypht-test.org/'
USER = 'testuser'
PASS = 'testuser'
DESIRED_CAP = None

def get_driver(cap):
    return webdriver.Chrome(service=DRIVER_CMD, options=chrome_options)

def success(driver):
    pass

