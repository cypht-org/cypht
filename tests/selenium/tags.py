from base import WebTest, USER, PASS
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from runner import test_runner


class TagTest(WebTest):

    def __init__(self):
        WebTest.__init__(self)
        self.login(USER, PASS)
        self.wait_with_folder_list()

    def wait_for_modal(self, modal_id):
        return WebDriverWait(self.driver, 30).until(
            lambda d: d.find_element(By.ID, modal_id)
            if 'show' in d.find_element(By.ID, modal_id).get_attribute('class')
            else False
        )

    def wait_for_modal_gone(self, modal_id):
        WebDriverWait(self.driver, 30).until(
            lambda d: len(d.find_elements(By.ID, modal_id)) == 0
            or 'show' not in d.find_element(By.ID, modal_id).get_attribute('class')
        )

    def expand_tags_section(self):
        self.by_css('[data-bs-target=".tags_folders"]').click()
        self.wait_on_class('tag_add_new_btn')

    def add_tag(self):
        self.by_class('tag_add_new_btn').click()
        modal = self.wait_for_modal('tagFormModal')
        name = modal.find_element(By.ID, 'modal_tag_name')
        name.send_keys('Test')
        modal.find_element(By.CSS_SELECTOR, '.modal-footer .btn-primary').click()
        self.wait_on_sys_message()
        alert_message = self.by_class('sys_messages').find_element(By.XPATH, ".//div[contains(@class, 'flex-grow-1')]").text
        assert alert_message.strip() == 'Tag Created'
        self.wait_for_modal_gone('tagFormModal')
        self.wait_on_class('tag_row')

    def edit_tag(self):
        row = self.by_class('tag_row')
        row.find_element(By.CLASS_NAME, 'tag_action_edit').click()
        modal = self.wait_for_modal('tagFormModal')
        name = modal.find_element(By.ID, 'modal_tag_name')
        name.clear()
        name.send_keys('Test 1')
        modal.find_element(By.CSS_SELECTOR, '.modal-footer .btn-primary').click()
        self.wait_on_sys_message()
        alert_message = self.by_class('sys_messages').find_element(By.XPATH, ".//div[contains(@class, 'flex-grow-1')]").text
        assert alert_message.strip() == 'Tag Edited'
        self.wait_for_modal_gone('tagFormModal')

    def del_tag(self):
        row = self.by_class('tag_row')
        row.find_element(By.CLASS_NAME, 'tag_action_delete').click()
        modal = self.wait_for_modal('tagDeleteModal')
        modal.find_element(By.CSS_SELECTOR, '.modal-footer .btn-danger').click()
        self.wait_on_sys_message()
        alert_message = self.by_class('sys_messages').find_element(By.XPATH, ".//div[contains(@class, 'flex-grow-1')]").text
        assert alert_message.strip() == 'Tag Deleted'
        self.wait_for_modal_gone('tagDeleteModal')


if __name__ == '__main__':

    print("TAGS TEST")
    test_runner(TagTest, [
        'expand_tags_section',
        'add_tag',
        'edit_tag',
        'del_tag'
    ])
