#!/usr/bin/python

from base import WebTest
from runner import test_runner

USER = 'testuser'
PASS = 'testuser'

class Test(WebTest):

    def test_bad_login(self):
        self.login('asdf', 'asdf')
        self.rest()
        assert self.by_class('err').text == 'Invalid username or password'

    def test_good_login(self):
        self.load()
        self.login(USER, PASS)
        self.rest()
        assert self.by_class('err') == None

    def test_folder_list(self):
        assert self.by_class('main_menu').text == 'Main'
        self.by_class('update_message_list').click()
        self.rest()
        assert self.by_class('main_menu').text == 'Main'

    def test_search(self):
        list_item = self.by_class('menu_search')
        list_item.find_element_by_tag_name('a').click()
        self.rest()
        assert self.by_class('content_title').text[0:6] == 'Search'

    def test_unread(self):
        list_item = self.by_class('menu_unread')
        list_item.find_element_by_tag_name('a').click()
        self.rest()
        assert self.by_class('mailbox_list_title').text == 'Unread'

    def test_combined_inbox(self):
        list_item = self.by_class('menu_combined_inbox')
        list_item.find_element_by_tag_name('a').click()
        self.rest()
        assert self.by_class('mailbox_list_title').text == 'Everything'

    def test_flagged(self):
        list_item = self.by_class('menu_flagged')
        list_item.find_element_by_tag_name('a').click()
        self.rest()
        assert self.by_class('mailbox_list_title').text == 'Flagged'

    def test_contacts(self):
        list_item = self.by_class('menu_contacts')
        list_item.find_element_by_tag_name('a').click()
        self.rest()
        assert self.by_class('content_title').text == 'Contacts'

    def test_compose(self):
        list_item = self.by_class('menu_compose')
        list_item.find_element_by_tag_name('a').click()
        self.rest()
        assert self.by_class('content_title').text == 'Compose'

    def test_calendar(self):
        list_item = self.by_class('menu_calendar')
        list_item.find_element_by_tag_name('a').click()
        self.rest()
        assert self.by_class('content_title').text == 'Calendar'

    def test_history(self):
        list_item = self.by_class('menu_history')
        list_item.find_element_by_tag_name('a').click()
        self.rest()
        assert self.by_class('content_title').text == 'Message history'

    def test_home(self):
        self.by_css('[data-source=".settings"]').click()
        list_item = self.by_class('menu_home')
        list_item.find_element_by_tag_name('a').click()
        self.rest()
        assert self.by_class('content_title').text == 'Home'

    def test_servers(self):
        list_item = self.by_class('menu_servers')
        list_item.find_element_by_tag_name('a').click()
        self.rest()
        assert self.by_class('content_title').text == 'Servers'

    def test_site(self):
        list_item = self.by_class('menu_settings')
        list_item.find_element_by_tag_name('a').click()
        self.rest()
        assert self.by_class('content_title').text == 'Site Settings'

    def test_general_settings(self):
        self.by_css('[data-target=".general_setting"]').click()
        self.by_name('smtp_auto_bcc').click()
        self.driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
        self.by_name('save_settings').click()
        self.rest()
        self.by_css('[data-source=".settings"]').click()
        assert self.by_class('sys_messages').text == 'Settings saved'

    def test_save(self):
        list_item = self.by_class('menu_save')
        list_item.find_element_by_tag_name('a').click()
        self.rest()
        assert self.by_class('content_title').text == 'Save Settings'

    def test_password(self):
        list_item = self.by_class('menu_change_password')
        list_item.find_element_by_tag_name('a').click()
        self.rest()
        assert self.by_class('content_title').text == 'Change Password'

    def test_profiles(self):
        list_item = self.by_class('menu_profiles')
        list_item.find_element_by_tag_name('a').click()
        self.rest()
        assert self.by_class('content_title').text == 'Profiles'

    def test_logout(self):
        self.logout()
        self.rest()
        assert self.by_class('sys_messages').text == 'Session destroyed on logout'


if __name__ == '__main__':

    test_runner(Test(), [
        'test_bad_login',
        'test_good_login',
        'test_folder_list',
        'test_search',
        'test_combined_inbox',
        'test_unread',
        'test_flagged',
        'test_contacts',
        'test_compose',
        'test_calendar',
        'test_history',
        'test_home',
        'test_servers',
        'test_site',
        'test_general_settings',
        'test_save',
        'test_password',
        'test_profiles',
        'test_logout',
    ])
