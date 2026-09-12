from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    """Konfiguration ausschliesslich ueber Umgebungsvariablen (.env)."""

    model_config = SettingsConfigDict(extra="ignore")

    database_url: str = Field(
        default="postgresql+psycopg://dcmlab:dcmlab@postgres:5432/dcmlab",
        validation_alias="ENGINE_DATABASE_URL",
    )
    internal_key: str = Field(
        default="change-me-in-production",
        validation_alias="DCMLAB_INTERNAL_KEY",
    )
    content_path: str = Field(
        default="../../content",
        validation_alias="CONTENT_PATH",
    )


settings = Settings()
