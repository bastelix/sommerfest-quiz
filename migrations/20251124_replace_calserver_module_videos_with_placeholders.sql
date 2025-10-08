-- Replace calServer module videos with gradient placeholders to avoid shipping binary assets
UPDATE pages
SET content = REPLACE(
    REPLACE(
        REPLACE(
            REPLACE(content,
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
                    </video>$$,
$$                    <div class="calserver-module-figure__visual"
                         data-module="device-management"
                         role="img"
                         aria-label="Screenshot der calServer-Geräteverwaltung mit Geräteakte, Historie und Messwerten">
                      <span class="calserver-module-figure__visual-label" aria-hidden="true">
                        Geräteverwaltung &amp; Historie
                      </span>
                    </div>$$),
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
                    </video>$$,
$$                    <div class="calserver-module-figure__visual"
                         data-module="calendar-resources"
                         role="img"
                         aria-label="Screenshot des calServer-Kalenders mit Ressourcen- und Terminplanung">
                      <span class="calserver-module-figure__visual-label" aria-hidden="true">
                        Kalender &amp; Ressourcen
                      </span>
                    </div>$$),
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
                    </video>$$,
$$                    <div class="calserver-module-figure__visual"
                         data-module="order-ticketing"
                         role="img"
                         aria-label="Screenshot der calServer-Auftrags- und Ticketverwaltung mit Workflow-Status">
                      <span class="calserver-module-figure__visual-label" aria-hidden="true">
                        Auftrags- &amp; Ticketverwaltung
                      </span>
                    </div>$$),
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
                    </video>$$,
$$                    <div class="calserver-module-figure__visual"
                         data-module="self-service"
                         role="img"
                         aria-label="Screenshot des calServer-Self-Service-Portals mit Kundenansicht und Zertifikaten">
                      <span class="calserver-module-figure__visual-label" aria-hidden="true">
                        Self-Service &amp; Extranet
                      </span>
                    </div>$$),
    updated_at = CURRENT_TIMESTAMP
WHERE slug = 'calserver';

UPDATE pages
SET content = REPLACE(
    REPLACE(
        REPLACE(
            REPLACE(content,
$$<video aria-label="Screenshot of the calServer device management with device files, history and measured values" class="calserver-module-figure__video" width="1200" height="675" autoplay muted loop playsinline preload="auto" poster="{{ basePath }}/uploads/calserver-module-device-management.webp">
<source src="{{ basePath }}/uploads/calserver-module-device-management.mp4" type="video/mp4"/>
Your browser does not support HTML5 video.
<a href="{{ basePath }}/uploads/calserver-module-device-management.mp4" target="_blank" rel="noopener">Download video</a>.
</video>$$,
$$<div aria-label="Screenshot of the calServer device management with device files, history and measured values"
     class="calserver-module-figure__visual"
     data-module="device-management"
     role="img">
  <span aria-hidden="true" class="calserver-module-figure__visual-label">Device management &amp; history</span>
</div>$$),
            $$<video aria-label="Screenshot of the calServer calendar with resource and scheduling" class="calserver-module-figure__video" width="1200" height="675" autoplay muted loop playsinline preload="auto" poster="{{ basePath }}/uploads/calserver-module-calendar-resources.webp">
<source src="{{ basePath }}/uploads/calserver-module-calendar-resources.mp4" type="video/mp4"/>
Your browser does not support HTML5 video.
<a href="{{ basePath }}/uploads/calserver-module-calendar-resources.mp4" target="_blank" rel="noopener">Download video</a>.
</video>$$,
$$<div aria-label="Screenshot of the calServer calendar with resource and scheduling"
     class="calserver-module-figure__visual"
     data-module="calendar-resources"
     role="img">
  <span aria-hidden="true" class="calserver-module-figure__visual-label">Calendar &amp; resources</span>
</div>$$),
        $$<video aria-label="Screenshot of the calServer mandate and ticket management with workflow status" class="calserver-module-figure__video" width="1200" height="675" autoplay muted loop playsinline preload="auto" poster="{{ basePath }}/uploads/calserver-module-order-ticketing.webp">
<source src="{{ basePath }}/uploads/calserver-module-order-ticketing.mp4" type="video/mp4"/>
Your browser does not support HTML5 video.
<a href="{{ basePath }}/uploads/calserver-module-order-ticketing.mp4" target="_blank" rel="noopener">Download video</a>.
</video>$$,
$$<div aria-label="Screenshot of the calServer mandate and ticket management with workflow status"
     class="calserver-module-figure__visual"
     data-module="order-ticketing"
     role="img">
  <span aria-hidden="true" class="calserver-module-figure__visual-label">Order &amp; ticket management</span>
</div>$$),
    $$<video aria-label="Screenshot of the calServer self-service portal with customer view and certificates" class="calserver-module-figure__video" width="1200" height="675" autoplay muted loop playsinline preload="auto" poster="{{ basePath }}/uploads/calserver-module-self-service.webp">
<source src="{{ basePath }}/uploads/calserver-module-self-service.mp4" type="video/mp4"/>
Your browser does not support HTML5 video.
<a href="{{ basePath }}/uploads/calserver-module-self-service.mp4" target="_blank" rel="noopener">Download video</a>.
</video>$$,
$$<div aria-label="Screenshot of the calServer self-service portal with customer view and certificates"
     class="calserver-module-figure__visual"
     data-module="self-service"
     role="img">
  <span aria-hidden="true" class="calserver-module-figure__visual-label">Self-service &amp; extranet</span>
</div>$$),
    updated_at = CURRENT_TIMESTAMP
