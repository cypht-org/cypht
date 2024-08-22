from base import WebTest, USER, PASS
from selenium.webdriver.common.by import By
from runner import test_runner


class TagTest(WebTest):

    def __init__(self):
        WebTest.__init__(self)
        self.login(USER, PASS)
        self.wait()

    def load_tag_page(self):
        self.by_css('[data-source=".tags_folders"]').click()
        list_item = self.by_class('tags_add_new')
        list_item.find_element(By.TAG_NAME, 'a').click()
        self.wait_with_folder_list()
        assert self.by_class('content_title').text == 'Tags'
        assert self.by_class('tree-view').text == 'No tags available yet.'

    def add_tag(self):
        name = self.by_name('tag_name')
        name.send_keys('Test')
        parent_tag = self.by_name('parent_tag')
        parent_tag.send_keys('')
        self.by_name('submit_tag').click()
        self.wait_with_folder_list()
        assert self.by_class('sys_messages').text == 'Tag Created'

    def edit_tag(self):
        self.wait()
        self.by_id('edit_tag').click()
        self.wait()
        name = self.by_name('tag_name')
        name.send_keys('Test 1')
        self.by_name('submit_tag').click()
        self.wait_with_folder_list()
        assert self.by_class('sys_messages').text == 'Tag Edited'

    def del_tag(self):
        self.wait()
        self.by_id('destroy_tag').click()
        self.wait_with_folder_list()
        assert self.by_class('sys_messages').text == 'Tag Deleted'


if __name__ == '__main__':

    print("TAGS TEST")
    test_runner(TagTest, [
        'load_tag_page',
        'add_tag',
        'edit_tag',
        'del_tag'
    ])
