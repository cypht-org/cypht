#!/usr/bin/python

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

    def navigate_msg_test(self):
        self.by_css('[data-source=".email_folders"]').click()
        allmsgs = self.by_class('menu_email')
        allmsgs.find_element_by_tag_name('a').click()
        self.wait_on_class('checkbox_cell')
        body = self.by_class('message_table_body')
        subject = body.find_element_by_class_name('subject')
        subject.find_element_by_tag_name('a').click()
        self.wait_on_class('header_subject')
        detail_subject = self.by_class('header_subject')
        header = detail_subject.find_element_by_tag_name('th')
        assert header.text.startswith('recent')


if __name__ == '__main__':

    print("INLINE MSG TESTS")
    test_runner(InlineMsgTests, [

        'set_inline_message_test',
        'navigate_msg_test',
        'logout'
    ])
