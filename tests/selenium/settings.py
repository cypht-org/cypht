#!/usr/bin/python

from base import WebTest, USER, PASS
from selenium.webdriver.common.by import By
from runner import test_runner
from selenium.webdriver.support.ui import Select


class SettingsHelpers(WebTest):

    def is_unchecked(self, name):
        selected = self.by_name(name).is_selected()
        print(f"The selected status of checkbox '{name}' is:", selected)
        assert selected == False

    def is_checked(self, name):
        assert self.by_name(name).is_selected() == True

    def toggle(self, name):
        self.by_name(name).click()

    def close_section(self, section):
        self.by_css('[data-target=".'+section+'"]').click()

    def save_settings(self):
        self.driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
        self.by_name('save_settings').click()
        self.wait_with_folder_list()
        self.safari_workaround()
        assert self.by_class('sys_messages').text == 'Settings updated'

    def settings_section(self, section):
        if not self.by_class('settings').is_displayed():
            self.by_css('[data-source=".settings"]').click()
        list_item = self.by_class('menu_settings')
        list_item.find_element(By.TAG_NAME, 'a').click()
        self.wait_with_folder_list()
        self.wait_for_navigation_to_complete()
        if not self.by_class(section).is_displayed():
            self.by_css('[data-target=".'+section+'"]').click()

    def checkbox_test(self, section, name, checked, mod=False):
        if mod and not self.mod_active(mod):
            return
        self.settings_section(section)
        if name == 'enable_keyboard_shortcuts':
            checked = self.by_name(name).is_selected()
        if checked:
            self.is_checked(name)
        else:
            self.is_unchecked(name)
        self.toggle(name)
        self.save_settings()
        if checked:
            self.is_unchecked(name)
        else:
            self.is_checked(name)

    def number_fld_test(self, section, name, current, new, mod=False):
        if mod and not self.mod_active(mod):
            return
        self.settings_section(section)
        assert int(self.by_name(name).get_attribute('value')) == current
        self.by_name(name).clear()
        self.by_name(name).send_keys(new)
        self.save_settings()
        assert int(self.by_name(name).get_attribute('value')) == new

    def dropdown_test(self, section, name, current, new, mod=False):
        if mod and not self.mod_active(mod):
            return
        self.settings_section(section)
        assert self.by_name(name).get_attribute('value') == current
        Select(self.by_name(name)).select_by_value(new)
        self.save_settings()
        assert self.by_name(name).get_attribute('value') == new


