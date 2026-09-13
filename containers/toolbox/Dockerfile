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
    openssl \
    && rm -rf /var/lib/apt/lists/*

COPY datasets/build/requirements.txt /opt/datasets-build/requirements.txt
RUN pip install --break-system-packages --no-cache-dir \
    -r /opt/datasets-build/requirements.txt \
    pynetdicom==3.0.*

COPY datasets/build/generate.py /opt/datasets-build/generate.py

# Orthanc beherrscht Storage Commitment nativ, aber keinen MPPS-Dienst
# (Lektion 4.8) -- dieser echte pynetdicom-SCP ist die Gegenstelle dafuer.
COPY containers/toolbox/scripts/mppsscp.py /opt/tools/mppsscp.py

# Selbstsigniertes Test-Zertifikat fuer DICOM-TLS (Lektion 4.9) -- einmal
# beim Image-Build erzeugt, nie committeter Binaerinhalt (Leitplanke 2 gilt
# sinngemaess: kein Geheimnis, das irgendwo echten Wert haette, wird ins Repo
# aufgenommen). Enthaelt Orthanc nicht: TLS wuerde dessen einzigen
# DICOM-Port fuer alle anderen Lektionen mit-verriegeln (Orthanc kennt nur
# einen DicomPort, TLS an/aus fuer den gesamten Port), deshalb laeuft die
# TLS-Gegenstelle dieser Lektion als eigener DCMTK-storescp im
# Toolbox-Container selbst.
RUN mkdir -p /opt/tools/tls && \
    openssl req -x509 -newkey rsa:2048 -nodes -days 3650 \
        -keyout /opt/tools/tls/server-key.pem \
        -out /opt/tools/tls/server-cert.pem \
        -subj "/CN=dcmlab-spielwiese-tls" && \
    chmod 644 /opt/tools/tls/server-key.pem /opt/tools/tls/server-cert.pem

RUN adduser --disabled-password --gecos "" dcmlab
# dumpcap braucht CAP_NET_RAW/CAP_NET_ADMIN, um ohne root mitzuschneiden --
# sonst muesste jede Node/Lektion mit tshark als root laufen (Abschnitt 6:
# Lernende bekommen keine Root-Shell in der Spielwiese).
RUN setcap cap_net_raw,cap_net_admin=eip /usr/bin/dumpcap
USER dcmlab
WORKDIR /home/dcmlab
