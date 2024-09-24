#!/usr/bin/python

from base import WebTest, USER, PASS
from selenium.webdriver.common.by import By
from runner import test_runner
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import NoSuchElementException
from selenium.common.exceptions import StaleElementReferenceException

class FolderListTests(WebTest):

    def __init__(self):
        WebTest.__init__(self)
        self.login(USER, PASS)
        self.wait_with_folder_list()

    def reload_folder_list(self):
        main_menu = self.by_class('main_menu')
        assert main_menu.text.startswith('Main')
        self.by_class('update_message_list').click()
        self.safari_workaround(3)
        # update_message_list triggers site reload, so we explicitly wait for element to become stale
        WebDriverWait(self.driver, 20).until(EC.staleness_of(main_menu))
        ignored_exceptions=(NoSuchElementException,StaleElementReferenceException,)
        # And once it is stale, we can now wait for it to become available again as the page contents are loaded again.
        main_menu = WebDriverWait(self.driver, 10,ignored_exceptions=ignored_exceptions).until(
        EC.presence_of_element_located((By.CLASS_NAME, 'main_menu'))
        )
        main_menu = self.by_class('main_menu')
        #and finally perform our test on the actual, refreshed element.
        assert main_menu.text.startswith('Main')

    def expand_section(self):
        self.by_css('[data-source=".settings"]').click()
        list_item = self.by_class('menu_home')
        list_item.find_element(By.TAG_NAME, 'a').click()
        self.wait_with_folder_list()
        assert self.by_class('content_title').text == 'Home'

    def collapse_section(self):
        self.by_css('[data-source=".main"]').click()
        list_item = self.by_class('menu_unread')
        link = list_item.find_element(By.TAG_NAME, 'a')
        assert link.is_displayed() == False

    def hide_folders(self):
        self.driver.execute_script("window.scrollBy(0, 1000);")
        self.wait(By.CLASS_NAME, 'hide_folders')
        # Use JavaScript to click the element
        hide_button = self.by_class('hide_folders')
        self.driver.execute_script("arguments[0].click();", hide_button)
        assert self.by_class('folder_toggle').text.startswith('Show folders')
        list_item = self.by_class('menu_home')
        link = list_item.find_element(By.TAG_NAME, 'a');
        assert link.is_displayed() == False

    def show_folders(self):
        folder_toggle = self.by_class('folder_toggle')
        self.driver.execute_script("arguments[0].click();", folder_toggle)
        self.wait(By.CLASS_NAME, 'main_menu')
        self.by_css('[data-source=".settings"]').click()
        list_item = self.by_class('menu_home')
        a_tag = list_item.find_element(By.TAG_NAME, 'a')
        self.driver.execute_script("arguments[0].scrollIntoView(true);", a_tag)
        self.driver.execute_script("arguments[0].click();", a_tag)
        self.wait_with_folder_list()
        assert self.by_class('content_title').text == 'Home'
        self.by_css('[data-source=".main"]').click()


if __name__ == '__main__':

    print("FOLDER LIST TESTS")
    test_runner(FolderListTests, [
        'reload_folder_list',
        'expand_section',
        'collapse_section',
        'hide_folders',
        'show_folders',
        'logout'
    ])
