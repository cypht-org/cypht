#!/usr/bin/python

from base import WebTest, USER, PASS
from runner import test_runner
from selenium.common.exceptions import ElementNotVisibleException

class SettingsTests(WebTest):

    def __init__(self):
        WebTest.__init__(self)
        self.login(USER, PASS)
        self.rest()

    def general_settings(self):
        self.by_css('[data-target=".general_setting"]').click()
        self.by_name('smtp_auto_bcc').click()
        self.driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
        self.by_name('save_settings').click()
        self.rest()
        self.by_css('[data-source=".settings"]').click()
        assert self.by_class('sys_messages').text == 'Settings saved'

if __name__ == '__main__':

    print "SETTINGS TESTS"
    test_runner(SettingsTests(), [
    ])
