from pydantic import BaseModel


class CreateSessionRequest(BaseModel):
    node_slug: str


class ExecRequest(BaseModel):
    host: str
    command: str


class ConfigRequest(BaseModel):
    host: str
    field: str
    value: str


class ActionRequest(BaseModel):
    host: str
    action: str


class FlagRequest(BaseModel):
    value: str


class HintRequest(BaseModel):
    hint_id: str
