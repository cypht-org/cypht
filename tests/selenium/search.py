from time import sleep
from base import WebTest, USER, PASS
from selenium.webdriver.common.by import By
from creds import RECIP
from runner import test_runner
from selenium.webdriver.support.ui import Select

class SearchTest(WebTest):

    def __init__(self):
        WebTest.__init__(self)
        self.login(USER, PASS)
        self.wait_with_folder_list()

    def load_search_page(self):
        list_item = self.by_class('menu_search')
        list_item.find_element(By.TAG_NAME, 'a').click()
        self.wait_with_folder_list()
        self.wait_for_navigation_to_complete()
        assert 'Search' in self.by_class('content_title').text

    def keyword_search(self):
        terms = self.by_id('search_terms')
        terms.send_keys('test')
        Select(self.by_name('search_since')).select_by_value('-5 years')
        self.by_class('search_update').click();
        self.wait_with_folder_list()
        sleep(1)
        table = self.by_class('message_table_body')
        table_rows = table.find_elements(By.TAG_NAME, 'tr')
        row_count = len(table_rows)
        assert row_count >= 0

    def reset_search(self):
        self.by_class('search_reset').click()
        self.wait_with_folder_list()
        sleep(1)
        assert self.by_id('search_terms').get_attribute('value') == ''
        table = self.by_class('message_table_body')
        assert len(table.find_elements(By.TAG_NAME, 'tr')) == 0

if __name__ == '__main__':

    print("SEARCH TEST")
    test_runner(SearchTest, [
        'load_search_page',
        'keyword_search',
        'reset_search'
    ])
