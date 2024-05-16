<?php

/*
   language       ：Chinese_simplified
   languageNative ：简体中文（中国）
   translators    ：PJ568

   ## 简体中文翻译规则

   1. 盘古之白：汉字两侧或汉字符号左侧与数字和其他非方块文字和符号之间留有空格；
   2. 短小精悍：翻译从简；
   3. 汉语语法：词组和语句的语法顺序需转换为标准汉语语法，非完整参考：“定语（修饰主语）、主语、状语、谓语、补语、定语（修饰宾语）、宾语”；
   4. 以礼待人：统一称谓，使用敬辞。己所欲，施于人；
   5. 面向用户：翻译时需考虑到翻译后在用户见面上的显示状态；
   6. 适当留原：不确定的翻译请勿翻译；常用外语词汇或特定领域专有名词无需翻译（ false ）。
*/

if (!defined('DEBUG_MODE')) { die(); }

return array(
    'interface_lang' => 'zh-Hans',
    'interface_direction' => 'ltr',

    'Main' => '主要',
    'Username' => '用户名',
    'Password' => '密码',
    'Notices' => '通知',
    'IMAP Server' => 'IMAP 服务器',
    'TLS' => false,
    'IMAP Summary' => 'IMAP 摘要',
    'Home' => '首页',
    'Unread' => '未读',
    'Servers' => '服务器',
    'Settings' => '设置',
    'Logout' => '注销',
    '%d configured' => '已配置 %d',
    'Snooze' => '设置提醒',
    'Today' => '今日',
    'You Need to have Javascript enabled to use HM3, sorry about that!' => '抱歉。启用 JavaScript 后方可使用 HM3 。',
    'Unsaved changes will be lost! Re-enter your password to save and exit.' => '任何未保存的更改都将丢失！若欲保存并退出，请再次输入密码。',
    'Save and Logout' => '保存并注销',
    'Just Logout' => '注销不保存',
    'Cancel' => '取消',
    'General' => '通用设置',
    'Language' => '语言',
    'Theme' => '主题',
    'Timezone' => '时区',
    'Message list style' => '信息列表格式',
    'Email' => '电子邮件',
    'News' => '新闻',
    'Show messages received since' => '显示从此时间后的信息',
    'Last 7 days' => '最近 7 天',
    'Last 2 weeks' => '最近 2 周',
    'Last 4 weeks' => '最近 4 周',
    'Last 6 weeks' => '最近 6 周',
    'Last 6 months' => '最近 6 个月',
    'Last year' => '近一年',
    'Max messages per source' => '显示信息数量上限',
    'Attachment Chunks' => '附件占用',
    'Flagged' => '已标记',
    'Everything' => '所有',
    'You must enter your password to save your settings on the server' => '若欲在服务器上保存设置，请输入密码',
    'Message_list' => '信息列表',
    'Read' => '已读',
    'Flag' => '标记',
    'Unflag' => '取消标记',
    'Delete' => '删除',
    'sources@%d each' => '账户@%d ',
    'total' => '总计',
    'Source' => '账户',
    'From' => '发件人',
    'Subject' => '主题',
    'Sent Date' => '发送日期',
    'IMAP Servers' => 'IMAP 服务器',
    'Add an IMAP Server' => '添加 IMAP 服务器',
    'Add Local' => '添加本地联系人',
    'Account name' => '账户名称',
    'Server address' => '服务器地址',
    'IMAP server address' => 'IMAP 服务器地址',
    'IMAP port' => 'IMAP 端口',
    'Port' => '端口',
    'Use TLS' => '使用 TLS',
    'Add' => '添加',
    '[saved]' => '[保存]',
    'IMAP password' => 'IMAP 密码',
    'Test' => '测试',
    'Forget' => '忘记',
    'IMAP username' => 'IMAP 用户名',
    'Feeds' => false,
    'Add an RSS/ATOM Feed' => '添加 RSS/ATOM 订阅源',
    'Feed name' => 'Feed名称',
    'Site address or feed URL' => '网址或 Feed 地址',
    'Compose' => '创作',
    'To' => '发送到',
    'Send' => '发送',
    'SMTP Servers' => 'SMTP 服务器',
    'Add an SMTP Server' => '添加 SMTP 服务器',
    'SMTP account name' => 'SMTP 账号名称',
    'SMTP server address' => 'SMTP 服务器地址',
    'SMTP port' => 'SMTP 端口',
    'SMTP username' => 'SMTP 用户名',
    'SMTP password' => 'SMTP 密码',
    'Search' => '搜索',
    'Entire message' => '完整信息',
    'Message body' => '信息正文',
    '[No From]' => '[无发信人]',
    'Allowed idle time until logout' => '空闲多久后注销',
    '1 Hour' => '1 小时',
    '2 Hours' => '2 小时',
    '3 Hours' => '3 小时',
    '1 Day' => '1 天',
    'Forever' => '永不注销',
    'New password' => '新密码',
    'New password again' => '确认新密码',
    'Exclude unread feed items' => '排除未读的 Feed',
    'Feed Settings' => 'Feed设定',
    'Show feed items received since' => '显示从此时间后的 Feed',
    'Max feed items to display' => 'Feed 显示条数',
    'Max messages to display' => '邮件显示条数',
    'Contacts' => '联系人',
    'Profiles' => '简介',
    'Help' => '帮助',
    'Development' => '开发',
    'Developer Documentation' => '开发者文档',
    'Bug report' => '错误反馈',
    'Report a bug' => '反馈错误',
    'If you found a bug or want a feature we want to hear from you!' => '如果您发现错误或希望我添加功能，请联系我！',
    'Settings saved' => '设置已保存',
    'Site Settings' => '网站设置',
    'Site' => '网站',
    'Bugs' => '错误',
    'All' => '全部',
    'Load Feed' => '加载 Feed',
    'Toggle folder' => '切换文件夹',
    'Collapse' => '崩溃',
    '[reload]' => '[重载]',
    'Type' => '类型',
    'Name' => '名称',
    'Status' => '状态',
    'FEED' => false,
    'Authenticated' => '已验证',
    'Connected' => '已连接',
    'Login' => '登录',
    'HM3' => 'HM3',
    'Create' => '创建',
    'Invalid username or password' => '用户名或密码不正确',
    'Update' => '更新',
    'Save' => '保存',
    'Restore Defaults' => '重置为默认',
    'Sources' => '来源',
    'Configure' => '配置',
    'Refresh' => '刷新',
    'Search Terms' => '搜索关键词',
    'Search Field' => '搜索字段',
    'Search Since' => '搜索时间',
    'link' => '链接',
    'Flags' => '标记',
    'Saved user data on logout' => '保存并注销',
    'Session destroyed on logout' => '销毁会话并注销',
    'Cound not add server: Connection refused' => '无法添加服务器：Connection refused',
    'small' => '小',
    'Forward' => '转发',
    'raw' => '显示消息来源',
    'All Email' => '所有邮件',
    'White Bread (Default)' => '白面包（默认）',
    'Boring Blues' => '平淡蓝',
    'Dark But Not Too Dark' => '黑但不完全黑',
    'More Gray Than White Bread' => '比”白面包“灰一点',
    'Poison Mist' => '毒雾',
    'A Bunch Of Browns' => '一坨棕',
    'VT100' => false,
    'Hacker News' => false,
    'Calendar' => '日历',
    'More info' => '更多信息',
    'Import from CSV file' => '从 CSV 文件导入',
    'Sunday' => '礼拜日',
    'Monday' => '星期一',
    'Tuesday' => '星期二',
    'Wednesday' => '星期三',
    'Thursday' => '星期四',
    'Friday' => '星期五',
    'Saturday' => '星期六',
    'Welcome to Cypht' => '欢迎来到 Cypht',
    'Add a popular E-mail source quickly and easily' => '快速轻松地添加常用电子邮件源',
    'Add an E-mail Account' => '添加电子邮件帐户',
    'Manage' => '管理',
    'You don\'t have any %s sources' => '你还没有任何 %s 账户',
    'You have %d %s source' => '有 %d %s 个账户',
    'You have %d %s sources' => '有 %d %s 个账户',
    'Cypht is a webmail program. You can use it to access your E-mail accounts from any service that offers IMAP, or SMTP access - which most do.' => 'Cypht 是一款网络邮件程序。它可用于访问提供 IMAP 和 SMTP 服务（大部分电子邮箱都支持）的电子邮件账户。',
    'Quickly add an account from popular E-mail providers. To manually configure an account, use the IMAP/SMTP sections below.' => '立即为常用电子邮件服务添加账户。若欲手动设置账户，请使用下面的 IMAP/SMTP 部分。',
    'Select an E-mail provider' => '选择电子邮件服务',
    'AOL' => false,
    'Fastmail' => false,
    'GMX' => false,
    'Gmail' => false,
    'Inbox.com' => false,
    'Mail.com' => false,
    'Outlook.com' => false,
    'Yahoo' => false,
    'Yandex' => false,
    'Zoho' => 'Zoho',
    'Your E-mail address' => '您的电子邮件地址',
    'Account Name [optional]' => '账户名 [可选]',
    'Next' => '下一个',
    'WordPress.com Connect' => 'WordPress.com 连接',
    'Notifications' => '通知',
    'Freshly Pressed' => false,
    'Trending' => '趋势',
    'Latest' => '最新',
    'WordPress' => false,
    'Info' => '服务器信息',
    'Dev' => '开发',
    'Score' => false,
    'Comments' => 'Comments',
    'So alone' => '什么都没有',
    'Unsaved Changes' => '更改未保存',
    'Enable' => '启用',
    'Development Updates' => '开发状态',
    'Author' => '作者',
    'Repository' => '存储库',
    'HTML' => false,
    'Outbound mail format' => '对外发送电子邮件的格式',
    'Plain text' => '纯文本',
    'Create Account' => '创建账户',
    'Add this folder to combined pages' => '将此文件夹添加到集成页面',
    'Remove this folder from combined pages' => '从整合页面移除该文件夹',
    'Remove' => '删除',
    'Sender' => '发送',
    'On %s %s said' => false,
    'Id' => false,
    'WordPress.com Notifications' => 'WordPress.com 通知',
    'IMAP' => false,
    'INBOX' => '收件箱',
    'Message' => '留言',
    'Reset' => '重置',
    '%s started watching this repo' => '已开始关注 %s 存储库',
    '%s repository created' => '已创建 %s 存储库',
    '%d commits: ' => false,
    '%d commit: ' => false,
    'Site settings updated' => '网站设置已更新',
    'Configuration Map' => '设置列表',
    'Default' => '默认',
    'Down' => '下',
    'Message Sent' => '已发送邮件',
    'Oauth2 access token updated' => 'OAuth2 访问令牌已更新',
    'No changes need to be saved' => '无需保存更改',
    'Cc' => false,
    'Github Connect' => 'GitHub 连接',
    'Hide From Combined Pages' => '从集成页面隐藏',
    'Save Settings' => '保存设置',
    'Settings are not saved permanently on the server unless you explicitly allow it. If you don\'t save your settings, any changes made since you last logged in will be deleted when your session expires or you logout. You must re-enter your password for security purposes to save your settings permanently.' => '得到您的批准前，所有设置不会上传并保存到服务器。若不保存设置，之前的任何更改都将在会话结束或注销时被删除。出于安全考虑，若欲保存设置，请输入密码。',
    'Add a Repository' => '添加存储库',
    'Disconnect' => '断开连接',
    'Already connected' => '已连接',
    '%d second' => '%d 秒',
    '%d seconds' => '%d 秒',
    '%d minute' => '%d 分',
    '%d minutes' => '%d 分',
    '%d hour' => '%d 时',
    '%d hours' => '%d 时',
    '%d day' => '%d 天',
    '%d days' => '%d 天',
    '%d week' => '%d 周',
    '%d weeks' => '%d 周',
    '%d month' => '%d 月',
    '%d months' => '%d 月',
    '%d year' => '%d 年',
    '%d years' => '%d 年',
    'English' => '英語',
    'German' => '德语',
    'Italian' => '意大利语',
    'Attachment' => '附件',
    'Tags' => '标签',
    'Light Blue' => '亮蓝',
    'Server' => '服务器',
    'Display Name' => '显示名称',
    'Reply-to' => '回复给',
    'SMTP Server' => 'SMTP服务器',
    'Signature' => '签名',
    'Edit' => '编辑',
    'No' => '否',
    'Yes' => '是',
    'Sign' => '签名',
    'Password Again' => '确认密码',
    'Change Password' => '更改密码',
    'Current password' => '当前密码',
    'Show messages inline' => '内联显示信息',
    'Always BCC sending address' => '总是通过 BCC 发送地址',
    'Exclude unread Github notices' => '排除 GitHub 上的未读通知',
    'Exclude unread WordPress notices' => '排除 WordPress 未读通知',
    'Last 5 years' => '最近 5 年',
    'Hide' => '隐藏',
    'Unhide' => '取消隐藏',
    'Owner' => '所有者',
    'Repositories' => '储存库',
    'NASA APIs' => false,
    'Enter your API key' => '输入您的 API 密钥',
    'iCloud' => false,
    'Connect' => '连接',
    'Your timezone is set to %s' => '您的时区设置为 %s',
    'You don\'t have any data sources assigned to this page.' => '未为该页面分配一个账户',
    'Add some' => '添加内容',
    '%s forked %s' => '%s 已复刻（分叉） %s',
    'All Feeds' => '所有 Feed',
    'Answered' => '已回答',
    'Astronomy Picture of the Day' => '今日天文图片',
    'Previous' => '上一页',
    'Picture of the day' => '今日图片',
    'Nasa apod' => false,
    'History' => '历史',
    'Message history' => '消息历史',
    'Bcc' => '抄送',
    'Attach' => '附件',
    'Sent' => '已发送',
    'E-mail Address' => '电子邮件地址',
    'Full Name' => '全名',
    'Telephone Number' => '电话号码',
    'Send To' => '发送到',
    'local' => '本地',
    'Add Local Contact' => '添加到本地联系人',
    'Delete search' => '删除搜索',
    'Delete saved search' => '删除已保存的搜索',
    'Save search' => '保存搜索',
    'Github repo' => 'GitHub 仓库',
    'Github' => false,
    'Accounts' => '账户',
    'NASA' => false,
    'You have unsaved changes' => '有未保存的变化',
    'APOD' => false,
    '%s services are not enabled for this site. Sorry about that!' => '抱歉。本网站未启用 %s 服务。',
    'January, %s' => '一月、%s',
    'February, %s' => '二月、%s',
    'March, %s' => '三月、%s',
    'April, %s' => '四月、%s',
    'May, %s' => '五月、%s',
    'June, %s' => '六月、%s',
    'July, %s' => '七月、%s',
    'August, %s' => '八月、%s',
    'September, %s' => '九月、%s',
    'October, %s' => '十月、%s',
    'November, %s' => '十一月、%s',
    'December, %s' => '十二月、%s',
    'All headers' => '所有标题',
    'Small headers' => '副标题',
    'Contact Deleted' => '已删除联系人',
    'Contact Updated' => '已更新联系人',
    'Contact Added' => '已添加联系人',
    'Update Local Contact' => '更新本地联系人',
    'Add an Event' => '其他活动',
    'Time' => '时间',
    'Repeat' => '重复',
    'Title' => '标题',
    'Detail' => '详情',
    'Event Created' => '创建活动',
    'Repeats every %s' => '每天重复 %s 次',
    'Event Deleted' => '已删除对象',
    'Copy' => '复制',
    'Move' => '移动',
    'Move to ...' => '移动到……',
    'Copy to ...' => '复制到……',
    'Removed non-IMAP messages from selection. They cannot be moved or copied' => '所选邮件中的非 IMAP 邮件已被删除。这些邮件无法移动或复制',
    'Messages moved' => '已移动邮件',
    'Messages copied' => '已复制邮件',
    'Some messages moved (only IMAP message types can be moved)' => '部分邮件已移动（仅 IMAP 邮件可移动）',
    'Some messages copied (only IMAP message types can be copied)' => '部分邮件已复制（仅 IMAP 邮件可复制）',
    'Unable to move/copy selected messages' => '无法移动或复制所选邮件',
    'Folders' => '文件夹',
    'Select an IMAP server' => '选择 IMAP 服务器',
    'You must select an IMAP server first' => '您必须首先选择一个 IMAP 服务器',
    'New folder name is required' => '请指定新文件夹名称',
    'Folder to delete is required' => '指定要删除的文件夹',
    'Are you sure you want to delete this folder, and all the messages in it?' => '您确定要删除文件夹及其中的所有邮件吗？',
    'Folder to rename is required' => '指定要重命名的文件夹',
    'Create a New Folder' => '创建新文件夹',
    'New Folder Name' => '新文件夹名称',
    'Select Parent Folder (optional)' => '选择父文件夹（可选）',
    'Rename a Folder' => '更改文件夹名称',
    'Rename' => '重命名',
    'Delete a Folder' => '删除文件夹',
    'Select Folder' => '选择文件夹',
    'Russian' => '俄语',
    'None' => '无',
    'Daily' => '毎日',
    'Weekly' => '毎周',
    'Monthly' => '毎月',
    'Yearly' => '毎年',
    'Add Event' => '添加事件',
    'Download' => '下载',
    'French' => '法语',
    'Romanian' => '罗马尼亚语',
    'Shortcuts' => '快捷键',
    'Unfocus all input elements' => false,
    'Jump to the "Everything" page' => '跳转到所有',
    'Jump to the "Unread" page' => '跳转到未读',
    'Jump to the "Flagged" page' => '跳转到重要',
    'Jump to Contacts' => '跳转到联系人',
    'Jump to History' => '跳转到历史',
    'Jump to the Compose page' => '跳转到创作页面',
    'Toggle the folder list' => '切换文件夹列表',
    'Message List' => '邮件列表',
    'Focus the next message in the list' => '聚焦下一邮件',
    'Focus the previous message in the list' => '聚焦上一邮件',
    'Open the currently focused message' => '打开当前聚焦的邮件',
    'Select/unselect the currently focused message' => '选择或取消选择当前聚焦的邮件',
    'Toggle all message selections' => false,
    'Mark selected messages as read' => '标记已选择邮件为已读',
    'Mark selected messages as unread' => '标记已选择邮件为未读',
    'Mark selected messages as flagged' => '标记已选择邮件为重要',
    'Mark selected messages as unflagged' => '取消标记已选择邮件为重要',
    'Delete selected messages' => '删除已选择邮件',
    'Message View' => '查看邮件',
    'View the next message in the list' => '查看下一邮件',
    'View the previous message in the list' => '查看上一邮件',
    'Reply' => '回复',
    'Reply-all' => '回复全部',
    'Flag the message' => '标记邮件为重要',
    'Unflag the message' => '取消标记邮件为重要',
    'Delete the message' => '删除邮件',
    'Enter the 6 digit code from your Authenticator application' => '输入您的双因素认证软件中的 6 位数字代码',
    'Login Code' => '登录代码',
    '2 factor authentication code does not match' => '双因素认证码不匹配',
    '2 Factor Authentication' => '双因素认证',
    'Configure Google Authenticator BEFORE enabling 2 factor authentication.' => '请在启用双因素身份验证之前配置 Google Authenticator 。',
    'Enable 2 factor authentication' => '开启双因素认证',
    'Added SMTP server!' => '请添加 SMTP 服务器',
    'Added repository' => '添加存储库',
    'Added server!' => '请添加服务器！',
    'Are you sure?' => '确定吗?',
    'Could not find that repository/owner combination at github.com' => '无法在 Github 查找到用户名的相关仓库',
    'Disable prompts when deleting' => '删除前无需确认',
    'Enable keyboard shortcuts' => '启用键盘快捷键',
    'Github repository added' => '添加 GitHub 存储库',
    'Github repository removed' => '删除 GitHub 存储库',
    'Message deleted' => '邮件已删除',
    'NASA API connection' => 'NASA API 连接',
    'NASA API connection disabled' => '已禁用 NASA API 连接',
    'Profile Updated' => '已更新个人信息',
    'Removed repository' => '删除存储库',
    'Saved a search' => '保存搜索结果',
    'Update saved search' => '更新已保存的搜索结果',
    'Update search' => '更新搜索结果',
    'WordPress connection deleted' => '已删除 WordPress.com 连接',
    'WordPress.com Freshly Pressed' => false,
    'WordPress.com connection' => 'WordPress.com 连接',
    'WordPress.com connection established' => '已建立 WordPress.com 连接',
    'Your timezone is NOT set' => '您还未设置时区',
    'Page %s' => '页码 %s',
    'Add LDAP' => '添加 LDAP',
    'Update %s' => '更新 %s',
    'Department Number' => false,
    'Employee Number' => false,
    'Employment Type' => false,
    'Fax Number' => '传真号码',
    'First Name' => '名',
    'Last Name' => '姓',
    'License Plate Number' => '车牌号码',
    'Locality' => '地区',
    'Mobile Number' => '电话号码',
    'Organization' => '组织',
    'Organization Unit' => '组织单位',
    'Postal Code' => '邮政编码',
    'Room Number' => '门牌号',
    'Seen' => false,
    'State' => '省（级行政单位）',
    'Street' => '街道',
    'Website' => '网站',
    'Account' => '账号',
    'Add %s' => '添加 %s',
    'Addressbooks' => '通讯录',
    'An error occurred during the RCPT command' => 'RCPT 命令期间发生错误',
    'Configure your authentication app using the barcode below BEFORE enabling 2 factor authentication.' => '警告：在启用双因素身份验证之前，请使用下面的条形码配置身份验证应用程序。',
    'Current password is incorrect' => '当前密码错误',
    'Details' => '详情',
    'Don\'t save account passwords between logins' => '不要保存登录密码',
    'Do you want to log out?' => '您是否要退出？',
    'Drafts' => '草稿',
    'Enter your passwords below to gain access to these services during this session.' => '在下方键入密码以获得在本次会话中对这些服务的使用权限。',
    'Failed to authenticate to the SMTP server' => '验证 SMTP 服务器失败',
    'Feed deleted' => 'Feed 已删除',
    'IMAP server added' => '已添加 IMAP 服务器',
    'IMAP server credentials forgotten' => 'IMAP 服务器证书已被删除',
    'IMAP server deleted' => '已移除 IMAP 服务器',
    'IMAP server saved' => '已保存 IMAP 服务器',
    'Incorrect password, could not save settings to the server' => '密码错误，无法存储设置至服务器',
    'Max Github notices per repository' => '每个仓库的最大通知数量',
    'Max WordPress.com notices per repository' => '每个版本库的最大通知数量',
    'Password changed' => '密码已更改',
    'Password saved' => '密码已保存',
    'SMTP server deleted' => 'SMTP 服务器已删除',
    'Default message sort order' => '默认排序规则',
    'Pick a date' => '选择日期',
    'Set as default' => '设置为默认值',
    'Show Github notices received since' => '显示从此时间后的 Github 提醒',
    'Show draft messages since' => '显示从此时间后的草稿',
    'Show WordPress.com notices received since' => '显示从此时间后的 WordPress.com 通知',
    'The following backup codes can be used to access your account if you lose your device' => '若您的设备遗失，可使用以下备份代码访问您的帐户',
    'Unlock' => '解锁',
    'Updated' => '已更新',
    'WordPress.com Settings' => 'WordPress.com 相关设置',
    'You have elected to not store passwords between logins.' => '已选择在登出后不存储密码。',
    'begin forwarded message' => false,
    'Employee Type' => false,
    'Given Name' => '字',
    'Homepage URL' => '主页地址',
    'Organizational Unit' => '组织部门',
    'Preferred Language' => '语言偏好',
    'Surname' => '姓氏',
    'Vehicle License' => '驾驶证',
    'Edit Shortcut' => '编辑快捷键',
    'Modifier Key(s)' => '更改按键',
    'Character' => false,
    'Unflagged' => '未标记',
    'Unanswered' => '未回复',
    'Spanish' => '西班牙语',
    'Japanese' => '日语',
    'Dutch' => '荷兰语',
    'Connected, but failed to authenticate to the SMTP server' => '已连接，但 SMTP 服务器验证失败',
    'First page after login' => '登陆后显示的页面',
    'Github Settings' => 'Github 相关设置',
    'Github-All' => false,
    'Hide folder list icons' => '隐藏文件夹列表图标',
    'Hungarian' => '匈牙利语',
    'Manage Folders' => '管理文件夹',
    'Prefer text over HTML when reading messages' => '阅读邮件时优先采用文本渲染而非 HTML',
    'Running in debug mode. See https://cypht.org/install.html Section 6 for more detail.' => '当前为调试模式。详见 https://cypht.org/install.html 第 6 节。',
    'Show icons in message lists' => '在邮件列表中显示图标',
    'Show message part icons when reading a message' => false,
    'Show simple message part structure when reading a message' => '读取信息时显示简介',
    'Unsaved changes' => '未保存的更改',
    'homephone' => '座机',
    'pager' => '传呼机',
    'Brazilian Portuguese' => '巴西葡萄牙语',
    'Debug',
    'STARTTLS or unencrypted',
    'Inline Message Style' => '内联信息样式',
    'Inline' => '内联',
    'Right' => false,
    'Messages per page for IMAP folder views' => false,
    'Arrival Date' => '收件日期',
    'Add a feed' => '添加 feed',
    'Apply' => '应用',
    'Azerbaijani' => '阿塞拜疆语',
    'CardDav Addressbooks' => 'CardDav 通讯录',
    'Company' => '企业',
    'Debug' => false,
    'Home Address' => false,
    'If set, a copy of outbound mail sent with a profile tied to this IMAP account, will be saved in this folder' => '如果启用该选项，使用与此 IMAP 帐户绑定的配置文件发送的外发邮件副本将保存在此文件夹中',
    'If set, deleted messages for this account will be moved to this folder' => '如启用该选项，该账户的已删除邮件将被移至该文件夹',
    'LDAP Addressbooks' => 'LDAP 通讯录',
    'Markdown' => false,
    'Nasa Apod' => false,
    'Nickname' => '昵称',
    'Not set' => '未设定',
    'Offline' => '离线状态',
    'PGP' => false,
    'PGP Encrypt for' => false,
    'PGP Sign as' => false,
    'Please enter your passphrase' => '请输入密码',
    'STARTTLS or unencrypted' => 'STARTTLS 或未加密',
    'Sent Folder' => '已发送文件夹',
    'Settings updated' => '已更新设置',
    'So Alone' => '什么都没有',
    'Stay logged in' => '保持登录状态',
    'Submit' => '提交',
    'Trash Folder' => '回收站',
    'Uid' => false,
    'Unable to access CardDav server' => '无法访问 CardDav 服务器',
    'Unflag on reply' => false,
    'Advanced Search' => '高级搜索',
    'Expand all' => false,
    'Advanced Search' => '高级搜索',
    'terms' => false,
    'Terms' => false,
    'terms: %d' => false,
    'sources' => false,
    'sources: %d' => false,
    'targets' => '目标',
    'Targets' => '目标',
    'targets: %d' => '目标：%d',
    'Body' => false,
    'Header' => false,
    'Custom Header' => false,
    'time' => '时间',
    'time ranges: %d' => '时间范围：%d',
    'other' => '其他',
    'Other' => '其他',
    'other settings: %d' => '其他设置：%d',
    'Character set' => false,
    'Deleted' => '已删除',
    'Not deleted' => '未删除',
    'Results' => '结果',
    'Search Results' => '搜索结果',
    'feed item' => 'feed 项目',
    'Allow handling of mailto links' => '启用 mailto 链接处理',
    'Show folders' => '显示文件夹',
    'Show next & previous emails links when reading a message' => '阅读邮件时显示下一封和上一封邮件的链接',
    'Archive' => '归档',
    'Show next email instead of your inbox after performing action (delete, archive, move, etc)' => '执行操作（删除、存档、移动等）后显示下一封邮件，而非收件箱',
    'Archive to the original folder' => '归档到原文件夹',
    'Move To Blocked Folder' => '移动到黑名单文件夹',
    'Sieve server capabilities' => false,
    'Warn for unsaved changes' => '当存在未保存的更改时警告',
    'We couldn\'t find the attachment you referred to. Please confirm if you attached it or provide the details again.' => '未找到附件。请确认其是否成功附上，或进一步提供详细信息。',
    'attachment,file,attach,attached,attaching,enclosed,CV,cover letter' => false,
    'Automatically add outgoing email addresses' => '自动添加外发电子邮件地址',
    'Trusted Senders' => '信任的发件人',
    'Collected Recipients' => '收件人',
    'Personal Addresses' => '个人地址',
    'Contact Group' => '群组',
    'You need to verify your 2 factor authentication code before processing' => '继续前，需要验证您的双因素身份验证代码',
    'You need to enter the verification code' => '请输入输入验证码',
    'You must enter at least one search term' => '请输入至少一个搜索词',
    'You must select at least one source' => false,
    'You must have at least one target' => '请指定至少一个目标',
    'You must enter at least one time range' => '请输入至少一个时间范围',
    "This phone number appears to contain invalid character (s).\nIf you are sure ignore this warning and continue!" => "检测到电话号码中包含潜在的非法字符。\n是否忽略本警告并继续？",
    'Server Error' => '服务器错误',
    'Close' => '关闭',
    'Unread in Everything' => '所有未读信息',
    'Unread in Email' => '未读邮件',
    'Unread in Feeds' => '未读 Feed',
    'Restore current value' => '恢复当前值',
    'Restore default value' => '恢复默认值',
    'New Message' => '新消息',
    'Your All-inkl Login' => false,
    'Could not unlock key with supplied passphrase' => false,
    'Could not access private key' => '无法访问私钥',
    'Unable to import private key' => '无法导入私钥',
    'Private key removed' => '已移除私钥',
    'Encrypting and signing message...' => false,
    'Signing message...' => false,
    'Decrypting message...' => '解密邮件中……',
    'Encrypting message...' => '加密邮件中……',
    'Do you want to unblock sender?' => '确定要解除对发件人的屏蔽？',
    'You must provide at least one action' => '请指定至少一个行为',
    'You must provide at least one condition' => '请指定至少一个条件为',
    'Filter name is required' => '请提供过滤器名称',
    'You must provide a name for your script' => '请提供脚本名称',
    'Empty script' => '空脚本',
    'Please create a profile for saving sent messages option' => '请创建用于保存已发送信息选项的配置文件',
    'Attachment storage unavailable, please contact your site administrator' => '附件存储不可用，请联系您的网站管理员',
    'Your subject is empty!' => '主题为空！',
    'Your body is empty!' => '内容为空！',
    'Your subject and body are empty!' => '主题和内容为空！',
    'Send anyway' => '强制发送',
    'Send anyway and don\'t warn in the future' => '强制发送并永久关闭警告',
    'Are you sure you want to send this message?' => '确定发送信息？',
    'IMAP and JMAP Servers' => 'IMAP 服务器及 JMAP 服务器',
    'Junk' => '垃圾',
    'Trash' => '已删除',
    'Pasted text has leading or trailing spaces' => false,
    'No tags available yet.' => false,
    'Server capabilities' => false,
    'Capabilities' => false,
    'Screen %s first emails' => false,
    'Yaml File' => false,
);
