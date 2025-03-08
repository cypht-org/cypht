#!/usr/bin/python

from base import WebTest, USER, PASS
from runner import test_runner
from selenium.webdriver.common.by import By
class LoginTests(WebTest):

    def bad_login_values(self):
        self.login('asdf', 'asdf')
        self.wait()
        self.safari_workaround()
        self.wait_on_class('sys_messages')
        assert self.by_class('sys_messages') != None

    def missing_password(self):
        self.load()
        self.login('asdf', '')
        self.wait()
        assert self.by_class('login_form') != None

    def missing_username(self):
        self.load()
        self.login('', 'asdf')
        self.wait()
        assert self.by_class('login_form') != None

    def missing_username_and_password(self):
        self.load()
        self.login('', '')
        self.wait()
        assert self.by_class('login_form') != None

    def bad_login_key(self):
        self.load()
        hidden_el = self.by_name('hm_page_key')
        self.change_val(hidden_el, 'asdf')
        self.login(USER, PASS)
        self.wait()
        assert self.by_class('login_form') != None

    def good_login(self):
        self.load()
        self.login(USER, PASS)
        self.wait_with_folder_list()
        assert self.by_class('content_title') != None

    def good_logout(self):
        self.logout()
        self.wait()
        assert self.by_class('sys_messages').text == 'Session destroyed on logout'



if __name__ == '__main__':

    print("LOGIN TESTS")
    test_runner(LoginTests, [
        'bad_login_values',
        'missing_password',
        'missing_username',
        'missing_username_and_password',
        'bad_login_key',
        'good_login',
        'good_logout'
    ])
