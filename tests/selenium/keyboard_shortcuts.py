#!/usr/bin/python

from selenium.common.exceptions import ElementNotVisibleException, ElementNotInteractableException
from selenium.webdriver.common.keys import Keys
from base import WebTest, USER, PASS, SITE_URL
from runner import test_runner
from settings import SettingsHelpers



class KeyboardShortcutTests(SettingsHelpers):

    def __init__(self):
        WebTest.__init__(self)
        self.login(USER, PASS)
        self.wait_with_folder_list()
        self.checkbox_test('general_setting', 'enable_keyboard_shortcuts', False, 'keyboard_shortcuts')

    def nav_to_page(self, key_combo, titlestr, title_class):
        el = self.by_tag('body')
        el.send_keys(key_combo)  
        self.wait_with_folder_list()
        assert self.by_class(title_class).text.startswith(titlestr)

    def nav_to_unread(self):
        self.nav_to_page(Keys.CONTROL + Keys.SHIFT + 'u', 'Unread', 'mailbox_list_title')

    def nav_to_everything(self):
        self.nav_to_page(Keys.CONTROL + Keys.SHIFT + 'e', 'Everything', 'mailbox_list_title')

    def nav_to_flagged(self):
        self.nav_to_page(Keys.CONTROL + Keys.SHIFT + 'f', 'Flagged', 'mailbox_list_title')

    def nav_to_history(self):
        self.nav_to_page(Keys.CONTROL + Keys.SHIFT + 'h', 'Message history', 'content_title')

    def nav_to_contacts(self):
        self.nav_to_page(Keys.CONTROL + Keys.SHIFT + 'c', 'Contacts', 'content_title')

    def nav_to_compose(self):
        self.nav_to_page(Keys.CONTROL + Keys.SHIFT + 's', 'Compose', 'content_title')

    def toggle_folders(self):
        el = self.by_tag('body')
        el.send_keys(Keys.CONTROL + Keys.SHIFT + 'y')
        try:
            self.by_class('folder_list').click()
            #assert False == True
        except ElementNotInteractableException:
            pass
        el.send_keys(Keys.CONTROL + Keys.SHIFT + 'y')
        self.by_class('folder_list').click()


if __name__ == '__main__':

    print("KEYBOARD SHORTCUT TESTS")
    test_runner(KeyboardShortcutTests, [

        'nav_to_history',
        'nav_to_contacts',
        'nav_to_everything',
        'nav_to_flagged',
        'nav_to_unread',
        'nav_to_compose',
        'toggle_folders',
        'logout'
    ])
