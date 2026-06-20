import json
from base import WebTest, USER, PASS
from selenium.webdriver.common.by import By
from runner import test_runner
from settings import SettingsHelpers
from selenium.webdriver.support.ui import Select, WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

class ProfileTest(SettingsHelpers):

    def __init__(self):
        WebTest.__init__(self)
        self.login(USER, PASS)
        self.wait_with_folder_list()

    def setup_servers(self):
        """Add IMAP/SMTP servers to the current session via in-browser AJAX.
        Called before add_profile so the profile dropdowns have options.
        Servers only need to exist in the current PHP session, no DB persistence required."""
        self.load()
        self.wait_with_folder_list()
        self.driver.set_script_timeout(120)
        result = self.driver.execute_async_script("""
            var done = arguments[arguments.length - 1];
            var pageKeyEl = document.getElementById('hm_page_key') ||
                            document.querySelector('input[name="hm_page_key"]');
            var pageKey = pageKeyEl ? pageKeyEl.value : '';
            var data = [
                {name: 'hm_ajax_hook',                        value: 'ajax_quick_servers_setup'},
                {name: 'hm_page_key',                         value: pageKey},
                {name: 'srv_setup_stepper_profile_name',      value: 'Test'},
                {name: 'srv_setup_stepper_email',             value: 'testuser@localhost'},
                {name: 'srv_setup_stepper_password',          value: 'testuser'},
                {name: 'srv_setup_stepper_provider',          value: ''},
                {name: 'srv_setup_stepper_is_sender',         value: true},
                {name: 'srv_setup_stepper_is_receiver',       value: true},
                {name: 'srv_setup_stepper_smtp_address',      value: 'localhost'},
                {name: 'srv_setup_stepper_smtp_port',         value: '25'},
                {name: 'srv_setup_stepper_smtp_tls',          value: false},
                {name: 'srv_setup_stepper_imap_address',      value: 'localhost'},
                {name: 'srv_setup_stepper_imap_port',         value: '143'},
                {name: 'srv_setup_stepper_imap_tls',          value: false},
                {name: 'srv_setup_stepper_enable_sieve',      value: false},
                {name: 'srv_setup_stepper_create_profile',    value: false},
                {name: 'srv_setup_stepper_profile_is_default',value: false},
                {name: 'srv_setup_stepper_profile_signature', value: ''},
                {name: 'srv_setup_stepper_profile_reply_to',  value: 'testuser@localhost'},
                {name: 'srv_setup_stepper_imap_sieve_host',   value: ''},
                {name: 'srv_setup_stepper_imap_sieve_mode_tls', value: false},
                {name: 'srv_setup_stepper_only_jmap',         value: false},
                {name: 'srv_setup_stepper_imap_hide_from_c_page', value: false},
                {name: 'srv_setup_stepper_jmap_address',      value: ''},
                {name: 'srv_setup_stepper_imap_server_id',    value: ''},
                {name: 'srv_setup_stepper_smtp_server_id',    value: ''},
            ];
            Hm_Ajax.request(data, function(res) {
                done({ok: true, res: res});
            }, null, true, undefined, function(err) {
                done({ok: false, err: String(err)});
            });
        """)
        saved = bool(result and result.get('ok') and result.get('res', {}).get('just_saved_credentials'))
        print(f"setup_servers: just_saved_credentials={saved}  result={json.dumps(result)[:200]}")
        assert saved, f"Failed to add servers for profile test: {result}"
        self.wait_with_folder_list()

    def load_profile_page(self):
        self.load()
        self.wait()
        self.wait_with_folder_list()
        self.by_css('[data-bs-target=".settings"]').click()
        WebDriverWait(self.driver, 20).until(lambda x: self.by_class('menu_profiles').is_displayed())
        list_item = self.by_class('menu_profiles')
        self.click_when_clickable(list_item.find_element(By.TAG_NAME, 'a'))
        self.wait_with_folder_list()
        self.wait_for_navigation_to_complete()
        assert self.by_class('profile_content_title').text == 'Profiles'

    def add_profile(self):
        self.by_class('add_profile').click()
        name = self.by_name('profile_name')
        name.send_keys('Test')
        addr = self.by_name('profile_address')
        addr.send_keys('test@test.com')
        reply = self.by_name('profile_replyto')
        reply.send_keys('test@test.com')
        profile_imap = self.by_name('profile_imap')
        profile_imap_value = profile_imap.get_attribute('value')
        print(f"Imap profile server Found: '{profile_imap_value}'")
        profile_smtp = self.by_name('profile_smtp')
        profile_smtp_value = profile_smtp.get_attribute('value')
        print(f"Smtp profile server Found: '{profile_smtp_value}'")
        sig = self.by_name('profile_sig')
        sig.send_keys('foo')
        rmk = self.by_name('profile_rmk')
        rmk.send_keys('Test selenium')
        self.by_name('profile_default').click()
        self.by_class('submit_profile').click()
        self.wait_with_folder_list()
        WebDriverWait(self.driver, 15).until(
            EC.text_to_be_present_in_element((By.CLASS_NAME, 'table-striped'), 'test@test.com')
        )
        assert 'test@test.com' in self.by_class('table-striped').text

    def edit_profile(self):
        # Use the last edit link in the list (the profile just added)
        edit_links = self.driver.find_elements(By.CSS_SELECTOR, 'a[href*="profile_id"]')
        self.click_when_clickable(edit_links[-1])
        self.wait_for_navigation_to_complete()
        name = self.by_name('profile_name')
        name.clear()
        name.send_keys('New Name')
        self.by_class('profile_update').click()
        self.wait_with_folder_list()
        WebDriverWait(self.driver, 15).until(
            EC.text_to_be_present_in_element((By.TAG_NAME, 'body'), 'New Name')
        )

    def del_profile(self):
        # Navigate to the edit form of the last profile then use the Delete button
        edit_links = self.driver.find_elements(By.CSS_SELECTOR, 'a[href*="profile_id"]')
        self.click_when_clickable(edit_links[-1])
        self.wait_for_navigation_to_complete()
        self.by_name('profile_delete').click()
        self.confirm_alert()
        self.wait_on_sys_message()
        assert 'Profile Deleted' in self.by_class('sys_messages').text


if __name__ == '__main__':

    print("PROFILE TEST")
    test_runner(ProfileTest, [
        'setup_servers',
        'load_profile_page',
        'add_profile',
        'edit_profile',
        'del_profile'
    ])
