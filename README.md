# Buto-Plugin-MailQueue

<p>Send mail in portions to avoid mail server to be tagged as spam server.</p>
<p>Using MySql database with two tables mailqueue_queue and mailqueue_send with PluginWfMysql. Then send email via schedule requests with PluginWfPhpmailer.  </p>
<p>Put mails in mailqueue table via create and call page send via schedule like a cron script.</p>
<ul>
<li>Param plugin/mail/queue/data/interval_minutes is how often this plugin will try to send email. A proper way should be to call page send every 5 minutes. </li>
<li>Param plugin/mail/queue/data/interval_messages is the limit of how many mail for each time it will try to send.</li>
<li>Param plugin_modules/mailqueue must be set to be able to call url /mailqueue/send/key/_my_secret<em>key</em>.</li>
</ul>

<a name="key_0"></a>

## Settings

<pre><code>plugin:
  mail:
    queue:
      enabled: true
      data:
        mysql: 'Settings needed by PluginWfMysql.'
        phpmailer: 'Settings needed by PluginWfPhpmailer.'
        interval_minutes: 4
        interval_messages: 5
        secret_key: '_my_secret_key_'
        attachment_folder: _my_attachment_folder_</code></pre>
<pre><code>plugin_modules:
  mailqueue:
    plugin: 'mail/queue'</code></pre>
<p>Schema is in /mysql/schema.yml.</p>

<a name="key_1"></a>

## Usage



<a name="key_1_0"></a>

### Tag

<p>Use the tag field to control if a specific email is sent.
Example of sql for sending welcome message to new account created last two days.</p>
<pre><code>select 
id, 
concat(id,'_welcome') as tag,
email
from account
where datediff(now(), created_at) &lt;= 2
having tag NOT in (select COALESCE(tag, '') from mailqueue_queue);</code></pre>

<a name="key_1_1"></a>

### Schema

<pre><code>/plugin/mail/queue/mysql/schema.yml</code></pre>

<a name="key_1_2"></a>

### Cron job

<p>Cron job every 5 minutes on your server.</p>
<pre><code>*/5 * * * * wget https://_domain_/mailqueue/send/key/_my_key_</code></pre>

<a name="key_1_3"></a>

### Force send

<p>If domain is localhost one could force send messages via a param.</p>
<pre><code>?time_to_send=1</code></pre>

<a name="key_2"></a>

## Pages



<a name="key_2_0"></a>

### page_send



<a name="key_3"></a>

## Widgets



<a name="key_3_0"></a>

### widget_test



<a name="key_4"></a>

## Event



<a name="key_5"></a>

## Construct



<a name="key_5_0"></a>

### __construct



<a name="key_6"></a>

## Methods



<a name="key_6_0"></a>

### db_open



<a name="key_6_1"></a>

### set_settings



<a name="key_6_2"></a>

### db_send_select_last_created_at



<a name="key_6_3"></a>

### db_send_insert



<a name="key_6_4"></a>

### db_queue_select_to_send



<a name="key_6_5"></a>

### db_queue_select_one



<a name="key_6_6"></a>

### create

<p>A plugin should use create method to add messages to queue.</p>
<pre><code>wfPlugin::includeonce('mail/queue');
$mail = new PluginMailQueue(true);
$mail-&gt;create($subject, $body, $mail_to, $send_id = null, $date_from = null ,$date_to = null, $rank = null, $account_id = null, $tag = null, $mail_from = null, $from_name = null, $attachment = array());</code></pre>

<a name="key_6_6_0"></a>

#### body

<p>Param body can be string or element.</p>
<pre><code>$body = 'My string message!';</code></pre>
<p>Element.</p>
<pre><code>-
  type: p
  innerHTML: 'My element message!';</code></pre>

<a name="key_6_6_1"></a>

#### attachment

<p>Example.</p>
<pre><code>attachment:
  -
    path: _full_path_to_file_
    name: _any_name_(optional)
  -
    path: '/User/Me/Test.pdf'
    name: My_test_file.pdf</code></pre>

<a name="key_6_7"></a>

### insert_attachment



<a name="key_6_8"></a>

### send



<a name="key_6_9"></a>

### db_queue_insert



<a name="key_6_10"></a>

### db_queue_update_to_sent



<a name="key_6_11"></a>

### db_queue_update_error_text



<a name="key_6_12"></a>

### getElement



<a name="key_6_13"></a>

### getSql



<a name="key_6_14"></a>

### isTimeToSend



<a name="key_6_15"></a>

### sendMessage



