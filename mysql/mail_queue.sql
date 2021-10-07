


#
select * from mailqueue_queue order by created_at desc;


# Sent emails.
select 
m.mail_to,
s.created_at,
m.mail_subject,
m.body,
m.tag,
m.error_text
from mailqueue_queue as m
inner join mailqueue_send as s on m.send_id=s.id
order by s.created_at desc
;

# Not sent emails.
select 
m.mail_to,
m.created_at,
m.mail_subject,
m.body,
m.tag
from mailqueue_queue as m
where isnull(m.send_id)
order by m.created_at desc
;
