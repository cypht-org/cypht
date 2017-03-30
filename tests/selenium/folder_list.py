#!/usr/bin/python

from base import WebTest, USER, PASS
from runner import test_runner
from selenium.common.exceptions import ElementNotVisibleException

class FolderListTests(WebTest):

    def __init__(self):
        WebTest.__init__(self)
        self.login(USER, PASS)
        self.rest()

    def reload_folder_list(self):
        assert self.by_class('main_menu').text == 'Main'
        self.by_class('update_message_list').click()
        self.rest()
        assert self.by_class('main_menu').text == 'Main'

    def expand_section(self):
        self.by_css('[data-source=".settings"]').click()
        list_item = self.by_class('menu_home')
        list_item.find_element_by_tag_name('a').click()
        self.rest()
        assert self.by_class('content_title').text == 'Home'

    def collapse_section(self):
        self.by_css('[data-source=".main"]').click()
        list_item = self.by_class('menu_unread')
        link = list_item.find_element_by_tag_name('a')
        assert link.is_displayed() == False
        
    def hide_folders(self):
        self.by_class('hide_folders').click()
        list_item = self.by_class('menu_home')
        link = list_item.find_element_by_tag_name('a');
        assert link.is_displayed() == False
        self.rest()

    def show_folders(self):
        self.by_class('folder_toggle').click()
        list_item = self.by_class('menu_home')
        list_item.find_element_by_tag_name('a').click()
        self.rest()
        assert self.by_class('content_title').text == 'Home'
        self.by_css('[data-source=".main"]').click()


if __name__ == '__main__':

    print "FOLDER LIST TESTS"
    test_runner(FolderListTests, [
        'reload_folder_list',
        'expand_section',
        'collapse_section',
        'hide_folders',
        'show_folders',
        'logout'
    ])
