#!/usr/bin/python

# To run these tests you must copy one of the example files to "creds.py" in
# this directory, and edit it for your environment. There are 2 example files
# in this directory:
#
# remote_creds.example.py:  Configure the Selenium tests to run with BrowserStack
# local_creds.example.py:   Configure the Selenium tests to run locally

import re
from creds import SITE_URL, USER, PASS, get_driver
from selenium.webdriver.common.by import By
from selenium.common import exceptions
from contextlib import contextmanager
from selenium.webdriver.support.ui import WebDriverWait 
from selenium.webdriver.support import expected_conditions as exp_cond

INI_PATH = '../../hm3.ini'

class WebTest:

    driver = None

    def __init__(self, cap=None):
        self.read_ini()
        self.driver = get_driver(cap)
        self.browser = False
        if 'browserName' in self.driver.capabilities:
            self.browser = self.driver.capabilities['browserName'].lower()
        self.load()

    def read_ini(self):
        self.modules = []
        ini = open(INI_PATH)
        for row in ini.readlines():
            if re.match('^modules\[\]\=', row):
                parts = row.split('=')
                self.modules.append(parts[1].strip())

    def load(self):
        print(" - loading site")
        self.go(SITE_URL)
        try:
            self.driver.maximize_window()
        except Exception:
            print(" - Could not maximize browser :(")
        if self.browser == 'safari':
            try:
                self.driver.set_window_size(1920,1080)
            except Exception:
                print(" - Could not maximize Safari")

    def mod_active(self, name):
        if name in self.modules:
            return True
        print(" - module not enabled: %s" % name)
        return False

    def go(self, url):
        self.driver.get(url)

    def login(self, user, password):
        print(" - logging in")
        user_el = self.by_name('username')
        pass_el = self.by_name('password')
        user_el.send_keys(user)
        pass_el.send_keys(password)
        self.by_css('input[value=Login]').click()

    def change_val(self, el, val):
        self.driver.execute_script('''
            var e=arguments[0]; var v=arguments[1]; e.value=v;''',
            el, val)

    def confirm_alert(self):
        WebDriverWait(self.driver, 3).until(exp_cond.alert_is_present(), 'timed out')
        alert = self.driver.switch_to.alert
        alert.accept()

    def logout_no_save(self):
        print(" - logging out")
        self.driver.find_element_by_class_name('logout_link').click()
        logout = self.by_id('logout_without_saving').click()

    def logout(self):
        print(" - logging out")
        self.driver.find_element_by_class_name('logout_link').click()

    def end(self):
        self.driver.quit()

    def by_id(self, el_id):
        print(" - finding element by id {0}".format(el_id))
        return self.driver.find_element_by_id(el_id)

    def by_tag(self, name):
        print(" - finding element by tag name {0}".format(name))
        return self.driver.find_element_by_tag_name(name)

    def by_name(self, name):
        print(" - finding element by name {0}".format(name))
        return self.driver.find_element_by_name(name)

    def by_css(self, selector):
        print(" - finding element by selector {0}".format(selector))
        return self.driver.find_element_by_css_selector(selector)

    def by_class(self, class_name):
        print(" - finding element by class {0}".format(class_name))
        return self.driver.find_element_by_class_name(class_name)

    def wait(self, el_type=By.TAG_NAME, el_value="body", timeout=30):
        print(" - waiting for page by {0}: {1} ...".format(el_type, el_value))
        element = WebDriverWait(self.driver, timeout).until(
            exp_cond.presence_of_element_located((el_type, el_value)))

    def wait_on_class(self, class_name, timeout=30):
        self.wait(By.CLASS_NAME, class_name)

    def wait_with_folder_list(self):
        self.wait(By.CLASS_NAME, "main_menu")

    def wait_on_sys_message(self, timeout=30):
        wait = WebDriverWait(self.driver, timeout)
        element = wait.until(wait_for_non_empty_text((By.CLASS_NAME, "sys_messages"))
)

    def safari_workaround(self, timeout=1):
        if self.browser == 'safari':
            print(" - waiting {0} extra second for Safari".format(timeout))
            self.driver.implicitly_wait(timeout)

class wait_for_non_empty_text(object):
    def __init__(self, locator):
        self.locator = locator

    def __call__(self, driver):
        try:
            element_text = exp_cond._find_element(driver, self.locator).text.strip()
            print(element_text)
            return element_text != ""
        except exceptions.StaleElementReferenceException:
            return False
