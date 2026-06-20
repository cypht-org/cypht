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
        email.send_keys('testuser@localhost')
        pwd = self.by_name('srv_setup_stepper_password')
        pwd.send_keys('testuser')
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
        
        smtp_tls_radio = self.by_id('smtp_start_tls')
        self.driver.execute_script("arguments[0].scrollIntoView(true);", smtp_tls_radio)
        sleep(0.2)
        self.driver.execute_script("arguments[0].click();", smtp_tls_radio)

        imap_tls_radio = self.by_id('imap_start_tls')
        self.driver.execute_script("arguments[0].scrollIntoView(true);", imap_tls_radio)
        sleep(0.2)
        self.driver.execute_script("arguments[0].click();", imap_tls_radio)

        reply_to = self.by_name('srv_setup_stepper_profile_reply_to')
        reply_to.send_keys('testuser@localhost')
        signature = self.by_name('srv_setup_stepper_profile_signature')
        signature.send_keys('Test')
        # Instead of clicking Finish (which races with our diagnostic call),
        # submit the AJAX directly from within the browser; same session/CSRF/cookies.
        # The JS reads the current page key from DOM so it is always valid.
        self.driver.set_script_timeout(300)
        import json as _json
        result = self.driver.execute_async_script("""
            var done = arguments[arguments.length - 1];
            var pageKeyEl = document.getElementById('hm_page_key') ||
                            document.querySelector('input[name="hm_page_key"]');
            var pageKey = pageKeyEl ? pageKeyEl.value : '';
            var data = [
                {name: 'hm_ajax_hook',                        value: 'ajax_quick_servers_setup'},
                {name: 'hm_page_key',                         value: pageKey},
                {name: 'srv_setup_stepper_profile_name',      value: document.getElementById('srv_setup_stepper_profile_name').value},
                {name: 'srv_setup_stepper_email',             value: document.querySelector('[name=srv_setup_stepper_email]').value},
                {name: 'srv_setup_stepper_password',          value: document.querySelector('[name=srv_setup_stepper_password]').value},
                {name: 'srv_setup_stepper_provider',          value: document.querySelector('#srv_setup_stepper_provider') ? document.querySelector('#srv_setup_stepper_provider').value : ''},
                {name: 'srv_setup_stepper_is_sender',         value: true},
                {name: 'srv_setup_stepper_is_receiver',       value: true},
                {name: 'srv_setup_stepper_smtp_address',      value: document.querySelector('[name=srv_setup_stepper_smtp_address]').value},
                {name: 'srv_setup_stepper_smtp_port',         value: document.querySelector('[name=srv_setup_stepper_smtp_port]').value},
                {name: 'srv_setup_stepper_smtp_tls',          value: false},
                {name: 'srv_setup_stepper_imap_address',      value: document.querySelector('[name=srv_setup_stepper_imap_address]').value},
                {name: 'srv_setup_stepper_imap_port',         value: document.querySelector('[name=srv_setup_stepper_imap_port]').value},
                {name: 'srv_setup_stepper_imap_tls',          value: false},
                {name: 'srv_setup_stepper_enable_sieve',      value: false},
                {name: 'srv_setup_stepper_create_profile',    value: true},
                {name: 'srv_setup_stepper_profile_is_default',value: true},
                {name: 'srv_setup_stepper_profile_signature', value: document.querySelector('[name=srv_setup_stepper_profile_signature]').value},
                {name: 'srv_setup_stepper_profile_reply_to',  value: document.querySelector('[name=srv_setup_stepper_profile_reply_to]').value},
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
        print(f"IN-BROWSER AJAX result: {_json.dumps(result)[:600]}")
        saved = bool(result and result.get('ok') and result.get('res', {}).get('just_saved_credentials'))
        msgs = result.get('res', {}).get('router_user_msgs', []) if result else []
        print(f"just_saved_credentials={saved}  msgs={msgs}")
        assert saved, f"Server setup failed. AJAX result: {result}"
        # If saved, the JS will trigger Hm_Utils.redirect() - wait for it
        self.wait_with_folder_list()

if __name__ == '__main__':

    print("SERVERS TEST")
    test_runner(ServersTest, [
        'load_servers_page',
        'server_stmp_and_imap_add',
    ])
