{{- define "edocs-cloud.fullname" -}}
{{- default .Chart.Name .Values.fullnameOverride | trunc 63 | trimSuffix "-" -}}
{{- end -}}
{{- define "edocs-cloud.name" -}}
{{- .Chart.Name -}}
{{- end -}}
