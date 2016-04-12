#!/usr/bin/python

from time import sleep
from selenium import webdriver
from selenium.common import exceptions

DRIVER_LOCATION = '/usr/lib/chromium/chromedriver'
DRIVER = webdriver.Chrome
SITE_URL = 'http://localhost/hm3'
SLEEP_INT = 1

class WebTest:

    driver = None

    def __init__(self):
        self.driver = DRIVER(DRIVER_LOCATION)
        self.load()

    def load(self):
        self.go(SITE_URL)

    def go(self, url):
        self.driver.get(url)

    def rest(self):
        sleep(SLEEP_INT)

    def login(self, user, password):
        user_el = self.by_name('username')
        pass_el = self.by_name('password')
        user_el.send_keys(user)
        pass_el.send_keys(password)
        self.by_id('login').click()

    def logout(self):
        self.driver.find_element_by_class_name('logout_link').click()
        self.driver.find_element_by_id('logout_without_saving').click()

    def end(self):
        self.driver.quit()

    def by_id(self, el_id):
        try:
            return self.driver.find_element_by_id(el_id)
        except exceptions.NoSuchElementException:
            return None

    def by_name(self, name):
        try:
            return self.driver.find_element_by_name(name)
        except exceptions.NoSuchElementException:
            return None

    def by_css(self, selector):
        try:
            return self.driver.find_element_by_css_selector(selector)
        except exceptions.NoSuchElementException:
            return None

    def by_class(self, class_name):
        try:
            return self.driver.find_element_by_class_name(class_name)
        except exceptions.NoSuchElementException:
            return None
