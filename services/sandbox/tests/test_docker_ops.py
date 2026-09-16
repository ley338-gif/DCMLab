"""Testet die Template-Aufloesung komplett ohne Docker-Daemon."""

from __future__ import annotations

import pytest

from app import docker_ops


def test_resolve_template_returns_the_known_template() -> None:
    template = docker_ops.resolve_template("dicom-basic-tools")

    assert template.orthanc_image == docker_ops.settings.orthanc_image
    assert template.toolbox_image == docker_ops.settings.toolbox_image


def test_resolve_template_raises_for_an_unknown_slug() -> None:
    with pytest.raises(docker_ops.UnknownTemplateError):
        docker_ops.resolve_template("does-not-exist")
