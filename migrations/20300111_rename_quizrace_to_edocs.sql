-- Rename quizrace.app domain references to edocs.cloud
UPDATE domains
   SET host            = 'edocs.cloud',
       normalized_host = 'edocs.cloud',
       zone            = 'edocs.cloud',
       label           = 'edocs (main/admin)'
 WHERE normalized_host = 'quizrace.app';
