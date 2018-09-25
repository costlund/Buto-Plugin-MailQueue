<?php

class PluginMailQueue{
// <editor-fold defaultstate="collapsed" desc="Variables">
  private $settings;
  private $mysql;
  private $last_sent_minutes;
// </editor-fold>
// <editor-fold defaultstate="collapsed" desc="Construct">
  function __construct($buto) {
    if($buto){
      wfPlugin::includeonce('wf/yml');
      wfPlugin::includeonce('wf/mysql');
      $this->mysql =new PluginWfMysql();
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
  }
// </editor-fold>
// <editor-fold defaultstate="collapsed" desc="Database">
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
   * @param type $subject
   * @param type $body
   * @param type $mail_to
   * @param type $send_id
   * @param type $date_from
   * @param type $date_to
   * @param type $rank
   * @return type
   */
  public function create($subject, $body, $mail_to, $send_id = null, $date_from = null ,$date_to = null, $rank = 1){
    return $this->db_queue_insert($subject, $body, $mail_to, $send_id, $date_from, $date_to, $rank);
  }
  /**
   * Create message in the queue and send at the same time.
   * @param type $subject
   * @param type $body
   * @param type $mail_to
   * @return type
   */
  public function send($subject, $body, $mail_to){
    /**
     * Create message and get id.
     */
    $id = $this->db_queue_insert($subject, $body, $mail_to, null, null, null, 999);
    /**
     * Get message via id.
     */
    $item = $this->db_queue_select_one($id);
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
  private function db_queue_insert($subject, $body, $mail_to, $send_id = null, $date_from = null ,$date_to = null, $rank = 1){
    if(is_null($subject) || is_null($body) || is_null($mail_to)){
      return false;
    }
    if(is_null($date_from)){
      $date_from = date('Y-m-d H:i:s');
    }
    if(is_null($date_to)){
      $date_to = date('Y-m-d H:i:s', strtotime($date_from.' + 1 days'));
    }
    $id = wfCrypt::getUid();
    $sql = $this->getSql('queue_insert');
    $sql->set('params/id/value', $id);
    $sql->set('params/send_id/value', $send_id);
    $sql->set('params/subject/value', $subject);
    $sql->set('params/body/value', $body);
    $sql->set('params/rank/value', $rank);
    $sql->set('params/mail_from/value', null);
    $sql->set('params/mail_to/value', $mail_to);
    $sql->set('params/date_from/value', $date_from);
    $sql->set('params/date_to/value', $date_to);
    $this->db_open();
    $this->mysql->execute($sql->get());
    return $id;
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
// </editor-fold>
// <editor-fold defaultstate="collapsed" desc="Methods">
  private function getElement($name){
    return new PluginWfYml(__DIR__."/element/$name.yml");
  }
  private function getSql($key){
    return new PluginWfYml(__DIR__.'/mysql/sql.yml', $key);
  }
  private function isTimeToSend(){
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
      //$sent_count++;
      return true;
    }else{
      //$error_count++;
      $this->db_queue_update_error_text($item->get('id'), wfHelp::getYmlDump($result->get()));
      return false;
    }
  }
// </editor-fold>
// <editor-fold defaultstate="collapsed" desc="Page">
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
//        /**
//         * 
//         */
//        $this->db_queue_update_to_sent($item->get('id'), $send_id);
//        /**
//         * Send...
//         */
//        $data_mail->set('To', $item->get('mail_to'));
//        $data_mail->set('Subject', $item->get('subject'));
//        $data_mail->set('Body', $item->get('body'));
//        /**
//         * Result.
//         */
//        $result = new PluginWfArray($phpmailer->send($data_mail->get()));
//        if($result->get('success')){
//          $sent_count++;
//        }else{
//          $error_count++;
//          $this->db_queue_update_error_text($item->get('id'), wfHelp::getYmlDump($result->get()));
//        }
        $i = $this->sendMessage($item, $send_id);
        if($i){
          $sent_count++;
        }else{
          $error_count++;
        }
        /**
         * 
         */
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
// </editor-fold>
}
