#!/usr/bin/python

from base import WebTest, USER, PASS
from runner import test_runner

class LoginTests(WebTest):

    def bad_login_values(self):
        self.load()
        self.login('asdf', 'asdf')
        self.rest()
        assert self.by_class('err') != None

    def bad_login_key(self):
        self.load()
        hidden_el = self.by_name('hm_page_key')
        self.change_val(hidden_el, 'asdf')
        self.login(USER, PASS)
        self.rest()
        assert self.by_class('content_title') == None

    def good_login(self):
        self.load()
        self.login(USER, PASS)
        self.rest()
        assert self.by_class('err') == None
        assert self.by_class('content_title') != None

    def good_logout(self):
        self.logout()
        self.rest()
        assert self.by_class('sys_messages').text == 'Session destroyed on logout'



if __name__ == '__main__':

    print "LOGIN TESTS"
    test_runner(LoginTests(), [
        'bad_login_values',
        'bad_login_key',
        'good_login',
        'good_logout'
    ])
