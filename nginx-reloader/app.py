from flask import Flask, request, jsonify
import os
import re
import subprocess

app = Flask(__name__)

RELOAD_TOKEN = os.environ.get("NGINX_RELOAD_TOKEN", "changeme")
NGINX_CONTAINER = os.environ.get("NGINX_CONTAINER", "nginx")
if not re.fullmatch(r"[\w-]+", NGINX_CONTAINER):
    raise RuntimeError("Invalid NGINX_CONTAINER name")

@app.route("/reload", methods=["POST"])
def reload_nginx():
    token = request.headers.get("X-Token")
    if token != RELOAD_TOKEN:
        return jsonify({"error": "Unauthorized"}), 403
    try:
        subprocess.check_call(["docker", "exec", NGINX_CONTAINER, "nginx", "-s", "reload"])
        return jsonify({"status": "nginx reloaded"}), 200
    except subprocess.CalledProcessError as e:
        return jsonify({"error": str(e)}), 500

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8080)
