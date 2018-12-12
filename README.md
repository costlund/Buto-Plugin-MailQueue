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
plugin_modules:
  mailqueue:
    plugin: 'mail/queue'
```

Schema is in /mysql/schema.yml.

