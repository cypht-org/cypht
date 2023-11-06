from selenium.webdriver.common.by import By
from base import WebTest, USER, PASS
from runner import test_runner
from creds import IMAP_ID

# This test needs to be modified.

class ServersTest(WebTest):

    def __init__(self):
        WebTest.__init__(self)
        self.login(USER, PASS)
        self.wait_with_folder_list()

    def toggle_server_section(self, name):
        return self.by_css('[data-target=".{0}_section"]'.format(name)).click()

    def load_servers_page(self):
        self.by_css('[data-source=".settings"]').click()
        list_item = self.by_class('menu_servers')
        list_item.find_element(By.TAG_NAME, 'a').click()
        self.wait_with_folder_list()
        assert self.by_class('content_title').text == 'Servers'

    def smtp_add(self):
        self.toggle_server_section('smtp')
        name = self.by_name('new_smtp_name')
        name.send_keys('Test')
        addr = self.by_name('new_smtp_address')
        addr.send_keys('localhost')
        port = self.by_name('new_smtp_port')
        port.clear()
        port.send_keys(25)
        self.by_id('smtp_notls').click()
        self.by_name('submit_smtp_server').click()
        self.wait_on_sys_message()
        assert self.by_class('sys_messages').text == 'Added SMTP server!'

    def smtp_del(self):
        self.by_class('delete_smtp_connection').click()
        self.confirm_alert()
        self.wait_on_sys_message()
        assert self.by_class('sys_messages').text == 'Server deleted'
        self.toggle_server_section('smtp')

    def smtp_confirm(self):
        user = self.by_id('smtp_user_0')
        user.send_keys('testuser')
        passw = self.by_id('smtp_pass_0')
        passw.send_keys('testuser')
        self.by_class('save_smtp_connection').click()
        from time import sleep; sleep(3)
        self.wait_on_sys_message()
        assert self.by_class('sys_messages').text == 'Server saved'
        self.toggle_server_section('smtp')

    def smtp_test(self):
        self.toggle_server_section('smtp')
        self.by_class('test_smtp_connection').click()
        self.wait_on_sys_message()
        assert self.by_class('sys_messages').text == 'Successfully authenticated to the SMTP server'
        self.toggle_server_section('smtp')

    def imap_add(self):
        self.toggle_server_section('imap')
        name = self.by_name('new_imap_name')
        name.send_keys('Test')
        addr = self.by_name('new_imap_address')
        addr.send_keys('localhost')
        port = self.by_name('new_imap_port')
        port.clear()
        port.send_keys(143)
        self.by_id('imap_notls').click()
        self.by_name('submit_imap_server').click()
        self.wait_on_sys_message()
        assert self.by_class('sys_messages').text == 'Added server!'

    def imap_confirm(self):
        user = self.by_id('imap_user_'+IMAP_ID)
        user.send_keys('testuser')
        passw = self.by_id('imap_pass_'+IMAP_ID)
        passw.send_keys('testuser')
        self.by_class('save_imap_connection').click()
        from time import sleep; sleep(3)
        self.wait_on_sys_message()
        assert self.by_class('sys_messages').text == 'Server saved'
        self.toggle_server_section('imap')

if __name__ == '__main__':

    print("SERVERS TEST")
    test_runner(ServersTest, [
        'load_servers_page',
        #'smtp_add',
        #'smtp_del',
        'smtp_confirm',
        #'smtp_test',
        'imap_add',
        'imap_confirm'
    ])
