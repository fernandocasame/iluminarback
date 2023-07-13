  #!/bin/bash
chown -R :nginx /var/www/html/iluminarback/iluminarback/storage/
chown -R :nginx /var/www/html/iluminarback/iluminarback/bootstrap/
chmod -R 0777 /var/www/html/iluminarback/iluminarback/storage/
chmod -R 0777 /var/www/html/iluminarback/iluminarback/bootstrap/
# semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/html/iluminarback/iluminarback/storage(/.*)?'
# semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/html/iluminarback/iluminarback/bootstrap/cache(/.*)?'
restorecon -Rv '/var/www/html/iluminarback/iluminarback'
sudo cp -rf /var/www/html/plataformaprolipa_server/vendor /var/www/html/iluminarback/iluminarback
