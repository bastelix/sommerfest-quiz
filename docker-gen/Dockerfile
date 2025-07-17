FROM nginxproxy/docker-gen:latest

COPY defaults/nginx.tmpl /defaults/nginx.tmpl
COPY entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
