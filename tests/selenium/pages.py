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
        self.wait_on_class('content_title')
        content_title = self.by_class('content_title')
        title_text = content_title.text.strip()
        print(f"MESSAGES FOUND: '{title_text}'")
        assert 'Search' in title_text or 'search' in title_text.lower(), f"Expected 'Search' in title, got: '{title_text}'"

if __name__ == '__main__':
    print("PAGES TEST")
    test_runner(PageTests)
