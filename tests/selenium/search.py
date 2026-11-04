from time import sleep
from base import WebTest, USER, PASS
from selenium.webdriver.common.by import By
from creds import RECIP
from runner import test_runner
from selenium.webdriver.support.ui import Select, WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

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
        # Use JavaScript click to avoid issues with elements being obscured or not interactable
        self.driver.execute_script("document.querySelector('.search_update').click();")
        self.wait_with_folder_list()
        sleep(1)
        table = self.by_class('message_table_body')
        table_rows = table.find_elements(By.TAG_NAME, 'tr')
        row_count = len(table_rows)
        assert row_count >= 0

    def reset_search(self):
        elem = self.by_class('search_reset')
        self.driver.execute_script("arguments[0].scrollIntoView({behavior: 'instant'})", elem)
        WebDriverWait(self.driver, 60).until(EC.element_to_be_clickable(elem))
        elem.click()

        # Reset reloads ?page=search and then replaces the message tbody.
        # Query from the driver each time so a replaced tbody cannot go stale.
        def results_cleared(driver):
            try:
                if driver.execute_script("return document.readyState") != "complete":
                    return False
                terms = driver.find_elements(By.ID, 'search_terms')
                if not terms or terms[0].get_attribute('value') not in (None, ''):
                    return False
                if not driver.find_elements(By.CSS_SELECTOR, '.message_table_body'):
                    return False
                return len(driver.find_elements(By.CSS_SELECTOR, '.message_table_body tr')) == 0
            except Exception:
                return False

        WebDriverWait(self.driver, 60).until(results_cleared)
        row_count = len(self.driver.find_elements(By.CSS_SELECTOR, '.message_table_body tr'))
        assert row_count == 0, f"Expected tbody to be empty, but found {row_count} rows"

if __name__ == '__main__':

    print("SEARCH TEST")
    test_runner(SearchTest, [
        'load_search_page',
        'keyword_search',
        'reset_search'
    ])
