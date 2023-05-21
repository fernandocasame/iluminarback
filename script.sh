  #!/bin/bash
chown -R :nginx /var/www/html/plataformaprolipa_server/storage/
chown -R :nginx /var/www/html/plataformaprolipa_server/bootstrap/cache/
chmod -R 0777 /var/www/html/plataformaprolipa_server/storage/
chmod -R 0775 /var/www/html/plataformaprolipa_server/bootstrap/cache/
semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/html/plataformaprolipa_server/storage(/.*)?'
semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/html/plataformaprolipa_server/bootstrap/cache(/.*)?'
restorecon -Rv '/var/www/html/plataformaprolipa_server'
