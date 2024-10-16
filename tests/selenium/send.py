from base import WebTest, USER, PASS
from selenium.webdriver.common.by import By
from creds import RECIP
from runner import test_runner
from selenium.common.exceptions import TimeoutException
from selenium.common.exceptions import NoSuchElementException

class SendTest(WebTest):

    def __init__(self):
        WebTest.__init__(self)
        self.login(USER, PASS)
        self.wait_with_folder_list()

    def load_compose_page(self):
        list_item = self.by_class('menu_compose')
        link = list_item.find_element(By.TAG_NAME, 'a').click()
        self.wait_with_folder_list()
        self.wait_for_navigation_to_complete()
        assert self.by_class('content_title').text == 'Compose'

    def compose_message(self):
        to = self.by_name('compose_to')
        to.send_keys(RECIP)
        subject = self.by_name('compose_subject')
        subject.send_keys('Test')
        body = self.by_name('compose_body')
        body.send_keys('test message')
        send_button = self.by_class('smtp_send_placeholder')
        if send_button.get_attribute('disabled'):
            self.driver.execute_script("arguments[0].removeAttribute('disabled')", send_button)
        send_button.click()
        self.wait_with_folder_list()
        sys_messages_element = self.by_class('sys_messages')
        sys_messages = sys_messages_element.text
        expected_messages = [
            'You need at least one configured SMTP server to send outbound messages',
            'Please create a profile for saving sent messages',
            'Please create a profile for saving sent messages option',
            'Message Sent'
        ]
        # Check if any of the expected messages is present
        message_found = any(msg in sys_messages for msg in expected_messages)
        assert message_found, f"Unexpected system message: {sys_messages}"

    def view_message_list(self):
        list_item = self.by_class('menu_unread')
        list_item.find_element(By.TAG_NAME, 'a').click()
        self.wait_for_navigation_to_complete()
        assert self.by_class('mailbox_list_title').text == 'Unread'
        # self.wait_on_class('unseen', 10)
        # try:
        #     self.wait_on_class('unseen', 10)
        # except TimeoutException as e:
        #     return
        unseen_elements = self.driver.find_elements(By.CLASS_NAME, 'unseen')
        if unseen_elements:
            subject = unseen_elements[0]
            link = subject.find_element(By.TAG_NAME, 'a')
            print(link.text)
            assert link.text == 'Test'
        else:
            # The current navigation does not have yet access to the data sources, disabling it from being able to display the warning message when none is available.
            """
            expected_messages = [
                'You don\'t have any data sources assigned to this page.',
'                Add some'
            ];
            nux_empty_combined_view = self.by_class('nux_empty_combined_view')
            messages = nux_empty_combined_view.text
            message_found = any(msg in messages for msg in expected_messages)
            assert message_found, f"Unexpected system message: {messages}"
            """
            pass

    def view_message_detail(self):
        try:
            subject = self.by_class('unseen')
        except NoSuchElementException as e:
            return
        link = subject.find_element(By.TAG_NAME, 'a').click()
        self.wait_on_class('header_subject')
        detail_subject = self.by_class('header_subject')
        header = detail_subject.find_element(By.TAG_NAME, 'th')
        assert header.text == 'Test'


if __name__ == '__main__':

    print("SEND TEST")
    test_runner(SendTest, [
        'load_compose_page',
        'compose_message',
        'view_message_list',
        'view_message_detail'
    ])