class SettingsTests(SettingsHelpers):

    def __init__(self):
        WebTest.__init__(self)
        self.login(USER, PASS)
        self.wait()

    def load_settings_page(self):
        self.by_css('[data-source=".settings"]').click()
        list_item = self.by_class('menu_settings')
        list_item.find_element(By.TAG_NAME, 'a').click()
        self.wait_with_folder_list()
        self.wait_for_navigation_to_complete()
        assert self.by_class('content_title').text == 'Site Settings'

    def list_style_test(self):
        self.dropdown_test('general_setting', 'list_style', 'email_style', 'news_style')

    def auto_bcc_test(self):
        self.checkbox_test('general_setting', 'smtp_auto_bcc', False, 'smtp')

    def keyboard_shortcuts_test(self):
        self.checkbox_test('general_setting', 'enable_keyboard_shortcuts', False, 'keyboard_shortcuts')

    def inline_message_test(self):
        self.checkbox_test('general_setting', 'inline_message', False, 'inline_message')

    def no_folder_icons_test(self):
        self.checkbox_test('general_setting', 'no_folder_icons', False)

    def mailto_handler_test(self):
        self.checkbox_test('general_setting', 'mailto_handler', False)

    def msg_list_icons_test(self):
        self.checkbox_test('general_setting', 'show_list_icons', True)

    def msg_part_icons_test(self):
        self.checkbox_test('general_setting', 'msg_part_icons', True)

    def simple_msg_parts_test(self):
        self.checkbox_test('general_setting', 'simple_msg_parts', False)

    def text_only_test(self):
        self.checkbox_test('general_setting', 'text_only', True)

    def disable_delete_prompt_test(self):
        self.checkbox_test('general_setting', 'disable_delete_prompt', False)

    def no_password_save_test(self):
        self.checkbox_test('general_setting', 'no_password_save', False)
        self.close_section('general_setting')

    def imap_per_page_test(self):
        self.number_fld_test('general_setting', 'imap_per_page', 20, 100, 'imap')

    def mail_format_test(self):
        self.dropdown_test('general_setting', 'smtp_compose_type', '0', '1', 'smtp')

    def theme_test(self):
        self.dropdown_test('general_setting', 'theme_setting', 'default', 'cosmo')

    def tz_test(self):
        self.dropdown_test('general_setting', 'timezone', 'UTC', 'Africa/Abidjan')

    def start_page_test(self):
        self.dropdown_test('general_setting', 'start_page', 'none', 'page=home')

    def unread_since_test(self):
        self.dropdown_test('unread_setting', 'unread_since', '-1 week', '-6 weeks')

    def unread_max_per_source_test(self):
        self.number_fld_test('unread_setting', 'unread_per_source', 20, 100)

    def unread_exclude_github_test(self):
        self.checkbox_test('unread_setting', 'unread_exclude_github', False, 'github')

    def unread_exclude_wp_test(self):
        self.checkbox_test('unread_setting', 'unread_exclude_wordpress', False, 'wordpress')

    def unread_exclude_feed_test(self):
        self.checkbox_test('unread_setting', 'unread_exclude_feeds', False, 'feeds')
        self.close_section('unread_setting')

    def flagged_since_test(self):
        self.dropdown_test('flagged_setting', 'flagged_since', '-1 week', '-6 weeks')

    def flagged_max_per_source_test(self):
        self.number_fld_test('flagged_setting', 'flagged_per_source', 20, 100)
        self.close_section('flagged_setting')

    def all_since_test(self):
        self.dropdown_test('all_setting', 'all_since', '-1 week', '-6 weeks')

    def all_max_per_source_test(self):
        self.number_fld_test('all_setting', 'all_per_source', 20, 100)
        self.close_section('all_setting')

    def all_email_since_test(self):
        self.dropdown_test('email_setting', 'all_email_since', '-1 week', '-6 weeks')

    def all_email_max_per_source_test(self):
        self.number_fld_test('email_setting', 'all_email_per_source', 20, 100)
        self.close_section('email_setting')

    def feeds_since_test(self):
        self.dropdown_test('feeds_setting', 'feed_since', '-1 week', '-6 weeks')

    def feeds_max_per_source_test(self):
        self.number_fld_test('feeds_setting', 'feed_limit', 20, 100)
        self.close_section('feeds_setting')

    def sent_since_test(self):
        self.dropdown_test('sent_setting', 'sent_since', '-1 week', '-6 weeks')

    def sent_max_per_source_test(self):
        self.number_fld_test('sent_setting', 'sent_per_source', 20, 100)
        self.close_section('sent_setting')

    def github_since_test(self):
        self.dropdown_test('github_all_setting', 'github_since', '-1 week', '-6 weeks', 'github')

    def github_max_per_source_test(self):
        self.number_fld_test('github_all_setting', 'github_limit', 20, 100, 'github')
        if self.mod_active('github'):
            self.close_section('github_all_setting')


if __name__ == '__main__':

    print("SETTINGS TESTS")
    test_runner(SettingsTests, [

        # general options
        'load_settings_page',
        'list_style_test',
        'start_page_test',
        'tz_test',
        'theme_test',
        'imap_per_page_test',
        'mail_format_test',
        'auto_bcc_test',
        'keyboard_shortcuts_test',
        'inline_message_test',
        'no_folder_icons_test',
        'msg_list_icons_test',
        'msg_part_icons_test',
        'simple_msg_parts_test',
        'text_only_test',
        'disable_delete_prompt_test',
        'no_password_save_test',

        # unread options
        'unread_since_test',
        'unread_max_per_source_test',
        'unread_exclude_github_test',
        'unread_exclude_wp_test',
        'unread_exclude_feed_test',

        # flagged options
        'flagged_since_test',
        'flagged_max_per_source_test',

        # everything options
        'all_since_test',
        'all_max_per_source_test',

        # all E-mail options
        'all_email_since_test',
        'all_email_max_per_source_test',

        # feed options
        'feeds_since_test',
        'feeds_max_per_source_test',

        # sent options
        'sent_since_test',
        'sent_max_per_source_test',

        # github options
        'github_since_test',
        'github_max_per_source_test',

        # exit
        'logout'
    ])