WHERE slug = 'calserver-en';

UPDATE pages
SET content = REPLACE(
    REPLACE(
        REPLACE(
            REPLACE(content,
$$                    <video class="calserver-module-figure__video"
                           width="1200"
                           height="675"
                           autoplay
                           muted
                           loop
                           playsinline
                           preload="auto"
                           poster="{{ basePath }}/uploads/calserver-module-device-management.mp4"
                           aria-label="Screenshot der calServer-Geräteverwaltung mit Geräteakte, Historie und Messwerten">
                      <source src="{{ basePath }}/uploads/calserver-module-device-management.mp4" type="video/mp4">
                      Ihr Browser unterstützt keine HTML5-Videos.
                      <a href="{{ basePath }}/uploads/calserver-module-device-management.mp4" target="_blank" rel="noopener">
                        Video herunterladen
                      </a>.
                    </video>$$,
$$                    <div class="calserver-module-figure__visual"
                         data-module="device-management"
                         role="img"
                         aria-label="Screenshot der calServer-Geräteverwaltung mit Geräteakte, Historie und Messwerten">
                      <span class="calserver-module-figure__visual-label" aria-hidden="true">
                        Geräteverwaltung &amp; Historie
                      </span>
                    </div>$$),
            $$                    <video class="calserver-module-figure__video"
                           width="1200"
                           height="675"
                           autoplay
                           muted
                           loop
                           playsinline
                           preload="auto"
                           poster="{{ basePath }}/uploads/calserver-module-calendar-resources.mp4"
                           aria-label="Screenshot des calServer-Kalenders mit Ressourcen- und Terminplanung">
                      <source src="{{ basePath }}/uploads/calserver-module-calendar-resources.mp4" type="video/mp4">
                      Ihr Browser unterstützt keine HTML5-Videos.
                      <a href="{{ basePath }}/uploads/calserver-module-calendar-resources.mp4" target="_blank" rel="noopener">
                        Video herunterladen
                      </a>.
                    </video>$$,
$$                    <div class="calserver-module-figure__visual"
                         data-module="calendar-resources"
                         role="img"
                         aria-label="Screenshot des calServer-Kalenders mit Ressourcen- und Terminplanung">
                      <span class="calserver-module-figure__visual-label" aria-hidden="true">
                        Kalender &amp; Ressourcen
                      </span>
                    </div>$$),
        $$                    <video class="calserver-module-figure__video"
                           width="1200"
                           height="675"
                           autoplay
                           muted
                           loop
                           playsinline
                           preload="auto"
                           poster="{{ basePath }}/uploads/calserver-module-order-ticketing.mp4"
                           aria-label="Screenshot der calServer-Auftrags- und Ticketverwaltung mit Workflow-Status">
                      <source src="{{ basePath }}/uploads/calserver-module-order-ticketing.mp4" type="video/mp4">
                      Ihr Browser unterstützt keine HTML5-Videos.
                      <a href="{{ basePath }}/uploads/calserver-module-order-ticketing.mp4" target="_blank" rel="noopener">
                        Video herunterladen
                      </a>.
                    </video>$$,
$$                    <div class="calserver-module-figure__visual"
                         data-module="order-ticketing"
                         role="img"
                         aria-label="Screenshot der calServer-Auftrags- und Ticketverwaltung mit Workflow-Status">
                      <span class="calserver-module-figure__visual-label" aria-hidden="true">
                        Auftrags- &amp; Ticketverwaltung
                      </span>
                    </div>$$),
    $$                    <video class="calserver-module-figure__video"
                           width="1200"
                           height="675"
                           autoplay
                           muted
                           loop
                           playsinline
                           preload="auto"
                           poster="{{ basePath }}/uploads/calserver-module-self-service.mp4"
                           aria-label="Screenshot des calServer-Self-Service-Portals mit Kundenansicht und Zertifikaten">
                      <source src="{{ basePath }}/uploads/calserver-module-self-service.mp4" type="video/mp4">
                      Ihr Browser unterstützt keine HTML5-Videos.
                      <a href="{{ basePath }}/uploads/calserver-module-self-service.mp4" target="_blank" rel="noopener">
                        Video herunterladen
                      </a>.
                    </video>$$,
$$                    <div class="calserver-module-figure__visual"
                         data-module="self-service"
                         role="img"
                         aria-label="Screenshot des calServer-Self-Service-Portals mit Kundenansicht und Zertifikaten">
                      <span class="calserver-module-figure__visual-label" aria-hidden="true">
                        Self-Service &amp; Extranet
                      </span>
                    </div>$$),
    updated_at = CURRENT_TIMESTAMP
