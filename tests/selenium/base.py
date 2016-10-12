#!/usr/bin/python

# To run these tests you must create a local file called creds.py in
# this directory. This file must define the following values:
#
# get_driver:  A function that returns a webdriver object
# SITE_URL:    The url of the installation of Cypht to test against
# USER:        The username used to login to Cypht
# PASS:        The password used to login to Cypht

from time import sleep
from creds import SITE_URL, USER, PASS, get_driver
from selenium.common import exceptions

SLEEP_INT = 1

class WebTest:

    driver = None

    def __init__(self):
        self.driver = get_driver()
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

    def change_val(self, el, val):
        self.driver.execute_script('''
            var e=arguments[0]; var v=arguments[1]; e.value=v;''',
            el, val)

    def logout(self):
        self.driver.find_element_by_class_name('logout_link').click()
        try:
            logout = self.by_id('logout_without_saving')
            if logout:
                logout.click()
        except:
            pass

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
