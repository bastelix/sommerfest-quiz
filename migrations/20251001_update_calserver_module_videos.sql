UPDATE pages
SET content = REPLACE(
    REPLACE(
        REPLACE(
            REPLACE(content,
$$                    <img src="{{ basePath }}/uploads/calserver-module-device-management.webp"
                         width="1200"
                         height="675"
                         loading="lazy"
                         decoding="async"
                         alt="Screenshot der calServer-Geräteverwaltung mit Geräteakte, Historie und Messwerten">$$,
$$                    <video class="calserver-module-figure__video"
                           width="1200"
                           height="675"
                           autoplay
                           muted
                           loop
                           playsinline
                           preload="auto"
                           poster="{{ basePath }}/uploads/calserver-module-device-management.webp"
                           aria-label="Screenshot der calServer-Geräteverwaltung mit Geräteakte, Historie und Messwerten">
                      <source src="{{ basePath }}/uploads/calserver-module-device-management.mp4" type="video/mp4">
                      Ihr Browser unterstützt keine HTML5-Videos.
                      <a href="{{ basePath }}/uploads/calserver-module-device-management.mp4" target="_blank" rel="noopener">
                        Video herunterladen
                      </a>.
                    </video>$$),
            $$                    <img src="{{ basePath }}/uploads/calserver-module-calendar-resources.webp"
                         width="1200"
                         height="675"
                         loading="lazy"
                         decoding="async"
                         alt="Screenshot des calServer-Kalenders mit Ressourcen- und Terminplanung">$$,
$$                    <video class="calserver-module-figure__video"
                           width="1200"
                           height="675"
                           autoplay
                           muted
                           loop
                           playsinline
                           preload="auto"
                           poster="{{ basePath }}/uploads/calserver-module-calendar-resources.webp"
                           aria-label="Screenshot des calServer-Kalenders mit Ressourcen- und Terminplanung">
                      <source src="{{ basePath }}/uploads/calserver-module-calendar-resources.mp4" type="video/mp4">
                      Ihr Browser unterstützt keine HTML5-Videos.
                      <a href="{{ basePath }}/uploads/calserver-module-calendar-resources.mp4" target="_blank" rel="noopener">
                        Video herunterladen
                      </a>.
                    </video>$$),
        $$                    <img src="{{ basePath }}/uploads/calserver-module-order-ticketing.webp"
                         width="1200"
                         height="675"
                         loading="lazy"
                         decoding="async"
                         alt="Screenshot der calServer-Auftrags- und Ticketverwaltung mit Workflow-Status">$$,
$$                    <video class="calserver-module-figure__video"
                           width="1200"
                           height="675"
                           autoplay
                           muted
                           loop
                           playsinline
                           preload="auto"
                           poster="{{ basePath }}/uploads/calserver-module-order-ticketing.webp"
                           aria-label="Screenshot der calServer-Auftrags- und Ticketverwaltung mit Workflow-Status">
                      <source src="{{ basePath }}/uploads/calserver-module-order-ticketing.mp4" type="video/mp4">
                      Ihr Browser unterstützt keine HTML5-Videos.
                      <a href="{{ basePath }}/uploads/calserver-module-order-ticketing.mp4" target="_blank" rel="noopener">
                        Video herunterladen
                      </a>.
                    </video>$$),
    $$                    <img src="{{ basePath }}/uploads/calserver-module-self-service.webp"
                         width="1200"
                         height="675"
                         loading="lazy"
                         decoding="async"
                         alt="Screenshot des calServer-Self-Service-Portals mit Kundenansicht und Zertifikaten">$$,
$$                    <video class="calserver-module-figure__video"
                           width="1200"
                           height="675"
                           autoplay
                           muted
                           loop
                           playsinline
                           preload="auto"
                           poster="{{ basePath }}/uploads/calserver-module-self-service.webp"
                           aria-label="Screenshot des calServer-Self-Service-Portals mit Kundenansicht und Zertifikaten">
                      <source src="{{ basePath }}/uploads/calserver-module-self-service.mp4" type="video/mp4">
                      Ihr Browser unterstützt keine HTML5-Videos.
                      <a href="{{ basePath }}/uploads/calserver-module-self-service.mp4" target="_blank" rel="noopener">
                        Video herunterladen
                      </a>.
                    </video>$$),
    updated_at = CURRENT_TIMESTAMP
WHERE slug = 'calserver';

UPDATE pages
SET content = REPLACE(
    REPLACE(
        REPLACE(
            REPLACE(content,
$$<img alt="Screenshot of the calServer device management with device files, history and measured values" decoding="async" height="675" loading="lazy" src="{{ basePath }}/uploads/calserver-module-device-management.webp" width="1200"/>$$,
$$<video aria-label="Screenshot of the calServer device management with device files, history and measured values" class="calserver-module-figure__video" width="1200" height="675" autoplay muted loop playsinline preload="auto" poster="{{ basePath }}/uploads/calserver-module-device-management.webp">
<source src="{{ basePath }}/uploads/calserver-module-device-management.mp4" type="video/mp4"/>
Your browser does not support HTML5 video.
<a href="{{ basePath }}/uploads/calserver-module-device-management.mp4" target="_blank" rel="noopener">Download video</a>.
</video>$$),
            $$<img alt="Screenshot of the calServer calendar with resource and scheduling" decoding="async" height="675" loading="lazy" src="{{ basePath }}/uploads/calserver-module-calendar-resources.webp" width="1200"/>$$,
$$<video aria-label="Screenshot of the calServer calendar with resource and scheduling" class="calserver-module-figure__video" width="1200" height="675" autoplay muted loop playsinline preload="auto" poster="{{ basePath }}/uploads/calserver-module-calendar-resources.webp">
<source src="{{ basePath }}/uploads/calserver-module-calendar-resources.mp4" type="video/mp4"/>
Your browser does not support HTML5 video.
<a href="{{ basePath }}/uploads/calserver-module-calendar-resources.mp4" target="_blank" rel="noopener">Download video</a>.
</video>$$),
        $$<img alt="Screenshot of the calServer mandate and ticket management with workflow status" decoding="async" height="675" loading="lazy" src="{{ basePath }}/uploads/calserver-module-order-ticketing.webp" width="1200"/>$$,
$$<video aria-label="Screenshot of the calServer mandate and ticket management with workflow status" class="calserver-module-figure__video" width="1200" height="675" autoplay muted loop playsinline preload="auto" poster="{{ basePath }}/uploads/calserver-module-order-ticketing.webp">
<source src="{{ basePath }}/uploads/calserver-module-order-ticketing.mp4" type="video/mp4"/>
Your browser does not support HTML5 video.
<a href="{{ basePath }}/uploads/calserver-module-order-ticketing.mp4" target="_blank" rel="noopener">Download video</a>.
</video>$$),
    $$<img alt="Screenshot of the calServer self-service portal with customer view and certificates" decoding="async" height="675" loading="lazy" src="{{ basePath }}/uploads/calserver-module-self-service.webp" width="1200"/>$$,
$$<video aria-label="Screenshot of the calServer self-service portal with customer view and certificates" class="calserver-module-figure__video" width="1200" height="675" autoplay muted loop playsinline preload="auto" poster="{{ basePath }}/uploads/calserver-module-self-service.webp">
<source src="{{ basePath }}/uploads/calserver-module-self-service.mp4" type="video/mp4"/>
Your browser does not support HTML5 video.
<a href="{{ basePath }}/uploads/calserver-module-self-service.mp4" target="_blank" rel="noopener">Download video</a>.
</video>$$),
    updated_at = CURRENT_TIMESTAMP
WHERE slug = 'calserver-en';