WHERE slug = 'calserver';

UPDATE pages
SET content = REPLACE(
    REPLACE(
        REPLACE(
            REPLACE(content,
$$<video aria-label="Screenshot of the calServer device management with device files, history and measured values" class="calserver-module-figure__video" width="1200" height="675" autoplay muted loop playsinline preload="auto" poster="{{ basePath }}/uploads/calserver-module-device-management.mp4">
<source src="{{ basePath }}/uploads/calserver-module-device-management.mp4" type="video/mp4"/>
Your browser does not support HTML5 video.
<a href="{{ basePath }}/uploads/calserver-module-device-management.mp4" target="_blank" rel="noopener">Download video</a>.
</video>$$,
$$<div aria-label="Screenshot of the calServer device management with device files, history and measured values"
     class="calserver-module-figure__visual"
     data-module="device-management"
     role="img">
  <span aria-hidden="true" class="calserver-module-figure__visual-label">Device management &amp; history</span>
</div>$$),
            $$<video aria-label="Screenshot of the calServer calendar with resource and scheduling" class="calserver-module-figure__video" width="1200" height="675" autoplay muted loop playsinline preload="auto" poster="{{ basePath }}/uploads/calserver-module-calendar-resources.mp4">
<source src="{{ basePath }}/uploads/calserver-module-calendar-resources.mp4" type="video/mp4"/>
Your browser does not support HTML5 video.
<a href="{{ basePath }}/uploads/calserver-module-calendar-resources.mp4" target="_blank" rel="noopener">Download video</a>.
</video>$$,
$$<div aria-label="Screenshot of the calServer calendar with resource and scheduling"
     class="calserver-module-figure__visual"
     data-module="calendar-resources"
     role="img">
  <span aria-hidden="true" class="calserver-module-figure__visual-label">Calendar &amp; resources</span>
</div>$$),
        $$<video aria-label="Screenshot of the calServer mandate and ticket management with workflow status" class="calserver-module-figure__video" width="1200" height="675" autoplay muted loop playsinline preload="auto" poster="{{ basePath }}/uploads/calserver-module-order-ticketing.mp4">
<source src="{{ basePath }}/uploads/calserver-module-order-ticketing.mp4" type="video/mp4"/>
Your browser does not support HTML5 video.
<a href="{{ basePath }}/uploads/calserver-module-order-ticketing.mp4" target="_blank" rel="noopener">Download video</a>.
</video>$$,
$$<div aria-label="Screenshot of the calServer mandate and ticket management with workflow status"
     class="calserver-module-figure__visual"
     data-module="order-ticketing"
     role="img">
  <span aria-hidden="true" class="calserver-module-figure__visual-label">Order &amp; ticket management</span>
</div>$$),
    $$<video aria-label="Screenshot of the calServer self-service portal with customer view and certificates" class="calserver-module-figure__video" width="1200" height="675" autoplay muted loop playsinline preload="auto" poster="{{ basePath }}/uploads/calserver-module-self-service.mp4">
<source src="{{ basePath }}/uploads/calserver-module-self-service.mp4" type="video/mp4"/>
Your browser does not support HTML5 video.
<a href="{{ basePath }}/uploads/calserver-module-self-service.mp4" target="_blank" rel="noopener">Download video</a>.
</video>$$,
$$<div aria-label="Screenshot of the calServer self-service portal with customer view and certificates"
     class="calserver-module-figure__visual"
     data-module="self-service"
     role="img">
  <span aria-hidden="true" class="calserver-module-figure__visual-label">Self-service &amp; extranet</span>
</div>$$),
    updated_at = CURRENT_TIMESTAMP
WHERE slug = 'calserver-en';
