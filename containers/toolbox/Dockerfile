# Toolbox-Container der Spielwiese (Abschnitt 6): DCMTK, Python, pydicom,
# pynetdicom, plus der Datensatz-Generator (Abschnitt 3.2: datasets/build/).
# Build-Kontext ist bewusst das Repo-Root (siehe Makefile), damit dieses
# COPY funktioniert.
FROM debian:bookworm-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
    dcmtk \
    python3 \
    python3-pip \
    iputils-ping \
    && rm -rf /var/lib/apt/lists/*

COPY datasets/build/requirements.txt /opt/datasets-build/requirements.txt
RUN pip install --break-system-packages --no-cache-dir \
    -r /opt/datasets-build/requirements.txt \
    pynetdicom==3.0.*

COPY datasets/build/generate.py /opt/datasets-build/generate.py

RUN adduser --disabled-password --gecos "" dcmlab
USER dcmlab
WORKDIR /home/dcmlab
