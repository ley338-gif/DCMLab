# Platzhalter fuer Phase P7 (Abschnitt 6). Wird dort um DCMTK, pydicom,
# pynetdicom und die per Web-Terminal angebundene Shell ergaenzt.
FROM debian:bookworm-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
    dcmtk \
    python3 \
    python3-pip \
    iputils-ping \
    && rm -rf /var/lib/apt/lists/*

RUN adduser --disabled-password --gecos "" dcmlab
USER dcmlab
WORKDIR /home/dcmlab
