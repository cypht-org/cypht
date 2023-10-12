from selenium.webdriver.common.by import By
from base import WebTest, USER, PASS
from runner import test_runner

class ProfileTest(WebTest):

    def __init__(self):
        WebTest.__init__(self)
        self.login(USER, PASS)
        self.wait_with_folder_list()

    def load_profile_page(self):
        self.by_css('[data-source=".settings"]').click()
        list_item = self.by_class('menu_profiles')
        list_item.find_element(By.TAG_NAME, 'a').click()
        self.wait_with_folder_list()
        assert self.by_class('content_title').text == 'Profiles'

    def add_profile(self):
        self.by_class('add_profile').click()
        name = self.by_name('profile_name')
        name.send_keys('Test')
        addr = self.by_name('profile_address')
        addr.send_keys('test@test.com')
        reply = self.by_name('profile_replyto')
        reply.send_keys('test@test.com')
        sig = self.by_name('profile_sig')
        sig.send_keys('foo')
        self.by_name('profile_default').click()
        self.by_class('submit_profile').click()
        self.wait_with_folder_list()
        from time import sleep; sleep(5)
        assert 'test@test.com' in self.by_class('profile_details').text
        
    def edit_profile(self):
        table = self.by_class('profile_details')
        table.find_element(By.TAG_NAME, 'a').click()
        self.wait_with_folder_list()
        name = self.by_name('profile_name')
        name.send_keys('New Name')
        self.by_class('profile_update').click()
        self.wait_with_folder_list()
        assert 'New Name' in self.by_class('profile_details').text
        
    def del_profile(self):
        table = self.by_class('profile_details')
        table.find_element(By.TAG_NAME, 'a').click()
        self.wait_with_folder_list()
        self.by_name('profile_delete').click()
        self.wait_on_sys_message()
        assert self.by_class('sys_messages').text == 'Profile Deleted'


if __name__ == '__main__':

    print("PROFIILE TEST")
    test_runner(ProfileTest, [
        'load_profile_page',
        'add_profile',
        'edit_profile',
        'del_profile'
    ])
