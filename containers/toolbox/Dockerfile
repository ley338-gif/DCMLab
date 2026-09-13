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
    tshark \
    libcap2-bin \
    curl \
    && rm -rf /var/lib/apt/lists/*

COPY datasets/build/requirements.txt /opt/datasets-build/requirements.txt
RUN pip install --break-system-packages --no-cache-dir \
    -r /opt/datasets-build/requirements.txt \
    pynetdicom==3.0.*

COPY datasets/build/generate.py /opt/datasets-build/generate.py

# Orthanc beherrscht Storage Commitment nativ, aber keinen MPPS-Dienst
# (Lektion 4.8) -- dieser echte pynetdicom-SCP ist die Gegenstelle dafuer.
COPY containers/toolbox/scripts/mppsscp.py /opt/tools/mppsscp.py

RUN adduser --disabled-password --gecos "" dcmlab
# dumpcap braucht CAP_NET_RAW/CAP_NET_ADMIN, um ohne root mitzuschneiden --
# sonst muesste jede Node/Lektion mit tshark als root laufen (Abschnitt 6:
# Lernende bekommen keine Root-Shell in der Spielwiese).
RUN setcap cap_net_raw,cap_net_admin=eip /usr/bin/dumpcap
USER dcmlab
WORKDIR /home/dcmlab
