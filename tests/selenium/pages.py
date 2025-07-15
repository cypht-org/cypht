#!/usr/bin/python

from base import WebTest, USER, PASS
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from runner import test_runner

class PageTests(WebTest):

    def __init__(self):
        WebTest.__init__(self)
        self.login(USER, PASS)
        self.wait_with_folder_list()

    def search(self):
        list_item = self.wait_for_element_by_class('menu_search')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert 'Search' in self.by_class('content_title').text

    def sent(self):
        list_item = self.by_class('menu_sent')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('mailbox_list_title').text == 'Sent'

    def unread(self):
        list_item = self.by_class('menu_unread')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('mailbox_list_title').text == 'Unread'

    def combined_inbox(self):
        if self.single_server():
            return
        list_item = self.by_class('menu_combined_inbox')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('mailbox_list_title').text == 'Everything'

    def flagged(self):
        list_item = self.by_class('menu_flagged')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('mailbox_list_title').text == 'Flagged'

    def contacts(self):
        if not self.mod_active('contacts'):
            return
        list_item = self.by_class('menu_contacts')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('content_title').text == 'Contacts'

    def compose(self):
        if not self.mod_active('smtp'):
            return
        list_item = self.by_class('menu_compose')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('content_title').text == 'Compose'

    def calendar(self):
        if not self.mod_active('calendar'):
            return
        list_item = self.by_class('menu_calendar')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('calendar_content_title').text == 'Calendar'

    def history(self):
        if not self.mod_active('history'):
            return
        list_item = self.by_class('menu_history')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('content_title').text == 'Message history'

    def home(self):
        settings_button = self.by_css('[data-bs-target=".settings"]')
        self.safe_click(settings_button)
        list_item = self.by_class('menu_home')
        self.safe_click(list_item)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('content_title').text == 'Home'

    def servers_page(self):
        self.wait_on_class('menu_servers')
        list_item = self.by_class('menu_servers')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('content_title').text == 'Servers'

    def site(self):
        list_item = self.by_class('menu_settings')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('content_title').text == 'Site Settings'

    def folders(self):
        if not self.mod_active('imap_folders'):
            return
        list_item = self.by_class('menu_folders')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('content_title').text == 'Folders'

    def save(self):
        list_item = self.by_class('menu_save')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('content_title').text == 'Save Settings'

    def password(self):
        if not self.mod_active('account'):
            return
        if self.auth_type != 'DB':
            return
        self.wait_on_class('menu_change_password')
        list_item = self.by_class('menu_change_password')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('content_title').text.strip() == 'Change Password'

    def profiles(self):
        if self.mod_active('profiles'):
            return
        list_item = self.by_class('menu_profiles')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('profile_content_title').text == 'Profiles'

if __name__ == '__main__':

    print("PAGE TESTS")
    test_runner(PageTests, [
        'search',
        'combined_inbox',
        'unread',
        'sent',
        'flagged',
        'contacts',
        'compose',
        'calendar',
        'history',
        'home',
        'folders',
        'save',
        'profiles',
        'servers_page',
        # 'site',
        'password',
        'logout',
    ])
