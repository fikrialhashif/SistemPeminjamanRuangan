FROM python:3.10-slim

ENV PYTHONUNBUFFERED=1
ENV INSIGHTFACE_HOME=/root/.insightface

RUN apt-get update \
    && apt-get install -y --no-install-recommends build-essential libgl1 libglib2.0-0 curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir "cython==0.29.37" "numpy==1.26.4"
RUN pip install --no-cache-dir --no-build-isolation "insightface==0.7.3"
RUN pip install --no-cache-dir -r requirements.txt \
    && pip install --no-cache-dir "numpy==1.26.4"

COPY . .

RUN mkdir -p /data/embeddings

EXPOSE 8001
CMD ["sh", "-c", "python -m app.warmup && uvicorn app.main:app --host 0.0.0.0 --port 8001"]
