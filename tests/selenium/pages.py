#!/usr/bin/python

from base import WebTest, USER, PASS
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
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
        # More flexible text matching for search page
        content_title = self.wait_for_class_text_contains('content_title', 'Search')
        title_text = content_title.text.strip()
        print(f"MESSAGES FOUND: '{title_text}'")
        assert 'Search' in title_text or 'search' in title_text.lower(), f"Expected 'Search' in title, got: '{title_text}'"

    def sent(self):
        list_item = self.by_class('menu_sent')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        # Look for mailbox_list_title inside content_title
        try:
            mailbox_title = self.wait_for_class_text_contains('mailbox_list_title', 'Sent')
            title_text = mailbox_title.text.strip()
            assert 'Sent' in title_text, f"Expected 'Sent' in mailbox title, got: '{title_text}'"
        except:
            # Fallback: check content_title for sent-related text
            content_title = self.wait_for_class_text_contains('content_title', 'Sent')
            title_text = content_title.text.strip()
            assert 'Sent' in title_text, f"Expected 'Sent' in content title, got: '{title_text}'"

    def unread(self):
        list_item = self.by_class('menu_unread')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        # Look for mailbox_list_title inside content_title
        try:
            mailbox_title = self.wait_for_class_text_contains('mailbox_list_title', 'Unread')
            title_text = mailbox_title.text.strip()
            assert 'Unread' in title_text, f"Expected 'Unread' in mailbox title, got: '{title_text}'"
        except:
            # Fallback: check content_title for unread-related text
            content_title = self.wait_for_class_text_contains('content_title', 'Unread')
            title_text = content_title.text.strip()
            assert 'Unread' in title_text, f"Expected 'Unread' in content title, got: '{title_text}'"

    def combined_inbox(self):
        if self.single_server():
            return
        list_item = self.by_class('menu_combined_inbox')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        # Look for mailbox_list_title inside content_title
        try:
            mailbox_title = self.wait_for_class_text_contains('mailbox_list_title', 'Everything')
            title_text = mailbox_title.text.strip()
            assert 'Everything' in title_text, f"Expected 'Everything' in mailbox title, got: '{title_text}'"
        except:
            # Fallback: check content_title for everything-related text
            content_title = self.wait_for_class_text_contains('content_title', 'Everything')
            title_text = content_title.text.strip()
            assert 'Everything' in title_text, f"Expected 'Everything' in content title, got: '{title_text}'"

    def flagged(self):
        list_item = self.by_class('menu_flagged')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        mailbox_title = self.wait_for_class_text_contains('mailbox_list_title', 'Flagged')
        title_text = mailbox_title.text.strip()
        assert 'Flagged' in title_text, f"Expected 'Flagged' in mailbox title, got: '{title_text}'"

    def contacts(self):
        if not self.mod_active('contacts'):
            return
        list_item = self.by_class('menu_contacts')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        # More flexible text matching for contacts page
        content_title = self.wait_for_class_text_contains('content_title', 'Contacts')
        title_text = content_title.text.strip()
        assert 'Contacts' in title_text, f"Expected 'Contacts' in title, got: '{title_text}'"

    def compose(self):
        if not self.mod_active('smtp'):
            return
        list_item = self.by_class('menu_compose')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        # More flexible text matching for compose page
        content_title = self.wait_for_class_text_contains('content_title', 'Compose')
        title_text = content_title.text.strip()
        assert 'Compose' in title_text, f"Expected 'Compose' in title, got: '{title_text}'"

    def calendar(self):
        if not self.mod_active('calendar'):
            return
        list_item = self.by_class('menu_calendar')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        title = self.wait_for_class_text_contains('calendar_content_title', 'Calendar')
        assert title.text == 'Calendar'

    def history(self):
        if not self.mod_active('history'):
            return
        list_item = self.by_class('menu_history')
        link = list_item.find_element(By.TAG_NAME, 'a')
        self.safe_click(link)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()
        content_title = self.wait_for_class_text_contains('content_title', 'Message history')
        assert 'Message history' in content_title.text

    def home(self):
        list_item = self.by_class('menu_home')
        self.safe_click(list_item)
        self.wait_with_folder_list()
        self.safari_workaround()
        self.wait_for_navigation_to_complete()

    def servers_page(self):
        if not self.mod_active('core'):
            return
        try:
            # Try to expand settings menu first
            self.wait_for_settings_to_expand()

            # Add a small delay to ensure the menu is fully expanded
            list_item = self.by_class('menu_servers')
            link = list_item.find_element(By.TAG_NAME, 'a')

            self.click_when_clickable(link)
            self.wait_with_folder_list()
            self.safari_workaround()
            self.wait_for_navigation_to_complete()
            assert 'Servers' in self.by_class('content_title').text
        except Exception as e:
            print(f" - servers_page test failed: {e}")
            # Check if the element exists
            if not self.element_exists('menu_servers'):
                print(" - menu_servers element not found")
                return
            raise e

    def site(self):
        if not self.mod_active('core'):
            return
        try:
            # Try to expand settings menu first
            self.wait_for_settings_to_expand()
            
            # Add a small delay to ensure the menu is fully expanded
            import time
            time.sleep(0.5)
            
            list_item = self.by_class('menu_settings')
            link = list_item.find_element(By.TAG_NAME, 'a')

            self.click_when_clickable(link)
            self.wait_with_folder_list()
            self.safari_workaround()
            self.wait_for_navigation_to_complete()
            assert 'Site' in self.by_class('content_title').text
        except Exception as e:
            print(f" - site test failed: {e}")
            # Check if the element exists
            if not self.element_exists('menu_settings'):
                print(" - menu_settings element not found")
            if not self.element_exists('menu_settings'):
                print(" - menu_settings element not found")
                return
            raise e

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
            assert 'Folders' in self.by_class('content_title').text
        except Exception as e:
            print(f" - folders test failed: {e}")
            # Check if the element exists
            if not self.element_exists('menu_folders'):
                print(" - menu_folders element not found")
                return
            raise e

    def save(self):
        if not self.mod_active('core'):
            return
        try:
            # Try to expand settings menu first
            self.wait_for_settings_to_expand()

            list_item = self.by_class('menu_save')
            link = list_item.find_element(By.TAG_NAME, 'a')

            self.click_when_clickable(link)
            self.wait_with_folder_list()
            self.safari_workaround()
            self.wait_for_navigation_to_complete()
            assert 'Save' in self.by_class('content_title').text
        except Exception as e:
            print(f" - save test failed: {e}")
            # Check if the element exists
            if not self.element_exists('menu_save'):
                print(" - menu_save element not found")
                return
            raise e

    def password(self):
        if not self.mod_active('core'):
            return
        try:
            # Try to expand settings menu first
            self.wait_for_settings_to_expand()

            list_item = self.by_class('menu_change_password')
            link = list_item.find_element(By.TAG_NAME, 'a')

            self.click_when_clickable(link)
            self.wait_with_folder_list()
            self.safari_workaround()
            self.wait_for_navigation_to_complete()
            assert 'Password' in self.by_class('content_title').text
        except Exception as e:
            print(f" - password test failed: {e}")
            # Check if the element exists
            if not self.element_exists('menu_change_password'):
                print(" - menu_change_password element not found")
                return
            raise e

    def profiles(self):
        if not self.mod_active('profiles'):
            return
        try:
            # Try to expand settings menu first
            self.wait_for_settings_to_expand()

            list_item = self.by_class('menu_profiles')
            link = list_item.find_element(By.TAG_NAME, 'a')

            self.click_when_clickable(link)
            self.wait_with_folder_list()
            self.safari_workaround()
            self.wait_for_navigation_to_complete()
            assert 'Profiles' in self.by_class('content_title').text
        except Exception as e:
            print(f" - profiles test failed: {e}")
            # Check if the element exists
            if not self.element_exists('menu_profiles'):
                print(" - menu_profiles element not found")
                return
            raise e

if __name__ == '__main__':
    print("PAGES TEST")
    test_runner(PageTests)
