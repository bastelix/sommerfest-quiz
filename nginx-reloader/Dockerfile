FROM python:3.11-alpine

RUN pip install flask

WORKDIR /app
COPY app.py requirements.txt ./

RUN pip install -r requirements.txt

CMD ["python", "app.py"]
