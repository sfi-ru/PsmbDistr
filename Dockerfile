FROM dimaip/docker-neos-alpine:latest
ENV PHP_TIMEZONE=Europe/Moscow
ENV AWS_ENDPOINT=https://hb.bizmrg.com
ENV AWS_BACKUP_ARN=s3://psmb-neos-resources/db/psmb/
ENV REPOSITORY_URL=https://github.com/psmb/PsmbDistr
ENV DONT_PUBLISH_PERSISTENT=1
RUN chown -R 80:80 /composer/
USER 80
COPY --chown=80:80 composer.json /data/www-provisioned/composer.json
RUN cd /data/www-provisioned && \
    composer install && \
    cd /data/www-provisioned && \
    beard patch && \
    rm -rf /composer/cache && \
    mkdir -p /data/www-provisioned/Configuration && \
    cp /Settings.yaml /data/www-provisioned/Configuration/ && \
    /bin/bash -c "source /init-php-conf.sh"
COPY --chown=80:80 ./ /data/www-provisioned/
RUN cd /data/www-provisioned && composer run-script post-update-cmd
HEALTHCHECK --interval=30s --timeout=15s --start-period=30s --retries=3 CMD curl -f http://localhost/ | grep "This website is powered by Neos"
