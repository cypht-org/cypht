#!/usr/bin/python

# To run these tests you must copy one of the example files to "creds.py" in
# this directory, and edit it for your environment. There are 2 example files
# in this directory:
#
# remote_creds.example.py:  Configure the Selenium tests to run with BrowserStack
# local_creds.example.py:   Configure the Selenium tests to run locally

import re
from creds import SITE_URL, USER, PASS, get_driver
from selenium.webdriver.common.by import By
from selenium.common import exceptions
from contextlib import contextmanager
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as exp_cond
import glob
import subprocess
import json

class WebTest:

    driver = None

    def __init__(self, cap=None):
        self.read_ini()
        self.driver = get_driver(cap)
        # Change the window size to make sure all elements are visible
        current_size = self.driver.get_window_size()
        new_height = 5000
        self.driver.set_window_size(current_size['width'], new_height)
        self.browser = False
        if 'browserName' in self.driver.capabilities:
            self.browser = self.driver.capabilities['browserName'].lower()
        self.load()

    def read_ini(self):
        self.modules = []
        self.servers = 1
        self.auth_type = ''
        result = subprocess.run(['php', 'get_config.php'], stdout=subprocess.PIPE)
        config_dict = json.loads(result.stdout.decode())
        if 'modules' in config_dict and isinstance(config_dict['modules'], list):
            self.modules += config_dict['modules']
        if 'auth_type' in config_dict:
            self.auth_type = config_dict['auth_type']

    def load(self):
        print(" - loading site")
        self.go(SITE_URL)
        try:
            self.driver.maximize_window()
        except Exception:
            print(" - Could not maximize browser :(")
        if self.browser == 'safari':
            try:
                self.driver.set_window_size(1920,1080)
            except Exception:
                print(" - Could not maximize Safari")

    def mod_active(self, name):
        # debug self.modules
        echo = " - modules enabled: "
        for mod in self.modules:
            echo += mod + " "
        print(echo) 
        if name in self.modules:
            return True
        print(" - module not enabled: %s" % name)
        return False
    
    def single_server(self):
        if self.servers <= 1:
            return True
        print(" - servers account: %s" % self.servers)
        return False

    def go(self, url):
        self.driver.get(url)

    def login(self, user, password):
        print(" - logging in")
        user_el = self.by_name('username')
        pass_el = self.by_name('password')
        user_el.send_keys(user)
        pass_el.send_keys(password)
        self.by_css('input[value=Login]').click()

    def change_val(self, el, val):
        self.driver.execute_script('''
            var e=arguments[0]; var v=arguments[1]; e.value=v;''',
            el, val)

    def confirm_alert(self):
        WebDriverWait(self.driver, 3).until(exp_cond.alert_is_present(), 'timed out')
        alert = self.driver.switch_to.alert
        alert.accept()
        
    def logout_no_save(self):
        print(" - logging out")
        self.driver.find_element(By.CLASS_NAME, 'logout_link').click()
        logout = self.by_id('logout_without_saving').click()

    def logout(self):
        print(" - logging out")
        self.driver.find_element(By.CLASS_NAME, 'logout_link').click()

    def end(self):
        self.driver.quit()

    def by_id(self, el_id):
        print(" - finding element by id {0}".format(el_id))
        return self.driver.find_element(By.ID, el_id)

    def by_tag(self, name):
        print(" - finding element by tag name {0}".format(name))
        return self.driver.find_element(By.TAG_NAME, name)

    def by_name(self, name):
        print(" - finding element by name {0}".format(name))
        return self.driver.find_element(By.NAME, name)

    def by_css(self, selector):
        print(" - finding element by selector {0}".format(selector))
        return self.driver.find_element(By.CSS_SELECTOR, selector)

    def by_class(self, class_name):
        print(" - finding element by class {0}".format(class_name))
        return self.driver.find_element(By.CLASS_NAME, class_name)

    def wait_for_element_by_class(self, class_name, timeout=10):
        """Wait for an element to be present and visible by class name"""
        print(" - waiting for element by class {0}".format(class_name))
        WebDriverWait(self.driver, timeout).until(
            exp_cond.presence_of_element_located((By.CLASS_NAME, class_name))
        )
        element = self.driver.find_element(By.CLASS_NAME, class_name)
        WebDriverWait(self.driver, timeout).until(
            exp_cond.visibility_of(element)
        )
        return element

    def by_xpath(self, element_xpath):
        print(" - finding element by xpath {0}".format(element_xpath))
        return self.driver.find_element(By.XPATH, element_xpath)
    
    def element_exists(self, class_name):
        print(" - checking if element exists by class {0}".format(class_name))
        try:
            self.by_class(class_name)
            return True
        except Exception:
            return False

    def wait(self, el_type=By.TAG_NAME, el_value="body", timeout=60):
        print(" - waiting for page by {0}: {1} ...".format(el_type, el_value))
        element = WebDriverWait(self.driver, timeout).until(
            exp_cond.presence_of_element_located((el_type, el_value)))

    def wait_on_class(self, class_name, timeout=30):
        self.wait(By.CLASS_NAME, class_name)

    def wait_with_folder_list(self):
        self.wait(By.CLASS_NAME, "main")

    def wait_on_sys_message(self, timeout=30):
        wait = WebDriverWait(self.driver, timeout)
        element = wait.until(wait_for_non_empty_text((By.CLASS_NAME, "sys_messages"))
)
        
    def wait_for_navigation_to_complete(self, timeout=30):
        print(" - waiting for the navigation to complete...")
        import time
        
        try:
            # First, check if we have navigation state attributes (new method)
            try:
                # Wait for navigation to start (state changes to 'loading')
                WebDriverWait(self.driver, 5).until(
                    lambda driver: driver.execute_script(
                        'return document.body.getAttribute("data-navigation-state") === "loading"'
                    )
                )
                print(" - navigation start detected")
                
                # Then wait for navigation to complete (state changes to 'complete')
                WebDriverWait(self.driver, timeout).until(
                    lambda driver: driver.execute_script(
                        'return document.body.getAttribute("data-navigation-state") === "complete"'
                    )
                )
                print(" - navigation completion detected via state attribute")
                
                # Small delay to ensure DOM is settled
                time.sleep(0.5)
                return
                
            except Exception as state_error:
                print(f" - navigation state monitoring failed: {state_error}, trying fallback methods")
                
                # Fallback 1: Try to detect fetch requests
                try:
                    get_current_navigations_request_entries_length = lambda: self.driver.execute_script(
                        'return window.performance.getEntriesByType("resource").filter((r) => r.initiatorType === "fetch").length'
                    )
                    navigation_length = get_current_navigations_request_entries_length()
                    
                    WebDriverWait(self.driver, min(timeout, 10)).until(
                        lambda driver: get_current_navigations_request_entries_length() > navigation_length
                    )
                    print(" - navigation detected via fetch requests")
                    
                    time.sleep(0.5)
                    return
                    
                except Exception as fetch_error:
                    print(f" - fetch monitoring failed: {fetch_error}, trying final fallback")
                    
                    # Fallback 2: Check for loading indicators and main element
                    try:
                        WebDriverWait(self.driver, 5).until_not(
                            lambda driver: len(driver.find_elements(By.ID, "loading_indicator")) > 0
                        )
                        print(" - loading indicator disappeared")
                    except:
                        pass
                    
                    try:
                        WebDriverWait(self.driver, min(timeout, 15)).until(
                            exp_cond.presence_of_element_located((By.TAG_NAME, "main"))
                        )
                        print(" - main element present")
                        
                        time.sleep(1)
                        
                    except Exception as main_error:
                        print(f" - all navigation waiting methods failed: {state_error}, {fetch_error}, {main_error}")
                        time.sleep(2)
                        
        except Exception as e:
            print(f" - unexpected error in navigation waiting: {e}")
            time.sleep(2)

    def wait_for_settings_to_expand(self):
        print(" - waiting for the settings section to expand...")
        try:
            # First try to find the settings button and click it
            settings_button = self.by_css('[data-bs-target=".settings"]')
            if settings_button.is_displayed():
                # Check if settings are already expanded
                try:
                    settings_div = self.by_class('settings')
                    if settings_div.is_displayed():
                        print(" - settings already expanded")
                        return
                except:
                    pass
                
                # Click to expand
                settings_button.click()
                
                # Wait for the settings to be displayed
                try:
                    WebDriverWait(self.driver, 10).until(lambda x: self.by_class('settings').is_displayed())
                    print(" - settings expanded successfully")
                except:
                    print(" - settings expansion timeout, continuing anyway")
            else:
                print(" - settings button not visible, skipping expansion")
        except Exception as e:
            print(f" - settings expansion failed: {e}")
            # Continue anyway, the settings might already be expanded

    def click_when_clickable(self, el):
        print(" - waiting for element to be clickable")
        try:
            # Scroll element into view
            self.driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", el)
            
            # Wait for element to be clickable
            WebDriverWait(self.driver, 10).until(
                exp_cond.element_to_be_clickable(el)
            )
            
            # Try regular click first
            try:
                el.click()
                return
            except Exception as click_error:
                print(f" - regular click failed: {click_error}")
                print(" - trying JavaScript click as fallback")
                # Use JavaScript click as fallback
                self.driver.execute_script("arguments[0].click();", el)
                
        except Exception as e:
            print(f" - click_when_clickable failed: {e}")
            # Final fallback: try JavaScript click without waiting
            try:
                self.driver.execute_script("arguments[0].click();", el)
            except Exception as js_error:
                print(f" - JavaScript click also failed: {js_error}")
                raise e

    def safe_click(self, element):
        """Safely click an element with retry logic"""
        print(" - safely clicking element")
        max_attempts = 3
        for attempt in range(max_attempts):
            try:
                # Scroll element into view
                self.driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", element)
                # Wait a moment for any animations
                import time
                time.sleep(0.5)
                # Try to click
                element.click()
                return
            except Exception as e:
                if attempt == max_attempts - 1:
                    raise e
                print(f" - click attempt {attempt + 1} failed, retrying...")
                time.sleep(1)

    def safari_workaround(self, timeout=1):
        if self.browser == 'safari':
            print(" - waiting {0} extra second for Safari".format(timeout))
            self.driver.implicitly_wait(timeout)

class wait_for_non_empty_text(object):
    def __init__(self, locator):
        self.locator = locator

    def __call__(self, driver):
        try:
            element_text = exp_cond._find_element(driver, self.locator).text.strip()
            print(element_text)
            return element_text != ""
        except exceptions.StaleElementReferenceException:
            return False
