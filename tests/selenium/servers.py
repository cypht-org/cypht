from time import sleep
from base import WebTest, USER, PASS
from selenium.webdriver.common.by import By
from runner import test_runner
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

class ServersTest(WebTest):

    def __init__(self):
        WebTest.__init__(self)
        self.login(USER, PASS)
        self.wait_with_folder_list()

    def toggle_server_section(self, name):
        return self.by_css('[data-target=".{0}_section"]'.format(name)).click()

    def load_servers_page(self):
        self.load()
        self.wait()
        self.wait_with_folder_list()
        self.by_css('[data-bs-target=".settings"]').click()
        self.wait_for_settings_to_expand()
        list_item = self.by_class('menu_servers')
        self.click_when_clickable(list_item.find_element(By.TAG_NAME, 'a'))
        self.wait()
        self.wait_with_folder_list()
        self.wait_for_navigation_to_complete()
        assert self.by_class('content_title').text == 'Servers'

    def server_stmp_and_imap_add(self):
        self.toggle_server_section('server_config')
        self.wait_on_class('imap-jmap-smtp-btn')
        self.by_id('add_new_server_button').click()
        # self.wait_on_class('srv_setup_stepper_profile_name')
        name = self.by_id('srv_setup_stepper_profile_name')
        name.send_keys('Test')
        email = self.by_name('srv_setup_stepper_email')
        email.send_keys('test@localhost')
        pwd = self.by_name('srv_setup_stepper_password')
        pwd.send_keys('test')
        next_button = WebDriverWait(self.driver, 10).until(
            EC.element_to_be_clickable((By.ID, "step_config_action_next"))
        )
         # Scroll to the button and wait for any animations/overlays to finish
        self.driver.execute_script("arguments[0].scrollIntoView({behavior: 'instant', block: 'center'});", next_button)
        WebDriverWait(self.driver, 60).until(EC.element_to_be_clickable(next_button))

        # Try multiple click methods for better reliability
        try:
            next_button.click()
        except Exception as e:
            print(f"Normal click failed: {e}. Trying JavaScript click...")
            self.driver.execute_script("arguments[0].click();", next_button)
        # show step two
        WebDriverWait(self.driver, 10).until(
            EC.visibility_of_element_located((By.XPATH, '//h2[text()="Step 2"]'))
        )
        stmp_addr = self.by_name('srv_setup_stepper_smtp_address')
        stmp_addr.send_keys('localhost')
        smtp_port = self.by_name('srv_setup_stepper_smtp_port')
        smtp_port.clear()
        smtp_port.send_keys(25)
        imap_addr = self.by_name('srv_setup_stepper_imap_address')
        imap_addr.send_keys('localhost')
        imap_port = self.by_name('srv_setup_stepper_imap_port')
        imap_port.clear()
        imap_port.send_keys(143)
        reply_to = self.by_name('srv_setup_stepper_profile_reply_to')
        reply_to.send_keys('test@localhost')
        signature = self.by_name('srv_setup_stepper_profile_signature')
        signature.send_keys('Test')
        elem = self.by_id('step_config_action_finish')
        self.driver.execute_script("arguments[0].scrollIntoView({behavior: 'instant'})", elem)
        WebDriverWait(self.driver, 60).until(EC.element_to_be_clickable(elem))
        elem.click()
        wait = WebDriverWait(self.driver, 30)
        element = wait.until(EC.visibility_of_element_located((By.CLASS_NAME, "sys_messages")))
        sys_message_text = element.text
        sys_message_texts = sys_message_text.split('\n')
        assert any("Authentication failed" in text for text in sys_message_texts)

if __name__ == '__main__':

    print("SERVERS TEST")
    test_runner(ServersTest, [
        'load_servers_page',
        'server_stmp_and_imap_add',
    ])
