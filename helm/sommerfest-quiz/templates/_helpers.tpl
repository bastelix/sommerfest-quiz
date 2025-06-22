{{- define "sommerfest-quiz.fullname" -}}
{{- default .Chart.Name .Values.fullnameOverride | trunc 63 | trimSuffix "-" -}}
{{- end -}}
{{- define "sommerfest-quiz.name" -}}
{{- .Chart.Name -}}
{{- end -}}
