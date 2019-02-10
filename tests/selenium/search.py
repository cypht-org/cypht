from time import sleep
from base import WebTest, USER, PASS
from creds import RECIP
from runner import test_runner

class SearchTest(WebTest):

    def __init__(self):
        WebTest.__init__(self)
        self.login(USER, PASS)
        self.wait_with_folder_list()

    def load_search_page(self):
        list_item = self.by_class('menu_search')
        link = list_item.find_element_by_tag_name('a').click()
        self.wait_with_folder_list()
        assert self.by_class('content_title').text.startswith('Search')

    def keyword_search(self):
        terms = self.by_id('search_terms')
        terms.send_keys('test')
        self.by_class('search_update').click();
        self.wait_with_folder_list()
        sleep(1)
        table = self.by_class('message_table_body')
        assert len(table.find_elements_by_tag_name('tr')) > 0

    def reset_search(self):
        self.by_class('search_reset').click()
        self.wait_with_folder_list()
        sleep(1)
        assert self.by_id('search_terms').get_attribute('value') == ''
        table = self.by_class('message_table_body')
        assert len(table.find_elements_by_tag_name('tr')) == 0

if __name__ == '__main__':

    print("SEARCH TEST")
    test_runner(SearchTest, [
        'load_search_page',
        'keyword_search',
        'reset_search'
    ])
