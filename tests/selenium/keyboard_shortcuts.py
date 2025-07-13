#!/usr/bin/python

from base import WebTest, USER, PASS, SITE_URL
from runner import test_runner
from selenium.webdriver import Keys, ActionChains
from settings import SettingsHelpers
from selenium.webdriver.common.by import By
from selenium.common.exceptions import ElementNotVisibleException, ElementNotInteractableException, NoSuchElementException


class KeyboardShortcutTests(SettingsHelpers):

    def __init__(self):
        WebTest.__init__(self)
        self.login(USER, PASS)
        self.wait_with_folder_list()
        self.by_css('[data-bs-target=".settings"]').click()
        self.wait_for_settings_to_expand()
        list_item = self.by_class('menu_settings')
        self.click_when_clickable(list_item.find_element(By.TAG_NAME, 'a'))
        self.wait()
        self.checkbox_test('general_setting', 'enable_keyboard_shortcuts', False, 'keyboard_shortcuts')

    def send_keys(self, key_combo):
        # Create ActionChains object for chaining key presses
        action = ActionChains(self.driver)
        # Iterate through the key_combo list and perform key_down for each
        for key in key_combo:
            action.key_down(key)
        # Release all keys after chaining
        action.perform()

    def nav_to_page(self, key_combo, titlestr, title_class):
        self.load()
        self.wait(timeout=10)
        el = self.by_tag('body')
        el.click()
        self.send_keys(key_combo)
        self.wait_with_folder_list()
        self.wait_on_class(title_class)
        # Debug info
        element_text = self.by_class(title_class).text.strip()
        print(f"Expected title prefix: '{titlestr}', Found: '{element_text}'")
        assert self.by_class(title_class).text.startswith(titlestr)

    def nav_to_unread(self):
        self.nav_to_page([Keys.CONTROL, Keys.SHIFT, 'k'], 'Unread', 'mailbox_list_title')

    def nav_to_everything(self):
        if self.element_exists('menu_combined_inbox'):
            self.nav_to_page([Keys.CONTROL, Keys.SHIFT, 'e'], 'Everything', 'mailbox_list_title')
        else:
            print("Skipping 'Everything' navigation: 'menu_combined_inbox' not present (likely single-server setup).")
            pass
    
    def nav_to_flagged(self):
        if self.element_exists('menu_flagged'):
            self.nav_to_page([Keys.CONTROL, Keys.SHIFT, 'f'], 'Flagged', 'mailbox_list_title')
        else:
            print("Skipping 'Flagged' navigation: 'menu_flagged' not present.")
            pass

    def nav_to_history(self):
        self.nav_to_page([Keys.CONTROL, Keys.SHIFT, 'h'], 'Message history', 'content_title')

    def nav_to_contacts(self):
        self.nav_to_page([Keys.CONTROL, Keys.SHIFT, 'c'], 'Contacts', 'content_title')

    def nav_to_compose(self):
        self.nav_to_page([Keys.CONTROL, Keys.SHIFT, 's'], 'Compose', 'content_title')

    def toggle_folders(self):
        el = self.by_tag('body')
        el.click()
        self.send_keys([Keys.CONTROL, Keys.SHIFT, 'y'])
        try:
            self.by_class('folder_list').click()
        except ElementNotInteractableException:
            pass
        self.send_keys([Keys.CONTROL, Keys.SHIFT, 'y'])
        self.wait(timeout=10)
        assert self.by_class('folder_list') != None


if __name__ == '__main__':

    print("KEYBOARD SHORTCUT TESTS")
    test_runner(KeyboardShortcutTests, [

        'nav_to_history',
        'nav_to_compose',
        'nav_to_contacts',
        'nav_to_everything',
        'nav_to_flagged',
        'nav_to_unread',
        'toggle_folders',
        'logout'
    ])