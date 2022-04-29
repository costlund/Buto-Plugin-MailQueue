# Buto-Plugin-MailQueue

Mail queue plugin.

Send mail in portions to avoid mail server to be tagged as spam server.

Using MySql database with two tables mailqueue_queue and mailqueue_send with PluginWfMysql. Then send email via schedule requests with PluginWfPhpmailer.  

Put mails in mailqueue table via create and call page send via schedule like a cron script.
- Param plugin/mail/queue/data/interval_minutes is how often this plugin will try to send email. A proper way should be to call page send every 5 minutes. 
- Param plugin/mail/queue/data/interval_messages is the limit of how many mail for each time it will try to send.
- Param plugin_modules/mailqueue must be set to be able to call url /mailqueue/send/key/_my_secret_key_.

```
plugin:
  mail:
    queue:
      enabled: true
      data:
        mysql: 'Settings needed by PluginWfMysql.'
        phpmailer: 'Settings needed by PluginWfPhpmailer.'
        interval_minutes: 4
        interval_messages: 5
        secret_key: '_my_secret_key_'
        attachment_folder: _my_attachment_folder_
```
```
plugin_modules:
  mailqueue:
    plugin: 'mail/queue'
```

Schema is in /mysql/schema.yml.

## PHP

A plugin should use create method to add messages to queue.

```
wfPlugin::includeonce('mail/queue');
$mail = new PluginMailQueue(true);
$mail->create($subject, $body, $mail_to, $send_id = null, $date_from = null ,$date_to = null, $rank = null, $account_id = null, $tag = null, $mail_from = null, $from_name = null, $attachment = array());
```
Param body can be string or element.
```
$body = 'My string message!';
```
```
-
  type: p
  innerHTML: 'My element message!';
```

### Attachment
Example.
```
attachment:
  -
    path: _full_path_to_file_
    name: _any_name_(optional)
  -
    path: '/User/Me/Test.pdf'
    name: My_test_file.pdf
```

## Tag message

Use the tag field to control if a specific email is sent.

Example of sql for sending welcome message to new account created last two days.

```
select 
id, 
concat(id,'_welcome') as tag,
email
from account
where datediff(now(), created_at) <= 2
having tag NOT in (select COALESCE(tag, '') from mailqueue_queue);
```


## Schema
```
/plugin/mail/queue/mysql/schema.yml
```

### Cron job
Cron job every 5 minutes on your server.
````
*/5 * * * * wget https://_domain_/mailqueue/send/key/_my_key_
````
