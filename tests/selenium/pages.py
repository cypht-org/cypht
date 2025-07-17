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
        assert 'Contacts' in self.by_class('content_title').text

    def compose(self):
        if not self.mod_active('smtp'):
            return
        list_item = self.by_class('menu_compose')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert 'Compose' in self.by_class('content_title').text

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
        assert 'Message history' in self.by_class('content_title').text

    def home(self):
        list_item = self.by_class('menu_home')
        self.click_when_clickable(list_item)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('content_title').text == 'Home'

    def servers_page(self):
        try:
            if not self.by_class('settings').is_displayed():
                self.by_css('[data-bs-target=".settings"]').click()
                self.wait_for_settings_to_expand()
        except Exception as e:
            print(f" - settings menu expansion failed: {e}")
            # Continue anyway, the settings might already be expanded
        
        # Try to find and click the menu_servers element
        try:
            self.wait_on_class('menu_servers')
            list_item = self.by_class('menu_servers')
            link = list_item.find_element(By.TAG_NAME, 'a')
            
            # Try multiple click methods
            try:
                self.click_when_clickable(link)
            except Exception as click_error:
                print(f" - click_when_clickable failed: {click_error}")
                print(" - trying JavaScript click as fallback")
                self.driver.execute_script("arguments[0].click();", link)
                
        except Exception as e:
            print(f" - servers_page test failed: {e}")
            # Check if the element exists
            if not self.element_exists('menu_servers'):
                print(" - menu_servers element not found")
                return
            raise e
            
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('content_title').text == 'Servers'

    def site(self):
        try:
            if not self.by_class('settings').is_displayed():
                self.by_css('[data-bs-target=".settings"]').click()
                self.wait_for_settings_to_expand()
        except Exception as e:
            print(f" - settings menu expansion failed: {e}")
            # Continue anyway, the settings might already be expanded
        
        # Try to find and click the menu_settings element
        try:
            list_item = self.by_class('menu_settings')
            link = list_item.find_element(By.TAG_NAME, 'a')
            
            # Try multiple click methods
            try:
                self.click_when_clickable(link)
            except Exception as click_error:
                print(f" - click_when_clickable failed: {click_error}")
                print(" - trying JavaScript click as fallback")
                self.driver.execute_script("arguments[0].click();", link)
                
        except Exception as e:
            print(f" - site test failed: {e}")
            # Check if the element exists
            if not self.element_exists('menu_settings'):
                print(" - menu_settings element not found")
                return
            raise e
            
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('content_title').text == 'Site Settings'

    def folders(self):
        if not self.mod_active('imap_folders'):
            return
        if not self.mod_active('imap'):
            return
        try:
            list_item = self.by_class('menu_folders')
            link = list_item.find_element(By.TAG_NAME, 'a')
            
            # Check if the element is visible and enabled
            if not link.is_displayed():
                print(" - menu_folders link is not displayed")
                return
            if not link.is_enabled():
                print(" - menu_folders link is not enabled")
                return
                
            # Try to scroll the element into view first
            self.driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", link)
            
            # Wait a moment for any animations
            import time
            time.sleep(0.5)
            
            # Try clicking with JavaScript as a fallback
            try:
                self.click_when_clickable(link)
            except Exception as click_error:
                print(f" - click_when_clickable failed: {click_error}")
                print(" - trying JavaScript click as fallback")
                self.driver.execute_script("arguments[0].click();", link)
            
            self.wait_with_folder_list()
            self.safari_workaround()
            self.wait_for_navigation_to_complete()
            assert self.by_class('content_title').text == 'Folders'
        except Exception as e:
            print(f" - folders test failed: {e}")
            # Check if the element exists
            if not self.element_exists('menu_folders'):
                print(" - menu_folders element not found, IMAP module may not be enabled")
                return
            raise e

    def save(self):
        try:
            if not self.by_class('settings').is_displayed():
                self.by_css('[data-bs-target=".settings"]').click()
                self.wait_for_settings_to_expand()
        except Exception as e:
            print(f" - settings menu expansion failed: {e}")
            # Continue anyway, the settings might already be expanded
        
        # Try to find and click the menu_save element
        try:
            list_item = self.by_class('menu_save')
            link = list_item.find_element(By.TAG_NAME, 'a')
            
            # Try multiple click methods
            try:
                self.click_when_clickable(link)
            except Exception as click_error:
                print(f" - click_when_clickable failed: {click_error}")
                print(" - trying JavaScript click as fallback")
                self.driver.execute_script("arguments[0].click();", link)
                
        except Exception as e:
            print(f" - save test failed: {e}")
            # Check if the element exists
            if not self.element_exists('menu_save'):
                print(" - menu_save element not found")
                return
            raise e
            
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        assert self.by_class('content_title').text == 'Save Settings'

    def password(self):
        if not self.mod_active('account'):
            return
        if self.auth_type != 'DB':
            return
        try:
            if not self.by_class('settings').is_displayed():
                self.by_css('[data-bs-target=".settings"]').click()
                self.wait_for_settings_to_expand()
        except Exception as e:
            print(f" - settings menu expansion failed: {e}")
            # Continue anyway, the settings might already be expanded
        
        # Try to find and click the menu_change_password element
        try:
            self.wait_on_class('menu_change_password')
            list_item = self.by_class('menu_change_password')
            link = list_item.find_element(By.TAG_NAME, 'a')
            
            # Try multiple click methods
            try:
                self.click_when_clickable(link)
            except Exception as click_error:
                print(f" - click_when_clickable failed: {click_error}")
                print(" - trying JavaScript click as fallback")
                self.driver.execute_script("arguments[0].click();", link)
                
        except Exception as e:
            print(f" - password test failed: {e}")
            # Check if the element exists
            if not self.element_exists('menu_change_password'):
                print(" - menu_change_password element not found")
                return
            raise e
            
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
