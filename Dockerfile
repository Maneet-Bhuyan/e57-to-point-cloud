# E57 Meshify — Hugging Face Spaces (Docker SDK)
# https://huggingface.co/docs/hub/spaces-sdks-docker

FROM python:3.9-slim-bookworm

WORKDIR /app

# Open3D runtime dependencies (OpenGL / mesh I/O)
RUN apt-get update && apt-get install -y --no-install-recommends \
    libgl1 \
    libgl1-mesa-glx \
    libglib2.0-0 \
    libgomp1 \
    libegl1 \
    libxext6 \
    libxrender1 \
    && rm -rf /var/lib/apt/lists/*

COPY backend/requirements.txt /app/requirements.txt

RUN pip install --no-cache-dir --upgrade pip \
    && pip install --no-cache-dir -r requirements.txt \
    && pip install --no-cache-dir gunicorn

COPY backend/ /app/

RUN mkdir -p /app/uploads /app/static/meshes

ENV PORT=7860
EXPOSE 7860

# Hugging Face routes public traffic to port 7860
CMD gunicorn --bind 0.0.0.0:7860 --workers 1 --threads 2 --timeout 600 --keep-alive 75 app:app
