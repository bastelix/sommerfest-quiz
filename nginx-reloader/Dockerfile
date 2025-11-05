FROM python:3.11-alpine

# The reloader needs the Docker CLI to send reload commands
RUN apk add --no-cache docker-cli curl \
    && pip install flask

WORKDIR /app
COPY app.py requirements.txt ./

RUN pip install -r requirements.txt

CMD ["python", "app.py"]
