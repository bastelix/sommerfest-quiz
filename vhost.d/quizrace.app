client_max_body_size 50m;

# Rate limiting per client IP
limit_req zone=perip burst=20 nodelay;
limit_conn connperip 20;

# Drop common scanner and exploit paths at the edge
location ~* \.(php|phtml|asp|aspx|cgi|pl)(/|$) { return 444; }
location ~* ^/(wp-admin|wp-login\.php|wp-content|wp-includes|xmlrpc\.php|wp-json)(/|$) { return 444; }
location ~* ^/(phpmyadmin|pma|adminer)(/|$) { return 444; }
location ~* ^/(\.env|\.git|\.svn|\.hg)(/|$) { return 444; }
