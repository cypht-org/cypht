from base import WebTest, USER, PASS
from creds import RECIP
from runner import test_runner

class SendTest(WebTest):

    def __init__(self):
        WebTest.__init__(self)
        self.login(USER, PASS)
        self.wait_with_folder_list()

    def load_compose_page(self):
        list_item = self.by_class('menu_compose')
        link = list_item.find_element_by_tag_name('a').click()
        self.wait_with_folder_list()
        assert self.by_class('content_title').text == 'Compose'

    def compose_message(self):
        to = self.by_name('compose_to')
        to.send_keys(RECIP)
        subject = self.by_name('compose_subject')
        subject.send_keys('Test')
        body = self.by_name('compose_body')
        body.send_keys('test message')
        send = self.by_name('smtp_send')
        send.click()
        self.wait_with_folder_list()
        assert self.by_class('sys_messages').text == 'Message Sent'

    def view_message_list(self):
        list_item = self.by_class('menu_unread')
        list_item.find_element_by_tag_name('a').click()
        self.wait_on_class('unseen')
        assert self.by_class('mailbox_list_title').text == 'Unread'
        subject = self.by_class('unseen')
        link = subject.find_element_by_tag_name('a')
        assert link.text == 'Test'

    def view_message_detail(self):
        subject = self.by_class('unseen')
        link = subject.find_element_by_tag_name('a').click()
        self.wait_on_class('header_subject')
        detail_subject = self.by_class('header_subject')
        header = detail_subject.find_element_by_tag_name('th')
        assert header.text == 'Test'

        
if __name__ == '__main__':

    print("SEND TEST")
    test_runner(SendTest, [
        'load_compose_page',
        'compose_message',
        'view_message_list',
        'view_message_detail'
    ])
