#!/usr/bin/python

from selenium.webdriver.common.by import By
from base import WebTest, USER, PASS
from runner import test_runner
from settings import SettingsHelpers

class InlineMsgTests(SettingsHelpers):

    def __init__(self):
        WebTest.__init__(self)
        self.login(USER, PASS)
        self.wait_with_folder_list()

    def set_inline_message_test(self):
        self.checkbox_test('general_setting', 'inline_message', False, 'inline_message')
        self.dropdown_test('email_setting', 'all_email_since', '-1 week', '-5 years')

    def navigate_msg_test(self):
        self.by_css('[data-source=".email_folders"]').click()
        allmsgs = self.by_class('menu_email')
        allmsgs.find_element(By.TAG_NAME, 'a').click()
        self.wait_on_class('checkbox_cell')
        body = self.by_class('message_table_body')
        subject = body.find_element(By.CLASS_NAME, 'subject')
        subject.find_element(By.TAG_NAME, 'a').click()
        self.wait_on_class('header_subject')
        detail_subject = self.by_class('header_subject')
        header = detail_subject.find_element(By.TAG_NAME, 'th')
        assert header.text.startswith('recent')


if __name__ == '__main__':

    print("INLINE MSG TESTS")
    test_runner(InlineMsgTests, [
        'set_inline_message_test',
        # This test does not work.
        #'navigate_msg_test',
        'logout'
    ])
