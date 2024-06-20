<?php
class PluginMailQueue{
  private $settings;
  private $mysql;
  private $last_sent_minutes;
  private $id = null;
  private $localhost = null;
  function __construct($buto) {
    if($buto){
      wfPlugin::includeonce('wf/yml');
      wfPlugin::includeonce('wf/mysql');
      $this->mysql =new PluginWfMysql();
      $this->mysql->event = false;
      $this->settings = wfPlugin::getPluginSettings('mail/queue', true);
      if(!$this->settings->get('data/interval_minutes')){
        $this->settings->set('data/interval_minutes', 2);
      }
      if(!$this->settings->get('data/interval_messages')){
        $this->settings->set('data/interval_messages', 4);
      }
      if(!is_array($this->settings->get('data/phpmailer'))){
        $this->settings->set('data/phpmailer', wfSettings::getSettingsFromYmlString($this->settings->get('data/phpmailer')));
      }
    }
    $this->localhost = wfServer::isHost('localhost');
  }
  private function db_open(){
    $this->mysql->open($this->settings->get('data/mysql'));
  }
  private function db_send_select_last_created_at(){
    $sql = $this->getSql('send_select_last_created_at');
    $this->db_open();
    $this->mysql->execute($sql->get());
    $rs = new PluginWfArray($this->mysql->getStmtAsArrayOne());
    if(!$rs->get('created_at')){
      return null;
    }else{
      return $rs->get('created_at');
    }
  }
  private function db_send_insert(){
    $id = wfCrypt::getUid();
    $sql = $this->getSql('send_insert');
    $sql->set('params/id/value', $id);
    $this->db_open();
    $this->mysql->execute($sql->get());
    return $id;
  }
  private function db_queue_select_to_send(){
    $sql = $this->getSql('queue_select_to_send');
    $this->db_open();
    $this->mysql->execute($sql->get());
    return $this->mysql->getStmtAsArray();
  }
  private function db_queue_select_one($id){
    $sql = $this->getSql('queue_select_one');
    $sql->set('params/id/value', $id);
    $this->db_open();
    $this->mysql->execute($sql->get());
    return new PluginWfArray($this->mysql->getStmtAsArrayOne());
  }
  /**
   * Create a message in the queue.
   * @param string $subject
   * @param mixed $body String, Array
   * @param string $mail_to
   * @param string $send_id
   * @param string $date_from
   * @param string $date_to
   * @param int $rank
   * @param string $account_id
   * @param string $tag
   * @return string ID or false
   */
  public function create($subject, $body, $mail_to, $send_id = null, $date_from = null ,$date_to = null, $rank = null, $account_id = null, $tag = null, $mail_from = null, $from_name = null, $attachment = array()){
    /**
     * 
     */
    $this->id = wfCrypt::getUid();
    /**
     * Attachment
     */
    $this->insert_attachment($attachment);
    /**
     * 
     */
    if(is_array($body)){
      wfDocument::$capture = 2;
      wfDocument::renderElement($body);
      $body = wfDocument::getContent();
    }
    /**
     * 
     */
    $this->db_queue_insert($subject, $body, $mail_to, $send_id, $date_from, $date_to, $rank, $account_id, $tag, $mail_from, $from_name);
    /**
     * 
     */
    return $this->id;
  }
  private function insert_attachment($attachment){
    if($attachment){
      /**
       * Validate
       */
      foreach($attachment as $v){
        $i = new PluginWfArray($v);
        $i->set('path', wfSettings::replaceDir($i->get('path')));
        if(!wfFilesystem::fileExist($i->get('path'))){
          throw new Exception(__CLASS__." says: Attachment file ".$i->get('path')." could not be find");
        }
      }
      /**
       * Save
       */
      foreach($attachment as $v){
        $i = new PluginWfArray($v);
        $i->set('path', wfSettings::replaceTheme($i->get('path')));
        $name = basename($i->get('path'));
        if($i->get('name')){
          $name = $i->get('name');
        }
        wfFilesystem::copyFile($i->get('path'), wfGlobals::getAppDir().$this->settings->get('data/attachment_folder').'/'.$this->id.'/'.$name);
      }
    }
  }
  /**
   * Create message in the queue and send at the same time.
   * @param string $subject
   * @param string $body
   * @param string $mail_to
   * @param string $send_id
   * @param string $date_from
   * @param string $date_to
   * @param int $rank
   * @param string $account_id
   * @param string $tag
   * @param string $mail_from
   * @param string $from_name
   * @param string $attachment
   * @return string ID or false
   */
  public function send($subject, $body, $mail_to, $send_id = null, $date_from = null ,$date_to = null, $rank = null, $account_id = null, $tag = null, $mail_from = null, $from_name = null, $attachment = array()){
    /**
     * replace
     */
    $body = wfPhpfunc::str_replace('−', '-', $body);
    /**
     * 
     */
    $this->id = wfCrypt::getUid();
    /**
     * Attachment
     */
    $this->insert_attachment($attachment);
    /**
     * Create message and get id.
     */
    $this->db_queue_insert($subject, $body, $mail_to, $send_id, $date_from, $date_to, 0, $account_id, $tag, $mail_from, $from_name);
    /**
     * Get message via id.
     */
    $item = $this->db_queue_select_one($this->id);
    /**
     * Create send record and get id.
     */
    $send_id = $this->db_send_insert();
    /**
     * Send message.
     */
    $x = $this->sendMessage($item, $send_id);
    /**
     * Return true/false.
     */
    return $x;
  }
  private function db_queue_insert($subject, $body, $mail_to, $send_id = null, $date_from = null ,$date_to = null, $rank = null, $account_id = null, $tag = null, $mail_from = null, $from_name = null){
    if(is_null($subject) || is_null($body) || is_null($mail_to)){
      return false;
    }
    if(is_null($date_from)){
      $date_from = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s').' + 30 minutes'));
    }
    if(is_null($date_to)){
      $date_to = date('Y-m-d H:i:s', strtotime($date_from.' + 1 days'));
    }
    if(is_null($rank)){
      $rank = 1;
    }
    $sql = $this->getSql('queue_insert');
    $sql->set('params/id/value', $this->id);
    $sql->set('params/send_id/value', $send_id);
    $sql->set('params/mail_subject/value', $subject);
    $sql->set('params/body/value', $body);
    $sql->set('params/mail_rank/value', $rank);
    $sql->set('params/mail_from/value', $mail_from);
    $sql->set('params/from_name/value', $from_name);
    $sql->set('params/mail_to/value', $mail_to);
    $sql->set('params/date_from/value', $date_from);
    $sql->set('params/date_to/value', $date_to);
    $sql->set('params/account_id/value', $account_id);
    $sql->set('params/tag/value', $tag);
    $this->db_open();
    $this->mysql->execute($sql->get());
    return $this->id;
  }
  private function db_queue_update_to_sent($id, $send_id){
    $sql = $this->getSql('queue_update_to_sent');
    $sql->set('params/id/value', $id);
    $sql->set('params/send_id/value', $send_id);
    $this->db_open();
    $this->mysql->execute($sql->get());
    return null;
  }
  private function db_queue_update_error_text($id, $error_text){
    $sql = $this->getSql('queue_update_error_text');
    $sql->set('params/id/value', $id);
    $sql->set('params/error_text/value', $error_text);
    $this->db_open();
    $this->mysql->execute($sql->get());
    return null;
  }
  private function getElement($name){
    return new PluginWfYml(__DIR__."/element/$name.yml");
  }
  private function getSql($key){
    return new PluginWfYml(__DIR__.'/mysql/sql.yml', $key);
  }
  private function isTimeToSend(){
    /**
     * Force send if localhost and param time_to_send.
     */
    if($this->localhost && wfRequest::get('time_to_send')){
      return true;
    }
    /**
     * 
     */
    $last_sent = $this->db_send_select_last_created_at();
    if(!$last_sent){
      /**
       * First send ever.
       */
      return true;
    }
    $this->last_sent_minutes = wfDate::diff('i', $last_sent, date('Y-m-d H:i:s'));
    if($this->last_sent_minutes > $this->settings->get('data/interval_minutes')){
      return true;
    }
    return false;
  }
  private function sendMessage($item, $send_id){
    wfPlugin::includeonce('wf/phpmailer');
    $phpmailer = new PluginWfPhpmailer();
    $data_mail = new PluginWfArray($this->settings->get('data/phpmailer'));
    /**
     * Mail from
     */
    if($item->get('mail_from')){
      $data_mail->set('From', $item->get('mail_from'));
    }
    if($item->get('from_name')){
      $data_mail->set('FromName', $item->get('from_name'));
    }
    /**
     * attachment
     */
    $dir = wfFilesystem::getScandir(wfGlobals::getAppDir().$this->settings->get('data/attachment_folder').'/'.$item->get('id'));
    if($dir){
      $attachment = array();
      foreach($dir as $v){
        $attachment[] = array('path' => wfSettings::replaceTheme($this->settings->get('data/attachment_folder')).'/'.$item->get('id').'/'.$v);
      }
      $data_mail->set('attachment', $attachment);
    }
    /**
     * 
     */
    $this->db_queue_update_to_sent($item->get('id'), $send_id);
    /**
     * Send...
     */
    $data_mail->set('To', $item->get('mail_to'));
    $data_mail->set('Subject', $item->get('subject'));
    $data_mail->set('Body', $item->get('body'));
    /**
     * Result.
     */
    $result = new PluginWfArray($phpmailer->send($data_mail->get()));
    if($result->get('success')){
      return true;
    }else{
      $result->set('smtp/Password', '******');
      $this->db_queue_update_error_text($item->get('id'), wfHelp::getYmlDump($result->get()));
      return false;
    }
  }
  public function page_send(){
    if(!$this->settings->get('data/secret_key')){
      exit('Not proper settings (0).');
    }elseif($this->settings->get('data/secret_key')!=wfRequest::get('key')){
      exit('Not proper settings (1).');
    }
    $element = $this->getElement('send');
    $count = null;
    $sent_count = null;
    $error_count = null;
    if($this->isTimeToSend()){
      /**
       * 
       */
      $element->setById('isTimeToSend', 'innerHTML', 'Yes');
      $messages = $this->db_queue_select_to_send();
      $count = sizeof($messages);
      $send_id = $this->db_send_insert();
      $sent_count = 0;
      $error_count = 0;
      $interval_messages = 0;
      foreach ($messages as $key => $value) {
        if($interval_messages+1>$this->settings->get('data/interval_messages')){
          break;
        }
        $interval_messages++;
        /**
         * 
         */
        $item = new PluginWfArray($value);
        $i = $this->sendMessage($item, $send_id);
        if($i){
          $sent_count++;
        }else{
          $error_count++;
        }
      }
    }else{
      $element->setById('isTimeToSend', 'innerHTML', 'No');
    }
    $element->setById('time', 'innerHTML', date('Y-m-d H:i:s'));
    $element->setById('count', 'innerHTML', $count);
    $element->setById('sent_count', 'innerHTML', $sent_count);
    $element->setById('error_count', 'innerHTML', $error_count);
    $element->setById('interval_minutes', 'innerHTML', $this->settings->get('data/interval_minutes'));
    $element->setById('interval_messages', 'innerHTML', $this->settings->get('data/interval_messages'));
    $element->setById('last_time_minutes', 'innerHTML', $this->last_sent_minutes);
    wfDocument::renderElement($element->get());
    exit;
  }
  public function widget_test($data){
    $data = new PluginWfArray($data);
    $id = $this->send($data->get('data/subject'), $data->get('data/body'), $data->get('data/mail_to'), null, null, null, null, null, null, null, null, $data->get('data/attachment'));
    wfHelp::yml_dump($id);
  }
}
